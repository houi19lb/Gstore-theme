/** @jest-environment jsdom */
const fs = require('fs');
const path = require('path');
const $ = require('jquery');
const { markup, summary, rates, instrument } = require('./fixtures/checkout');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/checkout-steps.js'), 'utf8');

describe('Checkout: quatro etapas visuais com contrato original', () => {
	let api;
	beforeEach(() => {
		jest.useFakeTimers();
		$(document).off(); $(document.body).off();
		localStorage.clear();
		document.body.innerHTML = markup();
		window.jQuery = $; window.$ = $;
		window.gstoreCheckout = { contract: { enabled: false } };
		$.fx.off = true;
		$.ajax = jest.fn(() => $.Deferred().promise());
		window.eval(instrument(source));
		api = window.checkoutFixture;
		api.init(); api.primeShipping(); api.seedSummary(summary);
	});
	afterEach(() => { jest.clearAllTimers(); jest.useRealTimers(); });
	const active = () => $('.Gstore-checkout-step.is-active').attr('data-step');
	test('avança pelas quatro telas sem antecipar a etapa de parcelamento do backend', () => {
		expect($('.Gstore-checkout-stepper__label').map((_, el) => el.textContent).get()).toEqual(['Pagamento', 'Dados Básicos', 'Frete', 'Finalizar']);
		expect(active()).toBe('payment-method');
		api.nextStep(); expect(active()).toBe('contact');
		expect($('#gstore_checkout_step').val()).toBe('1');
		api.nextStep(); expect(active()).toBe('shipping');
		expect($('#gstore_checkout_step').val()).toBe('1');
		expect($('#place_order')[0].style.display).toBe('none');
		api.loadCartSummary();
		expect($.ajax.mock.calls.at(-1)[0].data.gstore_checkout_step).toBe(1);
		api.nextStep(); expect(active()).toBe('payment');
		expect($('#gstore_checkout_step').val()).toBe('2');
		expect($('#place_order')[0].style.display).not.toBe('none');
		api.prevStep(); expect($('#gstore_checkout_step').val()).toBe('1');
	});
	test('mantém um único conjunto de radios, nomes cadastrados e seleção após refresh', () => {
		api.setActiveStep(2, false);
		const selector = 'input[type="radio"][name="gstore_selected_shipping_rate[item-a]"]';
		expect($(selector)).toHaveLength(rates.length);
		expect($('.Gstore-checkout-summary-top').find(selector)).toHaveLength(0);
		expect($('[data-gstore-shipping-step-items]').text()).toContain('Transportadora de teste · Serviço cadastrado');
		$(selector).filter('[value="gstore_custom_shipping:air"]').prop('checked', true).trigger('change');
		api.seedSummary(summary);
		expect($(selector + ':checked').val()).toBe('gstore_custom_shipping:air');
		expect($('form.checkout input[name="gstore_selected_shipping_rate[item-a]"]').val()).toBe('gstore_custom_shipping:air');
		expect($(selector)).toHaveLength(rates.length);
	});
	test('permite editar CEP sem perder dados', () => {
		api.setActiveStep(2, false);
		$('[data-gstore-shipping-edit-address]').trigger('click');
		expect(active()).toBe('contact');
		expect(document.activeElement.id).toBe('billing_postcode');
		expect($('#billing_email').val()).toBe('cliente@example.com');
	});
	test('continua bloqueando dados obrigatórios antes da etapa de frete', () => {
		api.setActiveStep(1, false);
		$('#billing_email').val('');
		api.nextStep(); expect(active()).toBe('contact');
		expect($('#billing_email_field').hasClass('woocommerce-invalid')).toBe(true);
	});
	test('Alterar frete na finalização retorna à nova etapa e ao estado intermediário', () => {
		api.setActiveStep(3, false);
		$('#order_review [data-gstore-shipping-change]').first().trigger('click');
		expect(active()).toBe('shipping');
		expect($('#gstore_checkout_step').val()).toBe('1');
	});
	test('Pix mantém os campos completos e as validações de contato', () => {
		$('.Gstore-blu-payment-unified input[value="blu_pix"]').prop('checked', true).trigger('change');
		api.setActiveStep(1, false);
		$('#billing_last_name').val('');
		api.nextStep();
		expect(active()).toBe('contact');
		expect($('#billing_last_name_field').hasClass('woocommerce-invalid')).toBe(true);
		$('#billing_last_name').val('Exemplo');
		api.nextStep(); expect(active()).toBe('shipping');
		api.nextStep(); expect(active()).toBe('payment');
		expect($('#gstore_checkout_step').val()).toBe('2');
	});
	test('aceite do contrato continua obrigatório na etapa final', () => {
		api.setActiveStep(3, false);
		$('[data-step="payment"]').append('<input type="checkbox" id="gstore_contract_terms">');
		api.nextStep();
		expect($('.woocommerce-notice').text()).toContain('aceitar os termos');
		expect($('#gstore_contract_terms').prop('checked')).toBe(false);
	});
	test('rascunho antigo de retorno Blu continua abrindo Finalizar, não Frete', () => {
		localStorage.setItem('gstore_blu_resume_checkout:draft:v1:/', JSON.stringify({
			step: 2, fields: { billing_email: 'retorno@example.com' }, updated_at: Date.now(), reason: 'blu_payment_waiting'
		}));
		expect(api.restoreCheckoutDraftState()).toBe(true);
		jest.advanceTimersByTime(110);
		expect(active()).toBe('payment');
		expect(api.getBackendCheckoutStep()).toBe(2);
		expect($('#billing_email').val()).toBe('retorno@example.com');
	});
});

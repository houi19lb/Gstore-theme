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
	test('boleto aparece no card Blu e mantém a seleção após atualizar o checkout', () => {
		$('#payment .payment_methods').append('<li class="payment_method_gstore_boleto"><input type="radio" name="payment_method" id="payment_method_gstore_boleto" value="gstore_boleto"><label for="payment_method_gstore_boleto">Boleto bancário</label></li>');
		$('form.checkout').prepend('<input type="hidden" name="payment_method" data-gstore-fallback="1" value="blu_pix" checked>');
		$(document.body).trigger('updated_checkout');
		jest.advanceTimersByTime(120);
		const $option = $('.Gstore-blu-payment-unified .Gstore-blu-payment-option--boleto');
		expect($option).toHaveLength(1);
		expect($option.find('.fa-file-invoice')).toHaveLength(1);
		expect($('.payment_method_gstore_boleto').hasClass('gstore-hidden-for-unified')).toBe(true);
		$option.find('input').prop('checked', true).trigger('change');
		expect($option.find('input').is(':checked')).toBe(true);
		expect($('form.checkout input[data-gstore-fallback="1"][name="payment_method"]')).toHaveLength(1);
		expect($('form.checkout').serializeArray().filter(field => field.name === 'payment_method')).toEqual([{ name: 'payment_method', value: 'gstore_boleto' }]);
		$(document.body).trigger('update_checkout');
		expect($option.find('input').is(':checked')).toBe(true);
		$('.Gstore-blu-payment-unified input[value="blu_pix"]').prop('checked', true);
		$(document.body).trigger('updated_checkout');
		jest.advanceTimersByTime(120);
		expect($('.Gstore-blu-payment-unified .Gstore-blu-payment-option--boleto input').is(':checked')).toBe(true);
		api.setActiveStep(3, false);
		expect($('.Gstore-boleto-final-notice').text()).toContain('pagamento integral do boleto');
		expect($('#place_order').text()).toContain('solicitar boleto');
		expect($('form.checkout').serializeArray().filter(field => field.name === 'payment_method')).toEqual([{ name: 'payment_method', value: 'gstore_boleto' }]);
		$('.Gstore-blu-payment-unified .Gstore-blu-payment-option').first().find('label').trigger('click');
		expect($('form.checkout').serializeArray().filter(field => field.name === 'payment_method')).toEqual([{ name: 'payment_method', value: 'blu_pix' }]);
	});
	test('boleto prepara uma nova guia no clique e a envia ao WhatsApp após criar o pedido', () => {
		$('#payment .payment_methods').append('<li class="payment_method_gstore_boleto"><input type="radio" name="payment_method" value="gstore_boleto"><label>Boleto bancário</label></li>');
		$(document.body).trigger('updated_checkout');
		jest.advanceTimersByTime(120);
		$('.Gstore-blu-payment-unified .Gstore-blu-payment-option--boleto input').prop('checked', true).trigger('change');
		api.setActiveStep(3, false);
		$('#gstore_contract_terms').prop('checked', true);
		const popup = { closed: false, opener: window, document: { title: '', body: { textContent: '' } }, location: { replace: jest.fn() }, close: jest.fn() };
		const open = jest.spyOn(window, 'open').mockReturnValue(popup);
		$('#place_order').trigger('click');
		expect(open).toHaveBeenCalledWith('about:blank', '_blank');
		expect(popup.document.body.textContent).toContain('Registrando seu pedido');
		const priorBlock = $.fn.block;
		$.fn.block = jest.fn();
		window.wc_checkout_params = { checkout_url: '/?wc-ajax=checkout' };
		jest.advanceTimersByTime(200);
		const checkoutRequest = $.ajax.mock.calls.map(call => call[0]).find(request => request.url === '/?wc-ajax=checkout');
		expect(checkoutRequest).toBeDefined();
		checkoutRequest.success({ result: 'success', redirect: '/checkout/order-received/15046/', gstore_boleto_whatsapp_url: 'https://wa.me/5511999999999?text=Pedido%20%2315046' });
		expect(popup.location.replace).toHaveBeenCalledWith('https://wa.me/5511999999999?text=Pedido%20%2315046');
		expect(popup.opener).toBeNull();
		$.fn.block = priorBlock;
		delete window.wc_checkout_params;
		open.mockRestore();
	});
	test('aceite do contrato continua obrigatório na etapa final', () => {
		api.setActiveStep(3, false);
		$('[data-step="payment"]').append('<input type="checkbox" id="gstore_contract_terms">');
		api.nextStep();
		expect($('.woocommerce-notice').text()).toContain('aceitar os termos');
		expect($('#gstore_contract_terms').prop('checked')).toBe(false);
	});
	test('modal mostra a prévia retornada pelo plugin para contratos por categoria', () => {
		const preview = $.Deferred();
		$.ajax.mockReturnValue(preview.promise());
		window.gstoreCheckout.contractPreview = { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'nonce-teste', action: 'gstore_contract_preview' };
		api.setActiveStep(3, false);
		$('.gstore-contract-open-modal').first().trigger('click');
		const request = $.ajax.mock.calls.at(-1)[0];
		expect(request.data.action).toBe('gstore_contract_preview');
		expect(request.data.post_data).toContain('shipping_method');
		preview.resolve({ success: true, data: { html: '<div class="gstore-contract-page"><div>Contrato da empresa parceira</div></div>' } });
		expect($('.Gstore-contract-modal__iframe').attr('srcdoc')).toContain('Contrato da empresa parceira');
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

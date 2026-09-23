/** @jest-environment node */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const jquery = require('jquery');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/cart.js'), 'utf8');
const rpa = { rate_id: 'gstore_custom_shipping:service:rpa:land', mode: 'land', label: 'RPA', cost: 180 };
const express = { rate_id: 'gstore_custom_shipping:xpress', mode: 'land', label: 'Express', cost: 250 };
const pickup = { rate_id: 'gstore_custom_shipping:pickup', mode: 'pickup', label: 'Retirada na loja', cost: 0 };

function item(key, { ammo = true, rates = [rpa, express, pickup], selected = rpa.rate_id } = {}) {
	return `<article data-cart-item-key="${key}" data-product-id="42" data-quantity="1" data-shipping-is-ammo="${ammo ? 1 : 0}">
		<div data-gstore-shipping-item>
			<div data-gstore-shipping-options>${rates.map((rate) => `<label class="Gstore-cart-card__shipping-option">
				<input type="radio" name="gstore_selected_shipping_rate[${key}]" value="${rate.rate_id}" data-gstore-mode="${rate.mode}" ${rate.rate_id === selected ? 'checked' : ''} />
			</label>`).join('')}</div>
			<input type="hidden" name="gstore_shipping_rates[${key}]" value='${JSON.stringify(rates)}' />
		</div>
	</article>`;
}

describe('escolha compartilhada do frete de munições no carrinho', () => {
	let dom;
	let win;
	let $;
	function mount(items) {
		dom = new JSDOM(`<form class="woocommerce-cart-form" method="post">${items}</form>`, {
			url: 'https://cart.example.test/carrinho/', runScripts: 'outside-only',
		});
		win = dom.window;
		$ = jquery(win);
		$.ajax = jest.fn(() => $.Deferred().promise());
		win.jQuery = $;
		win.eval(source);
		win.document.dispatchEvent(new win.Event('DOMContentLoaded'));
	}
	function choose(key, rate) {
		win.document.querySelector(`input[type="radio"][name="gstore_selected_shipping_rate[${key}]"][value="${rate.rate_id}"]`).click();
	}
	function selected(key) {
		return win.document.querySelector(`input[type="radio"][name="gstore_selected_shipping_rate[${key}]"]:checked`).value;
	}
	beforeEach(() => jest.useFakeTimers());
	afterEach(() => { if (dom) dom.window.close(); jest.clearAllTimers(); jest.useRealTimers(); });

	test('trocar qualquer munição sincroniza o ID exato nos radios, campos e persistência', () => {
		mount(item('ammo-a') + item('ammo-b') + item('ammo-c'));
		choose('ammo-b', express);
		for (const key of ['ammo-a', 'ammo-b', 'ammo-c']) {
			expect(selected(key)).toBe(express.rate_id);
			expect(win.document.querySelector(`input[data-gstore-rate-hidden][name="gstore_selected_shipping_rate[${key}]"]`).value).toBe(express.rate_id);
			expect(JSON.parse(win.localStorage.getItem('gstore_cart_selected_shipping_rate'))[key]).toBe(express.rate_id);
		}
		choose('ammo-c', rpa);
		expect(['ammo-a', 'ammo-b', 'ammo-c'].map(selected)).toEqual([rpa.rate_id, rpa.rate_id, rpa.rate_id]);
		jest.advanceTimersByTime(400);
		expect($.ajax).toHaveBeenCalledTimes(1);
		const posted = new URLSearchParams($.ajax.mock.calls[0][0].data);
		for (const key of ['ammo-a', 'ammo-b', 'ammo-c']) {
			expect(new Set(posted.getAll(`gstore_selected_shipping_rate[${key}]`))).toEqual(new Set([rpa.rate_id]));
			expect(posted.get(`gstore_shipping_mode[${key}]`)).toBe('land');
		}
	});

	test('armas e acessórios mantêm escolhas independentes', () => {
		mount(item('ammo-a') + item('ammo-b') + item('gun', { ammo: false }) + item('accessory', { ammo: false }));
		choose('ammo-a', express);
		expect(selected('ammo-b')).toBe(express.rate_id);
		expect(selected('gun')).toBe(rpa.rate_id);
		expect(selected('accessory')).toBe(rpa.rate_id);
		choose('gun', pickup);
		expect(selected('ammo-a')).toBe(express.rate_id);
		expect(selected('ammo-b')).toBe(express.rate_id);
		expect(selected('accessory')).toBe(rpa.rate_id);
	});

	test('não inventa serviço indisponível nem copia valores entre cotações', () => {
		const differentPrice = { ...express, cost: 500 };
		mount(item('ammo-a') + item('ammo-b', { rates: [rpa, differentPrice] }) + item('unavailable', { rates: [rpa] }));
		choose('ammo-a', express);
		expect(selected('ammo-b')).toBe(express.rate_id);
		expect(selected('unavailable')).toBe(rpa.rate_id);
		const rates = JSON.parse(win.document.querySelector('input[name="gstore_shipping_rates[ammo-b]"]').value);
		expect(rates.find((rate) => rate.rate_id === express.rate_id).cost).toBe(500);
	});

	test('também reflete retirada e funciona com apenas uma munição', () => {
		mount(item('ammo-a') + item('ammo-b'));
		choose('ammo-b', pickup);
		expect(selected('ammo-a')).toBe(pickup.rate_id);
		expect(win.document.querySelector('input[name="gstore_shipping_mode[ammo-a]"]').value).toBe('pickup');
		win.document.querySelector('[data-cart-item-key="ammo-b"]').remove();
		choose('ammo-a', express);
		expect(selected('ammo-a')).toBe(express.rate_id);
	});

	test('mantém a última escolha quando uma cotação iniciada antes dela termina', async () => {
		mount(item('ammo-a') + item('ammo-b'));
		win.document.body.insertAdjacentHTML('beforeend', '<div class="gstore-shipping-calculator--cart"><input class="gstore-shipping-calculator__cep" value="01310100"><button class="gstore-shipping-calculator__button">Calcular</button></div>');
		win.document.body.insertAdjacentHTML('beforeend', '<div class="cart_totals"><table><tbody><tr class="cart-subtotal"><td>R$ 8.000,00</td></tr><tr class="order-total"><td></td></tr></tbody></table></div>');
		const requests = [];
		$.ajax.mockImplementation(() => new Promise((resolve) => requests.push(resolve)));
		$('.gstore-shipping-calculator__button').trigger('click');
		expect(requests).toHaveLength(3);
		choose('ammo-b', express);
		// A cotação do carrinho já inclui a faixa adicional calculada pelo plugin.
		requests[0]({ success: true, data: { rates: [rpa, { ...express, cost: 500 }, pickup], destination: {} } });
		requests.slice(1).forEach((resolve) => resolve({ success: true, data: { rates: [rpa, express, pickup], destination: {} } }));
		// Drena as cadeias de cotação e o Promise.allSettled sem disparar o POST do carrinho.
		for (let i = 0; i < 12; i += 1) await Promise.resolve();
		expect(selected('ammo-a')).toBe(express.rate_id);
		expect(selected('ammo-b')).toBe(express.rate_id);
		expect(win.document.querySelector('.gstore-shipping-ground td').textContent).toMatch(/500,00/);
	});
});

/**
 * @jest-environment jsdom
 */
const fs = require('fs');
const path = require('path');
const $ = require('jquery');
describe.each(['single-product.js', 'single-product.min.js'])('%s', filename => {
	const script = fs.readFileSync(path.join(__dirname, '..', 'assets/js', filename), 'utf8');

	function boot({ oos = false, mode = 'hide', hidden = false } = {}) {
		document.body.innerHTML = `
			<div data-gstore-installment-card><span data-gstore-installment-target="1" data-product-id="11" data-max-installments="21" data-initial-text="ou 21x de R$209,05">ou 21x de R$209,05</span></div>
			<div data-gstore-installment-card>ou 21x de R$209,05</div>
			<div class="buybox ${oos ? 'is-out-of-stock' : 'is-in-stock'}">
				<div data-gstore-price-header data-gstore-hide-price="${hidden ? '1' : '0'}" data-gstore-oos-price-mode="${mode}"><div data-gstore-price-panel></div></div>
				<div data-gstore-stock-block data-default-class="is-in-stock"></div>
				<form class="variations_form"><input class="qty" value="1"></form>
				<div data-gstore-oos-card hidden></div>
			</div>`;
		window.jQuery = $;
		window.gstoreSingleProductInstallments = { ajaxUrl: '/quotes', productId: 11, max: 21 };
		let ready;
		const realAdd = document.addEventListener.bind(document);
		const spy = jest.spyOn(document, 'addEventListener').mockImplementation((type, listener, options) => {
			if (type === 'DOMContentLoaded') ready = listener;
			else realAdd(type, listener, options);
		});
		window.eval(script);
		spy.mockRestore();
		ready();
	}

	async function flush() {
		for (let i = 0; i < 12; i += 1) await Promise.resolve();
	}

	const quoteResponse = () => ({ ok: true, json: async () => ({ success: true, data: { max: 21, quotes: { 21: { installments: 21, per_installment_text: 'R$209,05' } } } }) });

	beforeEach(() => {
		jest.useFakeTimers();
		window.fetch = jest.fn().mockResolvedValue(quoteResponse());
		window.matchMedia = jest.fn(() => ({ matches: false, addEventListener: jest.fn() }));
	});
	afterEach(() => {
		jest.clearAllTimers();
		jest.useRealTimers();
		document.body.innerHTML = '';
	});

	test('out-of-stock prices disabled: no installment request or visible values', () => {
		boot({ oos: true });
		expect(window.fetch).not.toHaveBeenCalled();
		expect(document.querySelector('[data-gstore-installment-target]').textContent).toBe('');
	});

	test('explicitly hidden prices do not request installments', () => {
		boot({ hidden: true });
		expect(window.fetch).not.toHaveBeenCalled();
		expect(document.querySelector('[data-gstore-installment-target]').hidden).toBe(true);
	});

	test('reference prices enabled: out-of-stock installments remain available', async () => {
		boot({ oos: true, mode: 'show' });
		await flush();
		expect(window.fetch).toHaveBeenCalledTimes(1);
		expect(document.querySelector('[data-gstore-installment-target]').textContent).toBe('ou 21x de R$209,05');
	});

	test('switching to an unavailable variation hides both payment cards, and reset restores them', async () => {
		boot();
		await flush();
		$('.variations_form').trigger('found_variation', [{ variation_id: 12, is_in_stock: false }]);
		jest.advanceTimersByTime(200);
		expect([...document.querySelectorAll('[data-gstore-installment-card]')].every(card => card.hidden)).toBe(true);
		expect(window.fetch).toHaveBeenCalledTimes(1);
		$('.variations_form').trigger('reset_data');
		jest.advanceTimersByTime(200);
		expect([...document.querySelectorAll('[data-gstore-installment-card]')].every(card => !card.hidden)).toBe(true);
		expect(document.querySelector('[data-gstore-installment-target]').textContent).toBe('ou 21x de R$209,05');
	});

	test('a quote that arrives after selecting an unavailable variation cannot restore the amount', async () => {
		let resolve;
		window.fetch.mockImplementation(() => new Promise(done => { resolve = done; }));
		boot();
		$('.variations_form').trigger('found_variation', [{ variation_id: 12, is_in_stock: false }]);
		resolve(quoteResponse());
		await flush();
		expect(document.querySelector('[data-gstore-installment-target]').textContent).toBe('');
		expect(document.querySelector('[data-gstore-installment-target]').hidden).toBe(true);
	});
});

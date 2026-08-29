/**
 * @jest-environment jsdom
 */
const fs = require('fs');
const path = require('path');

const script = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'product-card.js'), 'utf8');

function card(productId, text = 'ou 10x de R$ 10,00', max = 10, scope = 'card') {
	return `<span data-gstore-installment-scope="${scope}" data-product-id="${productId}" data-max-installments="${max}" data-initial-text="${text}">${text}</span>`;
}

function response(products) {
	return Promise.resolve({
		ok: true,
		json: () => Promise.resolve({ success: true, data: { products, errors: {} } }),
	});
}

function boot(config = {}) {
	Object.defineProperty(document, 'readyState', { configurable: true, value: 'complete' });
	delete window.IntersectionObserver;
	window.gstoreProductCardConfig = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		action: 'gstore_blu_get_product_installment_quotes',
		batchAction: 'gstore_blu_get_product_installment_quotes_batch',
		mode: 'batch',
		batchSize: 20,
		batchDelay: 100,
		timeout: 8000,
		isProductPage: false,
		...config,
	};
	window.eval(script);
}

async function flushPromises(times = 6) {
	for (let i = 0; i < times; i += 1) {
		await Promise.resolve();
	}
}

describe('GStore product card installment batches', () => {
	beforeEach(() => {
		jest.useFakeTimers();
		document.body.innerHTML = '';
		window.fetch = jest.fn();
		delete window.gstoreProductCardConfig;
	});

	afterEach(() => {
		jest.useRealTimers();
	});

	test('several visible cards generate one batch request after 100ms', async () => {
		document.body.innerHTML = card(11) + card(22) + card(33);
		window.fetch.mockImplementation(() => response({
			11: { installments: 10, per_installment_text: 'R$ 11,00', text: 'ou 10x de R$ 11,00' },
			22: { installments: 10, per_installment_text: 'R$ 22,00', text: 'ou 10x de R$ 22,00' },
			33: { installments: 10, per_installment_text: 'R$ 33,00', text: 'ou 10x de R$ 33,00' },
		}));

		boot();
		expect(window.fetch).not.toHaveBeenCalled();
		jest.advanceTimersByTime(100);
		await flushPromises();

		expect(window.fetch).toHaveBeenCalledTimes(1);
		const body = new URLSearchParams(window.fetch.mock.calls[0][1].body);
		expect(body.get('action')).toBe('gstore_blu_get_product_installment_quotes_batch');
		expect(JSON.parse(body.get('items'))).toHaveLength(3);
		expect(document.querySelector('[data-product-id="22"]').textContent).toBe('ou 10x de R$ 22,00');
	});

	test('more than twenty products run in sequential batches with one request in flight', async () => {
		document.body.innerHTML = Array.from({ length: 21 }, (_, index) => card(index + 1)).join('');
		let active = 0;
		let maxActive = 0;
		window.fetch.mockImplementation((url, options) => {
			active += 1;
			maxActive = Math.max(maxActive, active);
			const items = JSON.parse(new URLSearchParams(options.body).get('items'));
			const products = Object.fromEntries(items.map((item) => [String(item.product_id), {
				installments: item.max,
				per_installment_text: 'R$ 10,00',
				text: `ou ${item.max}x de R$ 10,00`,
			}]));
			return response(products).finally(() => { active -= 1; });
		});

		boot();
		jest.advanceTimersByTime(100);
		await flushPromises(12);

		expect(window.fetch).toHaveBeenCalledTimes(2);
		expect(JSON.parse(new URLSearchParams(window.fetch.mock.calls[0][1].body).get('items'))).toHaveLength(20);
		expect(JSON.parse(new URLSearchParams(window.fetch.mock.calls[1][1].body).get('items'))).toHaveLength(1);
		expect(maxActive).toBe(1);
	});

	test('a failed or partial batch preserves every original value without retry', async () => {
		document.body.innerHTML = card(41, 'HTML original 41') + card(42, 'HTML original 42');
		window.fetch.mockImplementation(() => response({
			41: { installments: 8, per_installment_text: 'R$ 20,00', text: 'ou 8x de R$ 20,00' },
		}));

		boot();
		jest.advanceTimersByTime(100);
		await flushPromises();

		expect(document.querySelector('[data-product-id="41"]').textContent).toBe('ou 8x de R$ 20,00');
		expect(document.querySelector('[data-product-id="42"]').textContent).toBe('HTML original 42');
		expect(window.fetch).toHaveBeenCalledTimes(1);

		document.body.innerHTML = card(51, 'HTML original 51');
		window.fetch = jest.fn().mockRejectedValue(new Error('timeout'));
		boot();
		jest.advanceTimersByTime(100);
		await flushPromises();
		expect(document.querySelector('[data-product-id="51"]').textContent).toBe('HTML original 51');
		expect(window.fetch).toHaveBeenCalledTimes(1);
	});

	test('legacy uses individual requests and off keeps only server HTML', async () => {
		document.body.innerHTML = card(61) + card(62);
		window.fetch.mockImplementation(() => Promise.resolve({
			ok: true,
			json: () => Promise.resolve({
				success: true,
				data: { quotes: { 10: { installments: 10, per_installment_text: 'R$ 10,00' } } },
			}),
		}));
		boot({ mode: 'legacy' });
		await flushPromises();

		expect(window.fetch).toHaveBeenCalledTimes(2);
		window.fetch.mock.calls.forEach((call) => {
			expect(new URLSearchParams(call[1].body).get('action')).toBe('gstore_blu_get_product_installment_quotes');
		});

		document.body.innerHTML = card(63, 'HTML off');
		window.fetch = jest.fn();
		boot({ mode: 'off' });
		jest.advanceTimersByTime(1000);
		expect(window.fetch).not.toHaveBeenCalled();
		expect(document.querySelector('[data-product-id="63"]').textContent).toBe('HTML off');
	});

	test('product pages and single-product scopes never enter the batch endpoint', async () => {
		document.body.innerHTML = card(71, 'Card relacionado') + card(72, 'Produto principal', 10, 'single');
		window.fetch.mockImplementation(() => Promise.resolve({
			ok: true,
			json: () => Promise.resolve({ success: false, data: { message: 'test' } }),
		}));

		boot({ mode: 'batch', isProductPage: true });
		await flushPromises();

		expect(window.fetch).toHaveBeenCalledTimes(1);
		expect(new URLSearchParams(window.fetch.mock.calls[0][1].body).get('action')).toBe('gstore_blu_get_product_installment_quotes');
		expect(document.querySelector('[data-product-id="72"]').textContent).toBe('Produto principal');
	});
});

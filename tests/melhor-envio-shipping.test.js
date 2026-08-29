/**
 * @jest-environment jsdom
 */
const fs = require('fs');
const path = require('path');

const cartScript = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'cart.js'), 'utf8');

function itemHtml(key, rates) {
	return `
		<article data-cart-item-key="${key}" data-product-id="42" data-quantity="1">
			<div data-gstore-shipping-item>
				<div data-gstore-shipping-options></div>
				<input type="hidden" name="gstore_shipping_rates[${key}]" value='${JSON.stringify(rates)}' />
			</div>
		</article>
	`;
}

describe('Melhor Envio no carrinho', () => {
	beforeEach(() => {
		window.localStorage.clear();
		window.__GSTORE_SHIPPING_TEST__ = true;
		const melhorEnvioRate = {
			rate_id: 'gstore_custom_shipping:melhor_envio:1',
			mode: 'melhor_envio',
			provider: 'melhor_envio',
			package_key: 'package-123',
			applicable_cart_item_keys: ['item-a', 'item-b'],
			cost: 29.9,
			cost_formatted: 'R$ 29,90',
			delivery_time_min: 4,
			delivery_time_max: 6,
		};
		const regularRate = {
			rate_id: 'gstore_custom_shipping:land',
			mode: 'land',
			cost: 10,
			cost_formatted: 'R$ 10,00',
		};
		document.body.innerHTML = itemHtml('item-a', [regularRate, melhorEnvioRate]) + itemHtml('item-b', [regularRate, melhorEnvioRate]);
		window.eval(cartScript);
	});

	afterEach(() => {
		delete window.__GSTORE_SHIPPING_TEST__;
		delete window.gstoreCartMelhorEnvioTestApi;
		delete window.jQuery;
	});

	test('sincroniza o serviço em todos os itens aplicáveis e agrupa uma única cobrança', () => {
		const api = window.gstoreCartMelhorEnvioTestApi;
		const rate = {
			rate_id: 'gstore_custom_shipping:melhor_envio:1',
			mode: 'melhor_envio',
			provider: 'melhor_envio',
			package_key: 'package-123',
			applicable_cart_item_keys: ['item-a', 'item-b'],
		};

		api.synchronizeMelhorEnvioSelection(rate);

		expect(document.querySelector('input[name="gstore_selected_shipping_rate[item-a]"]').value).toBe(rate.rate_id);
		expect(document.querySelector('input[name="gstore_selected_shipping_rate[item-b]"]').value).toBe(rate.rate_id);
		expect(api.getSelectedRateGroupKeyForItem(rate, null)).toBe(`melhor_envio:package-123:${rate.rate_id}`);
		expect(api.normalizeRateMode('melhor-envio')).toBe('melhor_envio');
	});

	test('remove a seleção do pacote inteiro ao trocar um item e exibe o prazo', () => {
		const api = window.gstoreCartMelhorEnvioTestApi;
		const rate = {
			rate_id: 'gstore_custom_shipping:melhor_envio:1',
			mode: 'melhor_envio',
			provider: 'melhor_envio',
			package_key: 'package-123',
			applicable_cart_item_keys: ['item-a', 'item-b'],
			delivery_time_min: 4,
			delivery_time_max: 6,
		};

		api.synchronizeMelhorEnvioSelection(rate);
		api.leaveMelhorEnvioPackage(rate, 'item-a');

		expect(document.querySelector('input[name="gstore_selected_shipping_rate[item-b]"]').value).toBe('gstore_custom_shipping:land');
		expect(api.getRateDeadlineDisplay(rate)).toContain('4–6 dias úteis');
	});

	test('não solicita Melhor Envio nas cotações individuais', async () => {
		const api = window.gstoreCartMelhorEnvioTestApi;
		const item = document.querySelector('[data-cart-item-key="item-a"]');
		window.jQuery = {
			ajax: jest.fn().mockResolvedValue({ success: true, data: { rates: [], destination: {} } }),
		};

		await api.fetchRatesForItem(item, '01310100');

		expect(window.jQuery.ajax).toHaveBeenCalledWith(expect.objectContaining({
			data: expect.objectContaining({ exclude_melhor_envio: 1 }),
		}));
	});
});

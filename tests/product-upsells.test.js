/**
 * @jest-environment jsdom
 */
const fs = require('fs');
const path = require('path');

const script = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'product-upsells.js'), 'utf8');
const template = fs.readFileSync(path.join(__dirname, '..', 'inc', 'gstore-product-upsells.php'), 'utf8');

function boot() {
	window.gstoreProductUpsells = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		isCart: false,
	};
	window.fetch = jest.fn();
	window.MutationObserver = undefined;
	window.eval(script);
	document.dispatchEvent(new Event('DOMContentLoaded'));
}

function flushPromises() {
	return new Promise((resolve) => window.setTimeout(resolve, 0));
}

describe('GStore product upsells', () => {
	beforeEach(() => {
		document.body.innerHTML = '';
		jest.useRealTimers();
	});

	test('uses direct card actions instead of selection and bundle-total behavior', () => {
		expect(template).toContain("gstore_render_product_upsell_card( $item, 'single' )");
		expect(template).not.toContain('gstore_add_bundle');
		expect(template).not.toContain('data-gstore-upsell-checkbox');
		expect(script).not.toContain('data-gstore-upsell-checkbox');
		expect(script).not.toContain('data-gstore-upsell-bundle-add');
		expect(script).not.toContain('gstore_add_bundle');
	});

	test('sends the chosen card to the validated server endpoint and emits cart events', async () => {
		document.body.innerHTML = `
			<section data-gstore-product-upsells>
				<button data-gstore-upsell-add data-product-id="22" data-source-product-id="11">Adicionar</button>
				<p data-gstore-upsell-status></p>
			</section>
		`;
		window.fetch = jest.fn().mockResolvedValue({
			json: () => Promise.resolve({ success: true, data: { cart_hash: 'hash', message: 'Produto adicionado ao carrinho.' } }),
		});
		window.MutationObserver = undefined;
		const eventListener = jest.fn();
		document.addEventListener('wc-blocks_added_to_cart', eventListener);

		window.eval(script);
		document.dispatchEvent(new Event('DOMContentLoaded'));
		document.querySelector('[data-gstore-upsell-add]').click();
		await flushPromises();
		await flushPromises();

		expect(window.fetch).toHaveBeenCalledWith(
			'/wp-admin/admin-ajax.php',
			expect.objectContaining({ body: expect.stringContaining('action=gstore_add_product_upsell') })
		);
		expect(document.querySelector('[data-gstore-upsell-add]').textContent).toBe('Adicionado');
		expect(eventListener).toHaveBeenCalled();
	});
});

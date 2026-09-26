/** @jest-environment jsdom */
const fs = require('fs');
const path = require('path');
const $ = require('jquery');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/cashback-checkout.js'), 'utf8');

describe('Cashback no checkout', () => {
	beforeEach(() => {
		jest.useFakeTimers();
		$(document).off(); $(document.body).off();
		document.body.innerHTML = '<form class="checkout"></form><section data-step="payment"><div class="Gstore-checkout-step__payment-container"><div class="Gstore-checkout-step__order-review-slot"></div></div></section>';
		window.jQuery = $;
		window.gstoreCashback = {
			ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'nonce', loginUrl: '/minha-conta/',
			quote: { loggedIn: true, availableCoins: 1000, maximumCoins: 500, appliedCoins: 0, discountCents: 0 },
		};
		let selectedCoins = 0;
		$.ajax = jest.fn(({ data }) => {
			if ('gstore_cashback_set_coins' === data.action) selectedCoins = Number(data.coins) || 0;
			return $.Deferred().resolve({
				success: true,
				data: { ...window.gstoreCashback.quote, appliedCoins: selectedCoins, discountCents: selectedCoins },
			}).promise();
		});
		window.eval(source);
		$(document.body).trigger('updated_checkout');
		jest.runOnlyPendingTimers();
	});
	afterEach(() => { jest.clearAllTimers(); jest.useRealTimers(); });

	test('só usa moedas após a escolha do cliente e envia a quantidade ao servidor', () => {
		expect($('.gstore-cashback-checkout')).toHaveLength(1);
		expect($('form.checkout [name="gstore_cashback_coins"]').val()).toBe('0');
		$('[data-cashback-toggle]').prop('checked', true).trigger('change');
		expect($.ajax).toHaveBeenCalledWith(expect.objectContaining({ data: expect.objectContaining({ action: 'gstore_cashback_set_coins', coins: 500 }) }));
		expect($('form.checkout [name="gstore_cashback_coins"]').val()).toBe('500');
		expect($('.gstore-cashback-checkout__discount').text()).toMatch(/R\$\s*5,00/);
	});
});

const fields = {
	billing_first_name: 'Cliente', billing_last_name: 'Exemplo', billing_cpf: '00000000000',
	billing_postcode: '01001000', billing_phone: '11999999999', billing_email: 'cliente@example.com',
	billing_address_1: 'Endereço de exemplo', billing_number: '1', billing_address_2: 'Exemplo',
	billing_city: 'São Paulo', billing_state: 'SP'
};
const rates = [
	{ rate_id: 'gstore_custom_shipping:land', mode: 'land', label: 'Frete Terrestre', cost: 29.9 },
	{ rate_id: 'gstore_custom_shipping:air', mode: 'air', label: 'Frete Aéreo', cost: 49.9 },
	{ rate_id: 'gstore_custom_shipping:pickup', mode: 'pickup', label: 'Retirada na loja', cost: 0 },
	{ rate_id: 'gstore_custom_shipping:melhor_envio:1', mode: 'melhor_envio', label: 'Transportadora de teste · Serviço cadastrado', cost: 35, delivery_time_min: 3, delivery_time_max: 5 }
];
const summary = {
	items_count: 1, total: 'R$ 229,90', base_total: 'R$ 229,90', payment_method: 'blu_checkout', payment_method_title: 'Cartão',
	totals: { subtotal: 'R$ 200,00', shipping: 'R$ 29,90', discount: 'R$ 0,00', fees: [] },
	items: [{ key: 'item-a', product_id: 42, name: 'Produto de demonstração', quantity: 1, subtotal: 'R$ 200,00', image: '', gstore_shipping_rates: rates, gstore_selected_shipping_rate: rates[0].rate_id, gstore_shipping_mode: 'land' }]
};
function markup(payment = 'blu_checkout') {
	return `<main class="Gstore-checkout-steps-shell"><div class="Gstore-checkout"><form class="checkout woocommerce-checkout">
	<div class="woocommerce-billing-fields">${Object.entries(fields).map(([id, value]) => `<p id="${id}_field" class="form-row"><label for="${id}">${id.replace('billing_', '')}</label><input id="${id}" name="${id}" value="${value}"></p>`).join('')}</div>
	<div id="payment"><ul class="wc_payment_methods payment_methods methods"><li class="payment_method_blu_checkout"><input type="radio" name="payment_method" id="payment_method_blu_checkout" value="blu_checkout" ${payment === 'blu_checkout' ? 'checked' : ''}><label for="payment_method_blu_checkout">Cartão</label></li><li class="payment_method_blu_pix"><input type="radio" name="payment_method" id="payment_method_blu_pix" value="blu_pix" ${payment === 'blu_pix' ? 'checked' : ''}><label for="payment_method_blu_pix">Pix</label></li></ul></div>
	<div id="order_review"><table class="shop_table woocommerce-checkout-review-order-table"><tbody><tr><td>Produto de demonstração</td><td>R$ 200,00</td></tr></tbody><tfoot><tr class="cart-subtotal"><th>Subtotal</th><td>R$ 200,00</td></tr><tr class="order-total"><th>Total</th><td><span class="woocommerce-Price-amount">R$ 229,90</span></td></tr></tfoot></table></div>
	</form></div></main>`;
}
// Exposição somente na cópia executada pelo teste/preview; nenhum hook em produção.
function instrument(source) {
	return source.replace(/\}\)\(jQuery\);\s*$/, `window.checkoutFixture = {
		init, setActiveStep, nextStep, prevStep, renderSummary, loadCartSummary,
		getBackendCheckoutStep, restoreCheckoutDraftState,
		primeShipping() { calculatedShipping = { rates: [] }; lastCalculatedShippingCep = '01001000'; lastCalculatedDestination = { city: 'São Paulo', state: 'SP' }; },
		seedSummary(data) { lastCartSummaryData = data; renderSummary(data); }
	}; })(jQuery);`);
}
module.exports = { markup, summary, rates, instrument };

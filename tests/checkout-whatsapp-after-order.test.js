/** @jest-environment jsdom */
const fs = require('fs');
const path = require('path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/checkout-whatsapp-after-order.js'), 'utf8');
const key = 'gstore_whatsapp_after_order:v1';

beforeEach(() => {
	jest.useFakeTimers();
	sessionStorage.clear();
	document.body.innerHTML = '';
	window.history.replaceState(null, '', '/checkout/order-received/15046/');
});

afterEach(() => {
	jest.clearAllTimers();
	jest.useRealTimers();
	jest.restoreAllMocks();
});

test('abre o WhatsApp uma vez após carregar o pedido recebido', () => {
	sessionStorage.setItem(key, JSON.stringify({ path: window.location.pathname, createdAt: Date.now() }));
	document.body.innerHTML = '<a class="gstore-boleto-instructions__button" href="https://wa.me/5511999999999?text=Pedido%20%2315046">Ir para o WhatsApp</a>';
	const open = jest.spyOn(window, 'open').mockReturnValue(null);
	window.eval(source);
	expect(open).not.toHaveBeenCalled();
	window.dispatchEvent(new Event('load'));
	jest.runOnlyPendingTimers();
	expect(open).toHaveBeenCalledWith('https://wa.me/5511999999999?text=Pedido%20%2315046', '_blank', 'noopener,noreferrer');
	expect(sessionStorage.getItem(key)).toBeNull();
	window.eval(source);
	expect(open).toHaveBeenCalledTimes(1);
});

test('não abre WhatsApp para outro pedido nem para um registro expirado', () => {
	const open = jest.spyOn(window, 'open').mockReturnValue(null);
	document.body.innerHTML = '<a class="gstore-boleto-instructions__button" href="https://wa.me/5511999999999">Ir para o WhatsApp</a>';
	sessionStorage.setItem(key, JSON.stringify({ path: '/checkout/order-received/99999/', createdAt: Date.now() }));
	window.eval(source);
	window.dispatchEvent(new Event('load'));
	expect(open).not.toHaveBeenCalled();
	sessionStorage.setItem(key, JSON.stringify({ path: window.location.pathname, createdAt: Date.now() - 6 * 60 * 1000 }));
	window.eval(source);
	jest.runOnlyPendingTimers();
	expect(open).not.toHaveBeenCalled();
});

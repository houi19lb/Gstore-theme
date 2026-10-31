/* Direciona ao WhatsApp somente depois que a página do pedido recebido estiver carregada. */
(function () {
	'use strict';
	const storageKey = 'gstore_whatsapp_after_order:v1';
	let pending;
	try {
		pending = JSON.parse(window.sessionStorage.getItem(storageKey) || 'null');
	} catch (e) {
		return;
	}
	if (!pending || pending.path !== window.location.pathname) return;
	try {
		window.sessionStorage.removeItem(storageKey);
	} catch (e) {}
	if (!Number.isFinite(pending.createdAt) || Date.now() - pending.createdAt > 5 * 60 * 1000) return;

	function openFromOrderPage(attempt) {
		const button = document.querySelector('.gstore-boleto-instructions__button');
		if (!button) {
			if (attempt < 10) window.setTimeout(function () { openFromOrderPage(attempt + 1); }, 300);
			return;
		}
		try {
			const target = new URL(button.href);
			if (target.protocol === 'https:' && target.hostname === 'wa.me' && /^\/\d+$/.test(target.pathname)) {
				window.location.assign(target.href);
			}
		} catch (e) {}
	}
	if (document.readyState === 'complete') {
		window.setTimeout(function () { openFromOrderPage(0); }, 0);
	} else {
		window.addEventListener('load', function () { openFromOrderPage(0); }, { once: true });
	}
})();

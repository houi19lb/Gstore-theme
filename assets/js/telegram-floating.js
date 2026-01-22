/**
 * Gstore - Botão flutuante do Telegram
 *
 * Importante: não faz hardcode de URL. O botão copia o href/target/rel do link
 * de Telegram já existente na top bar.
 */
(function () {
	'use strict';

	var FLOAT_ID = 'gstore-telegram-float';
	var FLOAT_CLASS = 'Gstore-telegram-float';
	var MINI_CART_DRAWER_SELECTOR = '.wc-block-mini-cart__drawer';

	function normalizeText(str) {
		return (str || '').toString().trim().toLowerCase();
	}

	function isValidHref(href) {
		if (!href) return false;
		var h = normalizeText(href);
		if (h === '#' || h === '#0') return false;
		if (h.indexOf('javascript:') === 0) return false;
		// evita placeholders comuns
		if (h.indexOf('{{') !== -1 || h.indexOf('}}') !== -1) return false;
		return true;
	}

	function pickTelegramLink(anchors) {
		if (!anchors || !anchors.length) return null;

		// 1) Prioriza aria-label="Telegram"
		for (var i = 0; i < anchors.length; i++) {
			var a1 = anchors[i];
			var aria = normalizeText(a1.getAttribute('aria-label'));
			if (aria === 'telegram') return a1;
		}

		// 2) Depois, pelo texto visível contendo "telegram"
		for (var j = 0; j < anchors.length; j++) {
			var a2 = anchors[j];
			var txt = normalizeText(a2.textContent);
			if (txt.indexOf('telegram') !== -1) return a2;
		}

		return null;
	}

	function findTelegramAnchor() {
		// Suporta variações de markup (contacts/contact) e reduz risco de conflito.
		var scope =
			document.querySelector('.Gstore-top-bar__contacts') ||
			document.querySelector('.Gstore-top-bar__contact') ||
			document.querySelector('.Gstore-top-bar');

		if (!scope) return null;

		var anchors = scope.querySelectorAll('a.Gstore-top-bar__link');
		return pickTelegramLink(anchors);
	}

	function ensureFloatButton() {
		var existing = document.getElementById(FLOAT_ID);
		if (existing) return existing;

		var a = document.createElement('a');
		a.id = FLOAT_ID;
		a.className = FLOAT_CLASS;
		a.setAttribute('aria-label', 'Telegram');
		a.setAttribute('title', 'Telegram');

		// Ícone Telegram (inline SVG) — não depende de libs externas.
		a.innerHTML =
			'<svg class="Gstore-telegram-float__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
			'<path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>' +
			'</svg>';

		document.body.appendChild(a);
		return a;
	}

	function applyFromSource(sourceAnchor, floatAnchor) {
		if (!sourceAnchor || !floatAnchor) return false;

		var href = sourceAnchor.getAttribute('href');
		if (!isValidHref(href)) return false;

		floatAnchor.setAttribute('href', href);

		// Copia comportamentos de abertura/segurança do link original
		var target = sourceAnchor.getAttribute('target') || '_blank';
		floatAnchor.setAttribute('target', target);

		var rel = sourceAnchor.getAttribute('rel') || 'noopener';
		floatAnchor.setAttribute('rel', rel);

		// Importante: o link da top bar pode ficar oculto no mobile por CSS responsivo.
		// O botão flutuante deve continuar aparecendo desde que exista um href válido.
		floatAnchor.style.display = '';

		return true;
	}

	function toggleTelegramFloatVisibility(floatAnchor) {
		if (!floatAnchor) return;

		var drawer = document.querySelector(MINI_CART_DRAWER_SELECTOR);
		if (!drawer) {
			floatAnchor.style.display = '';
			return;
		}

		if (drawer.classList.contains('is-open')) {
			floatAnchor.style.display = 'none';
		} else {
			floatAnchor.style.display = '';
		}
	}

	function observeMiniCartDrawer(floatAnchor) {
		if (!floatAnchor) return false;
		if (floatAnchor.__gstoreMiniCartObserver) return true;

		var drawer = document.querySelector(MINI_CART_DRAWER_SELECTOR);
		if (!drawer) return false;

		toggleTelegramFloatVisibility(floatAnchor);

		try {
			var obs = new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
						toggleTelegramFloatVisibility(floatAnchor);
					}
				});
			});
			obs.observe(drawer, { attributes: true, attributeFilter: ['class'] });
			floatAnchor.__gstoreMiniCartObserver = true;
			return true;
		} catch (e) {
			return false;
		}
	}

	function sync() {
		var src = findTelegramAnchor();
		if (!src) return false;

		var floating = ensureFloatButton();
		var ok = applyFromSource(src, floating);
		if (!ok) return false;

		toggleTelegramFloatVisibility(floating);
		observeMiniCartDrawer(floating);

		// Observa alterações no link fonte para manter o botão atualizado.
		try {
			if (!src.__gstoreTelegramObserved) {
				var obs = new MutationObserver(function () {
					applyFromSource(src, floating);
				});
				obs.observe(src, { attributes: true, attributeFilter: ['href', 'target', 'rel', 'style', 'class'] });
				src.__gstoreTelegramObserved = true;
			}
		} catch (e) {
			// Silencioso: MutationObserver pode falhar em browsers muito antigos.
		}

		return true;
	}

	function boot() {
		// Tenta imediatamente e depois faz alguns retries (caso o header seja renderizado depois).
		if (sync()) return;

		var tries = 0;
		var maxTries = 20;
		var timer = window.setInterval(function () {
			tries++;
			if (sync() || tries >= maxTries) {
				window.clearInterval(timer);
			}
		}, 250);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();


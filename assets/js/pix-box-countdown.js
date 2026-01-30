/**
 * Pix Box v2 – Countdown + barra com curva (queda rápida no início, desacelera no fim)
 * Barra = (remaining/total)^GAMMA. Quanto maior GAMMA, mais a barra cai no começo.
 *
 * Uso: coloque data-expires-at (timestamp Unix em segundos) no container .pix-urgency.
 * Dentro dele: .pix-timer__right (onde aparece MM:SS) e .pix-bar > i (a barra).
 *
 * @package Gstore
 */

(function () {
	'use strict';

	var GAMMA = 2.4;

	function pad(n) {
		return String(n).padStart(2, '0');
	}

	function fmt(sec) {
		var m = Math.floor(sec / 60);
		var s = sec % 60;
		return pad(m) + ':' + pad(s);
	}

	function fmtCapped(sec, maxSec) {
		maxSec = maxSec || 99 * 60 + 59;
		var display = Math.min(Math.max(0, Math.floor(sec)), maxSec);
		var suffix = sec > maxSec ? '+' : '';
		return fmt(display) + suffix;
	}

	function initCountdown(urgency) {
		var expiresAt = parseInt(urgency.getAttribute('data-expires-at'), 10);
		if (!expiresAt || isNaN(expiresAt)) return;
		if (expiresAt > 1e12) {
			expiresAt = Math.floor(expiresAt / 1000);
		}

		var totalSeconds = parseInt(urgency.getAttribute('data-total-seconds'), 10) || 15 * 60;
		var ttlEl = urgency.querySelector('.pix-timer__right');
		var barEl = urgency.querySelector('.pix-bar > i');
		if (!ttlEl) return;

		var maxDisplaySec = 99 * 60 + 59;

		function getRemaining() {
			var now = Math.floor(Date.now() / 1000);
			return Math.max(0, expiresAt - now);
		}

		function render() {
			var remaining = getRemaining();

			if (ttlEl) {
				ttlEl.textContent = fmtCapped(remaining, maxDisplaySec);
				ttlEl.setAttribute('aria-live', 'polite');
			}

			if (barEl) {
				var ratio = Math.min(1, Math.max(0, remaining / totalSeconds));
				var curved = Math.pow(ratio, GAMMA);
				barEl.style.transform = 'scaleX(' + curved + ')';
			}

			if (remaining <= 0) {
				if (ttlEl) ttlEl.textContent = '0:00';
				if (barEl) barEl.style.transform = 'scaleX(0)';
				var timerEl = urgency.querySelector('.pix-timer');
				if (timerEl) timerEl.classList.remove('pix-timer--pulse');
				if (typeof window.dispatchEvent === 'function') {
					window.dispatchEvent(new CustomEvent('gstore_pix_countdown_finished', { detail: { element: urgency } }));
				}
				return true;
			}
			return false;
		}

		render();
		var interval = setInterval(function () {
			if (render()) clearInterval(interval);
		}, 1000);
	}

	/**
	 * Cria e insere o bloco .pix-urgency dentro de um .pix-box (layout do plugin)
	 * quando o plugin já envia data-expires-at no .pix-box mas não gera a estrutura v2.
	 */
	function injectUrgencyBlock(pixBox) {
		var expiresAt = pixBox.getAttribute('data-expires-at');
		var totalSeconds = pixBox.getAttribute('data-total-seconds') || '900';

		var urgency = document.createElement('div');
		urgency.className = 'pix-urgency';
		urgency.setAttribute('data-expires-at', expiresAt);
		urgency.setAttribute('data-total-seconds', totalSeconds);
		urgency.setAttribute('role', 'status');
		urgency.setAttribute('aria-live', 'polite');
		urgency.innerHTML =
			'<div class="pix-timer pix-timer--pulse">' +
			'<div class="pix-timer__left">' +
			'<strong>Seu Pix expira em</strong>' +
			'<span>Finalize agora para garantir a reserva.</span>' +
			'</div>' +
			'<div class="pix-timer__right">15:00</div>' +
			'</div>' +
			'<div class="pix-bar" aria-hidden="true"><i></i></div>';

		var details = pixBox.querySelector('.pix-box__details') || pixBox.querySelector('.pix-box__detail');
		var amount = pixBox.querySelector('.pix-box__amount');
		if (details) {
			if (amount) {
				details.insertBefore(urgency, amount.nextElementSibling);
			} else {
				details.insertBefore(urgency, details.firstChild);
			}
		} else {
			var content = pixBox.querySelector('.pix-box__content');
			if (content) {
				content.appendChild(urgency);
			} else {
				pixBox.appendChild(urgency);
			}
		}
		return urgency;
	}

	function init() {
		var urgencies = document.querySelectorAll('.pix-urgency[data-expires-at]');
		urgencies.forEach(initCountdown);

		var pixBoxes = document.querySelectorAll('.pix-box[data-expires-at]');
		pixBoxes.forEach(function (pixBox) {
			if (pixBox.querySelector('.pix-urgency[data-expires-at]')) {
				return;
			}
			if (!pixBox.classList.contains('pix-box--pending')) {
				return;
			}
			var urgency = injectUrgencyBlock(pixBox);
			initCountdown(urgency);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

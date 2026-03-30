(function () {
	'use strict';

	var config = window.gstorePwaConfig || {};
	var deferredPrompt = null;
	var modal = null;
	var ctaVisible = false;
	var mediaQuery = null;

	function isAndroidLikely() {
		return /android/i.test(window.navigator.userAgent || '');
	}

	function isStandaloneMode() {
		var standaloneByMedia = false;
		try {
			mediaQuery = mediaQuery || window.matchMedia('(display-mode: standalone)');
			standaloneByMedia = !!mediaQuery.matches;
		} catch (e) {
			standaloneByMedia = false;
		}

		return standaloneByMedia || window.navigator.standalone === true;
	}

	function canShowPageCta() {
		return !!config.canShowInstallCta && !!config.isAtendimentoPage && isAndroidLikely();
	}

	function ensureApi() {
		window.gstorePwa = window.gstorePwa || {};
		window.gstorePwa.canInstall = !!deferredPrompt;
		window.gstorePwa.isStandalone = isStandaloneMode();
		window.gstorePwa.promptInstall = function () {
			if (!deferredPrompt) {
				return Promise.reject(new Error('install_prompt_unavailable'));
			}

			var promptEvent = deferredPrompt;
			deferredPrompt = null;
			updateApiState();
			hideCta();

			return promptEvent.prompt()
				.then(function () {
					return promptEvent.userChoice;
				})
				.catch(function (error) {
					return Promise.reject(error);
				});
		};
	}

	function updateApiState() {
		ensureApi();
		window.gstorePwa.canInstall = !!deferredPrompt;
		window.gstorePwa.isStandalone = isStandaloneMode();
	}

	function applyStandaloneClass() {
		var standalone = isStandaloneMode();
		if (!document.body) return;

		document.body.classList.toggle('gstore-pwa-standalone', standalone);
		if (document.documentElement) {
			document.documentElement.classList.toggle('gstore-pwa-standalone', standalone);
		}

		updateApiState();
		if (standalone) {
			hideCta();
		}
	}

	function getText(key, fallback) {
		if (config.texts && typeof config.texts[key] === 'string' && config.texts[key]) {
			return config.texts[key];
		}

		return fallback;
	}

	function getModal() {
		if (modal) return modal;
		if (!document.body || !canShowPageCta()) return null;

		modal = document.createElement('div');
		modal.className = 'Gstore-pwa-install-modal';
		modal.hidden = true;
		modal.setAttribute('aria-hidden', 'true');
		modal.innerHTML =
			'<div class="Gstore-pwa-install-modal__panel" role="dialog" aria-modal="true" aria-labelledby="gstore-pwa-install-title">' +
				'<button type="button" class="Gstore-pwa-install-modal__close" data-gstore-pwa-close aria-label="' + getText('close', 'Fechar') + '">&times;</button>' +
				'<p class="Gstore-pwa-install-modal__badge">' + getText('badge', 'Android App') + '</p>' +
				'<h2 id="gstore-pwa-install-title" class="Gstore-pwa-install-modal__title">' + getText('title', 'Instalar o site como aplicativo') + '</h2>' +
				'<p class="Gstore-pwa-install-modal__description">' + getText('description', 'Teste a versao instalada no Android para validar navegacao, atalhos e experiencia em modo app.') + '</p>' +
				'<div class="Gstore-pwa-install-modal__actions">' +
					'<button type="button" class="Gstore-pwa-install-modal__button" data-gstore-pwa-install-button>' + getText('button', 'Instalar app') + '</button>' +
				'</div>' +
			'</div>';

		document.body.appendChild(modal);

		var installButton = modal.querySelector('[data-gstore-pwa-install-button]');
		if (installButton) {
			installButton.addEventListener('click', function () {
				if (!window.gstorePwa || typeof window.gstorePwa.promptInstall !== 'function') return;

				window.gstorePwa.promptInstall().catch(function () {
					// Sem acao: se o prompt nao existir mais, o modal permanece oculto.
				});
			});
		}

		var closeButton = modal.querySelector('[data-gstore-pwa-close]');
		if (closeButton) {
			closeButton.addEventListener('click', function () {
				hideCta();
			});
		}

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && ctaVisible) {
				hideCta();
			}
		});

		return modal;
	}

	function showCta() {
		var el = getModal();
		if (!el || ctaVisible || !canShowPageCta() || !deferredPrompt || isStandaloneMode()) {
			return;
		}

		el.hidden = false;
		el.setAttribute('aria-hidden', 'false');
		document.body.classList.add('gstore-pwa-install-modal-open');
		window.requestAnimationFrame(function () {
			el.classList.add('is-visible');
		});
		ctaVisible = true;
	}

	function hideCta() {
		var el = getModal();
		if (!el) return;

		el.classList.remove('is-visible');
		el.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('gstore-pwa-install-modal-open');
		window.setTimeout(function () {
			if (!el.classList.contains('is-visible')) {
				el.hidden = true;
			}
		}, 260);
		ctaVisible = false;
	}

	function registerServiceWorker() {
		if (!('serviceWorker' in window.navigator) || !config.serviceWorkerUrl) {
			return;
		}

		if (!window.isSecureContext && window.location.hostname !== 'localhost') {
			return;
		}

		window.navigator.serviceWorker.register(config.serviceWorkerUrl, { scope: config.scopePath || '/' }).catch(function () {
			// Sem fallback visual no v1.
		});
	}

	function bindStandaloneWatcher() {
		try {
			mediaQuery = mediaQuery || window.matchMedia('(display-mode: standalone)');
			if (typeof mediaQuery.addEventListener === 'function') {
				mediaQuery.addEventListener('change', applyStandaloneClass);
			} else if (typeof mediaQuery.addListener === 'function') {
				mediaQuery.addListener(applyStandaloneClass);
			}
		} catch (e) {
			// Sem suporte.
		}
	}

	function init() {
		ensureApi();
		applyStandaloneClass();
		registerServiceWorker();
		bindStandaloneWatcher();

		if (!canShowPageCta()) {
			hideCta();
		}
	}

	window.addEventListener('beforeinstallprompt', function (event) {
		event.preventDefault();
		deferredPrompt = event;
		updateApiState();
		showCta();
	});

	window.addEventListener('appinstalled', function () {
		deferredPrompt = null;
		updateApiState();
		applyStandaloneClass();
		hideCta();
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true });
	} else {
		init();
	}
})();

(function () {
	'use strict';

	var config = window.gstorePwaConfig || {};
	var deferredPrompt = null;
	var placeholder = null;
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

	function getPlaceholder() {
		if (placeholder) return placeholder;
		placeholder = document.querySelector('[data-gstore-pwa-cta]');
		return placeholder;
	}

	function renderCtaMarkup() {
		var el = getPlaceholder();
		if (!el) return;

		el.innerHTML =
			'<div class="Gstore-pwa-install-card">' +
				'<div class="Gstore-pwa-install-card__content">' +
					'<p class="Gstore-pwa-install-card__badge">' + getText('badge', 'Android App') + '</p>' +
					'<h2 class="Gstore-pwa-install-card__title">' + getText('title', 'Instalar o site como aplicativo') + '</h2>' +
					'<p class="Gstore-pwa-install-card__description">' + getText('description', 'Teste a versao instalada no Android para validar navegacao, atalhos e experiencia em modo app.') + '</p>' +
					'<p class="Gstore-pwa-install-card__hint">' + getText('hint', 'O app abre na Home e mantem acesso normal a Atendimento e Minha Conta.') + '</p>' +
				'</div>' +
				'<div class="Gstore-pwa-install-card__actions">' +
					'<button type="button" class="Gstore-pwa-install-card__button" data-gstore-pwa-install-button>' + getText('button', 'Instalar app') + '</button>' +
				'</div>' +
			'</div>';

		var button = el.querySelector('[data-gstore-pwa-install-button]');
		if (button) {
			button.addEventListener('click', function () {
				if (!window.gstorePwa || typeof window.gstorePwa.promptInstall !== 'function') return;

				window.gstorePwa.promptInstall().catch(function () {
					// Sem acao: se o prompt nao existir mais, o CTA permanece oculto.
				});
			});
		}
	}

	function showCta() {
		var el = getPlaceholder();
		if (!el || ctaVisible || !canShowPageCta() || !deferredPrompt || isStandaloneMode()) {
			return;
		}

		renderCtaMarkup();
		el.hidden = false;
		ctaVisible = true;
	}

	function hideCta() {
		var el = getPlaceholder();
		if (!el) return;

		el.hidden = true;
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

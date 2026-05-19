(function () {
	'use strict';

	var config = window.gstorePwaConfig || {};
	var deferredPrompt = null;
	var modal = null;
	var ctaVisible = false;
	var mediaQuery = null;
	var mobileQuery = null;
	var installTriggersBound = false;
	var fallbackTimer = null;
	var dismissStorageKey = 'gstore_pwa_install_cta_dismissed';

	function isAndroidLikely() {
		return /android/i.test(window.navigator.userAgent || '');
	}

	function isIosLikely() {
		var ua = window.navigator.userAgent || '';
		var platform = window.navigator.platform || '';
		return /iphone|ipad|ipod/i.test(ua) || (platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
	}

	function isSafariLikely() {
		var ua = window.navigator.userAgent || '';
		return /safari/i.test(ua) && !/crios|fxios|edgios|opios|chrome|android/i.test(ua);
	}

	function isMobileUserAgent() {
		return /android|iphone|ipad|ipod|iemobile|windows phone|mobile/i.test(window.navigator.userAgent || '');
	}

	function isMobileViewport() {
		try {
			mobileQuery = mobileQuery || window.matchMedia('(max-width: 767px)');
			return !!mobileQuery.matches;
		} catch (e) {
			return window.innerWidth ? window.innerWidth <= 767 : false;
		}
	}

	function isMobileExperience() {
		return isMobileUserAgent() || isMobileViewport();
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
		return !!config.canShowInstallCta && !!config.isAtendimentoPage && isMobileExperience() && (isAndroidLikely() || isIosLikely() || !!deferredPrompt);
	}

	function canOfferInstall() {
		return canShowPageCta() && !isStandaloneMode();
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
		updateInstallCards();
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

	function wasDismissed() {
		try {
			return window.sessionStorage.getItem(dismissStorageKey) === '1';
		} catch (e) {
			return false;
		}
	}

	function markDismissed() {
		try {
			window.sessionStorage.setItem(dismissStorageKey, '1');
		} catch (e) {
			// Sem suporte a sessionStorage.
		}
	}

	function hasBlockingModalOpen() {
		var ageModal = document.getElementById('gstore-age-modal');
		return !!ageModal && ageModal.getAttribute('aria-hidden') === 'false';
	}

	function getInstallCards() {
		if (!document.querySelectorAll) return [];
		return Array.prototype.slice.call(document.querySelectorAll('[data-gstore-pwa-card]'));
	}

	function getInstallTriggers() {
		if (!document.querySelectorAll) return [];
		return Array.prototype.slice.call(document.querySelectorAll('[data-gstore-pwa-open]'));
	}

	function closestInstallTrigger(target) {
		if (!target) return null;

		if (typeof target.closest === 'function') {
			return target.closest('[data-gstore-pwa-open]');
		}

		while (target && target !== document) {
			if (target.getAttribute && target.getAttribute('data-gstore-pwa-open') !== null) {
				return target;
			}
			target = target.parentNode;
		}

		return null;
	}

	function updateInstallCards() {
		var canOffer = canOfferInstall();
		var cards = getInstallCards();
		var triggers = getInstallTriggers();

		cards.forEach(function (card) {
			card.hidden = !canOffer;
		});

		triggers.forEach(function (trigger) {
			if ('disabled' in trigger) {
				trigger.disabled = !canOffer;
			}
			trigger.setAttribute('aria-disabled', canOffer ? 'false' : 'true');
		});
	}

	function updateModalMode() {
		var el = getModal();
		if (!el) return;

		var hasNativePrompt = !!deferredPrompt;
		var isIos = isIosLikely();
		var isIosSafari = isIos && isSafariLikely();
		var badge = el.querySelector('.Gstore-pwa-install-modal__badge');
		var title = el.querySelector('.Gstore-pwa-install-modal__title');
		var description = el.querySelector('.Gstore-pwa-install-modal__description');
		var hint = el.querySelector('.Gstore-pwa-install-modal__hint');
		var steps = el.querySelector('.Gstore-pwa-install-modal__steps');
		var button = el.querySelector('[data-gstore-pwa-install-button]');

		el.classList.toggle('is-fallback', !hasNativePrompt);
		el.classList.toggle('is-ios', isIos && !hasNativePrompt);

		if (badge) {
			badge.textContent = isIos && !hasNativePrompt ? getText('iosBadge', 'iPhone / Safari') : getText('badge', 'Android App');
		}

		if (title) {
			title.textContent = isIos && !hasNativePrompt
				? getText('iosTitle', 'Adicionar a Tela de Inicio')
				: getText('title', 'Instalar o site como aplicativo');
		}

		if (description) {
			if (hasNativePrompt) {
				description.textContent = getText('description', 'Teste a versao instalada no Android para validar navegacao, atalhos e experiencia em modo app.');
			} else if (isIosSafari) {
				description.textContent = getText('iosDescription', 'No iPhone, o Safari exige que voce adicione manualmente pela opcao Compartilhar.');
			} else if (isIos) {
				description.textContent = getText('iosOtherBrowserDescription', 'No iPhone, abra este site no Safari para adicionar como app na tela inicial.');
			} else {
				description.textContent = getText('fallbackDescription', 'No Android, abra o menu do navegador e escolha Instalar app ou Adicionar a tela inicial.');
			}
		}

		if (hint) {
			hint.hidden = hasNativePrompt || isIos;
			hint.textContent = getText('fallbackHint', 'Se o botao de instalacao nativo aparecer, use ele para baixar o app automaticamente.');
		}

		if (steps) {
			steps.hidden = hasNativePrompt || !isIos;
			steps.innerHTML = isIosSafari
				? '<li>Toque em Compartilhar no Safari.</li><li>Escolha Adicionar a Tela de Inicio.</li><li>Confirme em Adicionar.</li>'
				: '<li>Abra esta pagina no Safari.</li><li>Toque em Compartilhar.</li><li>Escolha Adicionar a Tela de Inicio.</li>';
		}

		if (button) {
			button.textContent = hasNativePrompt ? getText('button', 'Instalar app') : getText('fallbackButton', 'Entendi');
		}
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
				'<p class="Gstore-pwa-install-modal__hint" hidden></p>' +
				'<ol class="Gstore-pwa-install-modal__steps" hidden></ol>' +
				'<div class="Gstore-pwa-install-modal__actions">' +
					'<button type="button" class="Gstore-pwa-install-modal__button" data-gstore-pwa-install-button>' + getText('button', 'Instalar app') + '</button>' +
				'</div>' +
			'</div>';

		document.body.appendChild(modal);

		var installButton = modal.querySelector('[data-gstore-pwa-install-button]');
		if (installButton) {
			installButton.addEventListener('click', function () {
				if (!deferredPrompt) {
					hideCta(true);
					return;
				}

				if (!window.gstorePwa || typeof window.gstorePwa.promptInstall !== 'function') return;

				window.gstorePwa.promptInstall().catch(function () {
					// Sem acao: se o prompt nao existir mais, o modal permanece oculto.
				});
			});
		}

		var closeButton = modal.querySelector('[data-gstore-pwa-close]');
		if (closeButton) {
			closeButton.addEventListener('click', function () {
				hideCta(true);
			});
		}

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && ctaVisible) {
				hideCta(true);
			}
		});

		return modal;
	}

	function showCta(force) {
		updateInstallCards();

		if (!canOfferInstall()) {
			return false;
		}

		if (!force && wasDismissed()) {
			return false;
		}

		if (!force && hasBlockingModalOpen()) {
			scheduleAutoCta(1200);
			return false;
		}

		var el = getModal();
		if (!el) {
			return false;
		}

		updateModalMode();

		if (ctaVisible) {
			return true;
		}

		el.hidden = false;
		el.setAttribute('aria-hidden', 'false');
		document.body.classList.add('gstore-pwa-install-modal-open');
		window.requestAnimationFrame(function () {
			el.classList.add('is-visible');
		});
		ctaVisible = true;
		return true;
	}

	function hideCta(dismiss) {
		var el = getModal();
		if (!el) return;

		if (dismiss) {
			markDismissed();
		}

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

	function scheduleAutoCta(delay) {
		if (fallbackTimer || !canOfferInstall() || wasDismissed()) return;

		fallbackTimer = window.setTimeout(function () {
			fallbackTimer = null;
			if (canOfferInstall() && !wasDismissed()) {
				showCta();
			}
		}, delay || 1600);
	}

	function bindInstallTriggers() {
		if (installTriggersBound || !document.addEventListener) return;
		installTriggersBound = true;

		document.addEventListener('click', function (event) {
			var trigger = closestInstallTrigger(event.target);
			if (!trigger) return;

			event.preventDefault();
			if (trigger.disabled || trigger.getAttribute('aria-disabled') === 'true') {
				return;
			}

			showCta(true);
		});
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

	function bindMobileWatcher() {
		try {
			mobileQuery = mobileQuery || window.matchMedia('(max-width: 767px)');
			var onChange = function () {
				updateInstallCards();
				if (!canShowPageCta()) {
					hideCta();
				} else if (canOfferInstall() && !wasDismissed()) {
					scheduleAutoCta();
				}
			};

			if (typeof mobileQuery.addEventListener === 'function') {
				mobileQuery.addEventListener('change', onChange);
			} else if (typeof mobileQuery.addListener === 'function') {
				mobileQuery.addListener(onChange);
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
		bindMobileWatcher();
		bindInstallTriggers();
		updateInstallCards();

		if (canOfferInstall() && deferredPrompt) {
			showCta();
		} else {
			scheduleAutoCta();
		}

		if (!canShowPageCta()) {
			hideCta();
		}
	}

	window.addEventListener('beforeinstallprompt', function (event) {
		deferredPrompt = event;
		updateApiState();

		if (!canShowPageCta()) {
			return;
		}

		event.preventDefault();
		updateInstallCards();
		updateModalMode();
		showCta();
	});

	window.addEventListener('appinstalled', function () {
		deferredPrompt = null;
		updateApiState();
		applyStandaloneClass();
		updateInstallCards();
		hideCta();
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true });
	} else {
		init();
	}
})();

/**
 * Gstore - Quick action de atendimento (Telegram + Chat do site/Chatwoot)
 *
 * - Mantem o link do Telegram dinamico a partir da top bar (sem hardcode)
 * - Fallback seguro: se a bridge do plugin nao existir, Telegram continua funcional
 * - Modal "fake" para Chatwoot (estrategia A): tema controla moldura/overlay e aciona o widget
 */
(function () {
	'use strict';

	var CONFIG = window.gstoreSupportQuickAction || {};
	var TEXTS = CONFIG.texts || {};
	var STORAGE_KEY = (CONFIG.storageKey || 'gstore_support_preference').toString();
	var CHAT_PREF_VALUE = (CONFIG.chatPreferenceValue || 'chat_site').toString();
	var CHAT_UI_STRATEGY = (CONFIG.uiStrategy || 'A').toString().toUpperCase();

	var FLOAT_ID = 'gstore-support-float';
	var FLOAT_CLASS = 'Gstore-support-float';
	var FLOAT_LEGACY_CLASS = 'Gstore-telegram-float';
	var SELECTOR_MODAL_ID = 'gstore-support-selector-modal';
	var CHAT_MODAL_ID = 'gstore-support-chat-modal';
	var BODY_MODAL_CLASS = 'gstore-support-modal-open';
	var BODY_CHAT_CLASS = 'gstore-support-chat-open';

	var MINI_CART_DRAWER_SELECTOR = '.wc-block-mini-cart__drawer';
	var MINI_CART_OVERLAY_SELECTOR = '.wc-block-components-drawer__screen-overlay';
	var READY_EVENT_NAMES = [
		'gstore:chatwoot:ready',
		'gstore-chatwoot-ready',
		'gstore:support:chat-ready',
		'gstore-support-chat-ready',
		'chatwoot:ready',
		'chatwoot-ready',
		'chatwoot:widget-ready'
	];

	var state = {
		chatReady: false,
		preferredChannel: null,
		telegramLink: null,
		activeModal: null,
		quickActionConfig: null
	};

	function demojibakePt(value) {
		if (typeof value !== 'string' || value.indexOf('Ã') === -1) return value;
		return value
			.replace(/ÃƒÂ£/g, '\u00e3')
			.replace(/ÃƒÂµ/g, '\u00f5')
			.replace(/ÃƒÂ¡/g, '\u00e1')
			.replace(/ÃƒÂ©/g, '\u00e9')
			.replace(/ÃƒÂ­/g, '\u00ed')
			.replace(/ÃƒÂ³/g, '\u00f3')
			.replace(/ÃƒÂº/g, '\u00fa')
			.replace(/ÃƒÂ§/g, '\u00e7')
			.replace(/Ã£/g, '\u00e3')
			.replace(/Ãµ/g, '\u00f5')
			.replace(/Ã¡/g, '\u00e1')
			.replace(/Ã©/g, '\u00e9')
			.replace(/Ã­/g, '\u00ed')
			.replace(/Ã³/g, '\u00f3')
			.replace(/Ãº/g, '\u00fa')
			.replace(/Ã§/g, '\u00e7');
	}

	function normalizeTextMap(map) {
		if (!map || typeof map !== 'object') return map;
		for (var key in map) {
			if (Object.prototype.hasOwnProperty.call(map, key)) {
				map[key] = demojibakePt(map[key]);
			}
		}
		return map;
	}

	normalizeTextMap(TEXTS);

	function text(key, fallback) {
		if (TEXTS && typeof TEXTS[key] === 'string' && TEXTS[key]) return TEXTS[key];
		return fallback;
	}

	function normalizeText(str) {
		return (str || '').toString().trim().toLowerCase();
	}

	function isValidHref(href) {
		if (!href) return false;
		var h = normalizeText(href);
		if (h === '#' || h === '#0') return false;
		if (h.indexOf('javascript:') === 0) return false;
		if (h.indexOf('{{') !== -1 || h.indexOf('}}') !== -1) return false;
		return true;
	}

	function safeJsonParse(value) {
		try {
			return JSON.parse(value);
		} catch (e) {
			return null;
		}
	}

	function canUseStorage() {
		try {
			var key = '__gstore_support_test__';
			window.localStorage.setItem(key, '1');
			window.localStorage.removeItem(key);
			return true;
		} catch (e) {
			return false;
		}
	}

	function readLocalPreference() {
		if (!canUseStorage()) return null;
		var raw = window.localStorage.getItem(STORAGE_KEY);
		if (!raw) return null;

		var parsed = safeJsonParse(raw);
		if (parsed && parsed.channel === CHAT_PREF_VALUE) return CHAT_PREF_VALUE;
		if (raw === CHAT_PREF_VALUE) return CHAT_PREF_VALUE;
		return null;
	}

	function writeLocalPreference(channel) {
		if (!canUseStorage()) return;
		try {
			if (channel === CHAT_PREF_VALUE) {
				window.localStorage.setItem(
					STORAGE_KEY,
					JSON.stringify({
						channel: CHAT_PREF_VALUE,
						updatedAt: Date.now()
					})
				);
				return;
			}
			window.localStorage.removeItem(STORAGE_KEY);
		} catch (e) {
			// Silencioso: armazenamento pode falhar em modo privado/restrito.
		}
	}

	function emitEvent(name, detail) {
		try {
			window.dispatchEvent(
				new CustomEvent(name, {
					detail: detail || {}
				})
			);
		} catch (e) {
			// Fallback para browsers sem CustomEvent constructor.
			try {
				var evt = document.createEvent('CustomEvent');
				evt.initCustomEvent(name, false, false, detail || {});
				window.dispatchEvent(evt);
			} catch (e2) {
				// Sem suporte.
			}
		}
	}

	function getBridge() {
		if (window.GstoreSupportBridge) return window.GstoreSupportBridge;
		if (window.gstoreSupportBridge) return window.gstoreSupportBridge;
		if (window.GSTORE && window.GSTORE.supportBridge) return window.GSTORE.supportBridge;
		return null;
	}

	function callBridge(methodNames, args) {
		var bridge = getBridge();
		if (!bridge) return { ok: false };

		var names = Array.isArray(methodNames) ? methodNames : [methodNames];
		for (var i = 0; i < names.length; i++) {
			var name = names[i];
			if (typeof bridge[name] === 'function') {
				try {
					return { ok: true, value: bridge[name].apply(bridge, args || []) };
				} catch (e) {
					return { ok: false, error: e };
				}
			}
		}
		return { ok: false };
	}

	function getBridgeQuickActionConfig() {
		var viaMethod = callBridge(['getQuickActionConfig'], []);
		if (viaMethod.ok && viaMethod.value && typeof viaMethod.value === 'object') {
			return viaMethod.value;
		}

		var bridge = getBridge();
		if (bridge && bridge.quickAction && typeof bridge.quickAction === 'object') {
			return bridge.quickAction;
		}

		return null;
	}

	function syncQuickActionConfigFromBridge() {
		var qa = getBridgeQuickActionConfig();
		if (!qa || typeof qa !== 'object') return false;

		state.quickActionConfig = qa;
		if (isValidHref(qa.telegramUrl)) {
			state.telegramLink = qa.telegramUrl;
		}
		return true;
	}

	function getEffectiveQuickActionConfig() {
		return state.quickActionConfig && typeof state.quickActionConfig === 'object'
			? state.quickActionConfig
			: null;
	}

	function getEffectiveQuickActionType() {
		var qa = getEffectiveQuickActionConfig();
		if (qa && typeof qa.effectiveType === 'string' && qa.effectiveType) {
			return qa.effectiveType;
		}
		return 'selector';
	}

	function getQuickActionDisplayMode() {
		var qa = getEffectiveQuickActionConfig();
		if (qa && qa.display === 'icon_only') return 'icon_only';
		return 'icon_label';
	}

	function getQuickActionLabel() {
		var qa = getEffectiveQuickActionConfig();
		if (qa && typeof qa.effectiveLabel === 'string' && qa.effectiveLabel.trim()) {
			return qa.effectiveLabel.trim();
		}
		return null;
	}

	function getQuickActionIconKind() {
		var qa = getEffectiveQuickActionConfig();
		if (qa && typeof qa.effectiveIcon === 'string' && qa.effectiveIcon) {
			return qa.effectiveIcon;
		}
		return getEffectiveQuickActionType() === 'telegram' ? 'telegram' : 'chat';
	}

	function detectChatReady() {
		var qa = getEffectiveQuickActionConfig();
		if (qa && qa.chatwootEnabled === false) {
			return false;
		}

		var bridge = getBridge();
		if (bridge) {
			if (typeof bridge.isChatReady === 'function') {
				try {
					return !!bridge.isChatReady();
				} catch (e) {}
			}
			if (typeof bridge.isReady === 'function') {
				try {
					return !!bridge.isReady('chat');
				} catch (e2) {}
			}
			if (bridge.chatReady === true || bridge.isChatwootReady === true || bridge.ready === true) {
				return true;
			}
		}

		if (window.$chatwoot && typeof window.$chatwoot.toggle === 'function') return true;
		return false;
	}

	function openChatEngine() {
		var bridgeResult = callBridge(
			['openChat', 'openChatwoot', 'showChat', 'open'],
			[
				{
					presentation: 'modal-shell',
					strategy: CHAT_UI_STRATEGY
				}
			]
		);
		if (bridgeResult.ok) return true;

		if (window.$chatwoot && typeof window.$chatwoot.toggle === 'function') {
			try {
				window.$chatwoot.toggle('open');
				return true;
			} catch (e) {}
			try {
				window.$chatwoot.toggle();
				return true;
			} catch (e2) {}
		}

		emitEvent('gstore:support:open-chat', { strategy: CHAT_UI_STRATEGY });
		return false;
	}

	function closeChatEngine() {
		var bridgeResult = callBridge(
			['closeChat', 'closeChatwoot', 'hideChat', 'close'],
			[
				{
					presentation: 'modal-shell',
					strategy: CHAT_UI_STRATEGY
				}
			]
		);
		if (bridgeResult.ok) return true;

		if (window.$chatwoot && typeof window.$chatwoot.toggle === 'function') {
			try {
				window.$chatwoot.toggle('close');
				return true;
			} catch (e) {}
		}

		emitEvent('gstore:support:close-chat', { strategy: CHAT_UI_STRATEGY });
		return false;
	}

	function getPreferredChannel() {
		var fromBridge = callBridge(['getPreferredChannel', 'getPreference'], []);
		if (fromBridge.ok && fromBridge.value === CHAT_PREF_VALUE) return CHAT_PREF_VALUE;
		return readLocalPreference();
	}

	function persistPreferredChannel(channel) {
		state.preferredChannel = channel === CHAT_PREF_VALUE ? CHAT_PREF_VALUE : null;

		callBridge(
			['setPreferredChannel', 'setPreference'],
			[state.preferredChannel, { source: 'theme-quick-action' }]
		);

		if (!state.preferredChannel) {
			callBridge(['resetPreference'], [{ source: 'theme-quick-action' }]);
		}

		writeLocalPreference(state.preferredChannel);
		updateQuickActionPresentation();
	}

	function pickTelegramLink(anchors) {
		if (!anchors || !anchors.length) return null;

		for (var i = 0; i < anchors.length; i++) {
			var byAria = anchors[i];
			var aria = normalizeText(byAria.getAttribute('aria-label'));
			if (aria === 'telegram') return byAria;
		}

		for (var j = 0; j < anchors.length; j++) {
			var byText = anchors[j];
			var txt = normalizeText(byText.textContent);
			if (txt.indexOf('telegram') !== -1) return byText;
		}

		return null;
	}

	function findTelegramAnchor() {
		var scope =
			document.querySelector('.Gstore-top-bar__contacts') ||
			document.querySelector('.Gstore-top-bar__contact') ||
			document.querySelector('.Gstore-top-bar');

		if (!scope) return null;
		var anchors = scope.querySelectorAll('a.Gstore-top-bar__link');
		return pickTelegramLink(anchors);
	}

	function chatFloatIconSvg() {
		return (
			'<svg class="Gstore-support-float__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
			'<path d="M12 2C6.478 2 2 6.145 2 11.258c0 2.911 1.452 5.507 3.72 7.202V22l3.22-1.763c.97.27 2.002.413 3.06.413 5.523 0 10-4.145 10-9.258C22 6.145 17.523 2 12 2zm-4.1 8.9h8.2a.9.9 0 1 1 0 1.8H7.9a.9.9 0 1 1 0-1.8zm0-3.4h8.2a.9.9 0 1 1 0 1.8H7.9a.9.9 0 1 1 0-1.8z"/>' +
			'</svg>'
		);
	}

	function telegramFloatIconSvg() {
		return (
			'<svg class="Gstore-support-float__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
			'<path d="M21.4 4.6a1.4 1.4 0 0 0-1.45-.22L4.17 10.84a1.5 1.5 0 0 0 .1 2.81l4.2 1.47 1.47 4.2a1.5 1.5 0 0 0 2.81.08l6.86-15.33a1.4 1.4 0 0 0-.21-1.47zM9.35 14.65l-.7 3.15-1.1-3.15 7.6-6.7-5.8 6.7z"/>' +
			'</svg>'
		);
	}

	function supportFloatIconSvg(kind) {
		return kind === 'telegram' ? telegramFloatIconSvg() : chatFloatIconSvg();
	}

	function closeIconSvg() {
		return (
			'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
			'<path d="M6.7 5.3a1 1 0 0 0-1.4 1.4L10.6 12l-5.3 5.3a1 1 0 1 0 1.4 1.4l5.3-5.3 5.3 5.3a1 1 0 0 0 1.4-1.4L13.4 12l5.3-5.3a1 1 0 0 0-1.4-1.4L12 10.6 6.7 5.3z"/>' +
			'</svg>'
		);
	}

	function ensureQuickActionButton() {
		var existing = document.getElementById(FLOAT_ID);
		if (existing) return existing;

		var button = document.createElement('button');
		button.type = 'button';
		button.id = FLOAT_ID;
		button.className = FLOAT_CLASS + ' ' + FLOAT_LEGACY_CLASS;
		button.setAttribute('aria-haspopup', 'dialog');
		button.setAttribute('aria-label', text('openSupport', 'Atendimento'));
		button.setAttribute('title', text('openSupport', 'Atendimento'));
		button.innerHTML =
			'<span class="Gstore-support-float__icon-wrap">' + supportFloatIconSvg(getQuickActionIconKind()) + '</span>' +
			'<span class="Gstore-support-float__label"></span>';

		button.addEventListener('click', onQuickActionClick);
		document.body.appendChild(button);
		document.body.classList.add('gstore-support-quick-action-mounted');
		if (document.documentElement) {
			document.documentElement.classList.add('gstore-support-quick-action-mounted');
		}
		return button;
	}

	function ensureSelectorModal() {
		var existing = document.getElementById(SELECTOR_MODAL_ID);
		if (existing) return existing;

		var modal = document.createElement('div');
		modal.id = SELECTOR_MODAL_ID;
		modal.className = 'Gstore-support-modal';
		modal.setAttribute('aria-hidden', 'true');
		modal.innerHTML =
			'<div class="Gstore-support-modal__backdrop" data-action="close"></div>' +
			'<div class="Gstore-support-modal__panel" role="dialog" aria-modal="true" aria-labelledby="gstore-support-selector-title">' +
			'<button type="button" class="Gstore-support-modal__close" data-action="close" aria-label="' + text('close', 'Fechar') + '">' + closeIconSvg() + '</button>' +
			'<div class="Gstore-support-modal__body">' +
			'<p class="Gstore-support-modal__eyebrow">' + text('selectorEyebrow', 'Atendimento') + '</p>' +
			'<h3 id="gstore-support-selector-title" class="Gstore-support-modal__title">' + text('selectorTitle', 'Como prefere falar com a gente?') + '</h3>' +
			'<p class="Gstore-support-modal__text">' + text('selectorText', 'Escolha o canal. Se optar pelo chat do site, o botão passa a abrir o chat direto.') + '</p>' +
			'<div class="Gstore-support-modal__options">' +
			'<button type="button" class="Gstore-support-option" data-channel="chat">' +
			'<span class="Gstore-support-option__title">' + text('chatTitle', 'Chat do site') + '</span>' +
			'<span class="Gstore-support-option__desc" data-chat-status></span>' +
			'</button>' +
			'<button type="button" class="Gstore-support-option" data-channel="telegram">' +
			'<span class="Gstore-support-option__title">' + text('telegramTitle', 'Telegram') + '</span>' +
			'<span class="Gstore-support-option__desc">' + text('telegramDesc', 'Abrir grupo oficial no Telegram') + '</span>' +
			'</button>' +
			'</div>' +
			'</div>' +
			'</div>';

		modal.addEventListener('click', onModalClick);
		document.body.appendChild(modal);
		return modal;
	}

	function ensureChatShellModal() {
		var existing = document.getElementById(CHAT_MODAL_ID);
		if (existing) return existing;

		var modal = document.createElement('div');
		modal.id = CHAT_MODAL_ID;
		modal.className = 'Gstore-support-chat-shell';
		modal.setAttribute('aria-hidden', 'true');
		modal.innerHTML =
			'<div class="Gstore-support-chat-shell__backdrop" data-action="close-chat"></div>' +
			'<div class="Gstore-support-chat-shell__frame" aria-hidden="true">' +
			'<div class="Gstore-support-chat-shell__header" role="dialog" aria-modal="true" aria-labelledby="gstore-support-chat-title">' +
			'<div class="Gstore-support-chat-shell__headings">' +
			'<p class="Gstore-support-chat-shell__eyebrow">' + text('chatEyebrow', 'Atendimento online') + '</p>' +
			'<h3 id="gstore-support-chat-title" class="Gstore-support-chat-shell__title">' + text('chatModalTitle', 'Chat do site') + '</h3>' +
			'</div>' +
			'<div class="Gstore-support-chat-shell__actions">' +
			'<button type="button" class="Gstore-support-chat-shell__link" data-action="change-channel">' + text('changeChannel', 'Trocar canal') + '</button>' +
			'<button type="button" class="Gstore-support-chat-shell__close" data-action="close-chat" aria-label="' + text('close', 'Fechar') + '">' + closeIconSvg() + '</button>' +
			'</div>' +
			'</div>' +
			'<div class="Gstore-support-chat-shell__body">' +
			'<p>' + text('chatModalHint', 'Abrindo o chat do site. Se o widget estiver carregando, aguarde alguns segundos.') + '</p>' +
			'</div>' +
			'</div>';

		modal.addEventListener('click', onModalClick);
		document.body.appendChild(modal);
		return modal;
	}

	function getSelectorModal() {
		return document.getElementById(SELECTOR_MODAL_ID) || ensureSelectorModal();
	}

	function getChatShellModal() {
		return document.getElementById(CHAT_MODAL_ID) || ensureChatShellModal();
	}

	function onModalClick(event) {
		var actionEl = event.target && event.target.closest ? event.target.closest('[data-action], [data-channel]') : null;
		if (!actionEl) return;

		var action = actionEl.getAttribute('data-action');
		var channel = actionEl.getAttribute('data-channel');

		if (channel === 'chat') {
			event.preventDefault();
			handleSelectChat();
			return;
		}

		if (channel === 'telegram') {
			event.preventDefault();
			handleSelectTelegram();
			return;
		}

		if (action === 'close') {
			event.preventDefault();
			closeSelectorModal();
			return;
		}

		if (action === 'close-chat') {
			event.preventDefault();
			closeChatShellModal();
			return;
		}

		if (action === 'change-channel') {
			event.preventDefault();
			handleChangeChannel();
		}
	}

	function onQuickActionClick(event) {
		if (event) event.preventDefault();

		if (getEffectiveQuickActionType() === 'telegram') {
			handleSelectTelegram();
			return;
		}

		openSelectorModal();
	}

	function updateQuickActionPresentation() {
		var button = document.getElementById(FLOAT_ID);
		if (!button) return;

		var labelEl = button.querySelector('.Gstore-support-float__label');
		var iconWrapEl = button.querySelector('.Gstore-support-float__icon-wrap');
		var isDirectChat = state.preferredChannel === CHAT_PREF_VALUE;
		var effectiveType = getEffectiveQuickActionType();
		var iconKind = getQuickActionIconKind();
		var displayMode = getQuickActionDisplayMode();
		var configuredLabel = getQuickActionLabel();
		var isDirectTelegram = effectiveType === 'telegram';

		button.classList.toggle('is-direct-chat', !!isDirectChat);
		button.classList.toggle('is-direct-telegram', !!isDirectTelegram);
		button.classList.toggle('is-chat-ready', !!state.chatReady);
		button.classList.toggle('is-icon-only', displayMode === 'icon_only');

		if (iconWrapEl) {
			iconWrapEl.innerHTML = supportFloatIconSvg(iconKind);
		}

		var ariaLabel;
		if (isDirectTelegram) {
			ariaLabel = configuredLabel || text('telegramTitle', 'Telegram');
		} else if (isDirectChat) {
			ariaLabel = text('openChat', 'Abrir chat');
		} else {
			ariaLabel = configuredLabel || text('openSupport', 'Atendimento');
		}
		button.setAttribute('aria-label', ariaLabel);
		button.setAttribute('title', ariaLabel);
		button.setAttribute('aria-expanded', state.activeModal ? 'true' : 'false');

		if (labelEl) {
			labelEl.style.display = displayMode === 'icon_only' ? 'none' : '';
			if (isDirectTelegram) {
				labelEl.textContent = configuredLabel || text('telegramTitle', 'Telegram');
			} else if (isDirectChat) {
				labelEl.textContent = text('openChat', 'Abrir chat');
			} else {
				labelEl.textContent = configuredLabel || text('openSupportShort', 'Atendimento');
			}
		}
	}

	function updateSelectorChatOptionStatus() {
		var modal = document.getElementById(SELECTOR_MODAL_ID);
		if (!modal) return;

		var chatBtn = modal.querySelector('[data-channel="chat"]');
		var statusEl = modal.querySelector('[data-chat-status]');
		if (!chatBtn || !statusEl) return;

		if (getEffectiveQuickActionType() === 'telegram') {
			chatBtn.disabled = true;
			chatBtn.classList.add('is-disabled');
			statusEl.textContent = text('chatDisabledDesc', 'Chat do site desativado no momento');
			return;
		}

		if (state.chatReady) {
			chatBtn.disabled = false;
			chatBtn.classList.remove('is-disabled');
			statusEl.textContent = text('chatReadyDesc', 'Atendimento dentro do site (abre direto nas próximas vezes)');
		} else {
			chatBtn.disabled = true;
			chatBtn.classList.add('is-disabled');
			statusEl.textContent = text('chatLoadingDesc', 'Carregando chat...');
		}
	}

	function setChatReady(ready) {
		var next = !!ready;
		if (state.chatReady === next) return;
		state.chatReady = next;
		updateSelectorChatOptionStatus();
		updateQuickActionPresentation();
	}

	function openSelectorModal() {
		if (getEffectiveQuickActionType() === 'telegram') {
			handleSelectTelegram();
			return;
		}

		var modal = getSelectorModal();
		closeChatShellModal(true);
		state.activeModal = 'selector';

		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add(BODY_MODAL_CLASS);
		updateSelectorChatOptionStatus();
		updateQuickActionPresentation();

		var firstBtn = modal.querySelector('.Gstore-support-option:not(.is-disabled)');
		if (firstBtn && typeof firstBtn.focus === 'function') {
			window.setTimeout(function () {
				firstBtn.focus();
			}, 0);
		}
	}

	function closeSelectorModal(silent) {
		var modal = document.getElementById(SELECTOR_MODAL_ID);
		if (!modal) return;

		modal.setAttribute('aria-hidden', 'true');
		if (state.activeModal === 'selector') state.activeModal = null;
		syncBodyModalClasses();
		updateQuickActionPresentation();
		if (!silent) emitEvent('gstore:support:selector-closed', {});
	}

	function openChatShellModal() {
		if (!state.chatReady) {
			openSelectorModal();
			return;
		}

		closeSelectorModal(true);

		var modal = getChatShellModal();
		state.activeModal = 'chat';
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add(BODY_MODAL_CLASS);
		document.body.classList.add(BODY_CHAT_CLASS);
		document.body.setAttribute('data-gstore-chat-strategy', CHAT_UI_STRATEGY);

		updateQuickActionPresentation();
		openChatEngine();
		emitEvent('gstore:support:chat-shell-open', { strategy: CHAT_UI_STRATEGY });
	}

	function openChatDirect() {
		closeSelectorModal(true);
		closeChatShellModal(true);
		openChatEngine();
		emitEvent('gstore:support:chat-open-native', { strategy: CHAT_UI_STRATEGY });
	}

	function closeChatShellModal(silent) {
		var modal = document.getElementById(CHAT_MODAL_ID);
		if (modal) modal.setAttribute('aria-hidden', 'true');
		if (state.activeModal === 'chat') state.activeModal = null;

		document.body.classList.remove(BODY_CHAT_CLASS);
		document.body.removeAttribute('data-gstore-chat-strategy');
		syncBodyModalClasses();

		closeChatEngine();
		updateQuickActionPresentation();
		if (!silent) emitEvent('gstore:support:chat-shell-closed', { strategy: CHAT_UI_STRATEGY });
	}

	function syncBodyModalClasses() {
		var selectorOpen = false;
		var chatOpen = false;

		var selector = document.getElementById(SELECTOR_MODAL_ID);
		if (selector && selector.getAttribute('aria-hidden') === 'false') selectorOpen = true;

		var chat = document.getElementById(CHAT_MODAL_ID);
		if (chat && chat.getAttribute('aria-hidden') === 'false') chatOpen = true;

		if (!selectorOpen && !chatOpen) {
			document.body.classList.remove(BODY_MODAL_CLASS);
		}
	}

	function handleSelectChat() {
		if (!state.chatReady) return;
		persistPreferredChannel(CHAT_PREF_VALUE);
		openChatDirect();
		emitEvent('gstore:support:channel-selected', { channel: CHAT_PREF_VALUE });
	}

	function handleSelectTelegram() {
		persistPreferredChannel(null);
		closeSelectorModal(true);
		emitEvent('gstore:support:channel-selected', { channel: 'telegram' });
		openTelegramLink();
	}

	function handleChangeChannel() {
		persistPreferredChannel(null);
		closeChatShellModal(true);
		openSelectorModal();
		emitEvent('gstore:support:change-channel', {});
	}

	function openTelegramLink() {
		var href = state.telegramLink;
		if (!isValidHref(href)) return;
		try {
			window.open(href, '_blank', 'noopener');
		} catch (e) {
			window.location.href = href;
		}
	}

	function applyTelegramLinkFromSource(sourceAnchor) {
		if (!sourceAnchor) return false;
		var href = sourceAnchor.getAttribute('href');
		if (!isValidHref(href)) return false;
		state.telegramLink = href;
		return true;
	}

	function isMiniCartDrawerOpen(drawer) {
		if (!drawer) return false;
		if (drawer.classList.contains('is-open')) return true;

		var drawerAriaHidden = drawer.getAttribute('aria-hidden');
		if (drawerAriaHidden === 'false') return true;
		if (drawerAriaHidden === 'true') return false;
		if (drawer.hasAttribute('hidden')) return false;

		var overlay = document.querySelector(MINI_CART_OVERLAY_SELECTOR);
		if (overlay) {
			var overlayAriaHidden = overlay.getAttribute('aria-hidden');
			if (overlayAriaHidden === 'false') return true;
			if (overlayAriaHidden === 'true') return false;
			if (overlay.hasAttribute('hidden')) return false;

			var overlayStyle = window.getComputedStyle(overlay);
			if (overlayStyle && overlayStyle.pointerEvents !== 'none' && overlayStyle.opacity !== '0') return true;
		}

		var drawerStyle = window.getComputedStyle(drawer);
		if (
			drawerStyle &&
			drawerStyle.display !== 'none' &&
			drawerStyle.visibility !== 'hidden' &&
			drawerStyle.pointerEvents !== 'none'
		) {
			return true;
		}
		return false;
	}

	function toggleQuickActionVisibility() {
		var quick = document.getElementById(FLOAT_ID);
		if (!quick) return;

		if (document.body.classList.contains(BODY_CHAT_CLASS)) {
			quick.style.display = 'none';
			return;
		}

		var drawer = document.querySelector(MINI_CART_DRAWER_SELECTOR);
		if (isMiniCartDrawerOpen(drawer)) {
			quick.style.display = 'none';
		} else {
			quick.style.display = '';
		}
	}

	function observeMiniCartDrawer() {
		var quick = document.getElementById(FLOAT_ID);
		if (!quick || quick.__gstoreMiniCartObserver) return false;

		var drawer = document.querySelector(MINI_CART_DRAWER_SELECTOR);
		if (!drawer) return false;

		toggleQuickActionVisibility();

		try {
			var obs = new MutationObserver(function () {
				toggleQuickActionVisibility();
			});
			obs.observe(drawer, { attributes: true, attributeFilter: ['class', 'style', 'aria-hidden'] });
			var overlay = document.querySelector(MINI_CART_OVERLAY_SELECTOR);
			if (overlay) {
				obs.observe(overlay, { attributes: true, attributeFilter: ['class', 'style', 'aria-hidden'] });
			}
			quick.__gstoreMiniCartObserver = true;
			return true;
		} catch (e) {
			return false;
		}
	}

	function observeMiniCartMount() {
		var quick = document.getElementById(FLOAT_ID);
		if (!quick || quick.__gstoreMiniCartMountObserver) return;

		try {
			var obs = new MutationObserver(function () {
				if (observeMiniCartDrawer()) {
					obs.disconnect();
					quick.__gstoreMiniCartMountObserver = true;
				}
			});
			obs.observe(document.body, { childList: true, subtree: true });
			quick.__gstoreMiniCartMountObserver = true;
		} catch (e) {}
	}

	function bindKeyboardShortcuts() {
		if (document.__gstoreSupportKeyboardBound) return;
		document.__gstoreSupportKeyboardBound = true;

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') return;

			var chat = document.getElementById(CHAT_MODAL_ID);
			if (chat && chat.getAttribute('aria-hidden') === 'false') {
				closeChatShellModal();
				return;
			}

			var selector = document.getElementById(SELECTOR_MODAL_ID);
			if (selector && selector.getAttribute('aria-hidden') === 'false') {
				closeSelectorModal();
			}
		});
	}

	function bindChatReadySignals() {
		for (var i = 0; i < READY_EVENT_NAMES.length; i++) {
			window.addEventListener(READY_EVENT_NAMES[i], function () {
				setChatReady(true);
			});
		}

		var tries = 0;
		var maxTries = 60;
		var timer = window.setInterval(function () {
			tries++;
			if (detectChatReady()) {
				setChatReady(true);
				window.clearInterval(timer);
				return;
			}
			if (tries >= maxTries) {
				window.clearInterval(timer);
			}
		}, 1000);
	}

	function hideChatwootBrandingBestEffort(scope) {
		var root = scope && scope.querySelectorAll ? scope : document;
		var selector =
			'.woot-widget-holder [class*="powered"], ' +
			'.woot-widget-holder [class*="Powered"], ' +
			'[class*="woot"] [class*="powered"], ' +
			'[class*="woot"] [class*="Powered"], ' +
			'.woot-widget-holder a[href*="chatwoot"], ' +
			'[class*="woot"] a[href*="chatwoot"]';

		try {
			var directMatches = root.querySelectorAll(selector);
			for (var i = 0; i < directMatches.length; i++) {
				directMatches[i].style.display = 'none';
			}
		} catch (e) {}

		try {
			var textNodes = root.querySelectorAll('a, span, small, p, div');
			for (var j = 0; j < textNodes.length; j++) {
				var el = textNodes[j];
				if (!el || !el.textContent) continue;
				if (!el.closest || !el.closest('.woot-widget-holder, [class*="woot"]')) continue;
				if (el.querySelector && el.querySelector('iframe, button, input, textarea')) continue;
				var txt = normalizeText(el.textContent);
				if (!txt) continue;
				if (txt.length > 80) continue;
				if (txt.indexOf('chatwoot') === -1) continue;
				if (txt.indexOf('powered') === -1 && txt.indexOf('desenvolvido') === -1) continue;
				el.style.display = 'none';
			}
		} catch (e2) {}
	}

	function hideChatwootBubbleBestEffort(scope) {
		var root = scope && scope.querySelectorAll ? scope : document;
		var selector = '#cw-bubble-holder, .woot-widget-bubble, [class*="woot-widget-bubble"]';

		try {
			var nodes = root.querySelectorAll(selector);
			for (var i = 0; i < nodes.length; i++) {
				nodes[i].style.setProperty('display', 'none', 'important');
				nodes[i].style.setProperty('opacity', '0', 'important');
				nodes[i].style.setProperty('visibility', 'hidden', 'important');
				nodes[i].style.setProperty('pointer-events', 'none', 'important');
			}
		} catch (e) {}
	}

	function bindChatwootBrandingCleanup() {
		hideChatwootBubbleBestEffort(document);
		hideChatwootBrandingBestEffort(document);

		try {
			if (document.__gstoreChatwootBrandingObserverBound) return;
			var obs = new MutationObserver(function (mutations) {
				for (var i = 0; i < mutations.length; i++) {
					var m = mutations[i];
					if (m.addedNodes && m.addedNodes.length) {
						for (var j = 0; j < m.addedNodes.length; j++) {
							var node = m.addedNodes[j];
							if (node && node.nodeType === 1) {
								hideChatwootBubbleBestEffort(node);
								hideChatwootBrandingBestEffort(node);
							}
						}
					}
				}
			});
			obs.observe(document.body, { childList: true, subtree: true });
			document.__gstoreChatwootBrandingObserverBound = true;
		} catch (e) {}
	}

	function syncSourceAndUI() {
		syncQuickActionConfigFromBridge();

		var src = findTelegramAnchor();
		if (!src) return false;

		if (!applyTelegramLinkFromSource(src)) return false;

		ensureQuickActionButton();
		document.body.classList.add('gstore-support-quick-action-mounted');
		if (document.documentElement) {
			document.documentElement.classList.add('gstore-support-quick-action-mounted');
		}
		ensureSelectorModal();
		ensureChatShellModal();

		if (!observeMiniCartDrawer()) observeMiniCartMount();
		toggleQuickActionVisibility();

		try {
			if (!src.__gstoreTelegramObserved) {
				var obs = new MutationObserver(function () {
					applyTelegramLinkFromSource(src);
				});
				obs.observe(src, { attributes: true, attributeFilter: ['href', 'target', 'rel', 'style', 'class'] });
				src.__gstoreTelegramObserved = true;
			}
		} catch (e) {}

		return true;
	}

	function boot() {
		syncQuickActionConfigFromBridge();
		state.preferredChannel = getPreferredChannel();
		setChatReady(detectChatReady());

		if (!syncSourceAndUI()) {
			var tries = 0;
			var maxTries = 20;
			var timer = window.setInterval(function () {
				tries++;
				if (syncSourceAndUI() || tries >= maxTries) {
					window.clearInterval(timer);
					updateSelectorChatOptionStatus();
					updateQuickActionPresentation();
				}
			}, 250);
		} else {
			updateSelectorChatOptionStatus();
			updateQuickActionPresentation();
		}

		bindKeyboardShortcuts();
		bindChatReadySignals();
		bindChatwootBrandingCleanup();
		window.addEventListener('gstore:support-config-ready', function () {
			syncQuickActionConfigFromBridge();
			setChatReady(detectChatReady());
			updateSelectorChatOptionStatus();
			updateQuickActionPresentation();
		});

		// When fresh user-specific data arrives (after LiteSpeed cache),
		// re-sync preference and update button state.
		window.addEventListener('gstore:support-dynamic-ready', function (e) {
			var detail = e && e.detail ? e.detail : {};
			if (detail.preference === CHAT_PREF_VALUE) {
				state.preferredChannel = CHAT_PREF_VALUE;
			} else {
				// Also check localStorage as fallback.
				state.preferredChannel = readLocalPreference();
			}
			syncQuickActionConfigFromBridge();
			setChatReady(detectChatReady());
			updateSelectorChatOptionStatus();
			updateQuickActionPresentation();
		});

		window.addEventListener('resize', toggleQuickActionVisibility);
		window.addEventListener('gstore:support:chat-shell-open', toggleQuickActionVisibility);
		window.addEventListener('gstore:support:chat-shell-closed', toggleQuickActionVisibility);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();

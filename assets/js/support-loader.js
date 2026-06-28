/**
 * Gstore - Lightweight support loader.
 *
 * Defers the heavier support scripts until browser idle or clear support intent,
 * while keeping inline WordPress configs available on the page.
 */
(function () {
	'use strict';

	if (window.__gstoreSupportLoaderBooted) return;
	window.__gstoreSupportLoaderBooted = true;

	var config = window.gstoreSupportLoaderConfig || {};
	var quickActionConfig = window.gstoreSupportQuickAction || {};
	var chatBridgeConfig = window.gstoreChatwootBridgeConfig || {};
	var CHAT_PREF_VALUE = (config.chatPreference || quickActionConfig.chatPreferenceValue || 'chat_site').toString();
	var STORAGE_KEY = (quickActionConfig.storageKey || 'gstore_support_preference').toString();
	var IDLE_DELAY = parseInt(config.idleDelay, 10);
	var IDLE_TIMEOUT = parseInt(config.idleTimeout, 10);
	var loadingPromise = null;
	var loaded = false;
	var loadStarted = false;
	var loadReason = null;
	var statusTimer = null;

	if (!Number.isFinite(IDLE_DELAY) || IDLE_DELAY < 0) {
		IDLE_DELAY = 2400;
	}
	if (!Number.isFinite(IDLE_TIMEOUT) || IDLE_TIMEOUT < 0) {
		IDLE_TIMEOUT = 1200;
	}

	var INTENT_SELECTOR = [
		'#gstore-support-float',
		'.Gstore-support-float',
		'.Gstore-telegram-float',
		'[data-gstore-support]',
		'a[href*="t.me"]',
		'a[href*="telegram"]',
		'a[href*="/atendimento"]',
		'[aria-label*="Telegram"]',
		'[aria-label*="Atendimento"]',
		'[title*="Telegram"]',
		'[title*="Atendimento"]'
	].join(',');

	function parseIntSafe(value, fallback) {
		var parsed = parseInt(value, 10);
		return Number.isFinite(parsed) ? parsed : fallback;
	}

	function getScriptDefinitions() {
		var nodes = document.querySelectorAll('script[data-gstore-support-script][data-gstore-url]');
		var definitions = [];

		for (var i = 0; i < nodes.length; i++) {
			var node = nodes[i];
			var handle = node.getAttribute('data-gstore-support-script');
			var url = node.getAttribute('data-gstore-url');
			if (!handle || !url) continue;

			definitions.push({
				handle: handle,
				url: url,
				order: parseIntSafe(node.getAttribute('data-gstore-support-order'), 100),
				node: node
			});
		}

		definitions.sort(function (a, b) {
			return a.order - b.order;
		});

		return definitions;
	}

	function isRuntimePresent(handle) {
		var existing = document.getElementById(handle + '-js');
		if (existing && existing.getAttribute('src')) {
			return true;
		}

		return !!document.querySelector('script[data-gstore-support-runtime="' + handle + '"]');
	}

	function emit(name, detail) {
		try {
			window.dispatchEvent(new CustomEvent(name, {
				detail: detail || {}
			}));
			return;
		} catch (e) {}

		try {
			var event = document.createEvent('CustomEvent');
			event.initCustomEvent(name, false, false, detail || {});
			window.dispatchEvent(event);
		} catch (e2) {}
	}

	function loadScript(definition) {
		if (!definition || !definition.handle || !definition.url) {
			return Promise.resolve();
		}

		if (definition.node && definition.node.getAttribute('data-gstore-loaded') === '1') {
			return Promise.resolve();
		}

		if (isRuntimePresent(definition.handle)) {
			if (definition.node) {
				definition.node.setAttribute('data-gstore-loaded', '1');
			}
			return Promise.resolve();
		}

		return new Promise(function (resolve, reject) {
			var script = document.createElement('script');
			script.src = definition.url;
			script.id = definition.handle + '-js';
			script.async = false;
			script.defer = false;
			script.setAttribute('data-gstore-support-runtime', definition.handle);
			script.onload = function () {
				if (definition.node) {
					definition.node.setAttribute('data-gstore-loaded', '1');
				}
				resolve();
			};
			script.onerror = function () {
				reject(new Error('Failed to load support script: ' + definition.handle));
			};
			(document.body || document.head || document.documentElement).appendChild(script);
		});
	}

	function loadSupport(reason) {
		if (loaded) {
			return Promise.resolve();
		}
		if (loadingPromise) {
			return loadingPromise;
		}

		loadStarted = true;
		loadReason = reason || 'unknown';
		emit('gstore:support-loader:start', { reason: loadReason });

		var definitions = getScriptDefinitions();
		var chain = Promise.resolve();
		var errors = [];

		definitions.forEach(function (definition) {
			chain = chain.then(function () {
				return loadScript(definition).catch(function (error) {
					errors.push(error);
				});
			});
		});

		loadingPromise = chain.then(function () {
			if (errors.length) {
				loaded = false;
				loadStarted = false;
				loadingPromise = null;
				emit('gstore:support-loader:error', { reason: loadReason, errors: errors });
			} else {
				loaded = true;
			}
			emit('gstore:support-loader:ready', { reason: loadReason, partial: errors.length > 0 });
		});

		return loadingPromise;
	}

	function getBridge() {
		if (window.GstoreSupportBridge) return window.GstoreSupportBridge;
		if (window.gstoreSupportBridge) return window.gstoreSupportBridge;
		if (window.GSTORE && window.GSTORE.supportBridge) return window.GSTORE.supportBridge;
		return null;
	}

	function openChatAfterLoad() {
		return loadSupport('open-chat').then(function () {
			var bridge = getBridge();
			if (!bridge) return false;

			var methods = ['openChat', 'openChatwoot', 'showChat', 'open'];
			for (var i = 0; i < methods.length; i++) {
				if (typeof bridge[methods[i]] === 'function') {
					try {
						bridge[methods[i]]({
							source: 'support-loader'
						});
						return true;
					} catch (e) {
						return false;
					}
				}
			}
			return false;
		});
	}

	function getTelegramFallbackUrl() {
		var qa = chatBridgeConfig && chatBridgeConfig.quickAction ? chatBridgeConfig.quickAction : null;
		if (qa && qa.telegramUrl) return qa.telegramUrl;

		var link = document.querySelector('a[href*="t.me"], a[href*="telegram"]');
		return link ? link.getAttribute('href') : '';
	}

	function openTelegramFallback() {
		var href = getTelegramFallbackUrl();
		if (!href || href === '#' || href.indexOf('javascript:') === 0) return false;
		try {
			window.open(href, '_blank', 'noopener');
			return true;
		} catch (e) {
			window.location.href = href;
			return true;
		}
	}

	function showStatus(message) {
		var text = message || config.loadingText || 'Carregando atendimento...';
		var el = document.getElementById('gstore-support-loader-status');
		if (!el) {
			el = document.createElement('div');
			el.id = 'gstore-support-loader-status';
			el.setAttribute('role', 'status');
			el.setAttribute('aria-live', 'polite');
			el.style.cssText = [
				'position:fixed',
				'right:16px',
				'bottom:16px',
				'z-index:2147483647',
				'max-width:min(320px,calc(100vw - 32px))',
				'padding:12px 14px',
				'border-radius:999px',
				'background:#111827',
				'color:#fff',
				'font:600 13px/1.3 system-ui,-apple-system,Segoe UI,sans-serif',
				'box-shadow:0 12px 32px rgba(15,23,42,.22)'
			].join(';');
			document.body.appendChild(el);
		}
		el.textContent = text;
		el.hidden = false;
		if (statusTimer) {
			window.clearTimeout(statusTimer);
			statusTimer = null;
		}
	}

	function hideStatus(delay) {
		var el = document.getElementById('gstore-support-loader-status');
		if (!el) return;
		if (statusTimer) window.clearTimeout(statusTimer);
		statusTimer = window.setTimeout(function () {
			el.hidden = true;
		}, delay || 0);
	}

	function readLocalPreference() {
		try {
			var raw = window.localStorage ? window.localStorage.getItem(STORAGE_KEY) : null;
			if (!raw) return null;
			if (raw === CHAT_PREF_VALUE) return CHAT_PREF_VALUE;
			if (raw.indexOf('"channel":"' + CHAT_PREF_VALUE + '"') !== -1) return CHAT_PREF_VALUE;
		} catch (e) {}

		return null;
	}

	function hasAutoOpenFlag() {
		try {
			var url = new URL(window.location.href);
			return url.searchParams.get('gstore_open_chat') === '1';
		} catch (e) {
			return false;
		}
	}

	function shouldLoadImmediately() {
		if (hasAutoOpenFlag()) return true;
		if (readLocalPreference() === CHAT_PREF_VALUE) return true;
		if (chatBridgeConfig && chatBridgeConfig.preference === CHAT_PREF_VALUE) return true;
		return false;
	}

	function findIntentTarget(target) {
		if (!target || !target.closest) return null;
		try {
			return target.closest(INTENT_SELECTOR);
		} catch (e) {
			return null;
		}
	}

	function isQuickActionTarget(target) {
		if (!target || !target.matches) return false;
		try {
			return target.matches('#gstore-support-float, .Gstore-support-float, .Gstore-telegram-float, [data-gstore-support]');
		} catch (e) {
			return false;
		}
	}

	function bindIntentTriggers() {
		function warm(event) {
			if (loadStarted) return;
			if (!findIntentTarget(event.target)) return;
			loadSupport('intent-' + event.type);
		}

		function click(event) {
			var target = findIntentTarget(event.target);
			if (!target) return;

			if (isQuickActionTarget(target) && !loaded) {
				event.preventDefault();
				showStatus(config.loadingText);
				openChatAfterLoad().then(function (opened) {
					hideStatus(300);
					if (!opened) {
						showStatus(config.failedText);
						hideStatus(2500);
						openTelegramFallback();
					}
				});
				return;
			}

			loadSupport('support-click');
		}

		document.addEventListener('pointerover', warm, { capture: true, passive: true });
		document.addEventListener('focusin', warm, true);
		document.addEventListener('touchstart', warm, { capture: true, passive: true });
		document.addEventListener('click', click, true);
	}

	function bindProgrammaticTriggers() {
		window.addEventListener('gstore:support:load', function () {
			loadSupport('event-load');
		});

		window.addEventListener('gstore:support:open-chat', function () {
			openChatAfterLoad();
		});

		if (navigator.serviceWorker && navigator.serviceWorker.addEventListener) {
			navigator.serviceWorker.addEventListener('message', function (event) {
				var data = event && event.data ? event.data : {};
				if (!data || data.type !== 'gstore-chat-notification-click') return;
				openChatAfterLoad();
			});
		}
	}

	function scheduleIdleLoad() {
		window.setTimeout(function () {
			if (loaded || loadStarted) return;

			if ('requestIdleCallback' in window) {
				window.requestIdleCallback(function () {
					loadSupport('idle');
				}, { timeout: IDLE_TIMEOUT });
				return;
			}

			loadSupport('timeout');
		}, IDLE_DELAY);
	}

	window.gstoreSupportLoader = {
		load: loadSupport,
		openChat: openChatAfterLoad,
		isLoaded: function () {
			return loaded;
		},
		isLoading: function () {
			return !!loadingPromise && !loaded;
		}
	};

	bindIntentTriggers();
	bindProgrammaticTriggers();

	if (shouldLoadImmediately()) {
		loadSupport(hasAutoOpenFlag() ? 'auto-open' : 'preferred-chat');
	} else {
		scheduleIdleLoad();
	}
})();

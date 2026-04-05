/**
 * Store API Nonce Refresh
 *
 * Intercepta requisições fetch para a WC Store API e, quando o nonce
 * está expirado (resposta 401/403), busca um nonce novo e repete a
 * requisição automaticamente. Isso evita o erro "Nonce inválido" que
 * aparece ao remover/atualizar itens no mini-cart após cache ou sessão
 * prolongada (comum com LiteSpeed Cache).
 */
(function () {
	'use strict';

	var STORE_API_PATH = '/wc/store/';
	var NONCE_HEADER   = 'Nonce';
	var RESP_HEADER    = 'x-wc-store-api-nonce';
	var refreshing     = null; // Promise compartilhada para evitar múltiplas requisições simultâneas

	/**
	 * Retorna o nonce atual armazenado pelo WC Blocks.
	 */
	function getCurrentNonce() {
		// wcStoreApiNonce é exposto pelo WC Blocks via wp_add_inline_script
		if (window.wcStoreApiNonce) return window.wcStoreApiNonce;
		if (window.wc && window.wc.storeApiNonce) return window.wc.storeApiNonce;
		if (window.gstoreMiniCart && window.gstoreMiniCart.storeApiNonce) return window.gstoreMiniCart.storeApiNonce;
		return null;
	}

	/**
	 * Persiste o nonce novo em todos os locais conhecidos.
	 */
	function setNonce(nonce) {
		if (!nonce) return;
		if (window.wc) window.wc.storeApiNonce = nonce;
		if (typeof window.wcStoreApiNonce !== 'undefined') window.wcStoreApiNonce = nonce;
		if (window.gstoreMiniCart) window.gstoreMiniCart.storeApiNonce = nonce;

		// Atualiza também o data store do WC Blocks (se disponível)
		try {
			var store = window.wp && window.wp.data && window.wp.data.dispatch('wc/store/cart');
			if (store && typeof store.receiveError === 'function') {
				// Não há setter direto; o middleware do WC Blocks lê do header da resposta.
				// Nossa interceptação já cuida disso.
			}
		} catch (e) { /* ignora */ }
	}

	/**
	 * Verifica se a URL é uma chamada à Store API.
	 */
	function isStoreApiUrl(url) {
		if (!url) return false;
		var s = typeof url === 'string' ? url : url.toString();
		return s.indexOf(STORE_API_PATH) !== -1;
	}

	/**
	 * Busca um nonce novo via AJAX.
	 */
	function refreshNonce() {
		if (refreshing) return refreshing;

		refreshing = new Promise(function (resolve) {
			var xhr = new XMLHttpRequest();
			var ajaxUrl = (window.gstoreNonceRefresh && window.gstoreNonceRefresh.ajaxUrl) ||
			              (window.gstore_wc && window.gstore_wc.ajax_url) ||
			              '/wp-admin/admin-ajax.php';

			xhr.open('POST', ajaxUrl, true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.onload = function () {
				refreshing = null;
				try {
					var data = JSON.parse(xhr.responseText);
					if (data && data.success && data.data && data.data.nonce) {
						setNonce(data.data.nonce);
						resolve(data.data.nonce);
						return;
					}
				} catch (e) { /* parse error */ }
				resolve(getCurrentNonce());
			};
			xhr.onerror = function () {
				refreshing = null;
				resolve(getCurrentNonce());
			};
			xhr.send('action=gstore_refresh_store_api_nonce');
		});

		return refreshing;
	}

	/**
	 * Verifica se a resposta indica nonce inválido.
	 */
	function isNonceError(response) {
		return response && (response.status === 401 || response.status === 403);
	}

	/**
	 * Clona a Request original substituindo o header Nonce.
	 */
	function cloneRequestWithNonce(input, init, newNonce) {
		var newInit = {};

		// Copia todas as propriedades do init original
		if (init) {
			for (var key in init) {
				if (init.hasOwnProperty(key)) {
					newInit[key] = init[key];
				}
			}
		}

		// Se input é um Request, copia propriedades dele
		if (input instanceof Request) {
			if (!newInit.method) newInit.method = input.method;
			if (!newInit.credentials) newInit.credentials = input.credentials;
			if (!newInit.mode) newInit.mode = input.mode;
			if (!newInit.cache) newInit.cache = input.cache;
			if (!newInit.redirect) newInit.redirect = input.redirect;
			if (!newInit.referrer) newInit.referrer = input.referrer;
			if (!newInit.signal && init && init.signal) newInit.signal = init.signal;

			// Copia body se não foi fornecido no init
			if (!newInit.body && input.method !== 'GET' && input.method !== 'HEAD') {
				// Clona o body do Request original
				try {
					newInit.body = init && init.body ? init.body : input._bodyInit || input.body;
				} catch (e) { /* ignora */ }
			}
		}

		// Monta headers com nonce novo
		var headers = new Headers(newInit.headers || (input instanceof Request ? input.headers : {}));
		headers.set(NONCE_HEADER, newNonce);
		newInit.headers = headers;

		var url = typeof input === 'string' ? input : input.url;
		return { url: url, init: newInit };
	}

	// Salva referência do fetch original
	var originalFetch = window.fetch;

	window.fetch = function (input, init) {
		var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');

		// Se não é Store API, passa direto
		if (!isStoreApiUrl(url)) {
			return originalFetch.apply(window, arguments);
		}

		return originalFetch.apply(window, arguments).then(function (response) {
			// Atualiza nonce se a resposta trouxe um novo
			var respNonce = response.headers && response.headers.get(RESP_HEADER);
			if (respNonce) {
				setNonce(respNonce);
			}

			// Se não é erro de nonce, retorna normalmente
			if (!isNonceError(response)) {
				return response;
			}

			// Erro de nonce — tenta refresh e retry (apenas 1 vez)
			return refreshNonce().then(function (newNonce) {
				if (!newNonce) return response; // Sem nonce novo, retorna erro original

				var cloned = cloneRequestWithNonce(input, init, newNonce);
				return originalFetch.call(window, cloned.url, cloned.init).then(function (retryResponse) {
					// Atualiza nonce da nova resposta
					var retryNonce = retryResponse.headers && retryResponse.headers.get(RESP_HEADER);
					if (retryNonce) {
						setNonce(retryNonce);
					}
					return retryResponse;
				});
			});
		});
	};

	// Também intercepta XMLHttpRequest para cobrir requisições legacy
	var XHROpen = XMLHttpRequest.prototype.open;
	var XHRSend = XMLHttpRequest.prototype.send;
	var XHRSetHeader = XMLHttpRequest.prototype.setRequestHeader;

	XMLHttpRequest.prototype.open = function (method, url) {
		this._gstoreUrl = url;
		this._gstoreMethod = method;
		this._gstoreHeaders = {};
		return XHROpen.apply(this, arguments);
	};

	XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
		if (this._gstoreHeaders) {
			this._gstoreHeaders[name] = value;
		}
		return XHRSetHeader.apply(this, arguments);
	};

	XMLHttpRequest.prototype.send = function (body) {
		var xhr = this;
		var url = xhr._gstoreUrl;

		if (!isStoreApiUrl(url)) {
			return XHRSend.apply(xhr, arguments);
		}

		var originalOnReadyStateChange = xhr.onreadystatechange;
		var originalOnLoad = xhr.onload;
		var retried = false;

		function handleResponse() {
			if (xhr.readyState !== 4 || retried) return;
			if (!isNonceError({ status: xhr.status })) return;

			retried = true;
			refreshNonce().then(function (newNonce) {
				if (!newNonce) return;

				// Cria novo XHR com nonce atualizado
				var retryXhr = new XMLHttpRequest();
				// Usa o open original para não ativar nosso interceptor recursivamente
				XHROpen.call(retryXhr, xhr._gstoreMethod, url, true);

				// Copia headers
				var headers = xhr._gstoreHeaders || {};
				for (var h in headers) {
					if (h.toLowerCase() === 'nonce') continue;
					XHRSetHeader.call(retryXhr, h, headers[h]);
				}
				XHRSetHeader.call(retryXhr, NONCE_HEADER, newNonce);

				retryXhr.withCredentials = xhr.withCredentials;

				retryXhr.onload = function () {
					// Copia resposta para o XHR original via eventos customizados
					try {
						Object.defineProperty(xhr, 'status', { get: function () { return retryXhr.status; } });
						Object.defineProperty(xhr, 'statusText', { get: function () { return retryXhr.statusText; } });
						Object.defineProperty(xhr, 'responseText', { get: function () { return retryXhr.responseText; } });
						Object.defineProperty(xhr, 'response', { get: function () { return retryXhr.response; } });
					} catch (e) { /* some properties may not be configurable */ }

					if (originalOnLoad) originalOnLoad.call(xhr);
					if (originalOnReadyStateChange) {
						Object.defineProperty(xhr, 'readyState', { get: function () { return 4; } });
						originalOnReadyStateChange.call(xhr);
					}
				};

				XHRSend.call(retryXhr, body);
			});
		}

		xhr.onreadystatechange = function () {
			handleResponse();
			if (!retried && originalOnReadyStateChange) {
				originalOnReadyStateChange.apply(xhr, arguments);
			}
		};

		xhr.onload = function () {
			handleResponse();
			if (!retried && originalOnLoad) {
				originalOnLoad.apply(xhr, arguments);
			}
		};

		return XHRSend.apply(xhr, arguments);
	};
})();

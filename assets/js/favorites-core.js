/**
 * Gstore Favorites Core
 *
 * - Logado: persiste no user meta via admin-ajax
 * - Deslogado: persiste no localStorage
 * - Merge automático: ao logar, une localStorage + user meta
 */
(function () {
	'use strict';

	const STORAGE_KEY = 'gstore_favorites';

	const cfg = (window.gstoreFavoritesConfig || {});
	const isLoggedIn = Boolean(cfg.isLoggedIn);
	const ajaxUrl = String(cfg.ajaxUrl || '');
	const nonce = String(cfg.nonce || '');

	function uniqStrings(list) {
		const out = [];
		const seen = new Set();
		for (const v of list) {
			const s = String(v).trim();
			if (!s) continue;
			if (seen.has(s)) continue;
			seen.add(s);
			out.push(s);
		}
		return out;
	}

	function toIdStringArray(input) {
		if (!Array.isArray(input)) return [];
		const ids = input
			.map(v => String(v).trim())
			.filter(Boolean)
			// mantém apenas números (IDs de produto)
			.filter(v => /^\d+$/.test(v));
		return uniqStrings(ids);
	}

	function readLocal() {
		try {
			return toIdStringArray(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'));
		} catch (e) {
			return [];
		}
	}

	function writeLocal(ids) {
		try {
			localStorage.setItem(STORAGE_KEY, JSON.stringify(toIdStringArray(ids)));
		} catch (e) {
			// ignore
		}
	}

	function clearLocal() {
		try {
			localStorage.removeItem(STORAGE_KEY);
		} catch (e) {
			// ignore
		}
	}

	function postAjax(action, data) {
		if (!ajaxUrl) {
			return Promise.reject(new Error('ajaxUrl ausente'));
		}
		const body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', nonce);
		Object.entries(data || {}).forEach(([k, v]) => {
			if (Array.isArray(v)) {
				// Alguns ambientes/proxy/WAF podem bloquear o formato `ids[]`.
				// Usamos `ids[123]=123` (associativo) para garantir que chegue no PHP.
				v.forEach(item => {
					const s = String(item);
					body.set(`${k}[${s}]`, s);
				});
			} else if (v !== undefined && v !== null) {
				body.set(k, String(v));
			}
		});

		return fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		}).then(async (res) => {
			const json = await res.json();
			if (!json || json.success !== true) {
				const msg = (json && json.data && json.data.message) ? json.data.message : 'Falha na requisição';
				throw new Error(msg);
			}
			return json.data;
		});
	}

	let cachedIds = null; // array de strings numéricas
	let readyResolve;
	const ready = new Promise((resolve) => {
		readyResolve = resolve;
	});

	function emitChanged(ids) {
		try {
			window.dispatchEvent(new CustomEvent('gstore:favorites-changed', { detail: { ids } }));
		} catch (e) {
			// ignore
		}
	}

	function setCache(ids) {
		cachedIds = toIdStringArray(ids);
		emitChanged(cachedIds.slice());
		return cachedIds;
	}

	async function init() {
		// seed inicial (logado) vindo do PHP
		if (isLoggedIn && Array.isArray(cfg.initialIds)) {
			setCache(cfg.initialIds);
		}

		// merge automático: localStorage -> conta
		if (isLoggedIn) {
			const localIds = readLocal();
			if (localIds.length > 0) {
				try {
					const data = await postAjax('gstore_favorites_merge', { ids: localIds });
					if (data && Array.isArray(data.ids)) {
						setCache(data.ids);
					}
					clearLocal();
				} catch (e) {
					// se falhar, não apaga local
				}
			}
		}

		readyResolve(true);
	}

	// inicia sem bloquear scripts dependentes
	init();

	async function getIds() {
		await ready;
		if (!isLoggedIn) {
			return readLocal();
		}
		return Array.isArray(cachedIds) ? cachedIds.slice() : [];
	}

	async function isFavorited(productId) {
		const id = String(productId || '').trim();
		if (!/^\d+$/.test(id)) return false;
		const ids = await getIds();
		return ids.includes(id);
	}

	async function toggle(productId) {
		const id = String(productId || '').trim();
		if (!/^\d+$/.test(id)) {
			throw new Error('ID inválido');
		}

		await ready;

		if (!isLoggedIn) {
			const ids = readLocal();
			const has = ids.includes(id);
			const next = has ? ids.filter(x => x !== id) : ids.concat([id]);
			writeLocal(next);
			setCache(next); // cache local para emitir eventos
			return { ids: next, isFavorited: !has };
		}

		const data = await postAjax('gstore_favorites_toggle', { product_id: id });
		const ids = (data && Array.isArray(data.ids)) ? data.ids : [];
		setCache(ids);
		return { ids, isFavorited: ids.includes(id) };
	}

	window.GstoreFavorites = {
		ready,
		getIds,
		isFavorited,
		toggle
	};
})();


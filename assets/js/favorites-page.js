/**
 * Página /favoritos/
 * - Mostra lista renderizada via AJAX com os cards do tema
 * - Empty-state com CTA para /catalogo/
 */
(function () {
	'use strict';

	const cfg = (window.gstoreFavoritesConfig || {});
	const ajaxUrl = String(cfg.ajaxUrl || '');
	const nonce = String(cfg.nonce || '');
	const catalogUrl = String(cfg.catalogUrl || '/catalogo/');

	const root = document.getElementById('gstore-favorites-root');
	if (!root) return;

	function renderLoading() {
		root.innerHTML = '<div class="Gstore-favorites__loading">Carregando seus favoritos…</div>';
	}

	function renderEmpty() {
		root.innerHTML = `
			<div class="Gstore-favorites__empty">
				<h2 class="Gstore-favorites__empty-title">Sua lista de favoritos está vazia</h2>
				<p class="Gstore-favorites__empty-text">Explore o catálogo e marque seus produtos preferidos para ver tudo reunido aqui.</p>
				<a class="Gstore-btn Gstore-btn--primary" href="${catalogUrl}">Ver Catálogo</a>
			</div>
		`;
	}

	function renderHtml(html) {
		root.innerHTML = `
			<div class="Gstore-favorites__list">
				${html}
			</div>
		`;
	}

	function postAjax(action, data) {
		if (!ajaxUrl) return Promise.reject(new Error('ajaxUrl ausente'));
		const body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', nonce);
		Object.entries(data || {}).forEach(([k, v]) => {
			if (Array.isArray(v)) {
				v.forEach(item => body.append(k + '[]', String(item)));
			} else if (v !== undefined && v !== null) {
				body.set(k, String(v));
			}
		});
		return fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(async (res) => {
			const json = await res.json();
			if (!json || json.success !== true) {
				throw new Error((json && json.data && json.data.message) ? json.data.message : 'Falha ao carregar favoritos');
			}
			return json.data;
		});
	}

	let refreshTimer = null;
	async function refresh() {
		renderLoading();

		if (typeof window.GstoreFavorites?.getIds !== 'function') {
			renderEmpty();
			return;
		}

		let ids = [];
		try {
			await window.GstoreFavorites.ready;
			ids = await window.GstoreFavorites.getIds();
		} catch (e) {
			ids = [];
		}

		if (!Array.isArray(ids) || ids.length === 0) {
			renderEmpty();
			return;
		}

		try {
			const data = await postAjax('gstore_favorites_render', { ids });
			const html = String(data?.html || '').trim();
			if (!html) {
				renderEmpty();
				return;
			}
			renderHtml(html);
		} catch (e) {
			renderEmpty();
		}
	}

	function scheduleRefresh() {
		if (refreshTimer) clearTimeout(refreshTimer);
		refreshTimer = setTimeout(refresh, 150);
	}

	// Se o script for carregado no footer, o DOM pode já estar pronto.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', refresh);
	} else {
		refresh();
	}
	window.addEventListener('gstore:favorites-changed', scheduleRefresh);
})();


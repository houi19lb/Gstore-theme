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

	const searchInput = document.getElementById('gstore-favorites-search');
	const clearBtn = document.getElementById('gstore-favorites-clear');

	let activeCategorySlug = '';

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
		// O HTML vindo do endpoint já inclui o loop do Woo (ul.products ...).
		root.innerHTML = `<div class="Gstore-favorites__list">${html}</div>`;
	}

	function extractSlugFromCategoryHref(href) {
		if (!href) return '';
		try {
			const u = new URL(href, window.location.origin);
			const parts = u.pathname.split('/').filter(Boolean);
			return parts.length ? parts[parts.length - 1] : '';
		} catch (e) {
			const parts = String(href).split('/').filter(Boolean);
			return parts.length ? parts[parts.length - 1] : '';
		}
	}

	function applyFilters() {
		const query = String(searchInput?.value || '').trim().toLowerCase();
		const cards = root.querySelectorAll('li.Gstore-product-card');
		let visibleCount = 0;

		cards.forEach((li) => {
			// Categoria: Woo adiciona classes tipo `product_cat-slug`
			let okCategory = true;
			if (activeCategorySlug) {
				okCategory = li.classList.contains(`product_cat-${activeCategorySlug}`);
			}

			// Texto: filtra por título (e fallback por texto do card)
			let okText = true;
			if (query) {
				const title = li.querySelector('.Gstore-product-card__title a')?.textContent || '';
				const hay = String(title || li.textContent || '').toLowerCase();
				okText = hay.includes(query);
			}

			const show = okCategory && okText;
			li.style.display = show ? '' : 'none';
			if (show) visibleCount += 1;
		});

		if (clearBtn) {
			clearBtn.hidden = !(query || activeCategorySlug);
		}

		// Se a lista existir mas nenhum item bater, mostra um aviso simples
		const emptyHintId = 'gstore-favorites-empty-hint';
		const existingHint = document.getElementById(emptyHintId);
		if (cards.length > 0 && visibleCount === 0) {
			if (!existingHint) {
				root.insertAdjacentHTML(
					'afterbegin',
					`<div id="${emptyHintId}" class="Gstore-favorites__empty-hint">Nenhum favorito encontrado com os filtros atuais.</div>`
				);
			}
		} else if (existingHint) {
			existingHint.remove();
		}
	}

	function postAjax(action, data) {
		if (!ajaxUrl) return Promise.reject(new Error('ajaxUrl ausente'));
		const body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', nonce);
		Object.entries(data || {}).forEach(([k, v]) => {
			if (Array.isArray(v)) {
				// Evita o formato `ids[]` que pode ser filtrado em alguns ambientes.
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
			applyFilters();
		} catch (e) {
			renderEmpty();
		}
	}

	function scheduleRefresh() {
		if (refreshTimer) clearTimeout(refreshTimer);
		refreshTimer = setTimeout(refresh, 150);
	}

	// Busca instantânea (somente nos favoritos da página)
	if (searchInput) {
		searchInput.addEventListener('input', () => {
			applyFilters();
		});
	}

	// Clique em categoria: filtra sem navegar
	document.addEventListener('click', (e) => {
		const a = e.target?.closest?.('.wc-block-product-categories a');
		if (!a) return;

		// Só intercepta se a sidebar existir nesta página
		if (!document.getElementById('gstore-favorites-root')) return;

		e.preventDefault();
		const slug = extractSlugFromCategoryHref(a.getAttribute('href'));
		activeCategorySlug = (activeCategorySlug === slug) ? '' : slug;

		// feedback visual simples
		document.querySelectorAll('.wc-block-product-categories a').forEach((link) => {
			const linkSlug = extractSlugFromCategoryHref(link.getAttribute('href'));
			link.classList.toggle('is-active', activeCategorySlug && linkSlug === activeCategorySlug);
		});

		applyFilters();
	});

	if (clearBtn) {
		clearBtn.addEventListener('click', () => {
			activeCategorySlug = '';
			if (searchInput) searchInput.value = '';
			document.querySelectorAll('.wc-block-product-categories a').forEach((link) => {
				link.classList.remove('is-active');
			});
			applyFilters();
		});
	}

	// Se o script for carregado no footer, o DOM pode já estar pronto.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', refresh);
	} else {
		refresh();
	}
	window.addEventListener('gstore:favorites-changed', scheduleRefresh);
})();


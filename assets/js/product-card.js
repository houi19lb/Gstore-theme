/**
 * Funcionalidades dos cards de produto Gstore
 * 
 * @package Gstore
 */

(function() {
	'use strict';

	/* =========================================================================
	 * Parcelamento AJAX dos cards (mesma lógica da página de produto)
	 * ========================================================================= */

	const installmentCache = new Map();
	const installmentInFlight = new Map();

	const resolveAjaxUrl = () => {
		if (typeof gstoreCardInstallments !== 'undefined' && gstoreCardInstallments?.ajaxUrl) {
			return String(gstoreCardInstallments.ajaxUrl);
		}
		if (typeof gstoreSingleProductInstallments !== 'undefined' && gstoreSingleProductInstallments?.ajaxUrl) {
			return String(gstoreSingleProductInstallments.ajaxUrl);
		}
		if (typeof gstoreFavoritesConfig !== 'undefined' && gstoreFavoritesConfig?.ajaxUrl) {
			return String(gstoreFavoritesConfig.ajaxUrl);
		}
		if (typeof ajaxurl !== 'undefined') {
			return String(ajaxurl);
		}
		return '/wp-admin/admin-ajax.php';
	};

	const resolveAction = () => {
		if (typeof gstoreCardInstallments !== 'undefined' && gstoreCardInstallments?.action) {
			return String(gstoreCardInstallments.action);
		}
		if (typeof gstoreSingleProductInstallments !== 'undefined' && gstoreSingleProductInstallments?.action) {
			return String(gstoreSingleProductInstallments.action);
		}
		return 'gstore_blu_get_product_installment_quotes';
	};

	const formatCurrency = (value) => {
		if (!Number.isFinite(value)) return '';
		try {
			return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
		} catch (e) {
			return 'R$\u00a0' + value.toFixed(2).replace('.', ',');
		}
	};

	const chooseQuote = (quotes, preferredMax) => {
		if (!quotes || typeof quotes !== 'object') return null;
		const preferred = quotes[String(preferredMax)];
		if (preferred) return preferred;
		const keys = Object.keys(quotes)
			.map((k) => parseInt(k, 10))
			.filter((k) => Number.isFinite(k))
			.sort((a, b) => b - a);
		if (!keys.length) return null;
		return quotes[String(keys[0])] || null;
	};

	/**
	 * Busca as cotações de parcelamento via AJAX e atualiza o texto do card.
	 */
	function fetchCardInstallmentQuotes(target) {
		// Evita processar o mesmo target duas vezes (ex.: single-product.js também roda nos cards)
		if (target.dataset.gstoreInstallmentLoaded === '1') return;
		target.dataset.gstoreInstallmentLoaded = '1';

		const productId = String(target?.dataset?.productId || '');
		if (!productId) return;

		const maxRaw = parseInt(String(target?.dataset?.maxInstallments || '21'), 10);
		const max = Number.isFinite(maxRaw) && maxRaw > 0 ? maxRaw : 21;
		const signature = productId + '|1|' + max;

		if (installmentCache.has(signature)) {
			target.textContent = installmentCache.get(signature);
			return;
		}

		if (installmentInFlight.has(signature)) return;

		const ajaxUrl = resolveAjaxUrl();
		const action = resolveAction();
		if (!ajaxUrl) return;

		const body = new URLSearchParams();
		body.set('action', action);
		body.set('product_id', productId);
		body.set('quantity', '1');
		body.set('max', String(max));

		const fetchPromise = fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		})
			.then((r) => r.json())
			.then((payload) => {
				if (!payload?.success || !payload?.data?.quotes) {
					throw new Error('Quotes não encontrados.');
				}

				const quoteKeys = Object.keys(payload.data.quotes)
					.map((k) => parseInt(k, 10))
					.filter((k) => Number.isFinite(k))
					.sort((a, b) => b - a);

				const preferredMax = quoteKeys.length > 0 ? quoteKeys[0] : (payload.data.max || max);
				const quote = chooseQuote(payload.data.quotes, String(preferredMax));

				if (!quote || !quote.installments || !quote.per_installment_text) {
					throw new Error('Parcelas indisponíveis.');
				}

				const text = 'ou ' + quote.installments + 'x de ' + quote.per_installment_text;
				installmentCache.set(signature, text);
				target.textContent = text;
			})
			.catch(() => {
				// Em caso de erro, manter o texto server-side original (fallback)
			})
			.finally(() => {
				installmentInFlight.delete(signature);
			});

		installmentInFlight.set(signature, fetchPromise);
	}

	/**
	 * Inicializa o parcelamento AJAX para todos os cards visíveis.
	 * Usa IntersectionObserver para carregar sob demanda.
	 */
	function initCardInstallments() {
		const targets = Array.from(document.querySelectorAll('[data-gstore-installment-scope="card"]'));
		if (!targets.length) return;

		if ('IntersectionObserver' in window) {
			const observer = new IntersectionObserver(
				(entries) => {
					entries.forEach((entry) => {
						if (entry.isIntersecting) {
							observer.unobserve(entry.target);
							fetchCardInstallmentQuotes(entry.target);
						}
					});
				},
				{ rootMargin: '200px' }
			);
			targets.forEach((target) => observer.observe(target));
		} else {
			targets.forEach((target) => fetchCardInstallmentQuotes(target));
		}
	}

	/* =========================================================================
	 * Normalização de títulos e preços
	 * ========================================================================= */

	/**
	 * Remove quebras de linha indesejadas dos títulos dos produtos
	 */
	function normalizeProductTitles() {
		const titleBlocks = document.querySelectorAll('.Gstore-product-card__title');
		if (!titleBlocks.length) {
			return;
		}

		titleBlocks.forEach(titleBlock => {
			// Remove quaisquer <br> inseridos pelo WordPress / navegador
			titleBlock.querySelectorAll('br').forEach(br => br.remove());

			const link = titleBlock.querySelector('a');
			if (!link) {
				return;
			}

			const normalizedText = link.textContent.replace(/\s+/g, ' ').trim();
			link.textContent = normalizedText;
		});
	}

	/**
	 * Remove quebras de linha indesejadas da área de preços
	 */
	function normalizePriceDetails() {
		const priceBlocks = document.querySelectorAll('.Gstore-product-card__price-details');
		if (!priceBlocks.length) {
			return;
		}

		priceBlocks.forEach(priceBlock => {
			priceBlock.querySelectorAll('br').forEach(br => br.remove());
			priceBlock.querySelectorAll('p').forEach(paragraph => {
				while (paragraph.firstChild) {
					paragraph.parentNode.insertBefore(paragraph.firstChild, paragraph);
				}
				paragraph.remove();
			});
		});
	}

	/* =========================================================================
	 * Favoritos
	 * ========================================================================= */

	/**
	 * Inicializa os botões de favorito
	 */
	function initFavoriteButtons() {
		const hasCore = () => typeof window.GstoreFavorites?.toggle === 'function';

		const setUI = (button, icon, isActive) => {
			button.classList.toggle('is-favorited', isActive);
			button.setAttribute('aria-pressed', String(isActive));
			if (icon) {
				icon.classList.toggle('fa-solid', isActive);
				icon.classList.toggle('fa-regular', !isActive);
			}
		};

		const syncAllButtons = async () => {
			if (!hasCore()) return;
			try {
				await window.GstoreFavorites.ready;
				const ids = await window.GstoreFavorites.getIds();
				const set = new Set(ids);
				document.querySelectorAll('.Gstore-product-card__favorite').forEach((button) => {
					const card = button.closest('.Gstore-product-card');
					if (!card) return;
					const productId = getProductId(card);
					if (!productId) return;
					const icon = button.querySelector('.Gstore-product-card__favorite-icon');
					setUI(button, icon, set.has(String(productId)));
				});
			} catch (e) {
				// ignore
			}
		};

		// Delegação: funciona também para HTML injetado (ex.: página /favoritos/)
		document.addEventListener('click', async (e) => {
			const button = e.target?.closest?.('.Gstore-product-card__favorite');
			if (!button) return;

			e.preventDefault();
			e.stopPropagation();

			if (!hasCore()) return;

			const productCard = button.closest('.Gstore-product-card');
			if (!productCard) return;

			const productId = getProductId(productCard);
			if (!productId) return;

			const icon = button.querySelector('.Gstore-product-card__favorite-icon');
			const prev = button.classList.contains('is-favorited');
			setUI(button, icon, !prev);

			try {
				const result = await window.GstoreFavorites.toggle(productId);
				setUI(button, icon, Boolean(result?.isFavorited));
			} catch (err) {
				setUI(button, icon, prev);
			}
		});

		// Estado inicial + sincronização entre componentes
		syncAllButtons();
		window.addEventListener('gstore:favorites-changed', () => {
			syncAllButtons();
		});
	}
	
	/**
	 * Obtém o ID do produto
	 */
	function getProductId(productCard) {
		// Tenta extrair o ID da classe do elemento
		const classes = productCard.className.split(' ');
		for (let className of classes) {
			if (className.startsWith('post-')) {
				return className.replace('post-', '');
			}
		}
		return null;
	}
	
	/**
	 * Inicializa quando o DOM estiver pronto
	 */
	function initProductCards() {
		initFavoriteButtons();
		normalizeProductTitles();
		normalizePriceDetails();
		initCardInstallments();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initProductCards);
	} else {
		initProductCards();
	}
	
})();


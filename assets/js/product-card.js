/**
 * Funcionalidades dos cards de produto Gstore
 * 
 * @package Gstore
 */

(function() {
	'use strict';

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
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initProductCards);
	} else {
		initProductCards();
	}
	
})();


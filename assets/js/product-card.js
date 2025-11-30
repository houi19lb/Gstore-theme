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
		const favoriteButtons = document.querySelectorAll('.Gstore-product-card__favorite');
		
		favoriteButtons.forEach(button => {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				const icon = this.querySelector('.Gstore-product-card__favorite-icon');
				if (!icon) {
					return;
				}

				const isActive = !this.classList.contains('is-favorited');
				this.classList.toggle('is-favorited', isActive);
				this.setAttribute('aria-pressed', String(isActive));

				icon.classList.toggle('fa-solid', isActive);
				icon.classList.toggle('fa-regular', !isActive);
				
				if (isActive) {
					saveFavorite(this);
				} else {
					removeFavorite(this);
				}
			});
		});
		
		// Restaurar favoritos salvos
		restoreFavorites();
	}
	
	/**
	 * Salva produto favorito no localStorage
	 */
	function saveFavorite(button) {
		const productCard = button.closest('.Gstore-product-card');
		if (!productCard) return;
		
		const productId = getProductId(productCard);
		if (!productId) return;
		
		let favorites = getFavorites();
		if (!favorites.includes(productId)) {
			favorites.push(productId);
			localStorage.setItem('gstore_favorites', JSON.stringify(favorites));
		}
	}
	
	/**
	 * Remove produto favorito do localStorage
	 */
	function removeFavorite(button) {
		const productCard = button.closest('.Gstore-product-card');
		if (!productCard) return;
		
		const productId = getProductId(productCard);
		if (!productId) return;
		
		let favorites = getFavorites();
		favorites = favorites.filter(id => id !== productId);
		localStorage.setItem('gstore_favorites', JSON.stringify(favorites));
	}
	
	/**
	 * Obtém a lista de favoritos do localStorage
	 */
	function getFavorites() {
		const stored = localStorage.getItem('gstore_favorites');
		return stored ? JSON.parse(stored) : [];
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
	 * Restaura o estado dos favoritos salvos
	 */
	function restoreFavorites() {
		const favorites = getFavorites();
		if (favorites.length === 0) return;
		
		favorites.forEach(productId => {
			const productCard = document.querySelector(`.post-${productId}`);
			if (productCard) {
				const button = productCard.querySelector('.Gstore-product-card__favorite');
				const icon = button?.querySelector('.Gstore-product-card__favorite-icon');
				if (button && icon) {
					button.classList.add('is-favorited');
					button.setAttribute('aria-pressed', 'true');
					icon.classList.remove('fa-regular');
					icon.classList.add('fa-solid');
				}
			}
		});
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


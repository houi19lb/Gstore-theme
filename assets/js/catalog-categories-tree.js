/**
 * ==========================================
 * CATALOG CATEGORIES TREE - Expandir/Colapsar
 * ==========================================
 * Implementa funcionalidade de árvore para categorias
 * aninhadas no filtro do catálogo, permitindo expandir
 * e colapsar subcategorias.
 */

(function() {
	'use strict';

	// Aguarda o DOM estar pronto
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCategoriesTree);
	} else {
		initCategoriesTree();
	}

	function initCategoriesTree() {
		// Seletores para ambos os formatos (bloco WooCommerce e widget padrão)
		const categoryLists = document.querySelectorAll(
			'.Gstore-catalog-shell--light .wc-block-product-categories-list, ' +
			'.Gstore-catalog-shell--light .product-categories'
		);

		if (categoryLists.length === 0) {
			return; // Nenhuma lista de categorias encontrada
		}

		categoryLists.forEach(function(list) {
			setupCategoryTree(list);
		});
	}

	/**
	 * Configura a árvore de categorias para uma lista específica
	 */
	function setupCategoryTree(list) {
		// Encontra todos os itens que têm subcategorias (ul.children)
		const itemsWithChildren = list.querySelectorAll('li:has(ul.children)');

		if (itemsWithChildren.length === 0) {
			return; // Nenhuma categoria com filhos encontrada
		}

		itemsWithChildren.forEach(function(item) {
			// Marca o item como tendo filhos
			item.classList.add('has-children');

			// Verifica se já existe um botão toggle
			if (item.querySelector('.category-toggle')) {
				return; // Já foi processado
			}

			// Encontra o link da categoria
			const categoryLink = item.querySelector('> a');
			if (!categoryLink) {
				return;
			}

			// Cria o botão toggle
			const toggleButton = createToggleButton(item);

			// Insere o botão antes do link
			item.insertBefore(toggleButton, categoryLink);

			// Adiciona event listener
			toggleButton.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				toggleCategory(item, toggleButton);
			});

			// Encontra a lista de subcategorias
			const childrenList = item.querySelector('> ul.children');
			if (childrenList) {
				// Configura recursivamente as subcategorias
				setupCategoryTree(childrenList);
			}
		});

		// Expande automaticamente categorias ativas e seus pais
		expandActiveCategories(list);
	}

	/**
	 * Cria o botão toggle para expandir/colapsar
	 */
	function createToggleButton(item) {
		const button = document.createElement('button');
		button.className = 'category-toggle';
		button.setAttribute('type', 'button');
		button.setAttribute('aria-expanded', 'false');
		button.setAttribute('aria-label', 'Expandir categoria');

		// ID único para acessibilidade
		const childrenList = item.querySelector('> ul.children');
		if (childrenList) {
			const uniqueId = 'category-' + Math.random().toString(36).substr(2, 9);
			childrenList.id = uniqueId;
			button.setAttribute('aria-controls', uniqueId);
		}

		// Ícone SVG (seta para direita)
		const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		icon.setAttribute('viewBox', '0 0 24 24');
		icon.setAttribute('fill', 'none');
		icon.setAttribute('stroke', 'currentColor');
		icon.setAttribute('stroke-width', '2');
		icon.setAttribute('stroke-linecap', 'round');
		icon.setAttribute('stroke-linejoin', 'round');

		const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
		path.setAttribute('d', 'M9 18l6-6-6-6');

		icon.appendChild(path);
		button.appendChild(icon);

		// Texto para screen readers
		const srText = document.createElement('span');
		srText.className = 'screen-reader-text';
		srText.textContent = 'Expandir categoria';
		button.appendChild(srText);

		return button;
	}

	/**
	 * Alterna o estado expandido/colapsado de uma categoria
	 */
	function toggleCategory(item, button) {
		const isExpanded = item.classList.contains('is-expanded');
		const childrenList = item.querySelector('> ul.children');

		if (!childrenList) {
			return;
		}

		if (isExpanded) {
			// Colapsar
			item.classList.remove('is-expanded');
			button.setAttribute('aria-expanded', 'false');
			button.setAttribute('aria-label', 'Expandir categoria');
			button.querySelector('.screen-reader-text').textContent = 'Expandir categoria';
		} else {
			// Expandir
			item.classList.add('is-expanded');
			button.setAttribute('aria-expanded', 'true');
			button.setAttribute('aria-label', 'Colapsar categoria');
			button.querySelector('.screen-reader-text').textContent = 'Colapsar categoria';
		}

		// Salva estado no localStorage (opcional)
		saveCategoryState(item, !isExpanded);
	}

	/**
	 * Expande automaticamente categorias ativas e seus pais
	 */
	function expandActiveCategories(list) {
		// Encontra categorias ativas
		const activeCategories = list.querySelectorAll('li.current-cat, li.current-cat-parent');

		activeCategories.forEach(function(activeItem) {
			// Expande a categoria ativa
			if (activeItem.classList.contains('has-children')) {
				const toggleButton = activeItem.querySelector('.category-toggle');
				if (toggleButton && !activeItem.classList.contains('is-expanded')) {
					activeItem.classList.add('is-expanded');
					toggleButton.setAttribute('aria-expanded', 'true');
					toggleButton.setAttribute('aria-label', 'Colapsar categoria');
					if (toggleButton.querySelector('.screen-reader-text')) {
						toggleButton.querySelector('.screen-reader-text').textContent = 'Colapsar categoria';
					}
				}
			}

			// Expande todos os pais da categoria ativa
			let parent = activeItem.parentElement.closest('li.has-children');
			while (parent) {
				if (!parent.classList.contains('is-expanded')) {
					const parentToggle = parent.querySelector('.category-toggle');
					if (parentToggle) {
						parent.classList.add('is-expanded');
						parentToggle.setAttribute('aria-expanded', 'true');
						parentToggle.setAttribute('aria-label', 'Colapsar categoria');
						if (parentToggle.querySelector('.screen-reader-text')) {
							parentToggle.querySelector('.screen-reader-text').textContent = 'Colapsar categoria';
						}
					}
				}
				parent = parent.parentElement.closest('li.has-children');
			}
		});
	}

	/**
	 * Salva o estado da categoria no localStorage (opcional)
	 */
	function saveCategoryState(item, isExpanded) {
		try {
			const categoryLink = item.querySelector('> a');
			if (!categoryLink || !categoryLink.href) {
				return;
			}

			// Extrai o ID ou slug da categoria da URL
			const url = new URL(categoryLink.href);
			const categoryId = url.pathname.split('/').filter(Boolean).pop();

			if (!categoryId) {
				return;
			}

			const storageKey = 'gstore_category_states';
			let states = JSON.parse(localStorage.getItem(storageKey) || '{}');
			states[categoryId] = isExpanded;
			localStorage.setItem(storageKey, JSON.stringify(states));
		} catch (e) {
			// Ignora erros de localStorage (pode estar desabilitado)
			console.debug('Não foi possível salvar estado da categoria:', e);
		}
	}

	/**
	 * Restaura estados salvos do localStorage (opcional)
	 */
	function restoreCategoryStates(list) {
		try {
			const storageKey = 'gstore_category_states';
			const states = JSON.parse(localStorage.getItem(storageKey) || '{}');

			if (Object.keys(states).length === 0) {
				return;
			}

			list.querySelectorAll('li.has-children').forEach(function(item) {
				const categoryLink = item.querySelector('> a');
				if (!categoryLink || !categoryLink.href) {
					return;
				}

				const url = new URL(categoryLink.href);
				const categoryId = url.pathname.split('/').filter(Boolean).pop();

				if (categoryId && states[categoryId] === true) {
					const toggleButton = item.querySelector('.category-toggle');
					if (toggleButton) {
						item.classList.add('is-expanded');
						toggleButton.setAttribute('aria-expanded', 'true');
						toggleButton.setAttribute('aria-label', 'Colapsar categoria');
						if (toggleButton.querySelector('.screen-reader-text')) {
							toggleButton.querySelector('.screen-reader-text').textContent = 'Colapsar categoria';
						}
					}
				}
			});
		} catch (e) {
			// Ignora erros de localStorage
			console.debug('Não foi possível restaurar estados das categorias:', e);
		}
	}

	// Restaura estados após um pequeno delay para garantir que o DOM está pronto
	setTimeout(function() {
		const categoryLists = document.querySelectorAll(
			'.Gstore-catalog-shell--light .wc-block-product-categories-list, ' +
			'.Gstore-catalog-shell--light .product-categories'
		);
		categoryLists.forEach(function(list) {
			restoreCategoryStates(list);
		});
	}, 100);
})();





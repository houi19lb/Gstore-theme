/**
 * ==========================================
 * CATALOG CATEGORIES TREE - Expandir/Colapsar
 * ==========================================
 * Implementa funcionalidade de árvore para categorias
 * aninhadas no filtro do catálogo, permitindo expandir
 * e colapsar subcategorias.
 * 
 * VERSÃO 2.0: Busca hierarquia via REST API do WooCommerce
 * e reconstrói a árvore dinamicamente.
 */

const __GSTORE_TREE_RUN_ID = 'tree_' + Date.now() + '_' + Math.random().toString(16).slice(2);
// #region agent log
fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'SCRIPT_LOAD',location:'catalog-categories-tree.js:TOP',message:'Script v2 loaded',data:{url:window.location.href},timestamp:Date.now()})}).catch(()=>{});
// #endregion

(function() {
	'use strict';

	// Evita dupla inicialização (script pode ser enfileirado mais de uma vez em alguns cenários)
	if (window.__gstoreCategoriesTreeInit) {
		// #region agent log
		fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'DUP_INIT',location:'catalog-categories-tree.js:guard',message:'Init skipped (already initialized)',data:{url:window.location.href},timestamp:Date.now()})}).catch(()=>{});
		// #endregion
		return;
	}
	window.__gstoreCategoriesTreeInit = true;

	// Aguarda o DOM estar pronto
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCategoriesTree);
	} else {
		initCategoriesTree();
	}

	function initCategoriesTree() {
		// #region agent log
		fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'C',location:'catalog-categories-tree.js:initCategoriesTree',message:'initCategoriesTree v2 called',data:{readyState:document.readyState},timestamp:Date.now()})}).catch(()=>{});
		// #endregion

		// Seletores para ambos os formatos (bloco WooCommerce e widget padrão)
		const categoryLists = document.querySelectorAll(
			'.Gstore-catalog-shell--light ul.wc-block-product-categories-list--depth-0, ' +
			'.Gstore-catalog-shell--light .product-categories'
		);

		// #region agent log
		fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'LISTS',location:'catalog-categories-tree.js:lists',message:'Category list candidates',data:{count:categoryLists.length,classes:Array.from(categoryLists).map(n=>n.className)},timestamp:Date.now()})}).catch(()=>{});
		// #endregion

		if (categoryLists.length === 0) {
			return;
		}

		categoryLists.forEach(function(list) {
			if (list.dataset && list.dataset.gstoreTreeInit === '1') {
				return;
			}
			if (list.dataset) {
				list.dataset.gstoreTreeInit = '1';
			}
			// Primeiro tenta a abordagem antiga (se a hierarquia já está no HTML)
			const itemsWithChildren = list.querySelectorAll('li:has(ul.children)');
			if (itemsWithChildren.length > 0) {
				setupCategoryTreeLegacy(list);
			} else {
				// Se não há hierarquia no HTML, busca via API e reconstrói
				rebuildCategoryTreeFromAPI(list);
			}
		});
	}

	/**
	 * Busca categorias via REST API e reconstrói a árvore
	 */
	async function rebuildCategoryTreeFromAPI(list) {
		// #region agent log
		fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'API',location:'catalog-categories-tree.js:rebuildFromAPI',message:'Fetching categories from API',data:{},timestamp:Date.now()})}).catch(()=>{});
		// #endregion

		try {
			// Busca todas as categorias de produto via REST API do WooCommerce
			const response = await fetch('/wp-json/wc/store/v1/products/categories?per_page=100');
			if (!response.ok) {
				throw new Error('Failed to fetch categories: ' + response.status);
			}
			const categories = await response.json();

			// #region agent log
			const parentStats = categories.reduce((acc, c) => {
				const key = String(c.parent);
				acc[key] = (acc[key] || 0) + 1;
				return acc;
			}, {});
			fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'API',location:'catalog-categories-tree.js:apiResponse',message:'Categories fetched',data:{count:categories.length,parentStats,sample:categories.slice(0,5).map(c=>({id:c.id,name:c.name,parent:c.parent,slug:c.slug,count:c.count}))},timestamp:Date.now()})}).catch(()=>{});
			// #endregion

			// Constrói a estrutura hierárquica
			const tree = buildCategoryTree(categories);

			// #region agent log
			const treeHasChildrenCount = tree.reduce((acc, n) => acc + ((n.children && n.children.length) ? 1 : 0), 0);
			fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'API',location:'catalog-categories-tree.js:treeBuilt',message:'Tree structure built',data:{rootCategories:tree.length,rootNodesWithChildren:treeHasChildrenCount},timestamp:Date.now()})}).catch(()=>{});
			// #endregion

			// Substitui a lista original pela nova árvore
			const newList = renderCategoryTree(tree);
			list.innerHTML = '';
			list.innerHTML = newList;

			// #region agent log
			const liCount = list.querySelectorAll('li').length;
			const hasChildrenLiCount = list.querySelectorAll('li.has-children').length;
			const toggleBtnCount = list.querySelectorAll('button.category-toggle').length;
			const srVisibleSample = Array.from(list.querySelectorAll('button.category-toggle .screen-reader-text'))
				.slice(0, 3)
				.map(n => (n.textContent || '').trim());
			fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'UI',location:'catalog-categories-tree.js:domAfterRender',message:'DOM after render',data:{liCount,hasChildrenLiCount,toggleBtnCount,srTextSample:srVisibleSample},timestamp:Date.now()})}).catch(()=>{});
			// #endregion

			// Adiciona os event listeners para toggle
			setupToggleListeners(list);

			// Expande categorias ativas
			expandActiveCategories(list);

		} catch (error) {
			// #region agent log
			fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'API',location:'catalog-categories-tree.js:apiError',message:'API error',data:{error:error.message},timestamp:Date.now()})}).catch(()=>{});
			// #endregion
			console.error('Erro ao buscar categorias:', error);
		}
	}

	/**
	 * Constrói a árvore hierárquica a partir das categorias
	 */
	function buildCategoryTree(categories) {
		const categoryMap = {};
		const tree = [];

		// Primeiro, cria um mapa de todas as categorias
		categories.forEach(cat => {
			categoryMap[cat.id] = {
				...cat,
				children: []
			};
		});

		// Depois, organiza em hierarquia
		categories.forEach(cat => {
			if (cat.parent === 0) {
				// Categoria raiz
				tree.push(categoryMap[cat.id]);
			} else if (categoryMap[cat.parent]) {
				// Subcategoria
				categoryMap[cat.parent].children.push(categoryMap[cat.id]);
			} else {
				// Pai não encontrado, trata como raiz
				tree.push(categoryMap[cat.id]);
			}
		});

		// Ordena por nome
		const sortByName = (a, b) => a.name.localeCompare(b.name);
		tree.sort(sortByName);
		Object.values(categoryMap).forEach(cat => cat.children.sort(sortByName));

		return tree;
	}

	/**
	 * Renderiza a árvore de categorias em HTML
	 */
	function renderCategoryTree(categories, depth = 0) {
		if (!categories || categories.length === 0) return '';

		let html = '';
		categories.forEach(cat => {
			const hasChildren = cat.children && cat.children.length > 0;
			const childrenClass = hasChildren ? ' has-children' : '';
			const slug = cat.slug || cat.name.toLowerCase().replace(/\s+/g, '-');

			html += `<li class="wc-block-product-categories-list-item${childrenClass}" data-category-id="${cat.id}">`;
			
			if (hasChildren) {
				html += `<button class="category-toggle" type="button" aria-expanded="false" aria-label="Expandir categoria">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M9 18l6-6-6-6"/>
					</svg>
				</button>`;
			}

			html += `<a href="/categoria-produto/${slug}/">
				<span class="wc-block-product-categories-list-item__name">${cat.name}</span>
			</a>`;
			html += `<span class="wc-block-product-categories-list-item-count">
				<span aria-hidden="true">${cat.count || 0}</span>
				<span class="screen-reader-text">${cat.count === 1 ? '1 produto' : (cat.count || 0) + ' produtos'}</span>
			</span>`;

			if (hasChildren) {
				html += `<ul class="children wc-block-product-categories-list wc-block-product-categories-list--depth-${depth + 1}">`;
				html += renderCategoryTree(cat.children, depth + 1);
				html += '</ul>';
			}

			html += '</li>';
		});

		return html;
	}

	/**
	 * Adiciona event listeners para os botões toggle
	 */
	function setupToggleListeners(list) {
		const toggleButtons = list.querySelectorAll('.category-toggle');
		// #region agent log
		fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'CLICK',location:'catalog-categories-tree.js:setupToggleListeners',message:'Attaching toggle listeners',data:{toggleButtons:toggleButtons.length},timestamp:Date.now()})}).catch(()=>{});
		// #endregion
		toggleButtons.forEach(button => {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				const item = button.closest('li');
				// #region agent log
				const children = item ? item.querySelector(':scope > ul.children') : null;
				fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:__GSTORE_TREE_RUN_ID,hypothesisId:'CLICK',location:'catalog-categories-tree.js:toggleClick',message:'Toggle clicked',data:{hasItem:!!item,hadExpanded:item?.classList?.contains('is-expanded')||false,hasChildrenEl:!!children,childrenScrollHeight:children?children.scrollHeight:null},timestamp:Date.now()})}).catch(()=>{});
				// #endregion
				toggleCategory(item, button);
			});
		});
	}

	/**
	 * Abordagem legacy para quando a hierarquia já está no HTML
	 */
	function setupCategoryTreeLegacy(list) {
		const itemsWithChildren = list.querySelectorAll('li:has(ul.children)');

		itemsWithChildren.forEach(function(item) {
			item.classList.add('has-children');

			if (item.querySelector('.category-toggle')) {
				return;
			}

			const categoryLink = item.querySelector('> a');
			if (!categoryLink) {
				return;
			}

			const toggleButton = createToggleButton(item);
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





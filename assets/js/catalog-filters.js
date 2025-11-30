/**
 * ==========================================
 * CATALOG FILTERS TOGGLE - Mobile
 * ==========================================
 * Controla a abertura/fechamento dos filtros
 * na versão mobile do catálogo.
 */

(function() {
	'use strict';

	// Aguarda o DOM estar pronto
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCatalogFilters);
	} else {
		initCatalogFilters();
	}

	function initCatalogFilters() {
		const toggleButton = document.querySelector('.Gstore-catalog-filters-toggle');
		const sidebar = document.querySelector('.Gstore-catalog-sidebar__inner--collapsible');
		const closeButton = document.querySelector('.Gstore-catalog-sidebar__close');
		const overlay = document.createElement('div');
		overlay.className = 'Gstore-catalog-filters-overlay';
		
		// Inicializa breadcrumb dinâmico
		initDynamicBreadcrumb();
		
		if (!toggleButton || !sidebar) {
			return; // Elementos não encontrados, sair silenciosamente
		}

		// Adiciona overlay ao body
		document.body.appendChild(overlay);

		// Função para abrir filtros
		function openFilters() {
			sidebar.classList.add('is-open');
			toggleButton.classList.add('is-active');
			toggleButton.setAttribute('aria-expanded', 'true');
			overlay.classList.add('is-active');
			document.body.style.overflow = 'hidden'; // Previne scroll do body
		}

		// Função para fechar filtros
		function closeFilters() {
			sidebar.classList.remove('is-open');
			toggleButton.classList.remove('is-active');
			toggleButton.setAttribute('aria-expanded', 'false');
			overlay.classList.remove('is-active');
			document.body.style.overflow = ''; // Restaura scroll do body
		}

		// Toggle ao clicar no botão
		toggleButton.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			if (sidebar.classList.contains('is-open')) {
				closeFilters();
			} else {
				openFilters();
			}
		});

		// Fechar ao clicar no overlay
		overlay.addEventListener('click', closeFilters);

		// Fechar ao clicar no botão de fechar (se existir)
		if (closeButton) {
			closeButton.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				closeFilters();
			});
		}

		// Fechar ao pressionar ESC
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
				closeFilters();
			}
		});

		// Fechar ao redimensionar para desktop
		let resizeTimer;
		window.addEventListener('resize', function() {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function() {
				// Se a largura for maior que 1024px (breakpoint desktop), fecha os filtros
				if (window.innerWidth > 1024 && sidebar.classList.contains('is-open')) {
					closeFilters();
				}
			}, 250);
		});

		// Fecha filtros se já estiverem abertos ao carregar em desktop
		if (window.innerWidth > 1024) {
			closeFilters();
		}
	}

	/**
	 * Inicializa o breadcrumb dinâmico
	 * Preenche o nome da categoria/termo atual no breadcrumb
	 */
	function initDynamicBreadcrumb() {
		const dynamicBreadcrumb = document.querySelector('.Gstore-breadcrumb--dynamic');
		if (!dynamicBreadcrumb) {
			return;
		}

		const currentTermSpan = dynamicBreadcrumb.querySelector('.Gstore-breadcrumb__current-term');
		const currentSep = dynamicBreadcrumb.querySelector('.Gstore-breadcrumb__current-sep');
		
		if (!currentTermSpan) {
			return;
		}

		// Tenta obter o título da página de várias formas
		let termName = '';

		// 1. Tenta pegar do título de archive do Gutenberg
		const archiveTitle = document.querySelector('.Gstore-catalog-title');
		if (archiveTitle) {
			termName = archiveTitle.textContent.trim();
			// Remove prefixos comuns do WooCommerce
			termName = termName.replace(/^(Categoria:|Category:|Arquivo:|Archive:)\s*/i, '');
		}

		// 2. Fallback: tenta pegar da tag title
		if (!termName) {
			const pageTitle = document.title.split('–')[0].split('|')[0].trim();
			termName = pageTitle;
		}

		// 3. Fallback: usa a última parte da URL
		if (!termName) {
			const pathParts = window.location.pathname.split('/').filter(Boolean);
			if (pathParts.length > 0) {
				termName = pathParts[pathParts.length - 1]
					.replace(/-/g, ' ')
					.replace(/\b\w/g, l => l.toUpperCase());
			}
		}

		// Atualiza o breadcrumb
		if (termName) {
			currentTermSpan.textContent = termName;
		} else {
			// Se não encontrou termo, esconde o separador e span atual
			if (currentSep) currentSep.style.display = 'none';
			currentTermSpan.style.display = 'none';
		}
	}
})();


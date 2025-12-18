/**
 * Header JavaScript - Gstore Theme
 * Gerencia o menu toggle mobile, atualiza links dinâmicos e controla visibilidade sticky
 */
(function () {
	'use strict';

	var headerShell = null;
	var lastScrollTop = 0;
	var scrollThreshold = 100; // Distância mínima para começar a esconder/mostrar
	var isScrollingDown = false;
	var headerHeight = 0; // Altura do header (calculada uma vez)

	/**
	 * Calcula a altura do header uma vez
	 */
	function calculateHeaderHeight() {
		if (!headerShell) {
			return 0;
		}

		// Remove transform temporariamente para medir altura real
		var originalTransform = headerShell.style.transform;
		var originalVisibility = headerShell.style.visibility;
		
		headerShell.style.transform = 'translateY(0)';
		headerShell.style.visibility = 'visible';
		headerShell.style.position = 'absolute';
		headerShell.style.top = '0';
		
		// Força recalculo do layout
		void headerShell.offsetHeight;
		
		// Mede a altura usando getBoundingClientRect para maior precisão
		var rect = headerShell.getBoundingClientRect();
		var height = Math.round(rect.height);
		
		// Restaura estilos
		headerShell.style.transform = originalTransform;
		headerShell.style.visibility = originalVisibility;
		headerShell.style.position = 'fixed';
		
		// Limita altura máxima a 200px para evitar espaços excessivos
		return Math.min(height, 200);
	}

	/**
	 * Ajusta o padding-top do body para compensar o header fixo
	 */
	function adjustBodyPadding() {
		if (!headerShell) {
			return;
		}

		// Calcula altura apenas se ainda não foi calculada ou se a janela foi redimensionada
		if (headerHeight === 0) {
			headerHeight = calculateHeaderHeight();
		}
		
		// Aplica padding-top apenas no wp-site-blocks (não no body para evitar duplicação)
		var siteBlocks = document.querySelector('.wp-site-blocks');
		if (siteBlocks) {
			// Remove padding anterior do body se existir
			if (document.body.style.paddingTop) {
				document.body.style.paddingTop = '';
			}
			// Aplica apenas se a altura for maior que 0
			if (headerHeight > 0) {
				siteBlocks.style.paddingTop = headerHeight + 'px';
			} else {
				siteBlocks.style.paddingTop = '';
			}
		} else {
			// Fallback: aplica no body apenas se wp-site-blocks não existir
			if (headerHeight > 0) {
				document.body.style.paddingTop = headerHeight + 'px';
			} else {
				document.body.style.paddingTop = '';
			}
		}
	}

	/**
	 * Atualiza links da conta com URLs dinâmicas do WooCommerce
	 * Corrige problema de endpoints hardcoded em templates FSE
	 */
	function updateAccountLinks() {
		if (typeof gstoreAccountUrls === 'undefined') {
			return;
		}

		// Mapeamento de links hardcoded para URLs dinâmicas
		var linkMappings = {
			'/minha-conta': gstoreAccountUrls.myAccount,
			'/meus-pedidos': gstoreAccountUrls.orders
		};

		// Atualiza todos os links que correspondem aos padrões
		Object.keys(linkMappings).forEach(function(oldPath) {
			var newUrl = linkMappings[oldPath];
			if (!newUrl) return;

			// Busca links com href exato ou terminando com o path
			var links = document.querySelectorAll('a[href="' + oldPath + '"], a[href$="' + oldPath + '"]');
			links.forEach(function(link) {
				var currentHref = link.getAttribute('href');
				// Só atualiza se for exatamente o path ou terminar com ele
				if (currentHref === oldPath || currentHref.endsWith(oldPath)) {
					link.setAttribute('href', newUrl);
				}
			});
		});
	}

	/**
	 * Gerencia o menu drawer para mobile
	 */
	function setupMenuToggle() {
		var menuToggle = document.querySelector('.Gstore-header__menu-toggle');
		var drawer = document.querySelector('.Gstore-mobile-drawer');
		var drawerOverlay = document.querySelector('.Gstore-mobile-drawer__overlay');
		var drawerClose = document.querySelector('.Gstore-mobile-drawer__close');
		var drawerContent = document.querySelector('.Gstore-mobile-drawer__content');
		
		if (!menuToggle || !drawer) {
			return;
		}

		// Salva a posição de scroll atual antes de abrir
		var scrollPosition = 0;

		function setMenuState(isOpen) {
			if (isOpen) {
				drawer.classList.add('is-open');
				menuToggle.classList.add('is-active');
				menuToggle.setAttribute('aria-expanded', 'true');
				menuToggle.setAttribute('aria-label', 'Fechar menu');
				
				// Prevenir scroll do body
				scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
				document.body.classList.add('drawer-open');
				document.body.style.top = '-' + scrollPosition + 'px';
			} else {
				drawer.classList.remove('is-open');
				menuToggle.classList.remove('is-active');
				menuToggle.setAttribute('aria-expanded', 'false');
				menuToggle.setAttribute('aria-label', 'Abrir menu');
				
				// Restaurar scroll do body
				document.body.classList.remove('drawer-open');
				document.body.style.top = '';
				window.scrollTo(0, scrollPosition);
			}
		}

		function closeMenu() {
			setMenuState(false);
		}

		function openMenu() {
			setMenuState(true);
		}

		// Toggle menu ao clicar no botão hamburger
		menuToggle.addEventListener('click', function (e) {
			e.stopPropagation();
			var isOpen = drawer.classList.contains('is-open');
			if (isOpen) {
				closeMenu();
			} else {
				openMenu();
			}
		});

		// Fechar menu ao clicar no botão de fechar
		if (drawerClose) {
			drawerClose.addEventListener('click', function (e) {
				e.stopPropagation();
				closeMenu();
			});
		}

		// Fechar menu ao clicar no overlay
		if (drawerOverlay) {
			drawerOverlay.addEventListener('click', function (e) {
				e.stopPropagation();
				closeMenu();
			});
		}

		// Fechar menu ao clicar em um link dentro do drawer
		// Usa event delegation para funcionar com links adicionados dinamicamente
		if (drawerContent) {
			drawerContent.addEventListener('click', function(e) {
				var link = e.target.closest('a');
				if (link && !link.hasAttribute('target') || link.getAttribute('target') !== '_blank') {
					// Pequeno delay para permitir navegação antes de fechar
					setTimeout(closeMenu, 100);
				}
			});
		}

		// Fechar menu ao pressionar ESC
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
				closeMenu();
			}
		});

		// Fechar menu ao redimensionar para desktop
		window.addEventListener('resize', function () {
			if (window.innerWidth > 767 && drawer.classList.contains('is-open')) {
				closeMenu();
			}
		});
	}

	/**
	 * Controla a visibilidade do header baseado no scroll
	 */
	function setupStickyHeader() {
		headerShell = document.querySelector('.Gstore-header-shell');
		
		if (!headerShell) {
			return;
		}

		// Ajusta o padding do body
		adjustBodyPadding();

		// Recalcula altura e padding quando a janela é redimensionada
		window.addEventListener('resize', function() {
			headerHeight = 0; // Força recálculo da altura
			adjustBodyPadding();
		});

		// Inicializa como visível
		headerShell.classList.add('is-visible');
		headerShell.classList.remove('is-hidden');

		function handleScroll() {
			var currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
			
			// Se estiver no topo, sempre mostra
			if (currentScrollTop < scrollThreshold) {
				headerShell.classList.add('is-visible');
				headerShell.classList.remove('is-hidden');
				lastScrollTop = currentScrollTop;
				return;
			}

			// Detecta direção do scroll
			if (currentScrollTop > lastScrollTop) {
				// Rolando para baixo
				if (!isScrollingDown) {
					isScrollingDown = true;
					headerShell.classList.remove('is-visible');
					headerShell.classList.add('is-hidden');
					// Recalcula padding após esconder
					setTimeout(adjustBodyPadding, 300);
				}
			} else if (currentScrollTop < lastScrollTop) {
				// Rolando para cima
				if (isScrollingDown) {
					isScrollingDown = false;
					headerShell.classList.add('is-visible');
					headerShell.classList.remove('is-hidden');
					// Recalcula padding após mostrar
					setTimeout(adjustBodyPadding, 300);
				}
			}

			lastScrollTop = currentScrollTop;
		}

		// Usa throttle para melhor performance
		function throttle(func, wait) {
			var timeout;
			return function() {
				var context = this;
				var args = arguments;
				if (!timeout) {
					timeout = setTimeout(function() {
						timeout = null;
						func.apply(context, args);
					}, wait);
				}
			};
		}

		// Adiciona listener de scroll com throttle
		window.addEventListener('scroll', throttle(handleScroll, 10), { passive: true });
	}

	/**
	 * Mostra o header quando um produto é adicionado ao carrinho
	 */
	function setupCartHeaderShow() {
		if (!headerShell) {
			return;
		}

		// Listener para evento added_to_cart do WooCommerce
		if (typeof jQuery !== 'undefined') {
			jQuery(document.body).on('added_to_cart', function() {
				// Mostra o header
				headerShell.classList.add('is-visible');
				headerShell.classList.remove('is-hidden');
				
				// Atualiza lastScrollTop para evitar que o header suma imediatamente
				lastScrollTop = window.pageYOffset || document.documentElement.scrollTop;
				isScrollingDown = false;
				
				// Recalcula padding após mostrar
				setTimeout(adjustBodyPadding, 300);
			});
		}

		// Também escuta eventos nativos caso jQuery não esteja disponível
		document.addEventListener('added_to_cart', function() {
			headerShell.classList.add('is-visible');
			headerShell.classList.remove('is-hidden');
			lastScrollTop = window.pageYOffset || document.documentElement.scrollTop;
			isScrollingDown = false;
			
			// Recalcula padding após mostrar
			setTimeout(adjustBodyPadding, 300);
		});
	}

	/**
	 * Inicializa
	 */
	function init() {
		setupMenuToggle();
		updateAccountLinks();
		setupStickyHeader();
		setupCartHeaderShow();
	}

	// Inicializar quando o DOM estiver pronto
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	// Criar drawer dinamicamente se não existir (fallback para quando WordPress não renderiza o template)
	setTimeout(function() {
		var drawerEl = document.querySelector('.Gstore-mobile-drawer');
		if (!drawerEl && window.innerWidth <= 767) {
			createDrawerIfMissing();
		}
	}, 1000);
	
	/**
	 * Cria o drawer dinamicamente se ele não existir no DOM
	 */
	function createDrawerIfMissing() {
		var drawer = document.querySelector('.Gstore-mobile-drawer');
		if (drawer) {
			return; // Já existe
		}
		
		// Criar drawer dinamicamente
		var drawerHTML = '<div class="Gstore-mobile-drawer">' +
			'<div class="Gstore-mobile-drawer__overlay"></div>' +
			'<div class="Gstore-mobile-drawer__content">' +
				'<div class="Gstore-mobile-drawer__header">' +
					'<button class="Gstore-mobile-drawer__close" aria-label="Fechar menu">' +
						'<svg class="Gstore-icon" viewBox="0 0 24 24" fill="currentColor">' +
							'<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>' +
						'</svg>' +
					'</button>' +
				'</div>' +
				'<div class="Gstore-mobile-drawer__search"></div>' +
				'<div class="Gstore-mobile-drawer__nav"></div>' +
				'<div class="Gstore-mobile-drawer__footer">' +
					'<a href="/atendimento" class="Gstore-nav__mobile-link">' +
						'<svg class="Gstore-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>' +
						'<span>Atendimento</span>' +
					'</a>' +
				'</div>' +
			'</div>' +
		'</div>';
		
		document.body.insertAdjacentHTML('beforeend', drawerHTML);
		
		// Copiar navegação existente para o drawer
		var existingNav = document.querySelector('.Gstore-nav .wp-block-navigation__container') ||
			document.querySelector('.Gstore-nav__menu .wp-block-navigation__container');
		var drawerNav = document.querySelector('.Gstore-mobile-drawer__nav');
		
		if (existingNav && drawerNav) {
			var navClone = existingNav.cloneNode(true);
			navClone.classList.add('Gstore-nav--mobile');
			drawerNav.appendChild(navClone);
		}
		
		// Copiar pesquisa se existir
		var existingSearch = document.querySelector('.Gstore-header__search');
		var drawerSearch = document.querySelector('.Gstore-mobile-drawer__search');
		if (existingSearch && drawerSearch) {
			var searchClone = existingSearch.cloneNode(true);
			drawerSearch.appendChild(searchClone);
		}
		
		// Re-executar setupMenuToggle para conectar o drawer criado
		setupMenuToggle();
	}
})();

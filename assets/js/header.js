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
	 * Gerencia o menu toggle para mobile
	 */
	function setupMenuToggle() {
		var menuToggle = document.querySelector('.Gstore-header__menu-toggle');
		var navContainer = document.querySelector('.Gstore-nav .wp-block-navigation__container') ||
			document.querySelector('.Gstore-nav-shell .wp-block-navigation__container');
		var navInner = document.querySelector('.Gstore-nav__inner');
		
		if (!menuToggle || !navContainer) {
			return;
		}

		function setMenuState(isOpen) {
			navContainer.classList.toggle('is-open', isOpen);
			menuToggle.classList.toggle('is-active', isOpen);
			menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			menuToggle.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
			// Adiciona classe no container interno para controlar visibilidade da pesquisa
			if (navInner) {
				navInner.classList.toggle('is-open', isOpen);
			}
		}

		function closeMenu() {
			setMenuState(false);
		}

		// Toggle menu ao clicar no botão
		menuToggle.addEventListener('click', function (e) {
			e.stopPropagation();
			var isOpen = navContainer.classList.contains('is-open');
			setMenuState(!isOpen);
		});

		// Fechar menu ao clicar fora
		document.addEventListener('click', function (e) {
			var clickInsideNav = navContainer.contains(e.target);
			var clickOnToggle = e.target.closest('.Gstore-header__menu-toggle');

			if (!clickInsideNav && !clickOnToggle) {
				closeMenu();
			}
		});

		// Fechar menu ao clicar em um link
		navContainer.querySelectorAll('a').forEach(function(link) {
			link.addEventListener('click', closeMenu);
		});

		// Fechar menu ao pressionar ESC
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && navContainer.classList.contains('is-open')) {
				closeMenu();
			}
		});

		// Fechar menu ao redimensionar para desktop
		window.addEventListener('resize', function () {
			if (window.innerWidth > 767 && navContainer.classList.contains('is-open')) {
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
})();

/**
 * Header JavaScript - Gstore Theme
 * Gerencia o menu toggle mobile e atualiza links dinâmicos
 */
(function () {
	'use strict';

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
		
		if (!menuToggle || !navContainer) {
			return;
		}

		function setMenuState(isOpen) {
			navContainer.classList.toggle('is-open', isOpen);
			menuToggle.classList.toggle('is-active', isOpen);
			menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			menuToggle.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
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
	 * Inicializa
	 */
	function init() {
		setupMenuToggle();
		updateAccountLinks();
	}

	// Inicializar quando o DOM estiver pronto
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

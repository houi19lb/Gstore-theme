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
	var headerHeight = 0;
	var paddingAdjustFrame = null;
	var headerResizeObserver = null;

	/**
	 * Resolve URLs de conta com fallback para instalações em subdiretório.
	 */
	function getAccountUrl(type) {
		if (type === 'my-account') {
			if (typeof gstoreAccountUrls !== 'undefined') {
				return gstoreAccountUrls.myAccount || gstoreAccountUrls.minhaContaUrl || '/minha-conta';
			}
			return '/minha-conta';
		}

		if (type === 'atendimento') {
			if (typeof gstoreAccountUrls !== 'undefined') {
				return gstoreAccountUrls.atendimentoUrl || '/atendimento';
			}
			return '/atendimento';
		}

		return '/';
	}

	/**
	 * Cria link padrão do footer mobile.
	 */
	function createDrawerFooterLink(type, href) {
		var link = document.createElement('a');
		link.className = 'Gstore-nav__mobile-link Gstore-mobile-drawer__footer-link';
		link.setAttribute('data-gstore-mobile-link', type);
		link.setAttribute('href', href);

		if (type === 'my-account') {
			link.innerHTML = '<svg class="Gstore-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span>Minha conta</span>';
		} else {
			link.innerHTML = '<svg class="Gstore-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg><span>Atendimento</span>';
		}

		return link;
	}

	/**
	 * Garante que o footer do drawer tenha os 2 links padrões.
	 * Evita divergência quando o template antigo fica em cache.
	 */
	function ensureDrawerFooterLinks() {
		var footer = document.querySelector('.Gstore-mobile-drawer__footer');
		if (!footer) {
			return;
		}

		var myAccountLink = createDrawerFooterLink('my-account', getAccountUrl('my-account'));
		var atendimentoLink = createDrawerFooterLink('atendimento', getAccountUrl('atendimento'));

		footer.innerHTML = '';
		footer.appendChild(myAccountLink);
		footer.appendChild(atendimentoLink);
	}

	/**
	 * Garante logo no topo do drawer mobile, inclusive quando o drawer e criado via fallback.
	 */
	function ensureDrawerLogo() {
		var drawerHeader = document.querySelector('.Gstore-mobile-drawer__header');
		if (!drawerHeader) {
			return;
		}

		var existingLogo = drawerHeader.querySelector('.Gstore-mobile-drawer__logo');
		if (existingLogo && existingLogo.querySelector('img')) {
			return;
		}

		var source = document.querySelector('.Gstore-header__logo');
		var sourceLink = source
			? (source.tagName && source.tagName.toLowerCase() === 'a' ? source : source.querySelector('a'))
			: null;
		if (existingLogo && (!sourceLink || !sourceLink.querySelector('img'))) {
			return;
		}

		var logoLink = sourceLink ? sourceLink.cloneNode(true) : document.createElement('a');

		if (!sourceLink) {
			logoLink.href = '/';
			logoLink.innerHTML = '<span>' + (document.title || 'Home').split('|')[0].trim() + '</span>';
		}

		logoLink.className = 'Gstore-mobile-drawer__logo';
		logoLink.setAttribute('rel', 'home');
		if (!logoLink.getAttribute('aria-label')) {
			logoLink.setAttribute('aria-label', logoLink.textContent.trim() || 'Home');
		}

		if (existingLogo) {
			existingLogo.replaceWith(logoLink);
		} else {
			drawerHeader.insertBefore(logoLink, drawerHeader.firstChild);
		}
	}

	/**
	 * Calcula a altura atual do header.
	 */
	function calculateHeaderHeight() {
		if (!headerShell) {
			return 0;
		}

		var rect = headerShell.getBoundingClientRect();
		var height = rect.height || headerShell.offsetHeight || 0;

		// Limita altura máxima a 200px para evitar espaços excessivos
		return Math.min(height, 200);
	}

	/**
	 * Agenda o ajuste para depois do layout atual estabilizar.
	 */
	function scheduleBodyPaddingAdjust() {
		if (paddingAdjustFrame && window.cancelAnimationFrame) {
			window.cancelAnimationFrame(paddingAdjustFrame);
		}

		var run = function() {
			paddingAdjustFrame = null;
			adjustBodyPadding();
		};

		if (window.requestAnimationFrame) {
			paddingAdjustFrame = window.requestAnimationFrame(run);
		} else {
			window.setTimeout(run, 0);
		}
	}

	/**
	 * Ajusta o padding-top do body para compensar o header fixo
	 */
	function adjustBodyPadding() {
		if (!headerShell) {
			return;
		}

		headerHeight = calculateHeaderHeight();
		
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

		// Mapeamento de links hardcoded para URLs dinâmicas (respeitam subdiretório do WP)
		var linkMappings = {
			'/minha-conta': gstoreAccountUrls.myAccount || gstoreAccountUrls.minhaContaUrl,
			'/minha-conta/': gstoreAccountUrls.myAccount || gstoreAccountUrls.minhaContaUrl,
			'/meus-pedidos': gstoreAccountUrls.orders,
			'/meus-pedidos/': gstoreAccountUrls.orders,
			'/atendimento': gstoreAccountUrls.atendimentoUrl,
			'/atendimento/': gstoreAccountUrls.atendimentoUrl,
			'/favoritos/': gstoreAccountUrls.favoritosUrl
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
		ensureDrawerLogo();

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
				// Só fecha se clicou em um link e ele NÃO abre em nova aba
				if (link && (!link.hasAttribute('target') || link.getAttribute('target') !== '_blank')) {
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

		// Ajusta o padding inicial e revisa após estilos/fontes/blocks estabilizarem.
		adjustBodyPadding();
		[0, 50, 250, 1000].forEach(function(delay) {
			window.setTimeout(scheduleBodyPaddingAdjust, delay);
		});

		// Recalcula altura e padding quando a janela é redimensionada
		window.addEventListener('resize', function() {
			scheduleBodyPaddingAdjust();
		});

		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(scheduleBodyPaddingAdjust).catch(function() {});
		}

		if (window.ResizeObserver) {
			headerResizeObserver = new ResizeObserver(function() {
				scheduleBodyPaddingAdjust();
			});
			headerResizeObserver.observe(headerShell);
		}

		window.addEventListener('load', scheduleBodyPaddingAdjust, { once: true });

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
					setTimeout(scheduleBodyPaddingAdjust, 300);
				}
			} else if (currentScrollTop < lastScrollTop) {
				// Rolando para cima
				if (isScrollingDown) {
					isScrollingDown = false;
					headerShell.classList.add('is-visible');
					headerShell.classList.remove('is-hidden');
					// Recalcula padding após mostrar
					setTimeout(scheduleBodyPaddingAdjust, 300);
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
				setTimeout(scheduleBodyPaddingAdjust, 300);
			});
		}

		// Também escuta eventos nativos caso jQuery não esteja disponível
		document.addEventListener('added_to_cart', function() {
			headerShell.classList.add('is-visible');
			headerShell.classList.remove('is-hidden');
			lastScrollTop = window.pageYOffset || document.documentElement.scrollTop;
			isScrollingDown = false;
			
			// Recalcula padding após mostrar
			setTimeout(scheduleBodyPaddingAdjust, 300);
		});
	}

	/**
	 * Inicializa
	 */
	function init() {
		setupMenuToggle();
		updateAccountLinks();
		ensureDrawerLogo();
		ensureDrawerFooterLinks();
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
					'<a href="' + getAccountUrl('my-account') + '" class="Gstore-nav__mobile-link Gstore-mobile-drawer__footer-link">' +
						'<svg class="Gstore-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>' +
						'<span>Minha conta</span>' +
					'</a>' +
					'<a href="' + getAccountUrl('atendimento') + '" class="Gstore-nav__mobile-link Gstore-mobile-drawer__footer-link">' +
						'<svg class="Gstore-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>' +
						'<span>Atendimento</span>' +
					'</a>' +
				'</div>' +
			'</div>' +
		'</div>';
		
		document.body.insertAdjacentHTML('beforeend', drawerHTML);
		
		// Copiar navegação existente para o drawer
		var fallbackContainer = document.getElementById('gstore-mobile-menu-fallback');
		var fallbackNav = fallbackContainer
			? fallbackContainer.querySelector('.wp-block-navigation__container')
			: null;
		var mobileNav = document.querySelector('.Gstore-nav--mobile .wp-block-navigation__container');
		var desktopNav = document.querySelector('.Gstore-nav .wp-block-navigation__container');
		var alternativeNav = document.querySelector('.Gstore-nav__menu .wp-block-navigation__container');
		var existingNav = fallbackNav || mobileNav || desktopNav || alternativeNav;
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
		
		// Normaliza links do footer e conecta eventos do drawer criado
		ensureDrawerFooterLinks();
		setupMenuToggle();
	}
})();

/**
 * Carrossel móvel para os loops de produtos da Home.
 *
 * @package Gstore
 */

(function () {
	'use strict';

	const GRID_SELECTOR = '.Gstore-home-products .Gstore-products-grid';
	const LIST_SELECTOR = 'ul.products, ul.wc-block-product-template, .wc-block-product-template';
	const SLIDE_SELECTOR = 'li.product, .wc-block-product';
	const MOBILE_BREAKPOINT = 768;

	function getSlides(list) {
		return list ? Array.from(list.querySelectorAll(SLIDE_SELECTOR)) : [];
	}

	function getSectionLabel(grid) {
		const section = grid.closest('.Gstore-home-section');
		const heading = section ? section.querySelector('.Gstore-home-section__header .wp-block-heading') : null;
		const title = heading ? heading.textContent.trim() : '';
		return title ? 'Carrossel da seção ' + title : 'Carrossel de produtos';
	}

	function createButton(direction) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'Gstore-products-carousel__button Gstore-products-carousel__button--' + direction;
		button.setAttribute(
			'aria-label',
			direction === 'prev' ? 'Ver produtos anteriores' : 'Ver próximos produtos'
		);

		const icon = document.createElement('span');
		icon.className = 'fa-solid ' + (direction === 'prev' ? 'fa-chevron-left' : 'fa-chevron-right');
		icon.setAttribute('aria-hidden', 'true');
		button.appendChild(icon);

		return button;
	}

	function extractList(grid) {
		const viewport = grid.querySelector('.Gstore-products-carousel__viewport');
		if (viewport) {
			const listInside = viewport.querySelector(LIST_SELECTOR);
			if (listInside) {
				return { list: listInside, viewport: viewport };
			}
		}

		const list = grid.querySelector(LIST_SELECTOR);
		if (!list) {
			return { list: null, viewport: null };
		}

		const newViewport = document.createElement('div');
		newViewport.className = 'Gstore-products-carousel__viewport';
		list.parentNode.insertBefore(newViewport, list);
		newViewport.appendChild(list);

		return { list: list, viewport: newViewport };
	}

	function getGap(list) {
		const style = window.getComputedStyle(list);
		const gapValue = parseFloat(style.columnGap || style.gap || '16');
		return Number.isNaN(gapValue) ? 16 : gapValue;
	}

	function findViewMoreButton(grid) {
		const section = grid.closest('.Gstore-home-section');
		if (!section) {
			return null;
		}
		
		// Procurar todos os botões na seção
		const allButtonsContainers = Array.from(section.querySelectorAll('.wp-block-buttons'));
		
		// Encontrar o botão que vem após o grid (não os filtros do header)
		for (let i = 0; i < allButtonsContainers.length; i++) {
			const buttonsContainer = allButtonsContainers[i];
			
			// Verificar se é o botão "Ver mais" (não os filtros do header)
			const isInHeader = buttonsContainer.closest('.Gstore-home-section__header');
			if (isInHeader) {
				continue;
			}
			
			// Verificar se o grid vem antes deste botão
			const gridPosition = Array.from(section.children).indexOf(grid);
			const buttonPosition = Array.from(section.children).indexOf(buttonsContainer);
			
			if (buttonPosition > gridPosition) {
				const buttonLink = buttonsContainer.querySelector('.wp-block-button.is-style-primary .wp-block-button__link');
				if (buttonLink) {
					return { container: buttonsContainer, link: buttonLink };
				}
			}
		}
		
		return null;
	}

	function createViewMoreSlide(buttonLink) {
		// Verificar se já existe um slide do botão
		if (buttonLink.closest('[data-carousel-view-more="true"]')) {
			return null;
		}
		
		const slide = document.createElement('li');
		slide.className = 'product Gstore-view-more-slide';
		slide.setAttribute('data-carousel-view-more', 'true');
		
		const slideInner = document.createElement('div');
		slideInner.className = 'Gstore-product-card__inner Gstore-view-more-slide__inner';
		
		const buttonWrapper = document.createElement('div');
		buttonWrapper.className = 'Gstore-view-more-slide__wrapper';
		
		// Clonar o botão original
		const clonedButton = buttonLink.cloneNode(true);
		clonedButton.classList.add('Gstore-view-more-slide__button');
		
		buttonWrapper.appendChild(clonedButton);
		slideInner.appendChild(buttonWrapper);
		slide.appendChild(slideInner);
		
		return slide;
	}

	function initCarousel(grid) {
		if (grid.getAttribute('data-carousel-ready') === 'true') {
			return;
		}

		const { list, viewport } = extractList(grid);
		if (!list || !viewport) {
			return;
		}

		const slides = getSlides(list);
		if (slides.length < 2) {
			return;
		}

		const sectionLabel = getSectionLabel(grid);
		viewport.setAttribute('role', 'region');
		viewport.setAttribute('aria-label', sectionLabel);
		viewport.setAttribute('tabindex', '0');

		// Encontrar e processar botão "Ver mais" no mobile
		const viewMoreButton = findViewMoreButton(grid);
		
		if (viewMoreButton && window.innerWidth <= MOBILE_BREAKPOINT) {
			const existingSlide = list.querySelector('[data-carousel-view-more="true"]');
			if (!existingSlide) {
				const viewMoreSlide = createViewMoreSlide(viewMoreButton.link);
				if (viewMoreSlide) {
					list.appendChild(viewMoreSlide);
				}
			}
			
			// Marcar o container original para esconder no mobile
			viewMoreButton.container.classList.add('Gstore-view-more-button--mobile-hidden');
		}

		const prevButton = createButton('prev');
		const nextButton = createButton('next');

		// Criar container para os controles de navegação
		const controlsContainer = document.createElement('div');
		controlsContainer.className = 'Gstore-products-carousel__controls';
		controlsContainer.setAttribute('aria-label', 'Controles de navegação do carrossel');
		controlsContainer.appendChild(prevButton);
		controlsContainer.appendChild(nextButton);

		grid.appendChild(controlsContainer);
		grid.classList.add('Gstore-products-grid--has-carousel');
		grid.setAttribute('data-carousel-ready', 'true');
		
		// Função para atualizar visibilidade do botão ao redimensionar
		function updateViewMoreButtonVisibility() {
			if (!viewMoreButton) {
				return;
			}
			
			const isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
			const existingSlide = list.querySelector('[data-carousel-view-more="true"]');
			
			if (isMobile && !existingSlide) {
				// Mobile: adicionar slide e esconder botão original
				const newSlide = createViewMoreSlide(viewMoreButton.link);
				list.appendChild(newSlide);
				viewMoreButton.container.classList.add('Gstore-view-more-button--mobile-hidden');
			} else if (!isMobile && existingSlide) {
				// Desktop: remover slide e mostrar botão original
				existingSlide.remove();
				viewMoreButton.container.classList.remove('Gstore-view-more-button--mobile-hidden');
			}
		}
		
		// Atualizar ao redimensionar
		let resizeTimeout;
		window.addEventListener('resize', function() {
			clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(updateViewMoreButtonVisibility, 250);
		});

		function scrollByStep(direction) {
			if (window.innerWidth > MOBILE_BREAKPOINT) {
				return;
			}

			const referenceSlide = viewport.querySelector(SLIDE_SELECTOR);
			const slideWidth = referenceSlide ? referenceSlide.getBoundingClientRect().width : viewport.clientWidth;
			const scrollAmount = direction * (slideWidth + getGap(list));
			viewport.scrollBy({
				left: scrollAmount,
				behavior: 'smooth',
			});
		}

		function updateButtonsState() {
			const maxScrollLeft = viewport.scrollWidth - viewport.clientWidth - 1;
			prevButton.disabled = viewport.scrollLeft <= 0;
			nextButton.disabled = viewport.scrollLeft >= maxScrollLeft;
		}

		prevButton.addEventListener('click', function () {
			scrollByStep(-1);
		});

		nextButton.addEventListener('click', function () {
			scrollByStep(1);
		});

		viewport.addEventListener('scroll', updateButtonsState);
		window.addEventListener('resize', updateButtonsState);
		updateButtonsState();
	}

	function init() {
		const grids = document.querySelectorAll(GRID_SELECTOR);
		if (!grids.length) {
			return;
		}

		grids.forEach(initCarousel);
		
		// Atualizar visibilidade inicial dos botões "Ver mais"
		updateAllViewMoreButtons();
	}
	
	function updateAllViewMoreButtons() {
		const grids = document.querySelectorAll(GRID_SELECTOR);
		grids.forEach(function(grid) {
			if (grid.getAttribute('data-carousel-ready') !== 'true') {
				return;
			}
			
			const viewMoreButton = findViewMoreButton(grid);
			if (!viewMoreButton) {
				return;
			}
			
			const { list } = extractList(grid);
			if (!list) {
				return;
			}
			
			const isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
			const existingSlide = list.querySelector('[data-carousel-view-more="true"]');
			
			if (isMobile) {
				// Mobile: adicionar slide se não existir e esconder botão original
				if (!existingSlide) {
					const newSlide = createViewMoreSlide(viewMoreButton.link);
					list.appendChild(newSlide);
				}
				viewMoreButton.container.classList.add('Gstore-view-more-button--mobile-hidden');
			} else {
				// Desktop: remover slide se existir e mostrar botão original
				if (existingSlide) {
					existingSlide.remove();
				}
				viewMoreButton.container.classList.remove('Gstore-view-more-button--mobile-hidden');
			}
		});
	}

	function runInit() {
		init();
		
		// Executar novamente após um delay para capturar conteúdo carregado dinamicamente
		setTimeout(function() {
			updateAllViewMoreButtons();
		}, 500);
		
		// Executar após um delay maior para garantir que produtos carregados via AJAX sejam capturados
		setTimeout(function() {
			const grids = document.querySelectorAll(GRID_SELECTOR);
			grids.forEach(function(grid) {
				if (grid.getAttribute('data-carousel-ready') !== 'true') {
					initCarousel(grid);
				}
			});
			updateAllViewMoreButtons();
		}, 1000);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', runInit);
	} else {
		runInit();
	}
	
	// Observar mudanças no DOM para capturar conteúdo carregado dinamicamente
	if (window.MutationObserver) {
		const observer = new MutationObserver(function(mutations) {
			let shouldUpdate = false;
			mutations.forEach(function(mutation) {
				if (mutation.addedNodes.length > 0) {
					mutation.addedNodes.forEach(function(node) {
						if (node.nodeType === 1) { // Element node
							if (node.classList && (
								node.classList.contains('Gstore-products-grid') ||
								node.querySelector && node.querySelector('.Gstore-products-grid')
							)) {
								shouldUpdate = true;
							}
						}
					});
				}
			});
			
			if (shouldUpdate) {
				setTimeout(function() {
					const grids = document.querySelectorAll(GRID_SELECTOR);
					grids.forEach(function(grid) {
						if (grid.getAttribute('data-carousel-ready') !== 'true') {
							initCarousel(grid);
						}
					});
					updateAllViewMoreButtons();
				}, 300);
			}
		});
		
		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
	}
	
	// Atualizar botões ao redimensionar
	let globalResizeTimeout;
	window.addEventListener('resize', function() {
		clearTimeout(globalResizeTimeout);
		globalResizeTimeout = setTimeout(updateAllViewMoreButtons, 250);
	});
})();



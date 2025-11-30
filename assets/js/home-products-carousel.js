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
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();



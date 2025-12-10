'use strict';

(function () {
	const SLIDER_SELECTOR = '[data-gstore-benefits-slider]';
	const TRACK_SELECTOR = '[data-gstore-benefits-track]';
	const SLIDE_SELECTOR = '[data-gstore-benefits-slide]';
	const DOT_SELECTOR = '[data-gstore-benefits-dot]';
	const PREV_SELECTOR = '[data-gstore-benefits-prev]';
	const NEXT_SELECTOR = '[data-gstore-benefits-next]';
	const AUTOPLAY_DELAY = 4000;

	function initSlider(slider) {
		const track = slider.querySelector(TRACK_SELECTOR);
		const slides = Array.from(slider.querySelectorAll(SLIDE_SELECTOR));
		const dots = Array.from(slider.querySelectorAll(DOT_SELECTOR));
		const prevButton = slider.querySelector(PREV_SELECTOR);
		const nextButton = slider.querySelector(NEXT_SELECTOR);

		if (!track || slides.length === 0) {
			return;
		}

		let currentIndex = 0;
		const prefersReducedMotion =
			window.matchMedia &&
			window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		const canAutoplay = !prefersReducedMotion && slides.length > 1;
		let autoplayId = null;
		let touchStartX = 0;
		let touchEndX = 0;
		let isDragging = false;
		let slideWidth = 0;

		function getSliderInnerWidth() {
			const computedStyles = window.getComputedStyle(slider);
			const paddingLeft = parseFloat(computedStyles.paddingLeft) || 0;
			const paddingRight = parseFloat(computedStyles.paddingRight) || 0;
			const innerWidth = slider.clientWidth - paddingLeft - paddingRight;
			return innerWidth > 0 ? innerWidth : slider.clientWidth;
		}

		// Garantir que o track e os slides tenham a largura correta
		function setSlideWidths() {
			// Usar requestAnimationFrame para garantir que o layout esteja pronto
			requestAnimationFrame(function () {
				const innerWidth = getSliderInnerWidth();
				if (innerWidth > 0) {
					slideWidth = innerWidth;
					track.style.width = innerWidth * slides.length + 'px';
					slides.forEach(function (slide) {
						const widthValue = innerWidth + 'px';
						slide.style.width = widthValue;
						slide.style.flexBasis = widthValue;
						slide.style.minWidth = widthValue;
						slide.style.maxWidth = widthValue;
					});
					goTo(currentIndex);
				}
			});
		}
		// Executar imediatamente e também após um pequeno delay para garantir
		setSlideWidths();
		setTimeout(setSlideWidths, 120);

		function updateDots(index) {
			if (!dots.length) {
				return;
			}

			dots.forEach(function (dot, dotIndex) {
				const isActive = dotIndex === index;
				dot.classList.toggle('is-active', isActive);
				dot.setAttribute('aria-selected', String(isActive));
			});
		}

		function goTo(index) {
			const total = slides.length;
			currentIndex = (index + total) % total;
			const currentWidth = slideWidth || getSliderInnerWidth();
			const offset = currentIndex * currentWidth;
			track.style.transform = 'translate3d(-' + offset + 'px, 0, 0)';
			updateDots(currentIndex);
		}

		function nextSlide() {
			goTo(currentIndex + 1);
		}

		function prevSlide() {
			goTo(currentIndex - 1);
		}

		function stopAutoplay() {
			if (autoplayId) {
				window.clearInterval(autoplayId);
				autoplayId = null;
			}
		}

		function startAutoplay() {
			if (!canAutoplay) {
				return;
			}

			stopAutoplay();
			autoplayId = window.setInterval(nextSlide, AUTOPLAY_DELAY);
		}

		if (prevButton) {
			prevButton.addEventListener('click', function () {
				prevSlide();
				if (canAutoplay) {
					stopAutoplay();
					startAutoplay();
				}
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				nextSlide();
				if (canAutoplay) {
					stopAutoplay();
					startAutoplay();
				}
			});
		}

		if (dots.length) {
			dots.forEach(function (dot, index) {
				dot.addEventListener('click', function () {
					goTo(index);
					if (canAutoplay) {
						stopAutoplay();
						startAutoplay();
					}
				});
			});
		}

		// Suporte a swipe/touch para navegação
		function handleTouchStart(e) {
			touchStartX = e.touches[0].clientX;
			isDragging = true;
			stopAutoplay();
		}

		function handleTouchMove(e) {
			if (!isDragging) return;
			e.preventDefault();
		}

		function handleTouchEnd(e) {
			if (!isDragging) return;
			touchEndX = e.changedTouches[0].clientX;
			const swipeThreshold = 50;
			const diff = touchStartX - touchEndX;

			if (Math.abs(diff) > swipeThreshold) {
				if (diff > 0) {
					// Swipe para a esquerda - próximo slide
					nextSlide();
				} else {
					// Swipe para a direita - slide anterior
					prevSlide();
				}
			}

			isDragging = false;
			if (canAutoplay) {
				setTimeout(startAutoplay, 1000);
			}
		}

		slider.addEventListener('touchstart', handleTouchStart, { passive: false });
		slider.addEventListener('touchmove', handleTouchMove, { passive: false });
		slider.addEventListener('touchend', handleTouchEnd);

		if (canAutoplay) {
			slider.addEventListener('mouseenter', stopAutoplay);
			slider.addEventListener('mouseleave', startAutoplay);
			slider.addEventListener('focusin', stopAutoplay);
			slider.addEventListener('focusout', startAutoplay);
		}

		// Função para recalcular a largura do track e slides
		let trackResizeTimeout;
		function updateTrackWidth() {
			setSlideWidths();
		}

		// Atualizar largura ao redimensionar
		let resizeObserver;
		if (window.ResizeObserver) {
			resizeObserver = new ResizeObserver(function() {
				updateTrackWidth();
			});
			resizeObserver.observe(slider);
		} else {
			window.addEventListener('resize', function() {
				clearTimeout(trackResizeTimeout);
				trackResizeTimeout = setTimeout(updateTrackWidth, 100);
			});
		}

		updateDots(currentIndex);
		startAutoplay();
	}

	function init() {
		// Só inicializa o slider no mobile (max-width: 900px)
		if (window.innerWidth > 900) {
			return;
		}

		const sliders = document.querySelectorAll(SLIDER_SELECTOR);
		if (!sliders.length) {
			return;
		}

		sliders.forEach(function (slider) {
			if (!slider.hasAttribute('data-initialized')) {
				slider.setAttribute('data-initialized', 'true');
				initSlider(slider);
			}
		});
	}

	function handleResize() {
		// Reinicializa se mudar de desktop para mobile ou vice-versa
		if (window.innerWidth <= 900) {
			const sliders = document.querySelectorAll(SLIDER_SELECTOR);
			sliders.forEach(function (slider) {
				if (!slider.hasAttribute('data-initialized')) {
					slider.setAttribute('data-initialized', 'true');
					initSlider(slider);
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	// Reinicializa ao redimensionar a janela
	let resizeTimeout;
	window.addEventListener('resize', function () {
		clearTimeout(resizeTimeout);
		resizeTimeout = setTimeout(handleResize, 250);
	});
})();



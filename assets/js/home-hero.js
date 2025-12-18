'use strict';

(function () {
	const SLIDER_SELECTOR = '[data-gstore-hero-slider]';
	const AUTOPLAY_DELAY = 6500;
	const MOBILE_BREAKPOINT = 781;

	/**
	 * Inicializa um slider individual com suporte a Desktop/Mobile separados.
	 */
	function initSlider(slider) {
		// Elementos Desktop
		const desktopTrack = slider.querySelector('[data-gstore-hero-track]');
		const desktopSlides = Array.from(slider.querySelectorAll('[data-gstore-hero-slide]'));
		const desktopDots = Array.from(slider.querySelectorAll('[data-gstore-hero-dot]'));
		
		// Elementos Mobile
		const mobileTrack = slider.querySelector('[data-gstore-hero-track-mobile]');
		const mobileSlides = Array.from(slider.querySelectorAll('[data-gstore-hero-slide-mobile]'));
		const mobileDots = Array.from(slider.querySelectorAll('[data-gstore-hero-dot-mobile]'));
		
		// Controles compartilhados
		const prevButton = slider.querySelector('[data-gstore-hero-prev]');
		const nextButton = slider.querySelector('[data-gstore-hero-next]');

		// Estado
		let desktopIndex = 0;
		let mobileIndex = 0;
		let autoplayId = null;
		
		const prefersReducedMotion = window.matchMedia && 
			window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		/**
		 * Verifica se está em viewport mobile.
		 */
		function isMobile() {
			return window.innerWidth <= MOBILE_BREAKPOINT;
		}

		/**
		 * Obtém os elementos do slider atual (desktop ou mobile).
		 */
		function getCurrentSliderElements() {
			if (isMobile()) {
				return {
					track: mobileTrack,
					slides: mobileSlides,
					dots: mobileDots,
					getCurrentIndex: function() { return mobileIndex; },
					setCurrentIndex: function(val) { mobileIndex = val; }
				};
			}
			return {
				track: desktopTrack,
				slides: desktopSlides,
				dots: desktopDots,
				getCurrentIndex: function() { return desktopIndex; },
				setCurrentIndex: function(val) { desktopIndex = val; }
			};
		}

		/**
		 * Verifica se pode fazer autoplay.
		 */
		function canAutoplay() {
			const elements = getCurrentSliderElements();
			return !prefersReducedMotion && elements.slides.length > 1;
		}

		/**
		 * Atualiza os dots do slider atual.
		 */
		function updateDots(index) {
			const elements = getCurrentSliderElements();
			if (!elements.dots.length) {
				return;
			}

			elements.dots.forEach(function (dot, dotIndex) {
				const isActive = dotIndex === index;
				dot.classList.toggle('is-active', isActive);
				dot.setAttribute('aria-selected', String(isActive));
			});
		}

		/**
		 * Vai para um slide específico.
		 */
		function goTo(index) {
			const elements = getCurrentSliderElements();
			if (!elements.track || elements.slides.length === 0) {
				return;
			}

			const total = elements.slides.length;
			const newIndex = (index + total) % total;
			elements.setCurrentIndex(newIndex);
			
			const offset = newIndex * 100;
			elements.track.style.transform = 'translate3d(-' + offset + '%, 0, 0)';
			updateDots(newIndex);
		}

		/**
		 * Próximo slide.
		 */
		function nextSlide() {
			const elements = getCurrentSliderElements();
			goTo(elements.getCurrentIndex() + 1);
		}

		/**
		 * Slide anterior.
		 */
		function prevSlide() {
			const elements = getCurrentSliderElements();
			goTo(elements.getCurrentIndex() - 1);
		}

		/**
		 * Para o autoplay.
		 */
		function stopAutoplay() {
			if (autoplayId) {
				window.clearInterval(autoplayId);
				autoplayId = null;
			}
		}

		/**
		 * Inicia o autoplay.
		 */
		function startAutoplay() {
			if (!canAutoplay()) {
				return;
			}

			stopAutoplay();
			autoplayId = window.setInterval(nextSlide, AUTOPLAY_DELAY);
		}

		/**
		 * Reseta o slider ao mudar de viewport.
		 */
		function handleResize() {
			stopAutoplay();
			
			// Reseta posição para o slide atual de cada viewport
			if (desktopTrack && desktopSlides.length > 0) {
				const offset = desktopIndex * 100;
				desktopTrack.style.transform = 'translate3d(-' + offset + '%, 0, 0)';
			}
			
			if (mobileTrack && mobileSlides.length > 0) {
				const offset = mobileIndex * 100;
				mobileTrack.style.transform = 'translate3d(-' + offset + '%, 0, 0)';
			}
			
			// Atualiza dots do viewport atual
			updateDots(getCurrentSliderElements().getCurrentIndex());
			
			startAutoplay();
		}

		// Event listeners para controles
		if (prevButton) {
			prevButton.addEventListener('click', function () {
				prevSlide();
				if (canAutoplay()) {
					stopAutoplay();
					startAutoplay();
				}
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				nextSlide();
				if (canAutoplay()) {
					stopAutoplay();
					startAutoplay();
				}
			});
		}

		// Event listeners para dots Desktop
		if (desktopDots.length) {
			desktopDots.forEach(function (dot, index) {
				dot.addEventListener('click', function () {
					if (!isMobile()) {
						desktopIndex = index;
						goTo(index);
						if (canAutoplay()) {
							stopAutoplay();
							startAutoplay();
						}
					}
				});
			});
		}

		// Event listeners para dots Mobile
		if (mobileDots.length) {
			mobileDots.forEach(function (dot, index) {
				dot.addEventListener('click', function () {
					if (isMobile()) {
						mobileIndex = index;
						goTo(index);
						if (canAutoplay()) {
							stopAutoplay();
							startAutoplay();
						}
					}
				});
			});
		}

		// Pausa autoplay ao interagir
		if (!prefersReducedMotion) {
			slider.addEventListener('mouseenter', stopAutoplay);
			slider.addEventListener('mouseleave', startAutoplay);
			slider.addEventListener('focusin', stopAutoplay);
			slider.addEventListener('focusout', startAutoplay);
		}

		// Atualiza ao redimensionar
		let resizeTimeout;
		window.addEventListener('resize', function() {
			clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(handleResize, 150);
		});

		// Inicialização
		updateDots(getCurrentSliderElements().getCurrentIndex());
		startAutoplay();
	}

	/**
	 * Inicializa todos os sliders na página.
	 */
	function init() {
		const sliders = document.querySelectorAll(SLIDER_SELECTOR);
		if (!sliders.length) {
			return;
		}

		sliders.forEach(initSlider);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

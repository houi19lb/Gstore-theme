'use strict';

(function () {
	const SLIDER_SELECTOR = '[data-gstore-hero-slider]';
	const TRACK_SELECTOR = '[data-gstore-hero-track]';
	const SLIDE_SELECTOR = '[data-gstore-hero-slide]';
	const DOT_SELECTOR = '[data-gstore-hero-dot]';
	const PREV_SELECTOR = '[data-gstore-hero-prev]';
	const NEXT_SELECTOR = '[data-gstore-hero-next]';
	const AUTOPLAY_DELAY = 6500;

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
			const offset = currentIndex * 100;
			track.style.transform = 'translate3d(-' + offset + '%, 0, 0)';
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

		if (canAutoplay) {
			slider.addEventListener('mouseenter', stopAutoplay);
			slider.addEventListener('mouseleave', startAutoplay);
			slider.addEventListener('focusin', stopAutoplay);
			slider.addEventListener('focusout', startAutoplay);
		}

		updateDots(currentIndex);
		startAutoplay();
	}

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




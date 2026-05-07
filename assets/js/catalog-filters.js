/**
 * ==========================================
 * CATALOG FILTERS TOGGLE - Mobile
 * ==========================================
 * Controls open/close behavior for mobile sidebar filters.
 */
(function () {
	'use strict';

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCatalogFilters);
	} else {
		initCatalogFilters();
	}

	function initCatalogFilters() {
		const toggleButton = document.querySelector('.Gstore-catalog-filters-toggle');
		const sidebar = document.querySelector('.Gstore-catalog-sidebar__inner--collapsible');
		const closeButton = document.querySelector('.Gstore-catalog-sidebar__close');
		const overlay = ensureOverlay();

		const sidebarOriginalParent = sidebar ? sidebar.parentNode : null;
		const sidebarOriginalNextSibling = sidebar ? sidebar.nextSibling : null;
		let isDetached = false;

		initDynamicBreadcrumb();

		if (!toggleButton || !sidebar) {
			resetGlobalState();
			return;
		}

		resetGlobalState();

		function isMobileViewport() {
			return window.innerWidth <= 1024;
		}

		function resetGlobalState() {
			overlay.classList.remove('is-active');
			overlay.setAttribute('aria-hidden', 'true');
			document.documentElement.style.overflow = '';
			document.body.style.overflow = '';
		}

		function detachSidebarIfNeeded() {
			if (!isMobileViewport() || isDetached || !sidebarOriginalParent) {
				return;
			}
			sidebar.classList.add('Gstore-catalog-sidebar--detached');
			document.body.appendChild(sidebar);
			isDetached = true;
		}

		function restoreSidebarIfNeeded() {
			if (!isDetached || !sidebarOriginalParent) {
				return;
			}

			if (sidebarOriginalNextSibling && sidebarOriginalNextSibling.parentNode === sidebarOriginalParent) {
				sidebarOriginalParent.insertBefore(sidebar, sidebarOriginalNextSibling);
			} else {
				sidebarOriginalParent.appendChild(sidebar);
			}

			sidebar.classList.remove('Gstore-catalog-sidebar--detached');
			isDetached = false;
		}

		function openFilters() {
			detachSidebarIfNeeded();
			sidebar.classList.add('is-open');
			toggleButton.classList.add('is-active');
			toggleButton.setAttribute('aria-expanded', 'true');
			overlay.classList.add('is-active');
			overlay.setAttribute('aria-hidden', 'false');
			document.documentElement.style.overflow = 'hidden';
			document.body.style.overflow = 'hidden';
		}

		function closeFilters() {
			sidebar.classList.remove('is-open');
			toggleButton.classList.remove('is-active');
			toggleButton.setAttribute('aria-expanded', 'false');
			resetGlobalState();
		}

		toggleButton.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();

			if (sidebar.classList.contains('is-open')) {
				closeFilters();
				return;
			}

			openFilters();
		});

		overlay.addEventListener('click', function (e) {
			e.preventDefault();
			closeFilters();
		});

		if (closeButton) {
			closeButton.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				closeFilters();
			});
		}

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
				closeFilters();
			}
		});

		window.addEventListener('pageshow', function () {
			closeFilters();
		});

		let resizeTimer = null;
		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () {
				if (window.innerWidth > 1024 && sidebar.classList.contains('is-open')) {
					closeFilters();
					return;
				}

				// If the viewport changes while detached and closed, restore original DOM position.
				if (window.innerWidth > 1024) {
					restoreSidebarIfNeeded();
					resetGlobalState();
				}
			}, 140);
		});

		closeFilters();
		if (isMobileViewport()) {
			detachSidebarIfNeeded();
		}
	}

	function ensureOverlay() {
		let overlay = document.querySelector('.Gstore-catalog-filters-overlay');
		if (!overlay) {
			overlay = document.createElement('div');
			overlay.className = 'Gstore-catalog-filters-overlay';
			overlay.setAttribute('aria-hidden', 'true');
			document.body.appendChild(overlay);
		}
		return overlay;
	}

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

		let termName = '';
		const archiveTitle = document.querySelector('.woocommerce-products-header__title, .Gstore-catalog-title');
		if (archiveTitle) {
			termName = archiveTitle.textContent.trim();
			termName = termName.replace(/^(Categoria:|Category:|Arquivo:|Archive:)\s*/i, '');
		}

		if (!termName) {
			termName = document.title.split('|')[0].split('-')[0].trim();
		}

		if (!termName) {
			const pathParts = window.location.pathname.split('/').filter(Boolean);
			if (pathParts.length > 0) {
				termName = pathParts[pathParts.length - 1]
					.replace(/-/g, ' ')
					.replace(/\b\w/g, function (char) { return char.toUpperCase(); });
			}
		}

		if (termName) {
			currentTermSpan.textContent = termName;
		} else {
			if (currentSep) {
				currentSep.style.display = 'none';
			}
			currentTermSpan.style.display = 'none';
		}
	}
})();

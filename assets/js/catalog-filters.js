/**
 * Catalog filters toggle (mobile)
 * Bottom-sheet behavior based on existing catalog sidebar.
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
		let lockCloseUntil = 0;
		const sidebarOriginalParent = sidebar ? sidebar.parentNode : null;
		const sidebarOriginalNextSibling = sidebar ? sidebar.nextSibling : null;
		let mountedToBody = false;

		initDynamicBreadcrumb();

		if (!toggleButton || !sidebar) {
			return;
		}

		function isMobileViewport() {
			return window.innerWidth <= 1024;
		}

		function mountSidebarToBodyIfNeeded() {
			if (!isMobileViewport() || mountedToBody || !sidebarOriginalParent) {
				return;
			}
			document.body.appendChild(sidebar);
			mountedToBody = true;
		}

		function restoreSidebarToLayoutIfNeeded() {
			if (!mountedToBody || !sidebarOriginalParent) {
				return;
			}
			if (sidebarOriginalNextSibling && sidebarOriginalNextSibling.parentNode === sidebarOriginalParent) {
				sidebarOriginalParent.insertBefore(sidebar, sidebarOriginalNextSibling);
			} else {
				sidebarOriginalParent.appendChild(sidebar);
			}
			mountedToBody = false;
		}

		function openFilters() {
			mountSidebarToBodyIfNeeded();
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
			overlay.classList.remove('is-active');
			overlay.setAttribute('aria-hidden', 'true');
			document.documentElement.style.overflow = '';
			document.body.style.overflow = '';
			restoreSidebarToLayoutIfNeeded();
		}

		toggleButton.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();

			lockCloseUntil = Date.now() + 220;

			if (sidebar.classList.contains('is-open')) {
				closeFilters();
				return;
			}

			openFilters();
		});

		sidebar.addEventListener('click', function (e) {
			e.stopPropagation();
		});

		document.addEventListener('pointerdown', function (e) {
			if (!sidebar.classList.contains('is-open')) {
				return;
			}
			if (Date.now() < lockCloseUntil) {
				return;
			}
			if (sidebar.contains(e.target) || toggleButton.contains(e.target)) {
				return;
			}
			e.preventDefault();
			closeFilters();
		}, true);

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

		let resizeTimer = null;
		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () {
				if (window.innerWidth > 1024 && sidebar.classList.contains('is-open')) {
					closeFilters();
				}
				if (window.innerWidth > 1024) {
					restoreSidebarToLayoutIfNeeded();
				}
			}, 160);
		});

		if (window.innerWidth > 1024) {
			closeFilters();
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
		const archiveTitle = document.querySelector('.Gstore-catalog-title');
		if (archiveTitle) {
			termName = archiveTitle.textContent.trim().replace(/^(Categoria:|Category:|Arquivo:|Archive:)\s*/i, '');
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
			return;
		}

		if (currentSep) {
			currentSep.style.display = 'none';
		}
		currentTermSpan.style.display = 'none';
	}
})();

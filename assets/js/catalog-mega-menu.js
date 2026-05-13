(function () {
	'use strict';

	var DESKTOP_QUERY = '(min-width: 768px)';
	var mediaQuery = window.matchMedia ? window.matchMedia(DESKTOP_QUERY) : null;
	var observer = null;

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}
		callback();
	}

	function isDesktop() {
		return mediaQuery ? mediaQuery.matches : window.innerWidth >= 768;
	}

	function getToggle(root) {
		return root.querySelector(':scope > .gstore-catalog-mega__trigger .gstore-catalog-mega__toggle');
	}

	function setOpen(root, open) {
		var toggle = getToggle(root);
		root.classList.toggle('is-open', !!open);
		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
	}

	function closeAll(except) {
		document.querySelectorAll('.gstore-catalog-mega.is-open').forEach(function (root) {
			if (root !== except) {
				setOpen(root, false);
			}
		});
	}

	function setTreeItemOpen(item, open) {
		var toggle = item.querySelector(':scope > .gstore-catalog-mega__tree-row .gstore-catalog-mega__tree-toggle');
		item.classList.toggle('is-open', !!open);
		item.classList.toggle('is-collapsed', !open);
		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
	}

	function syncTreeForViewport(root) {
		var desktop = isDesktop();
		root.querySelectorAll('.gstore-catalog-mega__tree-item.has-children').forEach(function (item) {
			var toggle = item.querySelector(':scope > .gstore-catalog-mega__tree-row .gstore-catalog-mega__tree-toggle');
			if (desktop) {
				item.classList.remove('is-collapsed');
				if (toggle) {
					toggle.setAttribute('aria-expanded', 'true');
				}
				return;
			}

			var open = item.classList.contains('is-open');
			item.classList.toggle('is-collapsed', !open);
			if (toggle) {
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
		});
	}

	function initTree(root) {
		root.querySelectorAll('.gstore-catalog-mega__tree-item.has-children').forEach(function (item) {
			var toggle = item.querySelector(':scope > .gstore-catalog-mega__tree-row .gstore-catalog-mega__tree-toggle');
			if (!toggle || toggle.getAttribute('data-gstore-catalog-tree-ready') === '1') {
				return;
			}

			toggle.setAttribute('data-gstore-catalog-tree-ready', '1');
			toggle.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				setTreeItemOpen(item, !item.classList.contains('is-open'));
			});
		});
	}

	function initMega(root) {
		if (!root || root.classList.contains('gstore-catalog-mega--enhanced')) {
			return;
		}

		var toggle = getToggle(root);
		var panel = root.querySelector(':scope > .gstore-catalog-mega__panel');
		if (!toggle || !panel) {
			return;
		}

		root.classList.add('gstore-catalog-mega--enhanced');
		toggle.setAttribute('aria-expanded', 'false');

		toggle.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			var nextOpen = !root.classList.contains('is-open');
			closeAll(root);
			setOpen(root, nextOpen);
		});

		root.addEventListener('mouseenter', function () {
			if (isDesktop()) {
				closeAll(root);
				setOpen(root, true);
			}
		});

		root.addEventListener('mouseleave', function () {
			if (isDesktop()) {
				setOpen(root, false);
			}
		});

		root.addEventListener('focusin', function () {
			if (isDesktop()) {
				closeAll(root);
				setOpen(root, true);
			}
		});

		root.addEventListener('focusout', function () {
			window.setTimeout(function () {
				if (isDesktop() && !root.contains(document.activeElement)) {
					setOpen(root, false);
				}
			}, 0);
		});

		initTree(root);
		syncTreeForViewport(root);
	}

	function scan() {
		document.querySelectorAll('.gstore-catalog-mega').forEach(initMega);
	}

	function syncAllTrees() {
		document.querySelectorAll('.gstore-catalog-mega--enhanced').forEach(syncTreeForViewport);
	}

	function watchMutations() {
		if (!window.MutationObserver || observer) {
			return;
		}

		observer = new MutationObserver(function (mutations) {
			var shouldScan = false;
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) {
						return;
					}
					if (
						(node.classList && node.classList.contains('gstore-catalog-mega')) ||
						(node.querySelector && node.querySelector('.gstore-catalog-mega'))
					) {
						shouldScan = true;
					}
				});
			});
			if (shouldScan) {
				scan();
			}
		});

		observer.observe(document.body, { childList: true, subtree: true });
	}

	ready(function () {
		scan();
		watchMutations();

		document.addEventListener('click', function (event) {
			if (!event.target.closest('.gstore-catalog-mega')) {
				closeAll();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeAll();
			}
		});

		if (mediaQuery) {
			if (typeof mediaQuery.addEventListener === 'function') {
				mediaQuery.addEventListener('change', syncAllTrees);
			} else if (typeof mediaQuery.addListener === 'function') {
				mediaQuery.addListener(syncAllTrees);
			}
		}
	});
})();

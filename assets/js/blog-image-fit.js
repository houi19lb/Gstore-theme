'use strict';

(function () {
	const FRAME_SELECTOR = [
		'.gstore-hb-v1 .hb-cover',
		'.gstore-hb-v1 .hb-thumb',
		'.Gstore-home-blog__image',
		'.Gstore-blog-card__image',
		'.Gstore-blog-recent-post__image',
		'.Gstore-blog-single-image',
		'.Gstore-blog-single-related__image'
	].join(',');

	function cssUrl(url) {
		return 'url("' + String(url).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '")';
	}

	function getImageUrl(img) {
		return img.currentSrc || img.src || '';
	}

	function applyFrame(frame) {
		const img = frame.querySelector('img');
		if (!img) {
			return;
		}

		const update = function () {
			const url = getImageUrl(img);
			if (!url) {
				return;
			}

			frame.style.setProperty('--gstore-blog-image-bg', cssUrl(url));
			frame.classList.add('has-blog-image-bg');
		};

		update();

		if (!img.complete) {
			img.addEventListener('load', update, { once: true });
		}
	}

	function init(root) {
		const scope = root || document;

		if (scope.matches && scope.matches(FRAME_SELECTOR)) {
			applyFrame(scope);
		}

		scope.querySelectorAll(FRAME_SELECTOR).forEach(applyFrame);
	}

	function observeChanges() {
		const observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType === 1) {
						init(node);
					}
				});
			});
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
	}

	function start() {
		init(document);
		observeChanges();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
}());

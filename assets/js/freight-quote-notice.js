(function () {
	'use strict';

	var config = window.gstoreFreightQuoteNotice || {};
	var message = normalizeText(config.message || '');
	var buttonSelector = '[data-gstore-quote-notice-jump]';
	var highlightClass = 'gstore-shipping-quote-target-highlight';
	var scheduled = null;

	if (config.enabled === false) {
		return;
	}

	function normalizeText(value) {
		return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
	}

	function hasNoticeText(element) {
		var optionPrice;
		var priceText;

		if (!element) {
			return false;
		}

		if (element.matches('.gstore-shipping-quote-notice') || element.querySelector('.gstore-shipping-quote-notice')) {
			return true;
		}

		if (message && normalizeText(element.textContent).indexOf(message) !== -1) {
			return true;
		}

		optionPrice = element.matches('.Gstore-checkout-item-shipping-option')
			? element.querySelector('.Gstore-checkout-item-shipping-option__price')
			: null;
		priceText = normalizeText(optionPrice ? optionPrice.textContent : '');

		return !!priceText && !/(r\$\s*\d|\bgratis\b|\bfree\b)/i.test(priceText);
	}

	function isVisible(element) {
		var style;

		if (!element || !element.getClientRects().length) {
			return false;
		}

		style = window.getComputedStyle(element);
		return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
	}

	function findSelectedQuoteTarget() {
		var selectedOptions = Array.prototype.slice.call(
			document.querySelectorAll('.Gstore-checkout-item-shipping-option input:checked')
		)
			.map(function (input) {
				return input.closest('.Gstore-checkout-item-shipping-option');
			})
			.filter(hasNoticeText);

		var visibleSelected = selectedOptions.find(isVisible);
		if (visibleSelected) {
			return visibleSelected;
		}

		var activeOptions = Array.prototype.slice.call(
			document.querySelectorAll('.Gstore-checkout-item-shipping-option.is-selected')
		).filter(hasNoticeText);

		visibleSelected = activeOptions.find(isVisible);
		if (visibleSelected) {
			return visibleSelected;
		}

		var checkedMethods = Array.prototype.slice.call(
			document.querySelectorAll('#shipping_method input.shipping_method:checked, .gstore-shipping-method input.shipping_method:checked')
		)
			.map(function (input) {
				return input.closest('li, .gstore-shipping-method, .gstore-shipping-method__option');
			})
			.filter(hasNoticeText);

		visibleSelected = checkedMethods.find(isVisible);
		return visibleSelected || selectedOptions[0] || activeOptions[0] || checkedMethods[0] || null;
	}

	function findAnyQuoteTarget() {
		var target = findSelectedQuoteTarget();
		if (target) {
			return target;
		}

		var notices = Array.prototype.slice.call(
			document.querySelectorAll('.gstore-shipping-quote-notice, .Gstore-checkout-item-shipping-option__price')
		).filter(hasNoticeText);

		return notices.find(isVisible) || notices[0] || null;
	}

	function selectedFreightHasNotice() {
		return !!findSelectedQuoteTarget();
	}

	function createJumpButton() {
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'gstore-shipping-quote-jump';
		button.setAttribute('data-gstore-quote-notice-jump', '1');
		button.textContent = config.buttonLabel || 'Ver aviso';
		button.addEventListener('click', jumpToNotice);
		return button;
	}

	function createValueJumpButton() {
		var button = createJumpButton();
		button.classList.add('gstore-shipping-quote-jump--value');
		return button;
	}

	function findExpandDetailsButton() {
		return Array.prototype.slice.call(document.querySelectorAll('button')).find(function (button) {
			return /ver detalhes/i.test(button.textContent || '');
		});
	}

	function findVisibleShippingChangeButton() {
		return Array.prototype.slice.call(document.querySelectorAll('[data-gstore-shipping-change]')).find(isVisible);
	}

	function jumpToNotice(event) {
		var target;
		var expandButton;
		var changeButton;

		event.preventDefault();

		target = findSelectedQuoteTarget();
		if (!isVisible(target)) {
			expandButton = findExpandDetailsButton();
			if (expandButton) {
				expandButton.click();
			} else {
				changeButton = findVisibleShippingChangeButton();
				if (changeButton) {
					changeButton.click();
				}
			}
		}

		window.setTimeout(function () {
			target = findAnyQuoteTarget();
			if (!target) {
				return;
			}

			target.classList.add(highlightClass);
			target.setAttribute('tabindex', '-1');
			target.focus({ preventScroll: true });
			target.scrollIntoView({ behavior: 'smooth', block: 'center' });

			window.setTimeout(function () {
				target.classList.remove(highlightClass);
			}, 2400);
		}, 220);
	}

	function restoreQuoteValueCells() {
		document.querySelectorAll('[data-gstore-quote-notice-value-cell="1"]').forEach(function (container) {
			var originalHtml = container.getAttribute('data-gstore-quote-original-html');

			if (originalHtml !== null) {
				container.innerHTML = originalHtml;
			}

			container.removeAttribute('data-gstore-quote-notice-value-cell');
			container.removeAttribute('data-gstore-quote-original-html');
			container.classList.remove('gstore-shipping-quote-value-cell');
		});
	}

	function addValueButtonTo(container) {
		if (!container) {
			return;
		}

		if (container.getAttribute('data-gstore-quote-notice-value-cell') === '1') {
			return;
		}

		container.setAttribute('data-gstore-quote-original-html', container.innerHTML);
		container.setAttribute('data-gstore-quote-notice-value-cell', '1');
		container.classList.add('gstore-shipping-quote-value-cell');
		container.innerHTML = '';
		container.appendChild(createValueJumpButton());
	}

	function removeInlineNoticeButtons() {
		document
			.querySelectorAll(
				'.Gstore-checkout-shipping-totals__label > [data-gstore-quote-notice-jump], ' +
				'.Gstore-order-review-shipping-row > [data-gstore-quote-notice-jump]'
			)
			.forEach(function (button) {
				button.remove();
			});
	}

	function enhanceValueCells() {
		document.querySelectorAll('.Gstore-checkout-shipping-totals__row--shipping').forEach(function (row) {
			addValueButtonTo(row.lastElementChild);
		});

		document.querySelectorAll('tr[class*="gstore-shipping-"]').forEach(function (row) {
			addValueButtonTo(row.querySelector('td'));
		});
	}

	function removeButtons() {
		restoreQuoteValueCells();
		document.querySelectorAll(buttonSelector).forEach(function (button) {
			button.remove();
		});
	}

	function enhanceRows() {
		if (!selectedFreightHasNotice()) {
			removeButtons();
			return;
		}

		removeInlineNoticeButtons();
		enhanceValueCells();
	}

	function scheduleEnhance() {
		window.clearTimeout(scheduled);
		scheduled = window.setTimeout(enhanceRows, 120);
	}

	document.addEventListener('DOMContentLoaded', scheduleEnhance);
	window.addEventListener('load', scheduleEnhance);
	document.addEventListener('change', scheduleEnhance, true);
	document.addEventListener('click', function (event) {
		if (event.target && event.target.closest('[data-gstore-shipping-change], .Gstore-checkout-item-shipping-option')) {
			scheduleEnhance();
		}
	}, true);

	if (window.jQuery) {
		window.jQuery(document.body).on('updated_checkout updated_wc_div wc_fragments_refreshed', scheduleEnhance);
	}

	if (document.body && window.MutationObserver) {
		new window.MutationObserver(scheduleEnhance).observe(document.body, {
			childList: true,
			subtree: true,
		});
	}

	scheduleEnhance();
})();

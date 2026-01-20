/**
 * Cart JavaScript - Gstore Theme
 * Melhora o seletor de quantidade do carrinho com botões + e -
 */
(function () {
	'use strict';

	let cartUpdateTimeout = null;
	let ratesSyncInProgress = false;
	const CART_CEP_STORAGE_KEY = 'gstore_cart_cep';

	function getCartCep() {
		const cepInput = document.querySelector('.gstore-shipping-calculator__cep');
		if (!cepInput) {
			return '';
		}

		const raw = cepInput.value || '';
		const digits = raw.replace(/\D/g, '');
		return digits.length === 8 ? digits : '';
	}

	function restoreCartCep() {
		const cepInput = document.querySelector('.gstore-shipping-calculator__cep');
		if (!cepInput || cepInput.value) {
			return;
		}

		if (typeof window === 'undefined' || !window.localStorage) {
			const fallback = getCartPostcodeFieldValue();
			if (fallback) {
				cepInput.value = fallback.replace(/(\d{5})(\d{3})/, '$1-$2');
			}
			return;
		}

		const saved = window.localStorage.getItem(CART_CEP_STORAGE_KEY) || '';
		const digits = saved.replace(/\D/g, '') || getCartPostcodeFieldValue();
		if (digits.length !== 8) {
			return;
		}

		cepInput.value = digits.replace(/(\d{5})(\d{3})/, '$1-$2');
	}

	function storeCartCep(cep) {
		if (typeof window === 'undefined' || !window.localStorage) {
			return;
		}

		const digits = String(cep || '').replace(/\D/g, '');
		if (digits.length === 8) {
			window.localStorage.setItem(CART_CEP_STORAGE_KEY, digits);
		}
	}

	function getCartPostcodeField() {
		return document.querySelector('input[data-gstore-shipping-postcode]');
	}

	function getCartPostcodeFieldValue() {
		const field = getCartPostcodeField();
		if (!field) {
			return '';
		}
		const digits = String(field.value || '').replace(/\D/g, '');
		return digits.length === 8 ? digits : '';
	}

	function syncCartPostcodeField(cep) {
		const field = getCartPostcodeField();
		if (!field) {
			return;
		}
		const digits = String(cep || '').replace(/\D/g, '');
		field.value = digits.length === 8 ? digits : '';
	}

	function getShippingAjaxUrl() {
		if (typeof gstoreShippingCalculator !== 'undefined' && gstoreShippingCalculator.ajaxUrl) {
			return gstoreShippingCalculator.ajaxUrl;
		}
		if (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.ajax_url) {
			return wc_checkout_params.ajax_url;
		}
		return '/wp-admin/admin-ajax.php';
	}

	function normalizeRateMode(mode) {
		const value = String(mode || '').toLowerCase();
		if (value === 'air' || value === 'aereo') {
			return 'air';
		}
		if (value === 'ground' || value === 'land' || value === 'terrestre') {
			return 'land';
		}
		return '';
	}

	function fetchRatesForCart(cep) {
		if (!cep) {
			return Promise.resolve(null);
		}

		return jQuery.ajax({
			url: getShippingAjaxUrl(),
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'gstore_calculate_cart_item_rates',
				nonce: typeof gstoreShippingCalculator !== 'undefined' ? gstoreShippingCalculator.nonce : '',
				postcode: cep,
			},
		}).then((response) => {
			if (!response || !response.success || !response.data || !response.data.items) {
				return null;
			}
			return response.data.items;
		}).catch(() => null);
	}

	function updateShippingBlock(shippingBlock, rates, cartItemKey, selectedMode) {
		if (!shippingBlock) {
			return;
		}

		const optionsContainer = shippingBlock.querySelector('[data-gstore-shipping-options]');
		const fixedContainer = shippingBlock.querySelector('[data-gstore-shipping-fixed]');
		const ratesInput = shippingBlock.querySelector(`input[name="gstore_shipping_rates[${cartItemKey}]"]`);

		const normalizedRates = (rates || [])
			.map((rate) => ({
				mode: normalizeRateMode(rate.mode),
				label: rate.label || '',
				cost: rate.cost || '',
				cost_formatted: rate.cost_formatted || '',
			}))
			.filter((rate) => rate.mode);

		if (ratesInput) {
			ratesInput.value = JSON.stringify(normalizedRates);
		}

		if (!normalizedRates.length) {
			return;
		}

	if (!normalizedRates.some((rate) => rate.mode === selectedMode)) {
		selectedMode = normalizedRates[0].mode;
	}

		const hasMultiple = normalizedRates.length > 1;
		const optionsHtml = hasMultiple
			? normalizedRates.map((rate) => {
					const mode = rate.mode;
				const label = rate.label || (mode === 'air' ? 'Frete Aéreo' : 'Frete Terrestre');
				const cost = rate.cost_formatted || '-';
				const checked = selectedMode === mode ? 'checked' : '';
				return `
					<label class="Gstore-cart-card__shipping-option">
						<input type="radio" name="gstore_shipping_mode[${cartItemKey}]" value="${mode}" ${checked} />
						<span class="Gstore-cart-card__shipping-text">${label}</span>
						<span class="Gstore-cart-card__shipping-price">${cost}</span>
					</label>
				`;
			}).join('')
			: '';

		const fixedHtml = !hasMultiple
			? (() => {
				const onlyRate = normalizedRates[0];
				const label = onlyRate.label || (onlyRate.mode === 'air' ? 'Frete Aéreo' : 'Frete Terrestre');
				const cost = onlyRate.cost_formatted || '-';
				return `
					<span class="Gstore-cart-card__shipping-text">${label}</span>
					<span class="Gstore-cart-card__shipping-price">${cost}</span>
					<input type="hidden" name="gstore_shipping_mode[${cartItemKey}]" value="${onlyRate.mode}" />
				`;
			})()
			: '';

		if (optionsContainer) {
			optionsContainer.innerHTML = optionsHtml;
		} else if (optionsHtml) {
			const optionsWrapper = document.createElement('div');
			optionsWrapper.className = 'Gstore-cart-card__shipping-options';
			optionsWrapper.setAttribute('data-gstore-shipping-options', '');
			optionsWrapper.innerHTML = optionsHtml;
			shippingBlock.appendChild(optionsWrapper);
		}

		if (fixedContainer) {
			fixedContainer.innerHTML = fixedHtml;
		} else if (fixedHtml) {
			const fixedWrapper = document.createElement('div');
			fixedWrapper.className = 'Gstore-cart-card__shipping-fixed';
			fixedWrapper.setAttribute('data-gstore-shipping-fixed', '');
			fixedWrapper.innerHTML = fixedHtml;
			shippingBlock.appendChild(fixedWrapper);
		}

		initShippingChoices();
	}

	function ensureShippingBlocksExist() {
		const cartItems = document.querySelectorAll('[data-cart-item-key]');
		cartItems.forEach((item) => {
			if (item.querySelector('[data-gstore-shipping-item]')) {
				return;
			}
			const body = item.querySelector('.Gstore-cart-card__body');
			if (!body) {
				return;
			}

			const shippingBlock = document.createElement('div');
			shippingBlock.className = 'Gstore-cart-card__shipping';
			shippingBlock.setAttribute('data-gstore-shipping-item', '');
			shippingBlock.innerHTML = `
				<span class="Gstore-cart-card__label">Frete</span>
				<div class="Gstore-cart-card__shipping-fixed" data-gstore-shipping-fixed>
					<span class="Gstore-cart-card__shipping-text">Calcule o frete para ver os valores.</span>
				</div>
			`;
			body.appendChild(shippingBlock);
		});
	}

	function calculateRatesForCart(shouldUpdateCart) {
		if (ratesSyncInProgress) {
			return;
		}

		let cep = getCartCep();
		if (!cep) {
			if (typeof window !== 'undefined' && window.localStorage) {
				const saved = window.localStorage.getItem(CART_CEP_STORAGE_KEY) || '';
				const digits = saved.replace(/\D/g, '');
				if (digits.length === 8) {
					cep = digits;
				}
			}
		}

		if (!cep) {
			return;
		}

		storeCartCep(cep);
		syncCartPostcodeField(cep);

		const shippingBlocks = document.querySelectorAll('[data-gstore-shipping-item]');
		if (!shippingBlocks.length) {
			return;
		}

		ratesSyncInProgress = true;
	fetchRatesForCart(cep).then((itemsRates) => {
		if (!itemsRates) {
			ratesSyncInProgress = false;
			return;
		}

		shippingBlocks.forEach((shippingBlock) => {
			const itemEl = shippingBlock.closest('article.Gstore-cart-card, [data-cart-item-key]');
			if (!itemEl) {
				return;
			}

			const cartItemKey = itemEl.dataset.cartItemKey || itemEl.getAttribute('data-cart-item-key');
			if (!cartItemKey) {
				return;
			}

			const selectedInput = shippingBlock.querySelector('input[type="radio"]:checked');
			const selectedMode = selectedInput ? selectedInput.value : (shippingBlock.dataset.lastSelectedMode || 'land');
			const itemRates = itemsRates[cartItemKey] ? itemsRates[cartItemKey].rates : null;

			if (!itemRates) {
				return;
			}

			shippingBlock.dataset.lastSelectedMode = selectedMode;
			updateShippingBlock(shippingBlock, itemRates, cartItemKey, selectedMode);
		});

		ratesSyncInProgress = false;
		if (shouldUpdateCart) {
			scheduleCartUpdate();
		}
	});
	}

	/**
	 * Atualiza o carrinho automaticamente (AJAX).
	 */
	function updateCartAutomatically() {
		if (typeof jQuery === 'undefined') {
			return;
		}

		const $form = jQuery('.woocommerce-cart-form, .Gstore-cart-form');
		if ($form.length === 0) {
			return;
		}

		const form = $form[0];
		if (!form) {
			return;
		}

		if (typeof block === 'function') {
			block($form);
			block(jQuery('div.cart_totals'));
		}

		const updateInput = document.createElement('input');
		updateInput.type = 'hidden';
		updateInput.name = 'update_cart';
		updateInput.value = 'Update Cart';
		form.appendChild(updateInput);

		const actionUrl = form.action || (typeof wc_cart_params !== 'undefined' ? wc_cart_params.cart_url : null) || window.location.href;

		jQuery.ajax({
			type: form.method || 'POST',
			url: actionUrl,
			data: jQuery(form).serialize(),
			dataType: 'html',
			success: function (response) {
				if (typeof update_wc_div === 'function') {
					update_wc_div(response);
				} else {
					const $response = jQuery(response);
					const $cartContent = $response.find('.woocommerce-cart-form, .Gstore-cart-form');
					const $cartTotals = $response.find('.cart_totals, .Gstore-cart-sidebar');

					if ($cartContent.length > 0) {
						$form.replaceWith($cartContent);
					}
					if ($cartTotals.length > 0) {
						jQuery('.cart_totals, .Gstore-cart-sidebar').replaceWith($cartTotals);
					}

					setTimeout(() => {
						initQuantitySelectors();
						initShippingChoices();
					}, 100);
				}

				jQuery(document.body).trigger('updated_wc_div');
			},
			complete: function () {
				if (form.contains(updateInput)) {
					form.removeChild(updateInput);
				}

				if (typeof unblock === 'function') {
					unblock($form);
					unblock(jQuery('div.cart_totals'));
				}
			},
		});
	}

	/**
	 * Debounce para evitar múltiplas chamadas de atualização.
	 */
	function scheduleCartUpdate() {
		clearTimeout(cartUpdateTimeout);
		cartUpdateTimeout = setTimeout(updateCartAutomatically, 400);
	}

	/**
	 * Adiciona botões de incremento/decremento ao seletor de quantidade
	 */
	function enhanceQuantityField(quantityContainer) {
		if (quantityContainer.dataset.gstoreQtyEnhanced === 'true') {
			return;
		}

		const quantityWrapper = quantityContainer.querySelector('.quantity');
		if (!quantityWrapper) {
			return;
		}

		const input = quantityWrapper.querySelector('input.qty, input.input-text');
		if (!input) {
			return;
		}

		quantityContainer.dataset.gstoreQtyEnhanced = 'true';
		input.style.appearance = 'none';
		input.style.MozAppearance = 'textfield';

		const controlsWrapper = document.createElement('div');
		controlsWrapper.className = 'Gstore-cart-card__quantity-controls';

		const minusBtn = document.createElement('button');
		minusBtn.type = 'button';
		minusBtn.className = 'quantity-button quantity-button--minus';
		minusBtn.setAttribute('aria-label', 'Diminuir quantidade');
		minusBtn.textContent = '−';
		minusBtn.setAttribute('tabindex', '0');

		const plusBtn = document.createElement('button');
		plusBtn.type = 'button';
		plusBtn.className = 'quantity-button quantity-button--plus';
		plusBtn.setAttribute('aria-label', 'Aumentar quantidade');
		plusBtn.textContent = '+';
		plusBtn.setAttribute('tabindex', '0');

		const lastUnitWarning = document.createElement('span');
		lastUnitWarning.className = 'gstore-last-unit-warning';
		lastUnitWarning.textContent = 'Última unidade';
		lastUnitWarning.style.display = 'none';

		controlsWrapper.appendChild(minusBtn);
		controlsWrapper.appendChild(input);
		controlsWrapper.appendChild(plusBtn);

		quantityWrapper.replaceWith(controlsWrapper);
		controlsWrapper.parentNode.insertBefore(lastUnitWarning, controlsWrapper.nextSibling);

		const getMin = () => {
			const min = parseFloat(input.min);
			return isNaN(min) ? 0 : min;
		};

		const getMax = () => {
			const max = parseFloat(input.max);
			return isNaN(max) || max <= 0 ? Number.MAX_SAFE_INTEGER : max;
		};

		const getStep = () => {
			const step = parseFloat(input.step);
			return isNaN(step) || step <= 0 ? 1 : step;
		};

		const getCurrentValue = () => {
			const value = parseFloat(input.value);
			return isNaN(value) ? getMin() : value;
		};

		const updateButtons = () => {
			const current = getCurrentValue();
			const min = getMin();
			const max = getMax();

			if (max < 2) {
				controlsWrapper.style.display = 'none';
				lastUnitWarning.style.display = 'inline-block';
			} else {
				controlsWrapper.style.display = 'inline-flex';
				lastUnitWarning.style.display = 'none';
				minusBtn.style.display = 'inline-flex';
				minusBtn.disabled = current <= min;
				plusBtn.disabled = current >= max;
			}
		};

		const setValue = (newValue) => {
			const min = getMin();
			const max = getMax();
			const step = getStep();
			let value = Math.max(min, Math.min(max, newValue));
			value = Math.round(value / step) * step;
			value = Math.max(min, Math.min(max, value));

			const oldValue = parseFloat(input.value) || 0;
			input.value = value;
			updateButtons();

			input.dispatchEvent(new Event('change', { bubbles: true }));
			input.dispatchEvent(new Event('input', { bubbles: true }));

			if (value !== oldValue) {
				scheduleCartUpdate();
			}
		};

		minusBtn.addEventListener('click', (e) => {
			e.preventDefault();
			setValue(getCurrentValue() - getStep());
		});

		plusBtn.addEventListener('click', (e) => {
			e.preventDefault();
			setValue(getCurrentValue() + getStep());
		});

		input.addEventListener('input', () => {
			const value = getCurrentValue();
			const min = getMin();
			const max = getMax();

			if (value < min) {
				setValue(min);
			} else if (value > max) {
				setValue(max);
			} else {
				updateButtons();
				scheduleCartUpdate();
			}
		});

		input.addEventListener('blur', () => {
			const value = getCurrentValue();
			const min = getMin();
			if (isNaN(value) || value < min) {
				setValue(min);
			} else {
				setValue(value);
			}
			scheduleCartUpdate();
		});

		input.addEventListener('keydown', (e) => {
			if (e.key === 'ArrowUp') {
				e.preventDefault();
				plusBtn.click();
			} else if (e.key === 'ArrowDown') {
				e.preventDefault();
				minusBtn.click();
			}
		});

		updateButtons();
	}

	function initQuantitySelectors() {
		const quantityContainers = document.querySelectorAll('.Gstore-cart-card__quantity');
		quantityContainers.forEach(enhanceQuantityField);
	}

	function initShippingChoices() {
		const shippingInputs = document.querySelectorAll('.Gstore-cart-card__shipping-option input[type="radio"]');
		shippingInputs.forEach((input) => {
			if (input.dataset.gstoreShippingBound === 'true') {
				return;
			}
			input.dataset.gstoreShippingBound = 'true';
			input.addEventListener('change', () => {
				scheduleCartUpdate();
			});
		});
	}

	function setupMutationObserver() {
		const cartForm = document.querySelector('.Gstore-cart-form, .woocommerce-cart-form');
		if (!cartForm) {
			return;
		}
		const observer = new MutationObserver(() => {
			initQuantitySelectors();
		});
		observer.observe(cartForm, { childList: true, subtree: true });
	}

	function init() {
		initQuantitySelectors();
		initShippingChoices();
		setupMutationObserver();
		ensureShippingBlocksExist();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	if (typeof jQuery !== 'undefined') {
		jQuery(document).on('updated_wc_div updated_cart_totals', function () {
			setTimeout(init, 100);
			ensureShippingBlocksExist();
			restoreCartCep();
			calculateRatesForCart(false);
		});

		jQuery(document).on('click', '.gstore-shipping-calculator__button', function () {
			calculateRatesForCart(true);
		});

		jQuery(document).on('input', '.gstore-shipping-calculator__cep', function () {
			storeCartCep(this.value || '');
			syncCartPostcodeField(this.value || '');
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', restoreCartCep);
	} else {
		restoreCartCep();
	}
})();

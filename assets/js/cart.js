/**
 * Cart JavaScript - Gstore Theme
 * Melhora o seletor de quantidade do carrinho com botões + e -
 */
(function () {
	'use strict';

	let cartUpdateTimeout = null;
	let ratesSyncInProgress = false;
	let shippingChoicesDelegated = false;
	const CART_CEP_STORAGE_KEY = 'gstore_cart_cep';
	const CART_MODE_STORAGE_KEY = 'gstore_cart_shipping_mode';
	const CART_CALCULATED_STORAGE_KEY = 'gstore_cart_shipping_calculated';
	const CART_CALCULATED_SESSION_KEY = 'gstore_cart_shipping_calculated_session';

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
			return;
		}

		const saved = window.localStorage.getItem(CART_CEP_STORAGE_KEY) || '';
		const digits = saved.replace(/\D/g, '');
		// #region agent log
		fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:35',message:'restoreCartCep: read storage',data:{hasInput:Boolean(cepInput),inputValue:cepInput.value || '',storedDigitsLength:digits.length},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H7'})}).catch(()=>{});
		// #endregion
		if (digits.length !== 8) {
			return;
		}

		cepInput.value = digits.replace(/(\d{5})(\d{3})/, '$1-$2');
		// #region agent log
		fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:41',message:'restoreCartCep: applied',data:{inputValue:cepInput.value || ''},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H7'})}).catch(()=>{});
		// #endregion
	}

	function storeCartCep(cep) {
		if (typeof window === 'undefined' || !window.localStorage) {
			return;
		}

		const digits = String(cep || '').replace(/\D/g, '');
		if (digits.length === 8) {
			window.localStorage.setItem(CART_CEP_STORAGE_KEY, digits);
		} else {
			window.localStorage.removeItem(CART_CALCULATED_STORAGE_KEY);
		}
	}

	function hasCalculatedShippingFlag() {
		if (typeof window === 'undefined' || !window.sessionStorage) {
			return false;
		}
		const value = window.sessionStorage.getItem(CART_CALCULATED_SESSION_KEY) === 'true';
		// #region agent log
		fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:57',message:'hasCalculatedShippingFlag',data:{value},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H10'})}).catch(()=>{});
		// #endregion
		return value;
	}

	function setCalculatedShippingFlag(value) {
		if (typeof window === 'undefined' || !window.sessionStorage) {
			return;
		}
		if (value) {
			window.sessionStorage.setItem(CART_CALCULATED_SESSION_KEY, 'true');
		} else {
			window.sessionStorage.removeItem(CART_CALCULATED_SESSION_KEY);
		}
	}

	function getStoredShippingMode(cartItemKey) {
		if (typeof window === 'undefined' || !window.localStorage || !cartItemKey) {
			return '';
		}

		try {
			const raw = window.localStorage.getItem(CART_MODE_STORAGE_KEY);
			if (!raw) {
				return '';
			}
			const parsed = JSON.parse(raw);
			if (!parsed || typeof parsed !== 'object') {
				return '';
			}
			return parsed[cartItemKey] || '';
		} catch (e) {
			return '';
		}
	}

	function storeShippingMode(cartItemKey, mode) {
		if (typeof window === 'undefined' || !window.localStorage || !cartItemKey) {
			return;
		}

		const normalized = normalizeRateMode(mode);
		if (!normalized) {
			return;
		}

		let payload = {};
		try {
			const raw = window.localStorage.getItem(CART_MODE_STORAGE_KEY);
			payload = raw ? JSON.parse(raw) : {};
		} catch (e) {
			payload = {};
		}

		payload[cartItemKey] = normalized;
		try {
			window.localStorage.setItem(CART_MODE_STORAGE_KEY, JSON.stringify(payload));
		} catch (e) {
			// ignore storage errors
		}
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

	function parsePriceValue(rawText) {
		if (!rawText) {
			return 0;
		}
		const text = String(rawText).trim();
		if (!text) {
			return 0;
		}
		const normalized = text
			.replace(/[^\d.,-]/g, '')
			.replace(/\.(?=\d{3})/g, '')
			.replace(',', '.');
		const value = parseFloat(normalized);
		return Number.isFinite(value) ? value : 0;
	}

	function formatCurrency(value) {
		const amount = Number.isFinite(value) ? value : 0;
		try {
			return new Intl.NumberFormat('pt-BR', {
				style: 'currency',
				currency: 'BRL',
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			}).format(amount);
		} catch (e) {
			return `R$ ${amount.toFixed(2)}`.replace('.', ',');
		}
	}

	function getCartSubtotalValue() {
		const subtotalEl = document.querySelector('.cart_totals .cart-subtotal .woocommerce-Price-amount, .cart_totals .cart-subtotal td');
		if (!subtotalEl) {
			return 0;
		}
		return parsePriceValue(subtotalEl.textContent || '');
	}

	function hasCalculatedShipping() {
		const cep = getCartCep();
		// #region agent log
		fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:149',message:'hasCalculatedShipping: start',data:{cepPresent:Boolean(cep)},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H4'})}).catch(()=>{});
		// #endregion
		if (!cep) {
			return false;
		}
		const rateInputs = document.querySelectorAll('input[name^="gstore_shipping_rates["]');
		// #region agent log
		fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:154',message:'hasCalculatedShipping: rate inputs count',data:{count:rateInputs.length},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H4'})}).catch(()=>{});
		// #endregion
		for (const input of rateInputs) {
			try {
				const parsed = JSON.parse(input.value || '[]');
				if (Array.isArray(parsed) && parsed.length) {
					// #region agent log
					fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:158',message:'hasCalculatedShipping: found rates',data:{firstRateCount:parsed.length},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H4'})}).catch(()=>{});
					// #endregion
					return true;
				}
			} catch (e) {
				// ignore malformed JSON
			}
		}
		return false;
	}

	function ensureShippingNotice(summaryCard) {
		if (!summaryCard) {
			return null;
		}
		let notice = summaryCard.querySelector('[data-gstore-shipping-notice]');
		if (!notice) {
			notice = document.createElement('div');
			notice.className = 'gstore-cart-shipping-notice';
			notice.setAttribute('data-gstore-shipping-notice', 'true');
			notice.textContent = 'Informe o CEP e calcule o frete para continuar.';
			summaryCard.insertBefore(notice, summaryCard.firstChild);
		}
		return notice;
	}

	function updateCheckoutAvailability() {
		const summaryCard = document.querySelector('.Gstore-cart-summary-card');
		const checkoutButton = summaryCard
			? summaryCard.querySelector('.checkout-button, .wc-proceed-to-checkout .button')
			: document.querySelector('.checkout-button, .wc-proceed-to-checkout .button');

		if (!checkoutButton) {
			return;
		}

		if (ratesSyncInProgress && hasCalculatedShippingFlag()) {
			// #region agent log
			fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:193',message:'updateCheckoutAvailability: skipped (ratesSyncInProgress)',data:{ratesSyncInProgress},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H9'})}).catch(()=>{});
			// #endregion
			return;
		}

		const canProceed = hasCalculatedShipping();
		// #region agent log
		fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:193',message:'updateCheckoutAvailability',data:{canProceed},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H4'})}).catch(()=>{});
		// #endregion
		const notice = ensureShippingNotice(summaryCard);

		if (notice) {
			notice.style.display = canProceed ? 'none' : 'block';
		}

		checkoutButton.classList.toggle('is-disabled', !canProceed);
		checkoutButton.setAttribute('aria-disabled', canProceed ? 'false' : 'true');
		checkoutButton.dataset.gstoreDisabled = canProceed ? 'false' : 'true';
	}

	function getRatesForItem(cartItemKey) {
		if (!cartItemKey) {
			return [];
		}
		const ratesInput = document.querySelector(`input[name="gstore_shipping_rates[${cartItemKey}]"]`);
		if (!ratesInput || !ratesInput.value) {
			return [];
		}
		try {
			const parsed = JSON.parse(ratesInput.value);
			return Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			return [];
		}
	}

	function resolveSelectedMode(shippingBlock, cartItemKey, rates) {
		const selectedInput = shippingBlock.querySelector('input[type="radio"]:checked');
		const hiddenMode = shippingBlock.querySelector(`input[type="hidden"][name="gstore_shipping_mode[${cartItemKey}]"]`);
		const storedMode = getStoredShippingMode(cartItemKey);
		let mode = selectedInput
			? selectedInput.value
			: (hiddenMode && hiddenMode.value) || storedMode || shippingBlock.dataset.lastSelectedMode || '';
		mode = normalizeRateMode(mode);
		const modes = (rates || []).map((rate) => normalizeRateMode(rate.mode)).filter(Boolean);
		if (!mode || (modes.length && !modes.includes(mode))) {
			mode = modes[0] || '';
		}
		return mode;
	}

	function ensureTotalsRow(table, className, label) {
		if (!table) {
			return null;
		}
		let row = table.querySelector(`tr.${className}`);
		if (!row) {
			row = document.createElement('tr');
			row.className = className;
			row.innerHTML = `
				<th>${label}</th>
				<td data-gstore-value="true">-</td>
			`;
			const subtotalRow = table.querySelector('.cart-subtotal');
			if (subtotalRow && subtotalRow.parentNode) {
				subtotalRow.parentNode.insertBefore(row, subtotalRow.nextSibling);
			} else {
				table.appendChild(row);
			}
		}
		return row;
	}

	function updateCartTotalsSummary() {
		const totalsTable = document.querySelector('.Gstore-cart-summary-card .cart_totals table, .cart_totals table');
		if (!totalsTable) {
			return;
		}

		const subtotalValue = getCartSubtotalValue();
		const groundRow = ensureTotalsRow(totalsTable, 'gstore-shipping-ground', 'Frete terrestre');
		const airRow = ensureTotalsRow(totalsTable, 'gstore-shipping-air', 'Frete aéreo');

		const shippingBlocks = document.querySelectorAll('[data-gstore-shipping-item]');
		let groundTotal = 0;
		let airTotal = 0;
		let selectedTotal = 0;
		let hasGround = false;
		let hasAir = false;
		const selectedModes = new Set();

		shippingBlocks.forEach((shippingBlock) => {
			const itemEl = shippingBlock.closest('[data-cart-item-key]');
			if (!itemEl) {
				return;
			}
			const cartItemKey = itemEl.dataset.cartItemKey || itemEl.getAttribute('data-cart-item-key');
			if (!cartItemKey) {
				return;
			}
			const rates = getRatesForItem(cartItemKey);
			if (!rates.length) {
				return;
			}

			rates.forEach((rate) => {
				const mode = normalizeRateMode(rate.mode);
				const costValue = Number.isFinite(Number(rate.cost)) ? Number(rate.cost) : parsePriceValue(rate.cost_formatted || '');
				if (!mode || !Number.isFinite(costValue)) {
					return;
				}
				if (mode === 'land') {
					groundTotal += costValue;
					hasGround = true;
				} else if (mode === 'air') {
					airTotal += costValue;
					hasAir = true;
				}
			});

			const selectedMode = resolveSelectedMode(shippingBlock, cartItemKey, rates);
			if (selectedMode) {
				selectedModes.add(selectedMode);
				const selectedRate = rates.find((rate) => normalizeRateMode(rate.mode) === selectedMode);
				if (selectedRate) {
					const selectedCost = Number.isFinite(Number(selectedRate.cost))
						? Number(selectedRate.cost)
						: parsePriceValue(selectedRate.cost_formatted || '');
					if (Number.isFinite(selectedCost)) {
						selectedTotal += selectedCost;
					}
				}
			}
		});

		if (groundRow) {
			const valueCell = groundRow.querySelector('[data-gstore-value="true"]') || groundRow.querySelector('td');
			if (valueCell) {
				valueCell.innerHTML = hasGround ? formatCurrency(groundTotal) : '-';
			}
		}
		if (airRow) {
			const valueCell = airRow.querySelector('[data-gstore-value="true"]') || airRow.querySelector('td');
			if (valueCell) {
				valueCell.innerHTML = hasAir ? formatCurrency(airTotal) : '-';
			}
		}

		if (selectedModes.size === 1) {
			const [onlyMode] = Array.from(selectedModes);
			if (groundRow) {
				groundRow.style.display = onlyMode === 'land' ? '' : 'none';
			}
			if (airRow) {
				airRow.style.display = onlyMode === 'air' ? '' : 'none';
			}
		} else {
			if (groundRow) {
				groundRow.style.display = '';
			}
			if (airRow) {
				airRow.style.display = '';
			}
		}

		const totalValue = subtotalValue + selectedTotal;
		const orderTotalCell = totalsTable.querySelector('.order-total td .woocommerce-Price-amount, .order-total td');
		if (orderTotalCell) {
			orderTotalCell.innerHTML = formatCurrency(totalValue);
		}
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

	function fetchRatesForItem(itemEl, cep) {
		const productId = parseInt(itemEl.dataset.productId || '0', 10);
		const quantity = parseInt(itemEl.dataset.quantity || '1', 10);

		if (!productId || !quantity || !cep) {
			return Promise.resolve(null);
		}

		return jQuery.ajax({
			url: getShippingAjaxUrl(),
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'gstore_calculate_shipping',
				nonce: typeof gstoreShippingCalculator !== 'undefined' ? gstoreShippingCalculator.nonce : '',
				postcode: cep,
				product_id: productId,
				quantity: quantity,
			},
		}).then((response) => {
			if (!response || !response.success || !response.data || !Array.isArray(response.data.rates)) {
				return null;
			}
			return response.data.rates;
		}).catch(() => null);
	}

	function updateShippingBlock(shippingBlock, rates, cartItemKey, selectedMode) {
		if (!shippingBlock) {
			return;
		}

		const optionsContainer = shippingBlock.querySelector('[data-gstore-shipping-options]');
		const fixedContainer = shippingBlock.querySelector('[data-gstore-shipping-fixed]');
		let ratesInput = shippingBlock.querySelector(`input[name="gstore_shipping_rates[${cartItemKey}]"]`);

		const normalizedRates = (rates || [])
			.map((rate) => ({
				mode: normalizeRateMode(rate.mode),
				label: rate.label || '',
				cost: rate.cost || '',
				cost_formatted: rate.cost_formatted || '',
			}))
			.filter((rate) => rate.mode);

		if (!ratesInput) {
			ratesInput = document.createElement('input');
			ratesInput.type = 'hidden';
			ratesInput.name = `gstore_shipping_rates[${cartItemKey}]`;
			shippingBlock.appendChild(ratesInput);
		}
		ratesInput.value = JSON.stringify(normalizedRates);

		if (!normalizedRates.length) {
			return;
		}

		const hasMultiple = normalizedRates.length > 1;
		const modes = normalizedRates.map((rate) => rate.mode);
		let resolvedMode = normalizeRateMode(selectedMode);
		if (!resolvedMode || !modes.includes(resolvedMode)) {
			resolvedMode = modes[0] || '';
		}
		if (resolvedMode) {
			shippingBlock.dataset.lastSelectedMode = resolvedMode;
			storeShippingMode(cartItemKey, resolvedMode);
		}
		const optionsHtml = hasMultiple
			? normalizedRates.map((rate) => {
					const mode = rate.mode;
				const label = rate.label || (mode === 'air' ? 'Frete Aéreo' : 'Frete Terrestre');
				const cost = rate.cost_formatted || '-';
				const checked = resolvedMode === mode ? 'checked' : '';
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
				storeShippingMode(cartItemKey, onlyRate.mode);
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

		// #region agent log
		fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:485',message:'updateShippingBlock: applied',data:{cartItemKey,rateCount:normalizedRates.length,resolvedMode},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H5'})}).catch(()=>{});
		// #endregion
		initShippingChoices();
		updateCartTotalsSummary();
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

			const cartItemKey = item.dataset.cartItemKey || item.getAttribute('data-cart-item-key');
			if (!cartItemKey) {
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
				<input type="hidden" name="gstore_shipping_rates[${cartItemKey}]" value="" />
			`;
			body.appendChild(shippingBlock);
		});
	}

	function calculateRatesForCart(shouldUpdateCart) {
		if (ratesSyncInProgress) {
			return;
		}

		let cep = getCartCep();
		let cepSource = 'input';
		if (!cep) {
			if (typeof window !== 'undefined' && window.localStorage) {
				const saved = window.localStorage.getItem(CART_CEP_STORAGE_KEY) || '';
				const digits = saved.replace(/\D/g, '');
				if (digits.length === 8) {
					cep = digits;
					cepSource = 'storage';
				}
			}
		}

		if (!cep) {
			// #region agent log
			fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:535',message:'calculateRatesForCart: aborted (no cep)',data:{shouldUpdateCart},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H6'})}).catch(()=>{});
			// #endregion
			return;
		}

		storeCartCep(cep);
		// #region agent log
		fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:539',message:'calculateRatesForCart: start',data:{shouldUpdateCart,cepPresent:Boolean(cep),cepSource},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H6'})}).catch(()=>{});
		// #endregion

		const shippingBlocks = document.querySelectorAll('[data-gstore-shipping-item]');
		if (!shippingBlocks.length) {
			return;
		}

		ratesSyncInProgress = true;

		const requests = [];
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
			const hiddenMode = shippingBlock.querySelector(`input[type="hidden"][name="gstore_shipping_mode[${cartItemKey}]"]`);
			const storedMode = getStoredShippingMode(cartItemKey);
			const selectedMode = selectedInput
				? selectedInput.value
				: (hiddenMode && hiddenMode.value) || storedMode || shippingBlock.dataset.lastSelectedMode || 'land';

			requests.push(
				fetchRatesForItem(itemEl, cep).then((rates) => {
					if (!rates) {
						return;
					}
					updateShippingBlock(shippingBlock, rates, cartItemKey, selectedMode);
				})
			);
		});

		Promise.allSettled(requests).then(() => {
			ratesSyncInProgress = false;
			updateCartTotalsSummary();
			// #region agent log
			fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:579',message:'calculateRatesForCart: completed',data:{shouldUpdateCart},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H5'})}).catch(()=>{});
			// #endregion
			setCalculatedShippingFlag(true);
			updateCheckoutAvailability();
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
		// #region agent log
		fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:615',message:'updateCartAutomatically: start',data:{actionUrl},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H1'})}).catch(()=>{});
		// #endregion

		jQuery.ajax({
			type: form.method || 'POST',
			url: actionUrl,
			data: jQuery(form).serialize(),
			dataType: 'html',
			success: function (response) {
				// #region agent log
				fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:622',message:'updateCartAutomatically: success',data:{hasUpdateWcDiv:typeof update_wc_div === 'function',responseLength:typeof response === 'string' ? response.length : null},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H1'})}).catch(()=>{});
				// #endregion
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
						updateCartTotalsSummary();
					}, 100);
				}

				jQuery(document.body).trigger('updated_wc_div');
			},
			complete: function () {
				// #region agent log
				fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:646',message:'updateCartAutomatically: complete',data:{},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H1'})}).catch(()=>{});
				// #endregion
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
		// #region agent log
		fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:662',message:'scheduleCartUpdate',data:{},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H1'})}).catch(()=>{});
		// #endregion
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
		if (shippingChoicesDelegated) {
			return;
		}

		document.addEventListener('change', (event) => {
			const target = event.target;
			if (!(target instanceof HTMLInputElement)) {
				return;
			}
			if (!target.matches('.Gstore-cart-card__shipping-option input[type="radio"]')) {
				return;
			}

			const shippingBlock = target.closest('[data-gstore-shipping-item]');
			const itemEl = target.closest('[data-cart-item-key]');
			if (!shippingBlock || !itemEl) {
				return;
			}

			const cartItemKey = itemEl.dataset.cartItemKey || itemEl.getAttribute('data-cart-item-key');
			if (!cartItemKey) {
				return;
			}

			shippingBlock.dataset.lastSelectedMode = target.value;
			storeShippingMode(cartItemKey, target.value);

			let hiddenModeInput = shippingBlock.querySelector(`input[data-gstore-mode-hidden="true"][name="gstore_shipping_mode[${cartItemKey}]"]`);
			if (!hiddenModeInput) {
				hiddenModeInput = document.createElement('input');
				hiddenModeInput.type = 'hidden';
				hiddenModeInput.name = `gstore_shipping_mode[${cartItemKey}]`;
				hiddenModeInput.setAttribute('data-gstore-mode-hidden', 'true');
				shippingBlock.appendChild(hiddenModeInput);
			}
			hiddenModeInput.value = target.value;

			// #region agent log
			fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:867',message:'shipping mode changed',data:{cartItemKey,mode:target.value},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H2'})}).catch(()=>{});
			// #endregion
			updateCartTotalsSummary();
			updateCheckoutAvailability();
		});

		shippingChoicesDelegated = true;
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
		updateCartTotalsSummary();
		updateCheckoutAvailability();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	if (typeof jQuery !== 'undefined') {
		jQuery(document).on('updated_wc_div updated_cart_totals', function () {
			// #region agent log
			fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:902',message:'updated_wc_div/updated_cart_totals fired',data:{},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H6'})}).catch(()=>{});
			// #endregion
			setTimeout(init, 100);
			ensureShippingBlocksExist();
			// #region agent log
			fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:906',message:'updated_wc_div: before restoreCartCep',data:{},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H7'})}).catch(()=>{});
			// #endregion
			restoreCartCep();
			// #region agent log
			fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:908',message:'updated_wc_div: after restoreCartCep',data:{},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H7'})}).catch(()=>{});
			// #endregion
			const shouldRecalculate = hasCalculatedShippingFlag();
			// #region agent log
			fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:913',message:'updated_wc_div: decide recalculation',data:{shouldRecalculate},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H8'})}).catch(()=>{});
			// #endregion
			if (shouldRecalculate) {
				calculateRatesForCart(false);
			} else {
				updateCartTotalsSummary();
				updateCheckoutAvailability();
			}
		});

		jQuery(document).on('click', '.gstore-shipping-calculator__button', function () {
			// #region agent log
			fetch('http://127.0.0.1:7247/ingest/cce9ccaa-d42e-4577-9651-ba22a488615c',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'cart.js:910',message:'shipping calculator clicked',data:{},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-fix',hypothesisId:'H5'})}).catch(()=>{});
			// #endregion
			setCalculatedShippingFlag(true);
			calculateRatesForCart(false);
			updateCartTotalsSummary();
			updateCheckoutAvailability();
		});

		jQuery(document).on('input', '.gstore-shipping-calculator__cep', function () {
			storeCartCep(this.value || '');
			if (!getCartCep()) {
				setCalculatedShippingFlag(false);
			}
			updateCheckoutAvailability();
		});

		jQuery(document).on('click', '.checkout-button, .wc-proceed-to-checkout .button', function (event) {
			const target = event.currentTarget;
			if (target && target.dataset.gstoreDisabled === 'true') {
				event.preventDefault();
				updateCheckoutAvailability();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', restoreCartCep);
	} else {
		restoreCartCep();
	}
})();

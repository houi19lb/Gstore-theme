/**
 * Cart JavaScript - Gstore Theme
 * Melhora o seletor de quantidade do carrinho com botões + e -
 */
(function () {
	'use strict';

	let cartUpdateTimeout = null;
	let ratesSyncInProgress = false;
	let shippingChoicesDelegated = false;
	let initialRatesHydrated = false;
	let mixedCartActionsDelegated = false;
	const CART_CEP_STORAGE_KEY = 'gstore_cart_cep';
	const CART_MODE_STORAGE_KEY = 'gstore_cart_shipping_mode';
	const CART_SELECTED_RATE_STORAGE_KEY = 'gstore_cart_selected_shipping_rate';
	const CART_CALCULATED_SESSION_KEY = 'gstore_cart_shipping_calculated_session';
	const CART_RATES_STORAGE_KEY = 'gstore_cart_shipping_rates';

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

	function hasCalculatedShippingFlag() {
		if (typeof window === 'undefined' || !window.sessionStorage) {
			return false;
		}
		const value = window.sessionStorage.getItem(CART_CALCULATED_SESSION_KEY) === 'true';
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

	function getStoredSelectedRateId(cartItemKey) {
		if (typeof window === 'undefined' || !window.localStorage || !cartItemKey) {
			return '';
		}

		try {
			const raw = window.localStorage.getItem(CART_SELECTED_RATE_STORAGE_KEY);
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

	function storeSelectedRateId(cartItemKey, rateId) {
		if (typeof window === 'undefined' || !window.localStorage || !cartItemKey) {
			return;
		}

		const normalized = String(rateId || '').trim();
		if (!normalized) {
			return;
		}

		let payload = {};
		try {
			const raw = window.localStorage.getItem(CART_SELECTED_RATE_STORAGE_KEY);
			payload = raw ? JSON.parse(raw) : {};
		} catch (e) {
			payload = {};
		}

		payload[cartItemKey] = normalized;
		try {
			window.localStorage.setItem(CART_SELECTED_RATE_STORAGE_KEY, JSON.stringify(payload));
		} catch (e) {
			// ignore storage errors
		}
	}

	function storeShippingRates(cartItemKey, rates) {
		if (typeof window === 'undefined' || !window.localStorage || !cartItemKey) {
			return;
		}
		let payload = {};
		try {
			const raw = window.localStorage.getItem(CART_RATES_STORAGE_KEY);
			payload = raw ? JSON.parse(raw) : {};
		} catch (e) {
			payload = {};
		}
		payload[cartItemKey] = Array.isArray(rates) ? rates : [];
		try {
			window.localStorage.setItem(CART_RATES_STORAGE_KEY, JSON.stringify(payload));
		} catch (e) {
			// ignore storage errors
		}
	}

	function parseAndNormalizeRates(rawRates) {
		const rates = Array.isArray(rawRates) ? rawRates : [];
		return rates
			.map((rate) => ({
				rate_id: rate && typeof rate === 'object' ? String(rate.rate_id || rate.id || '') : '',
				mode: normalizeRateMode(rate && (rate.mode || rate.label || '')),
				label: rate && rate.label ? String(rate.label) : '',
				carrier_id: rate && rate.carrier_id ? String(rate.carrier_id) : '',
				service_id: rate && rate.service_id ? String(rate.service_id) : '',
				cost: rate && typeof rate === 'object' && 'cost' in rate ? rate.cost : '',
				cost_formatted: rate && typeof rate === 'object' && 'cost_formatted' in rate ? rate.cost_formatted : '',
			}))
			.filter((rate) => rate.mode && rate.rate_id);
	}

	function parseRatesFromInputValue(rawValue) {
		if (!rawValue) {
			return [];
		}
		try {
			const parsed = JSON.parse(rawValue);
			return parseAndNormalizeRates(parsed);
		} catch (e) {
			return [];
		}
	}

	function getCurrentCartItemKeys() {
		const keys = [];
		document.querySelectorAll('[data-cart-item-key]').forEach((el) => {
			const key = el.dataset.cartItemKey || el.getAttribute('data-cart-item-key') || '';
			if (key) {
				keys.push(String(key));
			}
		});
		return keys;
	}

	function pruneShippingStorageToCurrentCart() {
		if (typeof window === 'undefined' || !window.localStorage) {
			return;
		}

		const currentKeys = new Set(getCurrentCartItemKeys());
		if (!currentKeys.size) {
			return;
		}

		const prunePayload = (storageKey) => {
			let payload = {};
			try {
				const raw = window.localStorage.getItem(storageKey);
				payload = raw ? JSON.parse(raw) : {};
			} catch (e) {
				payload = {};
			}

			if (!payload || typeof payload !== 'object') {
				return;
			}

			let changed = false;
			Object.keys(payload).forEach((key) => {
				if (key === 'default') {
					return;
				}
				if (!currentKeys.has(key)) {
					delete payload[key];
					changed = true;
				}
			});

			if (changed) {
				try {
					window.localStorage.setItem(storageKey, JSON.stringify(payload));
				} catch (e) {
					// ignore storage errors
				}
			}
		};

		prunePayload(CART_RATES_STORAGE_KEY);
		prunePayload(CART_MODE_STORAGE_KEY);
		prunePayload(CART_SELECTED_RATE_STORAGE_KEY);
	}

	function syncDomShippingRatesToStorage() {
		document.querySelectorAll('input[name^="gstore_shipping_rates["]').forEach((input) => {
			const match = String(input.name || '').match(/^gstore_shipping_rates\[(.+)\]$/);
			if (!match || !match[1]) {
				return;
			}
			const cartItemKey = String(match[1]);
			const normalizedRates = parseRatesFromInputValue(input.value || '');
			if (!normalizedRates.length) {
				return;
			}
			input.value = JSON.stringify(normalizedRates);
			storeShippingRates(cartItemKey, normalizedRates);
		});
	}

	function restoreShippingRatesFromStorage() {
		if (typeof window === 'undefined' || !window.localStorage) {
			return;
		}

		let storedRates = null;
		try {
			const raw = window.localStorage.getItem(CART_RATES_STORAGE_KEY);
			if (!raw) {
				return;
			}
			storedRates = JSON.parse(raw);
		} catch (e) {
			return;
		}

		if (!storedRates || typeof storedRates !== 'object') {
			return;
		}

		Object.keys(storedRates).forEach((cartItemKey) => {
			const storedNormalizedRates = parseAndNormalizeRates(storedRates[cartItemKey]);
			if (!storedNormalizedRates.length) {
				return;
			}

			let ratesInput = document.querySelector(`input[name="gstore_shipping_rates[${cartItemKey}]"]`);
			if (!ratesInput) {
				const shippingBlock = document.querySelector(
					`[data-cart-item-key="${cartItemKey}"] [data-gstore-shipping-item]`
				);
				if (!shippingBlock) {
					return;
				}
				ratesInput = document.createElement('input');
				ratesInput.type = 'hidden';
				ratesInput.name = `gstore_shipping_rates[${cartItemKey}]`;
				shippingBlock.appendChild(ratesInput);
			}

			const currentRates = parseRatesFromInputValue(ratesInput.value || '');
			if (currentRates.length) {
				// Prefer server-rendered/cart-session rates to avoid stale localStorage overriding newer modes (ex.: pickup).
				ratesInput.value = JSON.stringify(currentRates);
				storeShippingRates(cartItemKey, currentRates);
				return;
			}

			ratesInput.value = JSON.stringify(storedNormalizedRates);
		});
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

	function getMixedCartConfig() {
		const localizedCartData = typeof window !== 'undefined' && window.gstoreCartData && typeof window.gstoreCartData === 'object'
			? window.gstoreCartData
			: {};
		const localizedWooData = typeof window !== 'undefined' && window.gstore_wc && typeof window.gstore_wc === 'object'
			? window.gstore_wc
			: {};

		return {
			ajaxUrl: localizedCartData.ajax_url || localizedWooData.ajax_url || getShippingAjaxUrl(),
			mixedCartNonce: localizedCartData.mixed_cart_nonce || localizedWooData.mixed_cart_nonce || '',
			checkoutUrl: localizedCartData.checkout_url || '',
		};
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
		const calculator = document.querySelector('.gstore-shipping-calculator--cart');
		if (!calculator) {
			return true;
		}

		const cep = getCartCep();
		if (!cep) {
			return false;
		}
		const rateInputs = document.querySelectorAll('input[name^="gstore_shipping_rates["]');
		if (!rateInputs.length) {
			return false;
		}
		for (const input of rateInputs) {
			try {
				const parsed = JSON.parse(input.value || '[]');
				if (Array.isArray(parsed) && parsed.length) {
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

		const hasCalculator = !!document.querySelector('.gstore-shipping-calculator--cart');
		if (!hasCalculator) {
			const notice = ensureShippingNotice(summaryCard);
			if (notice) {
				notice.style.display = 'none';
			}
			checkoutButton.classList.remove('is-disabled');
			checkoutButton.setAttribute('aria-disabled', 'false');
			checkoutButton.dataset.gstoreDisabled = 'false';
			return;
		}

		if (ratesSyncInProgress && hasCalculatedShippingFlag()) {
			return;
		}

		const canProceed = hasCalculatedShipping();
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

	function resolveSelectedRate(shippingBlock, cartItemKey, rates) {
		const selectedInput = shippingBlock.querySelector('input[type="radio"]:checked');
		const hiddenMode = shippingBlock.querySelector(`input[type="hidden"][name="gstore_shipping_mode[${cartItemKey}]"]`);
		const hiddenRateId = shippingBlock.querySelector(`input[type="hidden"][name="gstore_selected_shipping_rate[${cartItemKey}]"]`);
		const storedMode = getStoredShippingMode(cartItemKey);
		const storedRateId = getStoredSelectedRateId(cartItemKey);
		const selectedRateId = selectedInput
			? String(selectedInput.value || '')
			: (hiddenRateId && hiddenRateId.value) || storedRateId || shippingBlock.dataset.lastSelectedRateId || '';
		let selectedRate = (rates || []).find((rate) => String(rate.rate_id || '') === selectedRateId);
		if (!selectedRate) {
			const fallbackMode = normalizeRateMode((hiddenMode && hiddenMode.value) || storedMode || shippingBlock.dataset.lastSelectedMode || '');
			selectedRate = (rates || []).find((rate) => normalizeRateMode(rate.mode) === fallbackMode) || (rates || [])[0] || null;
		}
		return selectedRate;
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

			const selectedRate = resolveSelectedRate(shippingBlock, cartItemKey, rates);
			if (selectedRate) {
				const selectedMode = normalizeRateMode(selectedRate.mode);
				selectedModes.add(selectedMode);
				const selectedCost = Number.isFinite(Number(selectedRate.cost))
					? Number(selectedRate.cost)
					: parsePriceValue(selectedRate.cost_formatted || '');
				if (Number.isFinite(selectedCost)) {
					selectedTotal += selectedCost;
					if (selectedMode === 'land') {
						groundTotal += selectedCost;
						hasGround = true;
					} else if (selectedMode === 'air') {
						airTotal += selectedCost;
						hasAir = true;
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
		if (value === 'air' || value === 'aereo' || value === 'aéreo') {
			return 'air';
		}
		if (value === 'ground' || value === 'land' || value === 'terrestre') {
			return 'land';
		}
		if (value === 'pickup' || value === 'retirada' || value === 'retirada na loja' || value === 'retirada-na-loja' || value === 'store_pickup') {
			return 'pickup';
		}
		return '';
	}

	function getRateModeLabel(mode) {
		const normalized = normalizeRateMode(mode);
		if (normalized === 'air') {
			return 'Aéreo';
		}
		if (normalized === 'pickup') {
			return 'Retirada na loja';
		}
		return 'Terrestre';
	}

	function getRateDisplayLabel(rate) {
		const mode = normalizeRateMode(rate && rate.mode);
		const kind = String((rate && rate.rate_kind) || '');
		const label = String((rate && rate.label) || '').trim();
		const rateId = String((rate && rate.rate_id) || '').trim();
		const isLegacyRate = kind === 'legacy_mode'
			|| rateId === 'gstore_custom_shipping:land'
			|| rateId === 'gstore_custom_shipping:air';
		if (isLegacyRate) {
			return 'Transportadora padrão';
		}
		if (kind === 'pickup' || mode === 'pickup') {
			return label || 'Retirada na loja';
		}
		return label || getRateModeLabel(mode);
	}

	function groupRatesByMode(rates) {
		const groups = new Map();
		(rates || []).forEach((rate) => {
			const mode = normalizeRateMode(rate && rate.mode);
			if (!mode) {
				return;
			}
			if (!groups.has(mode)) {
				groups.set(mode, []);
			}
			groups.get(mode).push(rate);
		});

		return ['land', 'air', 'pickup']
			.filter((mode) => groups.has(mode))
			.map((mode) => ({
				mode,
				label: getRateModeLabel(mode),
				rates: groups.get(mode) || [],
			}));
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
				rate_id: String(rate.rate_id || rate.id || ''),
				// Alinha com o checkout: quando mode não vier preenchido, tenta inferir pelo label.
				mode: normalizeRateMode(rate.mode || rate.label || ''),
				label: rate.label || '',
				carrier_id: rate.carrier_id || '',
				service_id: rate.service_id || '',
				cost: rate.cost || '',
				cost_formatted: rate.cost_formatted || '',
			}))
			.filter((rate) => rate.mode && rate.rate_id);
		storeShippingRates(cartItemKey, normalizedRates);

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
		const storedRateId = getStoredSelectedRateId(cartItemKey);
		let resolvedRate = normalizedRates.find((rate) => rate.rate_id === selectedMode)
			|| normalizedRates.find((rate) => rate.rate_id === storedRateId)
			|| normalizedRates.find((rate) => normalizeRateMode(rate.mode) === normalizeRateMode(selectedMode))
			|| normalizedRates[0]
			|| null;
		if (resolvedRate) {
			shippingBlock.dataset.lastSelectedMode = resolvedRate.mode;
			shippingBlock.dataset.lastSelectedRateId = resolvedRate.rate_id;
			storeShippingMode(cartItemKey, resolvedRate.mode);
			storeSelectedRateId(cartItemKey, resolvedRate.rate_id);
		}
		const optionsHtml = hasMultiple
			? groupRatesByMode(normalizedRates).map((group) => {
				const groupOptionsHtml = group.rates.map((rate) => {
					const cost = rate.cost_formatted || '-';
					const checked = resolvedRate && resolvedRate.rate_id === rate.rate_id ? 'checked' : '';
					const label = getRateDisplayLabel(rate);
					return `
					<label class="Gstore-cart-card__shipping-option">
						<input type="radio" name="gstore_selected_shipping_rate[${cartItemKey}]" value="${rate.rate_id}" data-gstore-mode="${group.mode}" ${checked} />
						<span class="Gstore-cart-card__shipping-text">${label}</span>
						<span class="Gstore-cart-card__shipping-price">${cost}</span>
					</label>
				`;
				}).join('');

				return `
				<div class="Gstore-cart-card__shipping-group Gstore-cart-card__shipping-group--${group.mode}">
					<div class="Gstore-cart-card__shipping-group-title">${group.label}</div>
					<div class="Gstore-cart-card__shipping-group-options">
						${groupOptionsHtml}
					</div>
				</div>
			`;
			}).join('')
			: '';

		const fixedHtml = !hasMultiple
			? (() => {
				const onlyRate = normalizedRates[0];
				const modeLabel = getRateModeLabel(onlyRate.mode);
				const label = getRateDisplayLabel(onlyRate);
				const cost = onlyRate.cost_formatted || '-';
				storeShippingMode(cartItemKey, onlyRate.mode);
				storeSelectedRateId(cartItemKey, onlyRate.rate_id);
				return `
					<div class="Gstore-cart-card__shipping-group Gstore-cart-card__shipping-group--${onlyRate.mode}">
						<div class="Gstore-cart-card__shipping-group-title">${modeLabel}</div>
						<div class="Gstore-cart-card__shipping-fixed-option">
							<span class="Gstore-cart-card__shipping-text">${label}</span>
							<span class="Gstore-cart-card__shipping-price">${cost}</span>
							<input type="hidden" name="gstore_selected_shipping_rate[${cartItemKey}]" value="${onlyRate.rate_id}" />
							<input type="hidden" name="gstore_shipping_mode[${cartItemKey}]" value="${onlyRate.mode}" />
						</div>
					</div>
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
			return;
		}

		storeCartCep(cep);

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
			const hiddenRateId = shippingBlock.querySelector(`input[type="hidden"][name="gstore_selected_shipping_rate[${cartItemKey}]"]`);
			const storedMode = getStoredShippingMode(cartItemKey);
			const selectedRateId = selectedInput
				? String(selectedInput.value || '')
				: (hiddenRateId && hiddenRateId.value) || getStoredSelectedRateId(cartItemKey) || '';
			const selectedMode = selectedInput
				? String(selectedInput.dataset.gstoreMode || '')
				: (hiddenMode && hiddenMode.value) || storedMode || shippingBlock.dataset.lastSelectedMode || 'land';

			requests.push(
				fetchRatesForItem(itemEl, cep).then((rates) => {
					if (!rates) {
						return;
					}
					updateShippingBlock(shippingBlock, rates, cartItemKey, selectedRateId || selectedMode);
				})
			);
		});

		Promise.allSettled(requests).then(() => {
			ratesSyncInProgress = false;
			updateCartTotalsSummary();
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
					const shouldPreserveTotals = hasCalculatedShippingFlag();

					if ($cartContent.length > 0) {
						if (shouldPreserveTotals) {
							// Atualização cirúrgica: apenas preços e quantidades
							const $newItems = $cartContent.find('[data-cart-item-key]');
							$newItems.each(function() {
								const $newItem = jQuery(this);
								const key = $newItem.attr('data-cart-item-key');
								if (!key) return;
								const $currentItem = jQuery(`[data-cart-item-key="${key}"]`);
								if ($currentItem.length === 0) return;
								// Atualiza preço do item
								const $newPrice = $newItem.find('.Gstore-cart-card__price, .product-price');
								const $currentPrice = $currentItem.find('.Gstore-cart-card__price, .product-price');
								if ($newPrice.length > 0 && $currentPrice.length > 0) {
									$currentPrice.html($newPrice.html());
								}
								// Subtotal do item (card)
								const $newItemSubtotal = $newItem.find('.Gstore-cart-card__subtotal, .product-subtotal, .line-subtotal');
								const $currentItemSubtotal = $currentItem.find('.Gstore-cart-card__subtotal, .product-subtotal, .line-subtotal');
								if ($newItemSubtotal.length > 0 && $currentItemSubtotal.length > 0) {
									$currentItemSubtotal.html($newItemSubtotal.html());
								}
								// Atualiza quantidade (validação do servidor)
								const $newQty = $newItem.find('input.qty');
								const $currentQty = $currentItem.find('input.qty');
								if ($newQty.length > 0 && $currentQty.length > 0) {
									const newVal = $newQty.val();
									const newMax = $newQty.attr('max');
									if (newVal) $currentQty.val(newVal);
									if (newMax) $currentQty.attr('max', newMax);
								}
								// Atualiza data-quantity
								const newQuantity = $newItem.attr('data-quantity');
								if (newQuantity) {
									$currentItem.attr('data-quantity', newQuantity);
								}
							});
						} else {
							const newForm = $cartContent[0];
							if (newForm && form) {
								form.action = newForm.getAttribute('action') || form.action;
								form.method = newForm.getAttribute('method') || form.method;
								form.innerHTML = newForm.innerHTML;
							} else {
								$form.replaceWith($cartContent);
							}
						}
					}
					if ($cartTotals.length > 0) {
						if (shouldPreserveTotals) {
							const newSubtotalEl = $response.find('.cart_totals .cart-subtotal .woocommerce-Price-amount, .cart_totals .cart-subtotal td').first();
							const newSubtotalText = newSubtotalEl.length > 0 ? newSubtotalEl.text() : '';
							const currentSubtotalEl = jQuery('.cart_totals .cart-subtotal .woocommerce-Price-amount, .cart_totals .cart-subtotal td').first();
							if (currentSubtotalEl.length > 0 && newSubtotalText) {
								currentSubtotalEl.text(newSubtotalText);
							}
						} else {
							const $currentTotals = jQuery('.cart_totals, .Gstore-cart-sidebar');
							$currentTotals.html($cartTotals.html());
						}
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

			const selectedRateId = String(target.value || '');
			const selectedMode = normalizeRateMode(target.dataset.gstoreMode || '');
			shippingBlock.dataset.lastSelectedMode = selectedMode;
			shippingBlock.dataset.lastSelectedRateId = selectedRateId;
			storeShippingMode(cartItemKey, selectedMode);
			storeSelectedRateId(cartItemKey, selectedRateId);

			let hiddenModeInput = shippingBlock.querySelector(`input[data-gstore-mode-hidden="true"][name="gstore_shipping_mode[${cartItemKey}]"]`);
			if (!hiddenModeInput) {
				hiddenModeInput = document.createElement('input');
				hiddenModeInput.type = 'hidden';
				hiddenModeInput.name = `gstore_shipping_mode[${cartItemKey}]`;
				hiddenModeInput.setAttribute('data-gstore-mode-hidden', 'true');
				shippingBlock.appendChild(hiddenModeInput);
			}
			hiddenModeInput.value = selectedMode;

			let hiddenRateInput = shippingBlock.querySelector(`input[data-gstore-rate-hidden="true"][name="gstore_selected_shipping_rate[${cartItemKey}]"]`);
			if (!hiddenRateInput) {
				hiddenRateInput = document.createElement('input');
				hiddenRateInput.type = 'hidden';
				hiddenRateInput.name = `gstore_selected_shipping_rate[${cartItemKey}]`;
				hiddenRateInput.setAttribute('data-gstore-rate-hidden', 'true');
				shippingBlock.appendChild(hiddenRateInput);
			}
			hiddenRateInput.value = selectedRateId;

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
		restoreCartCep();
		pruneShippingStorageToCurrentCart();
		syncDomShippingRatesToStorage();
		initQuantitySelectors();
		initShippingChoices();
		setupMutationObserver();
		ensureShippingBlocksExist();
		initMixedCartActions();
		restoreShippingRatesFromStorage();
		updateCartTotalsSummary();
		updateCheckoutAvailability();

		if (!initialRatesHydrated) {
			initialRatesHydrated = true;
			if (document.querySelector('.gstore-shipping-calculator--cart') && getCartCep()) {
				setTimeout(() => {
					calculateRatesForCart(false);
				}, 120);
			}
		}
	}

	function initMixedCartActions() {
		if (mixedCartActionsDelegated || typeof jQuery === 'undefined') {
			return;
		}

		document.addEventListener('click', (event) => {
			const btn = event.target instanceof Element ? event.target.closest('[data-gstore-keep-group]') : null;
			if (!(btn instanceof HTMLButtonElement)) {
				return;
			}

			event.preventDefault();

			const keepGroup = btn.getAttribute('data-gstore-keep-group');
			if (!keepGroup) {
				return;
			}

			const config = getMixedCartConfig();
			if (!config.ajaxUrl || !config.mixedCartNonce) {
				window.alert('Nao foi possivel preparar a separacao do carrinho. Recarregue a pagina e tente novamente.');
				return;
			}

			const allBtns = document.querySelectorAll('[data-gstore-keep-group]');
			allBtns.forEach((buttonEl) => {
				buttonEl.disabled = true;
				buttonEl.classList.add('Gstore-cart-btn--loading');
			});

			jQuery.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'gstore_remove_cart_token_group',
					nonce: config.mixedCartNonce,
					keep_group: keepGroup,
				},
				success(response) {
					if (response && response.success) {
						const redirectUrl = response.data && response.data.redirect_url ? response.data.redirect_url : config.checkoutUrl || window.location.href;
						window.location.href = redirectUrl;
						return;
					}

					const message = response && response.data && response.data.message
						? response.data.message
						: 'Erro ao processar.';
					window.alert(message);
					allBtns.forEach((buttonEl) => {
						buttonEl.disabled = false;
						buttonEl.classList.remove('Gstore-cart-btn--loading');
					});
				},
				error() {
					window.alert('Erro de conexao. Tente novamente.');
					allBtns.forEach((buttonEl) => {
						buttonEl.disabled = false;
						buttonEl.classList.remove('Gstore-cart-btn--loading');
					});
				},
			});
		});

		mixedCartActionsDelegated = true;
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
			const hasCalculator = !!document.querySelector('.gstore-shipping-calculator--cart');
			if (hasCalculator) {
				if (!hasCalculatedShipping()) {
					setCalculatedShippingFlag(false);
				}
				restoreCartCep();
				const shouldRecalculate = hasCalculatedShippingFlag();
				if (shouldRecalculate) {
					setTimeout(() => {
						calculateRatesForCart(false);
					}, 200);
				} else {
					restoreShippingRatesFromStorage();
					updateCartTotalsSummary();
					updateCheckoutAvailability();
				}
			} else {
				updateCheckoutAvailability();
			}
		});

		jQuery(document).on('click', '.gstore-shipping-calculator__button', function () {
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

	/* Desabilitar botão de checkout quando carrinho misto */
	if (document.querySelector('[data-gstore-mixed-cart]')) {
		var checkoutBtns = document.querySelectorAll('.checkout-button, .wc-proceed-to-checkout .button');
		checkoutBtns.forEach(function (btn) {
			btn.dataset.gstoreDisabled = 'true';
			btn.classList.add('Gstore-cart-btn--disabled');
			btn.style.pointerEvents = 'none';
			btn.style.opacity = '0.5';
		});
	}
})();

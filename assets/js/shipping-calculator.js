/**
 * Calculador de Frete Gstore
 * 
 * Componente reutilizável para calcular frete na página de produto único e checkout.
 */

(function($) {
	'use strict';

	/**
	 * Classe principal do calculador de frete
	 */
	class ShippingCalculator {
		constructor(container, options = {}) {
			this.container = $(container);
			this.options = $.extend({
				productId: 0,
				quantity: 1,
				ajaxUrl: '',
				nonce: '',
				context: '',
				i18n: {}
			}, options);

			if (typeof gstoreShippingCalculator !== 'undefined') {
				this.options = $.extend(this.options, gstoreShippingCalculator);
			}
			this.lastDestination = null;

			this.init();
		}

		init() {
			this.cepInput = this.container.find('.gstore-shipping-calculator__cep');
			this.calculateBtn = this.container.find('.gstore-shipping-calculator__button');
			this.resultContainer = this.container.find('.gstore-shipping-calculator__result');
			this.errorContainer = this.container.find('.gstore-shipping-calculator__error');

			this.bindEvents();
		}

		bindEvents() {
			const self = this;

			// Máscara para CEP
			this.cepInput.on('input', function() {
				let value = $(this).val().replace(/\D/g, '');
				if (value.length > 8) value = value.slice(0, 8);
				
				if (value.length > 5) {
					value = value.replace(/(\d{5})(\d{1,3})/, '$1-$2');
				}
				
				$(this).val(value);
			});

			// Calcular ao clicar no botão
			this.calculateBtn.on('click', function(e) {
				e.preventDefault();
				self.calculate();
			});

			// Calcular ao pressionar Enter no campo CEP
			this.cepInput.on('keypress', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					self.calculate();
				}
			});

			// Limpar resultado quando CEP mudar
			this.cepInput.on('input', function() {
				if (self.resultContainer.hasClass('is-visible')) {
					self.clearResult();
				}
			});
		}

		validateCep(cep) {
			const cleanCep = cep.replace(/\D/g, '');
			return cleanCep.length === 8;
		}

		resolveAjaxUrl() {
			if (this.options.ajaxUrl) {
				return this.options.ajaxUrl;
			}
			if (typeof gstoreShippingCalculator !== 'undefined' && gstoreShippingCalculator.ajaxUrl) {
				return gstoreShippingCalculator.ajaxUrl;
			}
			if (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.ajax_url) {
				return wc_checkout_params.ajax_url;
			}
			return '/wp-admin/admin-ajax.php';
		}

		resolveContext() {
			const context = this.container.data('gstoreShippingContext') || this.container.attr('data-gstore-shipping-context') || this.options.context || '';
			if (context) {
				return String(context);
			}
			return $('body').hasClass('single-product') ? 'single_product' : '';
		}

		resolveProductId() {
			const scopedProductId = parseInt(this.container.data('productId') || this.container.attr('data-product-id') || 0, 10);
			if (scopedProductId > 0) {
				return scopedProductId;
			}

			const productId = parseInt(this.options.productId || 0, 10);
			return productId > 0 ? productId : 0;
		}

		resolveQuantity() {
			const $scope = this.container.closest('.Gstore-single-product__summary-card, .summary, .product');
			const $searchScope = $scope.length ? $scope : $(document);
			const $quantityInput = $searchScope.find('form.cart input[name="quantity"], form.cart .quantity input').first();

			if ($quantityInput.length) {
				const qty = parseInt($quantityInput.val(), 10);
				if (qty > 0) {
					return qty;
				}
			}

			const quantity = parseInt(this.options.quantity || 1, 10);
			return quantity > 0 ? quantity : 1;
		}

		escapeHtml(value) {
			return String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}

		calculate() {
			const cep = this.cepInput.val().trim();
			const context = this.resolveContext();

			// Valida CEP
			if (!this.validateCep(cep)) {
				this.showError(this.options.i18n.invalidCep || 'CEP inválido. Por favor, informe um CEP válido com 8 dígitos.');
				return;
			}

			// Limpa erros anteriores
			this.clearError();
			this.clearResult();

			// Mostra loading
			this.setLoading(true);

			// Prepara dados
			const data = {
				action: 'gstore_calculate_shipping',
				nonce: this.options.nonce,
				postcode: cep.replace(/\D/g, ''),
				product_id: this.resolveProductId(),
				quantity: this.resolveQuantity(),
				calculation_context: context,
				isolated_product: context === 'single_product' ? 1 : 0
			};

			// Faz requisição AJAX
			$.ajax({
				url: this.resolveAjaxUrl(),
				type: 'POST',
				data: data,
				dataType: 'json',
				success: (response) => {
					this.setLoading(false);

					if (response.success && response.data) {
						this.lastDestination = response.data.destination || null;
						this.showResult(response.data);
					} else {
						const message = response.data && response.data.message 
							? response.data.message 
							: (this.options.i18n.error || 'Erro ao calcular frete. Tente novamente.');
						this.showError(message);
						this.lastDestination = null;
					}
				},
				error: () => {
					this.setLoading(false);
					this.showError(this.options.i18n.error || 'Erro ao calcular frete. Tente novamente.');
					this.lastDestination = null;
				}
			});
		}

		setLoading(loading) {
			if (loading) {
				this.calculateBtn.prop('disabled', true);
				this.calculateBtn.html(
					'<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> ' +
					(this.options.i18n.calculating || 'Calculando...')
				);
			} else {
				this.calculateBtn.prop('disabled', false);
				this.calculateBtn.html(
					'<i class="fa-solid fa-truck" aria-hidden="true"></i> ' +
					(this.options.i18n.calculate || 'Calcular frete')
				);
			}
		}

		showResult(data) {
			const i18n = this.options.i18n;
			let rates = Array.isArray(data.rates) ? data.rates : [];
			const destination = data.destination || {};
			const city = destination.city ? String(destination.city).trim() : '';
			const state = destination.state ? String(destination.state).trim() : '';
			const destinationLabel = city && state ? `${city}/${state}` : (city || state);

			if (!rates.length && data.cost_formatted) {
				rates = [{
					label: i18n.frete || 'Frete',
					cost_formatted: data.cost_formatted,
					mode: data.mode || 'land'
				}];
			}

			if (!rates.length) {
				this.showError(this.options.i18n.error || 'Erro ao calcular frete. Tente novamente.');
				return;
			}

			const ratesHtml = rates.map((rate) => {
				const label = rate.label || '';
				const labelText = label
					? label
					: ((rate.mode || '').toLowerCase() === 'air' ? 'Frete Aéreo' : (rate.mode || '').toLowerCase() === 'pickup' ? 'Retirada na loja' : 'Frete Terrestre');
				const hasQuoteNotice = rate.quote_value_enabled === false
					|| String(rate.quote_notice_html || rate.cost_formatted || '').indexOf('gstore-shipping-quote-notice') !== -1;
				const costHtml = hasQuoteNotice
					? (rate.quote_notice_html || rate.cost_formatted || `<span class="gstore-shipping-quote-notice">${this.escapeHtml(rate.quote_notice_message || '')}</span>`)
					: (rate.cost_formatted || '-');
				return `
					<div class="gstore-shipping-calculator__result-row${hasQuoteNotice ? ' gstore-shipping-calculator__result-row--quote-notice' : ''}">
						<span class="gstore-shipping-calculator__result-label">
							<i class="fa-solid fa-truck" aria-hidden="true"></i>
							${labelText}:
						</span>
						<strong class="gstore-shipping-calculator__result-value">${costHtml || '-'}</strong>
					</div>
				`;
			}).join('');
			
			const html = `
				<div class="gstore-shipping-calculator__result-content">
					${ratesHtml}
					<div class="gstore-shipping-calculator__result-row">
						<span class="gstore-shipping-calculator__result-label">
							<i class="fa-solid fa-map-marker-alt" aria-hidden="true"></i>
							${i18n.destination || 'Destino'}:
						</span>
						<span class="gstore-shipping-calculator__result-value">${destinationLabel || '-'}</span>
					</div>
				</div>
			`;

			this.resultContainer.html(html).addClass('is-visible');
		}

		showError(message) {
			this.errorContainer.html(
				'<i class="fa-solid fa-exclamation-circle" aria-hidden="true"></i> ' + message
			).addClass('is-visible');
		}

		clearResult() {
			this.resultContainer.removeClass('is-visible').html('');
		}

		clearError() {
			this.errorContainer.removeClass('is-visible').html('');
		}
	}

	/**
	 * Inicializa calculador na página de produto único
	 */
	function initProductPage() {
		const $calculator = $('.gstore-shipping-calculator');
		if ($calculator.length) {
			$calculator.each(function() {
				new ShippingCalculator(this, { context: 'single_product' });
			});
		}
	}

	/**
	 * Inicializa calculador no checkout
	 */
	function initCheckout() {
		// Cria container do calculador se não existir
		let $calculator = $('.gstore-shipping-calculator');
		
		if (!$calculator.length) {
			// Insere antes da seção de métodos de envio
			const $shippingSection = $('.woocommerce-shipping-fields, .woocommerce-shipping-methods');
			if ($shippingSection.length) {
				const calculatorHtml = `
					<div class="gstore-shipping-calculator gstore-shipping-calculator--checkout">
						<h3 class="gstore-shipping-calculator__title">
							<i class="fa-solid fa-calculator" aria-hidden="true"></i>
							Calcular Frete
						</h3>
						<div class="gstore-shipping-calculator__form">
							<input 
								type="text" 
								class="gstore-shipping-calculator__cep" 
								placeholder="00000-000"
								maxlength="9"
							/>
							<button type="button" class="gstore-shipping-calculator__button">
								<i class="fa-solid fa-truck" aria-hidden="true"></i>
								Calcular frete
							</button>
						</div>
						<div class="gstore-shipping-calculator__result"></div>
						<div class="gstore-shipping-calculator__error"></div>
					</div>
				`;
				$shippingSection.before(calculatorHtml);
				$calculator = $('.gstore-shipping-calculator');
			}
		}

		if ($calculator.length) {
			$calculator.each(function() {
				new ShippingCalculator(this);
			});
		}

		// Sincroniza CEP do checkout com o calculador
		$(document.body).on('updated_checkout', function() {
			const $billingPostcode = $('#billing_postcode');
			if ($billingPostcode.length && $billingPostcode.val()) {
				const cep = $billingPostcode.val().replace(/\D/g, '');
				if (cep.length === 8) {
					$calculator.find('.gstore-shipping-calculator__cep').val(
						cep.replace(/(\d{5})(\d{3})/, '$1-$2')
					);
				}
			}
		});

		// Auto-calcula quando CEP do checkout mudar
		const $billingPostcode = $('#billing_postcode');
		if ($billingPostcode.length) {
			let checkoutCepTimeout;
			$billingPostcode.on('blur', function() {
				const cep = $(this).val().replace(/\D/g, '');
				if (cep.length === 8 && $calculator.length) {
					clearTimeout(checkoutCepTimeout);
					checkoutCepTimeout = setTimeout(function() {
						$calculator.find('.gstore-shipping-calculator__cep').val(
							cep.replace(/(\d{5})(\d{3})/, '$1-$2')
						);
						$calculator.find('.gstore-shipping-calculator__button').trigger('click');
					}, 500);
				}
			});
		}
	}

	/**
	 * Inicializa calculador no carrinho
	 */
	function initCart() {
		const $calculator = $('.gstore-shipping-calculator');
		if ($calculator.length) {
			$calculator.each(function() {
				new ShippingCalculator(this);
			});
		}
	}

	// Inicialização
	$(document).ready(function() {
		// Página de produto único
		if ($('body').hasClass('single-product')) {
			initProductPage();
		}

		// Carrinho
		// No carrinho, o cálculo deve ser feito pelo cart.js (por item).
		// Evita disparar cálculo global com product_id=0.
		if ($('body').hasClass('woocommerce-cart')) {
			return;
		}

		// Checkout — desativado: o checkout-steps.js já gerencia o frete no checkout.
		// A initCheckout() injetava um bloco "Calcular Frete" duplicado dentro do
		// order review, conflitando com o sistema próprio de shipping do checkout.
		// if ($('body').hasClass('woocommerce-checkout')) {
		// 	setTimeout(initCheckout, 500);
		// }
	});

})(jQuery);

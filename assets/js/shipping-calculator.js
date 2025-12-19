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
				i18n: {}
			}, options);

			if (typeof gstoreShippingCalculator !== 'undefined') {
				this.options = $.extend(this.options, gstoreShippingCalculator);
			}

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

		calculate() {
			const cep = this.cepInput.val().trim();

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
			};

			// Adiciona product_id se disponível
			if (this.options.productId > 0) {
				data.product_id = this.options.productId;
				
				// Tenta obter quantidade do formulário de produto
				const quantityInput = $('input[name="quantity"], .quantity input');
				if (quantityInput.length) {
					const qty = parseInt(quantityInput.val(), 10);
					if (qty > 0) {
						data.quantity = qty;
					}
				}
			}

			// Faz requisição AJAX
			$.ajax({
				url: this.options.ajaxUrl,
				type: 'POST',
				data: data,
				dataType: 'json',
				success: (response) => {
					this.setLoading(false);

					if (response.success && response.data) {
						this.showResult(response.data);
					} else {
						const message = response.data && response.data.message 
							? response.data.message 
							: (this.options.i18n.error || 'Erro ao calcular frete. Tente novamente.');
						this.showError(message);
					}
				},
				error: () => {
					this.setLoading(false);
					this.showError(this.options.i18n.error || 'Erro ao calcular frete. Tente novamente.');
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
			
			const html = `
				<div class="gstore-shipping-calculator__result-content">
					<div class="gstore-shipping-calculator__result-row">
						<span class="gstore-shipping-calculator__result-label">
							<i class="fa-solid fa-truck" aria-hidden="true"></i>
							${i18n.frete || 'Frete'}:
						</span>
						<strong class="gstore-shipping-calculator__result-value">${data.cost_formatted}</strong>
					</div>
					<div class="gstore-shipping-calculator__result-row">
						<span class="gstore-shipping-calculator__result-label">
							<i class="fa-solid fa-map-marker-alt" aria-hidden="true"></i>
							${i18n.region || 'Região'}:
						</span>
						<span class="gstore-shipping-calculator__result-value">${data.region_label}</span>
					</div>
					<div class="gstore-shipping-calculator__result-row">
						<span class="gstore-shipping-calculator__result-label">
							<i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
							${i18n.estimatedDelivery || 'Prazo estimado'}:
						</span>
						<span class="gstore-shipping-calculator__result-value">${data.estimated_days} ${i18n.days || 'dias úteis'}</span>
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
			new ShippingCalculator($calculator);
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
			new ShippingCalculator($calculator);
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

	// Inicialização
	$(document).ready(function() {
		// Página de produto único
		if ($('body').hasClass('single-product')) {
			initProductPage();
		}

		// Checkout
		if ($('body').hasClass('woocommerce-checkout')) {
			// Aguarda um pouco para garantir que o WooCommerce carregou
			setTimeout(initCheckout, 500);
		}
	});

})(jQuery);






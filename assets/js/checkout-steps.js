/**
 * Checkout em 3 Etapas - Gstore
 * 
 * Fluxo simplificado:
 * - Etapa 1: Escolha do método de pagamento (Cartão ou PIX)
 * - Etapa 2: Dados básicos (email e telefone)
 * - Etapa 3: Finalizar pedido
 * 
 * O mesmo fluxo para Cartão e PIX - simplificado e consistente.
 */

(function($) {
	'use strict';

	// Configuração única de etapas - sempre 3 etapas
	const STEPS = [
		{
			id: 'payment-method',
			name: 'Pagamento',
			icon: 'fa-credit-card',
			title: 'Escolha o Método de Pagamento',
			description: 'Selecione como deseja pagar seu pedido.',
			fields: []
		},
		{
			id: 'contact',
			name: 'Dados Básicos',
			icon: 'fa-envelope',
			title: 'Seus Dados',
			description: 'Informe seu email, telefone e CEP para calcular o frete.',
			fields: [
				'billing_email',
				'billing_phone',
				'billing_postcode'
			]
		},
		{
			id: 'payment',
			name: 'Finalizar',
			icon: 'fa-check',
			title: 'Finalizar Pedido',
			description: 'Clique no botão abaixo para finalizar seu pedido.',
			fields: []
		}
	];

	let currentStep = 0;
	let $checkoutForm = null;
	let $stepsContainer = null;
	let initialized = false;
	let isUpdatingPayment = false; // Flag para evitar loops ao atualizar pagamento
	let calculatedShipping = null; // Armazena o frete calculado
	let isCalculatingShipping = false; // Flag para evitar múltiplos cálculos simultâneos
	let lastCalculatedShippingCep = ''; // CEP (somente dígitos) do último frete calculado com sucesso
	let lastRequestedShippingCep = ''; // CEP (somente dígitos) da última requisição de frete disparada
	let lastCartSummaryData = null;
	let installmentQuotes = null;
	let isLoadingInstallmentQuotes = false;
	let lastInstallmentQuotesSignature = '';

	/**
	 * Garante cálculo/validação do frete quando o CEP já está preenchido (sem precisar clicar/sair do campo).
	 * Útil para sessão/autofill e para quando a etapa "Dados" fica ativa.
	 */
	function ensureShippingAutofilled() {
		const $postcodeInput = $('#billing_postcode');
		if (!$postcodeInput.length) return;

		const raw = String($postcodeInput.val() || '');
		const cleanCep = raw.replace(/\D/g, '');

		// Só tenta calcular se CEP estiver completo
		if (cleanCep.length !== 8) return;

		// Se já temos frete calculado para o mesmo CEP, não precisa recalcular
		if (calculatedShipping && lastCalculatedShippingCep === cleanCep) return;

		// Evita disparar várias requisições iguais
		if (isCalculatingShipping && lastRequestedShippingCep === cleanCep) return;

		// Dispara cálculo (sem exigir blur/click)
		calculateShipping(raw);
	}

	/**
	 * Inicializa o checkout de etapas
	 */
	function init() {
		if (initialized) return;
		
		$checkoutForm = $('form.checkout.woocommerce-checkout');
		
		if (!$checkoutForm.length) {
			return;
		}

		// Verifica se já foi inicializado
		if ($('.Gstore-checkout-steps').length) {
			return;
		}

		buildStepsUI();
		bindEvents();
		loadCartSummary();
		// Se o CEP já veio preenchido (sessão/autofill), calcula o frete imediatamente
		setTimeout(ensureShippingAutofilled, 0);
		
		initialized = true;
	}

	/**
	 * Constrói a interface do checkout em etapas
	 */
	function buildStepsUI() {
		const $shell = $('.Gstore-checkout-steps-shell');
		if (!$shell.length) return;

		// Esconde o wrapper original do checkout (mas NÃO o form)
		$shell.find('.Gstore-checkout').hide();

		// Cria container principal
		$stepsContainer = $('<div class="Gstore-checkout-steps"></div>');

		// 1. Resumo do pedido no topo
		$stepsContainer.append(buildSummaryTop());

		// 2. Stepper
		$stepsContainer.append(buildStepper());

		// 3. Container das etapas
		const $stepsContent = $('<div class="Gstore-checkout-steps__content"></div>');

		STEPS.forEach((step, index) => {
			$stepsContent.append(buildStepPanel(step, index));
		});

		$stepsContainer.append($stepsContent);

		// Adiciona à shell para manter o layout
		$shell.append($stepsContainer);

		// Move campos para as etapas corretas
		organizeFields();

		// Ativa primeira etapa sem forçar scroll (evita pular para o fim na carga inicial)
		setActiveStep(0, false);
	}

	/**
	 * Constrói o resumo do pedido no topo
	 */
	function buildSummaryTop() {
		return `
			<div class="Gstore-checkout-summary-top">
				<div class="Gstore-checkout-summary-top__inner">
					<div class="Gstore-checkout-summary-top__info">
						<div class="Gstore-checkout-summary-top__icon">
							<i class="fa-solid fa-shopping-bag"></i>
						</div>
						<div class="Gstore-checkout-summary-top__text">
							<h2>Seu Pedido</h2>
							<p class="Gstore-summary-items-count">Carregando...</p>
						</div>
					</div>
					<div class="Gstore-checkout-summary-top__actions">
						<span class="Gstore-checkout-summary-top__total-amount" aria-live="polite">R$ --,--</span>
						<span class="Gstore-checkout-summary-top__actions-divider" aria-hidden="true"></span>
						<button type="button" class="Gstore-checkout-summary-top__toggle">
							Ver detalhes
							<i class="fa-solid fa-chevron-down"></i>
						</button>
					</div>
				</div>
				<div class="Gstore-checkout-summary-top__details">
					<div class="Gstore-checkout-summary-top__items"></div>
					<div class="Gstore-checkout-summary-top__totals"></div>
				</div>
			</div>
		`;
	}

	/**
	 * Constrói o stepper
	 */
	function buildStepper() {
		let html = '<nav class="Gstore-checkout-stepper" aria-label="Etapas do checkout">';

		STEPS.forEach((step, index) => {
			if (index > 0) {
				html += `<div class="Gstore-checkout-stepper__connector" data-connector="${index}"></div>`;
			}
			html += `
				<button type="button" class="Gstore-checkout-stepper__step" data-step-index="${index}">
					<span class="Gstore-checkout-stepper__number">
						<span>${index + 1}</span>
					</span>
					<span class="Gstore-checkout-stepper__label">${step.name}</span>
				</button>
			`;
		});

		html += '</nav>';
		return html;
	}

	/**
	 * Constrói o painel de uma etapa
	 */
	function buildStepPanel(step, index) {
		const isLast = index === STEPS.length - 1;
		
		let actionsHtml = '';
		if (!isLast) {
			actionsHtml = `
				<div class="Gstore-checkout-step__actions">
					${index > 0 ? '<button type="button" class="Gstore-btn Gstore-btn--back" data-action="prev"><i class="fa-solid fa-arrow-left"></i> Voltar</button>' : '<div></div>'}
					<button type="button" class="Gstore-btn Gstore-btn--continue" data-action="next">
						Continuar
						<i class="fa-solid fa-arrow-right"></i>
					</button>
				</div>
			`;
		} else {
			actionsHtml = `
				<div class="Gstore-checkout-step__actions Gstore-checkout-step__actions--payment">
					<button type="button" class="Gstore-btn Gstore-btn--back" data-action="prev">
						<i class="fa-solid fa-arrow-left"></i> Voltar
					</button>
				</div>
			`;
		}

		return `
			<div class="Gstore-checkout-step" data-step="${step.id}" data-step-index="${index}">
				<div class="Gstore-checkout-step__header">
					<span class="Gstore-checkout-step__eyebrow">
						<i class="fa-solid ${step.icon}"></i>
						Etapa ${index + 1} de ${STEPS.length}
					</span>
					<h2 class="Gstore-checkout-step__title">${step.title}</h2>
					<p class="Gstore-checkout-step__description">${step.description}</p>
				</div>
				<div class="Gstore-checkout-step__fields"></div>
				${actionsHtml}
				${isLast ? '<div class="Gstore-checkout-step__payment-container"><div class="Gstore-checkout-step__order-review-slot"></div><div class="Gstore-blu-installments-slot"></div></div>' : ''}
			</div>
		`;
	}

	/**
	 * Unifica métodos de pagamento Blu em um card único
	 */
	function unifyBluPaymentMethods() {
		// Verifica se já existe o card unificado
		const $existingUnified = $('.Gstore-blu-payment-unified');
		if ($existingUnified.length) {
			// Se já existe, apenas sincroniza a seleção
			const $selected = $('input[name="payment_method"]:checked');
			if ($selected.length) {
				const selectedValue = $selected.val();
				const $option = $existingUnified.find(`input[type="radio"][value="${selectedValue}"]`);
				if ($option.length && !$option.is(':checked')) {
					$option.prop('checked', true).trigger('change');
				}
			}
			return;
		}
		
		const $bluCheckout = $('.payment_method_blu_checkout').not('.Gstore-blu-payment-unified .payment_method_blu_checkout');
		const $bluPix = $('.payment_method_blu_pix').not('.Gstore-blu-payment-unified .payment_method_blu_pix');
		
		// Se ambos os métodos Blu estão disponíveis, unifica em um card
		if ($bluCheckout.length && $bluPix.length) {
			// Esconde os elementos originais com classe CSS (mais confiável que inline styles)
			$bluCheckout.addClass('gstore-hidden-for-unified');
			$bluPix.addClass('gstore-hidden-for-unified');
			
			// Cria card unificado
			const $bluUnified = $('<li class="payment_method_blu_unified Gstore-blu-payment-unified"></li>');
			
			// Adiciona título unificado "Pagamento via Blu"
			$bluUnified.append('<div class="Gstore-blu-payment-unified__title">Pagamento via Blu</div>');
			
			// Cria container para as opções
			const $optionsContainer = $('<div class="Gstore-blu-payment-options"></div>');
			
			// Prepara opção Cartão - usa o radio original mas escondido
			const $checkoutOption = $('<div class="Gstore-blu-payment-option"></div>');
			const $checkoutRadio = $bluCheckout.find('input[type="radio"]').first();
			const checkoutId = $checkoutRadio.attr('id') || 'payment_method_blu_checkout';
			const checkoutChecked = $checkoutRadio.is(':checked');
			
			// Clona o radio para usar no card unificado, mantendo o original escondido
			const $checkoutRadioClone = $checkoutRadio.clone();
			$checkoutRadioClone.appendTo($checkoutOption);
			$checkoutOption.append(`
				<label for="${checkoutId}" class="Gstore-blu-payment-option__label">
					<i class="fa-solid fa-credit-card"></i>
					<span>Cartão (Link de Pagamento)</span>
				</label>
			`);
			$optionsContainer.append($checkoutOption);
			
			// Sincroniza cliques no radio clone com o original
			$checkoutRadioClone.on('change', function() {
				if ($(this).is(':checked')) {
					$checkoutRadio.prop('checked', true).trigger('change');
					$pixRadio.prop('checked', false);
				}
			});
			
			// Prepara opção Pix - usa o radio original mas escondido
			const $pixOption = $('<div class="Gstore-blu-payment-option"></div>');
			const $pixRadio = $bluPix.find('input[type="radio"]').first();
			const pixId = $pixRadio.attr('id') || 'payment_method_blu_pix';
			const pixChecked = $pixRadio.is(':checked');
			
			
			// Clona o radio para usar no card unificado, mantendo o original escondido
			const $pixRadioClone = $pixRadio.clone();
			$pixRadioClone.appendTo($pixOption);
			$pixOption.append(`
				<label for="${pixId}" class="Gstore-blu-payment-option__label">
					<i class="fa-solid fa-qrcode"></i>
					<span>Pix</span>
				</label>
			`);
			$optionsContainer.append($pixOption);
			
			// Sincroniza cliques no radio clone com o original
			$pixRadioClone.on('change', function() {
				if ($(this).is(':checked')) {
					$pixRadio.prop('checked', true).trigger('change');
					$checkoutRadio.prop('checked', false);
				}
			});
			
			$bluUnified.append($optionsContainer);
			
			// Move payment_box do método selecionado para dentro do card unificado
			$bluUnified.append('<div class="Gstore-blu-payment-unified__content"></div>');
			const $content = $bluUnified.find('.Gstore-blu-payment-unified__content');
			
			// Adiciona event listeners para mostrar/esconder conteúdo baseado na seleção
			/**
			 * Atualiza conteúdo do método de pagamento selecionado
			 * Função usada apenas para sincronização interna
			 */
			function updatePaymentContent() {
				const $livePixRadio = $('input[name="payment_method"][value="blu_pix"]');
				const $liveCheckoutRadio = $('input[name="payment_method"][value="blu_checkout"]');
				
				const isCheckoutSelected = $liveCheckoutRadio.filter(':checked').length > 0;
				const isPixSelected = $livePixRadio.filter(':checked').length > 0;
				
				if (!isCheckoutSelected && !isPixSelected) return;
				
				if ($checkoutRadioClone) $checkoutRadioClone.prop('checked', isCheckoutSelected);
				if ($pixRadioClone) $pixRadioClone.prop('checked', isPixSelected);
				
				$content.empty();
				
				if (isCheckoutSelected) {
					const $box = $('.payment_method_blu_checkout.gstore-hidden-for-unified .payment_box').first().clone();
					$content.append($box);
					toggleBillingFieldsForPaymentMethod(false);
				} else {
					const $box = $('.payment_method_blu_pix.gstore-hidden-for-unified .payment_box').first().clone();
					$content.append($box);
					toggleBillingFieldsForPaymentMethod(true);
				}
			}
			
		// Handler para cliques nos labels de pagamento (sem disparar update_checkout)
		function selectPaymentMethod(selectedMethod) {
			const $livePixRadio = $('input[name="payment_method"][value="blu_pix"]');
			const $liveCheckoutRadio = $('input[name="payment_method"][value="blu_checkout"]');
			
			const isCheckout = selectedMethod === 'blu_checkout';
			
			// Atualiza os radios originais
			$liveCheckoutRadio.prop('checked', isCheckout);
			$livePixRadio.prop('checked', !isCheckout);
				
				// Atualiza os clones visuais
				if ($checkoutRadioClone) $checkoutRadioClone.prop('checked', isCheckout);
				if ($pixRadioClone) $pixRadioClone.prop('checked', !isCheckout);
				
				// Atualiza conteúdo e billing fields
				toggleBillingFieldsForPaymentMethod(!isCheckout);
				$content.empty();
				
				if (isCheckout) {
					const $box = $('.payment_method_blu_checkout.gstore-hidden-for-unified .payment_box').first().clone();
					$content.append($box);
				} else {
					const $box = $('.payment_method_blu_pix.gstore-hidden-for-unified .payment_box').first().clone();
					$content.append($box);
				}

				// Esconde/mostra parcelamento imediatamente (PIX não tem parcelamento)
				$('.Gstore-blu-installments').toggle(isCheckout);

				// Atualiza totais/sessão do WooCommerce
				$(document.body).trigger('update_checkout');
			}
			
			$checkoutOption.find('label').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				selectPaymentMethod('blu_checkout');
			});
			
		$pixOption.find('label').on('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			selectPaymentMethod('blu_pix');
		});
			
		// Sincroniza quando WooCommerce atualiza o checkout (ex: cupom aplicado)
		$(document.body).on('updated_checkout.gstore-unify', function() {
			// Re-esconde os elementos originais que podem ter sido recriados
			$('.payment_method_blu_checkout').not('.Gstore-blu-payment-unified .payment_method_blu_checkout').addClass('gstore-hidden-for-unified');
			$('.payment_method_blu_pix').not('.Gstore-blu-payment-unified .payment_method_blu_pix').addClass('gstore-hidden-for-unified');
			
			// Sincroniza a seleção visual com o estado atual dos radios
			setTimeout(function() {
				const $livePixRadio = $('input[name="payment_method"][value="blu_pix"]');
				const $liveCheckoutRadio = $('input[name="payment_method"][value="blu_checkout"]');
				const isPixSelected = $livePixRadio.filter(':checked').length > 0;
				const isCheckoutSelected = $liveCheckoutRadio.filter(':checked').length > 0;
					
					if (isPixSelected || isCheckoutSelected) {
						if ($checkoutRadioClone) $checkoutRadioClone.prop('checked', isCheckoutSelected);
						if ($pixRadioClone) $pixRadioClone.prop('checked', isPixSelected);
						toggleBillingFieldsForPaymentMethod(isPixSelected);
						
						// Esconde/mostra parcelamento (PIX não tem parcelamento)
						$('.Gstore-blu-installments').toggle(isCheckoutSelected);
						
						$content.empty();
						if (isCheckoutSelected) {
							const $box = $('.payment_method_blu_checkout.gstore-hidden-for-unified .payment_box').first().clone();
							$content.append($box);
						} else {
							const $box = $('.payment_method_blu_pix.gstore-hidden-for-unified .payment_box').first().clone();
							$content.append($box);
						}
					}
				}, 50);
			});
			
		// Mostra conteúdo inicial
		setTimeout(function() {
			const isPixSelected = $pixRadio.is(':checked');
			const isCheckoutSelected = $checkoutRadio.is(':checked');
			
			if (!isPixSelected && !isCheckoutSelected) {
				$checkoutRadio.prop('checked', true);
			}
			
			const finalSelection = $pixRadio.is(':checked');
			if ($pixRadioClone) $pixRadioClone.prop('checked', finalSelection);
			if ($checkoutRadioClone) $checkoutRadioClone.prop('checked', !finalSelection);
			
			// Esconde/mostra parcelamento na inicialização (PIX não tem parcelamento)
			$('.Gstore-blu-installments').toggle(!finalSelection);
			
			updatePaymentContent();
		}, 100);
			
			// Adiciona badges de confiança simplificados
			$bluUnified.append(`
				<div class="Gstore-blu-trust-badges-simple">
					<span class="Gstore-blu-trust-badge-simple">
						<i class="fa-solid fa-shield-halved"></i> Pagamento seguro
					</span>
				</div>
			`);
			
			// Insere o card unificado na lista de métodos de pagamento
			const $paymentMethods = $('#payment .payment_methods');
			if ($paymentMethods.length) {
				$paymentMethods.prepend($bluUnified);
			} else {
				const $paymentSection = $('#payment');
				if ($paymentSection.length) {
					const $newPaymentMethods = $('<ul class="payment_methods"></ul>');
					$newPaymentMethods.append($bluUnified);
					$paymentSection.prepend($newPaymentMethods);
				}
			}
		}
	}

	/**
	 * Move/ativa o seletor de parcelas da Blu (renderizado no PHP) dentro do card unificado
	 * e sincroniza com o método selecionado.
	 */
	function ensureBluInstallmentsUI() {
		const $installments = $('.Gstore-blu-installments').first();
		if (!$installments.length) return;

		// Move para dentro da Etapa 3 (Finalizar)
		const $slot = $('.Gstore-blu-installments-slot').first();
		if ($slot.length && !$slot.find('.Gstore-blu-installments').length) {
			$slot.append($installments.detach());
		}

		const allow = $installments.data('allow') === 1 || $installments.data('allow') === '1';
		const $liveCheckoutRadio = $('input[name="payment_method"][value="blu_checkout"]');
		const isCheckoutSelected = $liveCheckoutRadio.filter(':checked').length > 0;

		// Só faz sentido mostrar quando cartão estiver selecionado
		$installments.toggle(allow && isCheckoutSelected);

		const $hidden = $('#gstore_blu_installments');
		const $select = $('#gstore_blu_installments_select');

		if (allow && $hidden.length && $select.length) {
			// Sincroniza select <- hidden
			if ($hidden.val() && $select.val() !== $hidden.val()) {
				$select.val($hidden.val());
			}

			// Bind 1x
			if (!$select.data('gstore-bound')) {
				$select.data('gstore-bound', true);
				$select.on('change', function() {
					const val = $(this).val() || '1';
					$hidden.val(val);
					$(document.body).trigger('update_checkout');
				});
			}
		}

		// Atualiza labels das opções (Nx de R$ ...) quando disponível
		applyInstallmentQuotesToSelect();

		// Busca tabela de parcelas quando necessário
		maybeFetchInstallmentQuotes();

		// Atualiza preview com o último resumo disponível
		if (lastCartSummaryData && lastCartSummaryData.installments) {
			updateInstallmentsPreview(lastCartSummaryData);
		}
	}

	function maybeFetchInstallmentQuotes() {
		const $installments = $('.Gstore-blu-installments').first();
		if (!$installments.length) return;

		const allow = $installments.data('allow') === 1 || $installments.data('allow') === '1';
		if (!allow) return;

		// Só faz sentido para blu_checkout
		if (!lastCartSummaryData || lastCartSummaryData.payment_method !== 'blu_checkout') return;

		const max = parseInt($installments.data('max'), 10) || 1;
		const selected = (lastCartSummaryData.installments && lastCartSummaryData.installments.selected)
			? String(lastCartSummaryData.installments.selected)
			: $('#gstore_blu_installments').val() || '1';

		// Assinatura simples para evitar spam de requests
		const signature = `${max}|${selected}|${lastCartSummaryData.total || ''}`;
		if (signature === lastInstallmentQuotesSignature) return;
		if (isLoadingInstallmentQuotes) return;
		lastInstallmentQuotesSignature = signature;

		const ajaxUrl = (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.ajax_url)
			? wc_checkout_params.ajax_url
			: '/wp-admin/admin-ajax.php';

		isLoadingInstallmentQuotes = true;
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'gstore_blu_get_installment_quotes',
				max: max
			},
			success: function(res) {
				isLoadingInstallmentQuotes = false;
				if (res && res.success && res.data && res.data.quotes) {
					installmentQuotes = res.data.quotes;
					applyInstallmentQuotesToSelect();
				}
			},
			error: function() {
				isLoadingInstallmentQuotes = false;
			}
		});
	}

	function applyInstallmentQuotesToSelect() {
		const $select = $('#gstore_blu_installments_select');
		if (!$select.length) return;
		if (!installmentQuotes) return;

		$select.find('option').each(function() {
			const $opt = $(this);
			const val = String($opt.attr('value') || '');
			if (!val || !installmentQuotes[val]) return;

			const q = installmentQuotes[val];
			// Exibe apenas "Nx de R$ ..." (com valor correto para aquele N)
			const perText = q.per_installment_text || q.per_installment || '';
			$opt.text(`${q.installments}x de ${perText}`);
		});
	}

	// Campos de billing completos (usados quando Pix é selecionado)
	const PIX_BILLING_FIELDS = [
		'billing_first_name',
		'billing_last_name',
		'billing_cpf',
		'billing_postcode',
		'billing_address_1',
		'billing_number',
		'billing_address_2',
		'billing_neighborhood',
		'billing_city',
		'billing_state'
	];
	
	/**
	 * Mostra/esconde campos de billing baseado no método de pagamento selecionado
	 */
	function toggleBillingFieldsForPaymentMethod(showForPix) {
		const $contactStep = $('[data-step="contact"] .Gstore-checkout-step__fields');
		if (!$contactStep.length) return;
		
		if (showForPix) {
			// PIX selecionado: Move campos de billing para a etapa de contato e mostra
			PIX_BILLING_FIELDS.forEach(fieldId => {
				// Primeiro tenta encontrar na etapa de contato
				let $field = $contactStep.find(`#${fieldId}_field`);
				
				if ($field.length) {
					// Já está na etapa de contato, apenas mostra
					$field.show();
				} else {
					// Busca em qualquer lugar da página
					$field = $(`#${fieldId}_field`);
					if ($field.length) {
						// Move para a etapa de contato
						$contactStep.append($field.detach());
						$field.show();
					}
				}
			});
			
			// Também mostra a seção de billing se existir
			$('.woocommerce-billing-fields').show();
			
			// Atualiza descrição da etapa
			const $stepDescription = $('[data-step="contact"] .Gstore-checkout-step__description');
			$stepDescription.text('Preencha seus dados completos para finalizar o pedido via Pix.');
		} else {
			// CARTÃO selecionado: Mostra apenas email, telefone e CEP
			PIX_BILLING_FIELDS.forEach(fieldId => {
				const $field = $(`#${fieldId}_field`);
				if ($field.length) {
					// Mostra apenas CEP, esconde os demais
					if (fieldId === 'billing_postcode') {
						$field.show();
					} else {
						$field.hide();
					}
				}
			});
			
			// Garante que CEP está visível na etapa de contato
			const $contactStep = $('[data-step="contact"] .Gstore-checkout-step__fields');
			const $postcodeField = $('#billing_postcode_field');
			if ($postcodeField.length && $contactStep.length) {
				// Move CEP para a etapa de contato se não estiver lá
				if (!$contactStep.find('#billing_postcode_field').length) {
					$contactStep.append($postcodeField.detach());
				}
				$postcodeField.show();
			}
			
			// Atualiza descrição da etapa
			const $stepDescription = $('[data-step="contact"] .Gstore-checkout-step__description');
			$stepDescription.text('Informe seu email, telefone e CEP para calcular o frete.');
		}
	}

	/**
	 * Organiza os campos nas etapas corretas
	 */
	function organizeFields() {
		// Etapa 1: Move métodos de pagamento
		const $paymentMethodStep = $('[data-step="payment-method"] .Gstore-checkout-step__fields');
		if ($paymentMethodStep.length) {
			const $paymentSection = $('#payment');
			if ($paymentSection.length) {
				// Remove botão de finalizar (será recriado na última etapa)
				$paymentSection.find('.place-order').remove();
				$paymentMethodStep.append($paymentSection.detach());
				setTimeout(unifyBluPaymentMethods, 150);
			}
		}

		// Etapa 2: Move campos de contato (email e telefone)
		const $contactStep = $('[data-step="contact"] .Gstore-checkout-step__fields');
		if ($contactStep.length) {
			STEPS[1].fields.forEach(fieldId => {
				const $field = $(`#${fieldId}_field`);
				if ($field.length) {
					$contactStep.append($field.detach());
					
					// Se for o campo de CEP, move também o resultado do frete se existir
					if (fieldId === 'billing_postcode') {
						const $shippingResult = $('.Gstore-shipping-result');
						if ($shippingResult.length) {
							$contactStep.append($shippingResult.detach());
						}
					}
				}
			});
		}

		// Etapa 3: Adiciona botão de finalizar
		const $finalizeStep = $('[data-step="payment"] .Gstore-checkout-step__payment-container');
		if ($finalizeStep.length && !$finalizeStep.find('#place_order').length) {
			$finalizeStep.append(`
				<div class="Gstore-finalize-container">
					<button type="submit" class="Gstore-btn Gstore-btn--submit" name="woocommerce_checkout_place_order" id="place_order" value="Finalizar pedido" data-value="Finalizar pedido">
						<i class="fa-solid fa-lock"></i>
						Finalizar pedido
					</button>
					<p class="Gstore-finalize-privacy">
						Seus dados estão protegidos. Ao finalizar, você concorda com nossa 
						<a href="/politica-de-privacidade" target="_blank">política de privacidade</a>.
					</p>
				</div>
			`);
		}

		// Etapa 3: Move o resumo do pedido (order review) para dentro do container principal da última etapa
		const $orderReviewSlot = $('[data-step="payment"] .Gstore-checkout-step__order-review-slot');
		const $orderReview = $('#order_review');
		if ($orderReviewSlot.length && $orderReview.length) {
			// Header do card (não depende do heading padrão do Woo, que é escondido nos steps)
			if (!$orderReviewSlot.find('.Gstore-order-review-header').length) {
				$orderReviewSlot.prepend(`
					<div class="Gstore-order-review-header">
						<div class="Gstore-order-review-header__title">Resumo do pedido</div>
					</div>
				`);
			}
			// Evita duplicar se o WooCommerce re-renderizar e o elemento já estiver no slot
			if (!$orderReviewSlot.find('#order_review').length) {
				$orderReviewSlot.append($orderReview.detach());
			}
		}

		// Esconde seções do WooCommerce não utilizadas
		$('.woocommerce-additional-fields').hide();
		$('.woocommerce-billing-fields').hide();
		$('.woocommerce-shipping-fields').hide();
	}

	/**
	 * Calcula o frete baseado no CEP informado
	 */
	function calculateShipping(postcode) {
		// Limpa CEP (remove caracteres não numéricos)
		const cleanCep = postcode.replace(/\D/g, '');
		
		// Valida CEP (deve ter 8 dígitos)
		if (cleanCep.length !== 8) {
			hideShippingResult();
			return;
		}

		// Evita múltiplos cálculos simultâneos
		if (isCalculatingShipping) {
			return;
		}

		lastRequestedShippingCep = cleanCep;
		isCalculatingShipping = true;
		showShippingLoading();

		// Prepara dados para AJAX
		const ajaxUrl = typeof gstoreShippingCalculator !== 'undefined' && gstoreShippingCalculator.ajaxUrl
			? gstoreShippingCalculator.ajaxUrl
			: (typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.ajax_url : '/wp-admin/admin-ajax.php');
		
		const nonce = typeof gstoreShippingCalculator !== 'undefined' && gstoreShippingCalculator.nonce
			? gstoreShippingCalculator.nonce
			: '';
		
		const data = {
			action: 'gstore_calculate_shipping',
			nonce: nonce,
			postcode: cleanCep
		};

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: data,
			dataType: 'json',
			success: function(response) {
				isCalculatingShipping = false;
				
				if (response.success && response.data) {
					calculatedShipping = response.data;
					lastCalculatedShippingCep = cleanCep;
					showShippingResult(response.data);
					updateSummaryWithShipping(response.data);
				} else {
					const message = response.data && response.data.message 
						? response.data.message 
						: 'Erro ao calcular frete. Tente novamente.';
					showShippingError(message);
					calculatedShipping = null;
					lastCalculatedShippingCep = '';
				}
			},
			error: function() {
				isCalculatingShipping = false;
				showShippingError('Erro ao calcular frete. Tente novamente.');
				calculatedShipping = null;
				lastCalculatedShippingCep = '';
			}
		});
	}

	/**
	 * Mostra loading do cálculo de frete
	 */
	function showShippingLoading() {
		const $postcodeField = $('#billing_postcode_field');
		if (!$postcodeField.length) return;

		// Remove qualquer resultado duplicado que possa ter ficado órfão
		const $existingResults = $('.Gstore-shipping-result');
		let $shippingResult;

		if ($existingResults.length > 0) {
			// Se existem resultados, usa o primeiro e remove os outros
			$shippingResult = $($existingResults[0]);
			if ($existingResults.length > 1) {
				$existingResults.slice(1).remove();
			}
			// Garante que ele esteja após o campo de CEP (pode ter sido movido)
			$postcodeField.after($shippingResult);
		} else {
			$shippingResult = $('<div class="Gstore-shipping-result"></div>');
			$postcodeField.after($shippingResult);
		}
		
		$shippingResult.html(`
			<div class="Gstore-shipping-result__loading">
				<i class="fa-solid fa-spinner fa-spin"></i>
				<span>Calculando frete...</span>
			</div>
		`).addClass('is-visible');
	}

	/**
	 * Mostra resultado do frete calculado
	 */
	function showShippingResult(data) {
		const $postcodeField = $('#billing_postcode_field');
		if (!$postcodeField.length) return;

		// Busca por um resultado existente
		let $shippingResult = $('.Gstore-shipping-result').first();
		
		if (!$shippingResult.length) {
			$shippingResult = $('<div class="Gstore-shipping-result"></div>');
			$postcodeField.after($shippingResult);
		} else {
			// Garante que ele esteja após o campo de CEP
			$postcodeField.after($shippingResult);
		}
		
		$shippingResult.html(`
			<div class="Gstore-shipping-result__content">
				<div class="Gstore-shipping-result__row">
					<i class="fa-solid fa-truck"></i>
					<span class="Gstore-shipping-result__label">Frete:</span>
					<strong class="Gstore-shipping-result__value">${data.cost_formatted}</strong>
				</div>
				<div class="Gstore-shipping-result__row">
					<i class="fa-solid fa-map-marker-alt"></i>
					<span class="Gstore-shipping-result__label">Região:</span>
					<span class="Gstore-shipping-result__value">${data.region_label}</span>
				</div>
				<div class="Gstore-shipping-result__row">
					<i class="fa-solid fa-calendar-days"></i>
					<span class="Gstore-shipping-result__label">Prazo:</span>
					<span class="Gstore-shipping-result__value">${data.estimated_days} dias úteis</span>
				</div>
			</div>
		`).removeClass('has-error').addClass('is-visible');
	}

	/**
	 * Mostra erro no cálculo de frete
	 */
	function showShippingError(message) {
		const $postcodeField = $('#billing_postcode_field');
		if (!$postcodeField.length) return;

		// Busca por um resultado existente
		let $shippingResult = $('.Gstore-shipping-result').first();
		
		if (!$shippingResult.length) {
			$shippingResult = $('<div class="Gstore-shipping-result"></div>');
			$postcodeField.after($shippingResult);
		} else {
			// Garante que ele esteja após o campo de CEP
			$postcodeField.after($shippingResult);
		}
		
		$shippingResult.html(`
			<div class="Gstore-shipping-result__error">
				<i class="fa-solid fa-exclamation-circle"></i>
				<span>${message}</span>
			</div>
		`).removeClass('is-visible').addClass('has-error');
	}

	/**
	 * Esconde resultado do frete
	 */
	function hideShippingResult() {
		$('.Gstore-shipping-result').removeClass('is-visible has-error').html('');
	}

	/**
	 * Atualiza resumo com valor do frete
	 */
	function updateSummaryWithShipping(shippingData) {
		// Atualiza o endereço no WooCommerce para que ele calcule o frete oficialmente
		const $postcodeField = $('#billing_postcode');
		const $checkoutForm = $('form.checkout');
		
		if ($postcodeField.length && $postcodeField.val()) {
			// Garante que o campo de método de envio existe no formulário
			let $shippingMethodField = $checkoutForm.find('input[name="shipping_method[0]"]');
			if (!$shippingMethodField.length) {
				// Cria campo hidden para o método de envio
				$checkoutForm.append('<input type="hidden" name="shipping_method[0]" value="gstore_custom_shipping" />');
			} else {
				$shippingMethodField.val('gstore_custom_shipping');
			}
			
			// Dispara evento para atualizar checkout do WooCommerce
			// Isso fará com que o WooCommerce calcule o frete oficialmente
			$(document.body).trigger('update_checkout');
		}
		
		// Atualiza o resumo do topo após um delay maior para o WooCommerce processar
		setTimeout(function() {
			loadCartSummary();
		}, 1000);
	}

	/**
	 * Atualiza o resumo dos dados do cliente
	 */
	function updateReviewData() {
		// Dados pessoais
		const firstName = $('#billing_first_name').val() || '';
		const lastName = $('#billing_last_name').val() || '';
		const cpf = $('#billing_cpf').val() || '';
		const email = $('#billing_email').val() || '';
		const phone = $('#billing_phone').val() || '';

		let personalHtml = '';
		if (firstName || lastName) {
			personalHtml += `<p><strong>${firstName} ${lastName}</strong></p>`;
		}
		if (cpf) {
			personalHtml += `<p>CPF: ${cpf}</p>`;
		}
		if (email) {
			personalHtml += `<p>${email}</p>`;
		}
		if (phone) {
			personalHtml += `<p>${phone}</p>`;
		}
		$('#review-personal').html(personalHtml || '<p class="Gstore-checkout-review__empty">Dados não preenchidos</p>');

		// Endereço
		const address = $('#billing_address_1').val() || '';
		const number = $('#billing_number').val() || '';
		const complement = $('#billing_address_2').val() || '';
		const neighborhood = $('#billing_neighborhood').val() || '';
		const city = $('#billing_city').val() || '';
		const state = $('#billing_state').val() || '';
		const postcode = $('#billing_postcode').val() || '';

		let shippingHtml = '';
		if (address) {
			shippingHtml += `<p>${address}${number ? ', ' + number : ''}${complement ? ' - ' + complement : ''}</p>`;
		}
		if (neighborhood || city || state) {
			shippingHtml += `<p>${neighborhood}${neighborhood && city ? ' - ' : ''}${city}${state ? '/' + state : ''}</p>`;
		}
		if (postcode) {
			shippingHtml += `<p>CEP: ${postcode}</p>`;
		}
		$('#review-shipping').html(shippingHtml || '<p class="Gstore-checkout-review__empty">Endereço não preenchido</p>');
	}

	/**
	 * Define a etapa ativa
	 */
	function setActiveStep(index, shouldScroll = true) {
		if (index < 0 || index >= STEPS.length) return;

		currentStep = index;

		// Atualiza painéis
		$('.Gstore-checkout-step').removeClass('is-active')
			.eq(index).addClass('is-active');

		// Atualiza stepper
		$('.Gstore-checkout-stepper__step').each(function(i) {
			$(this).removeClass('is-active is-complete');
			if (i === index) {
				$(this).addClass('is-active');
			} else if (i < index) {
				$(this).addClass('is-complete');
			}
		});

		// Atualiza conectores
		$('.Gstore-checkout-stepper__connector').each(function(i) {
			$(this).toggleClass('is-complete', i < index);
		});

		// Scroll suave para o topo apenas quando solicitado
		if (shouldScroll) {
			$('html, body').animate({
				scrollTop: $('.Gstore-checkout-steps__content').offset().top - 100
			}, 300);
		}

		// Controla visibilidade do botão "Finalizar pedido"
		const lastStepIndex = STEPS.length - 1;
		const $placeOrderBtn = $('#place_order, .place-order');
		if ($placeOrderBtn.length) {
			if (index === lastStepIndex) {
				// Mostra o botão apenas na última etapa
				$placeOrderBtn.show();
			} else {
				// Esconde o botão em todas as outras etapas
				$placeOrderBtn.hide();
			}
		}

		// Atualiza quando entrar na última etapa
		if (index === lastStepIndex) {
			setTimeout(function() {
				$(document.body).trigger('update_checkout');
				
				// Garante que o botão place_order esteja visível e clicável
				const $placeOrderBtn = $('#place_order');
				if ($placeOrderBtn.length) {
					$placeOrderBtn.prop('disabled', false)
						.removeClass('disabled')
						.show();
				}
				
				// Remove class 'processing' se existir (pode ter ficado de tentativa anterior)
				$checkoutForm.removeClass('processing');
			}, 200);
		}

		// Ao entrar na etapa de dados, verifica/calcule o frete automaticamente se o CEP já estiver preenchido
		if (STEPS[index] && STEPS[index].id === 'contact') {
			setTimeout(ensureShippingAutofilled, 0);
		}

		// Trigger evento para outros scripts
		$(document.body).trigger('gstore_checkout_step_changed', [index, STEPS[index]]);
	}

	/**
	 * Valida os campos da etapa atual
	 */
	function validateCurrentStep() {
		const step = STEPS[currentStep];
		let isValid = true;
		let $firstError = null;

		// Se a etapa é escolha de método de pagamento, valida se um método foi selecionado
		if (step.id === 'payment-method') {
			const $paymentMethod = $('input[name="payment_method"]:checked');
			if (!$paymentMethod.length) {
				showNotice('Por favor, selecione um método de pagamento.', 'error');
				return false;
			}
			return true;
		}

		step.fields.forEach(fieldId => {
			const $fieldWrapper = $(`#${fieldId}_field`);
			const $input = $fieldWrapper.find('input, select, textarea');
			
			if (!$input.length) return;

			const isRequired = $fieldWrapper.hasClass('validate-required') || 
			                   $input.prop('required') ||
			                   $input.attr('aria-required') === 'true';

			const value = $input.val() ? $input.val().trim() : '';

			// Remove estado de erro anterior
			$fieldWrapper.removeClass('woocommerce-invalid woocommerce-invalid-required-field');

			// Validação de campo obrigatório
			if (isRequired && !value) {
				isValid = false;
				$fieldWrapper.addClass('woocommerce-invalid woocommerce-invalid-required-field');
				if (!$firstError) $firstError = $input;
			}

			// Validação de email
			if (fieldId === 'billing_email' && value) {
				const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if (!emailRegex.test(value)) {
					isValid = false;
					$fieldWrapper.addClass('woocommerce-invalid woocommerce-invalid-email');
					if (!$firstError) $firstError = $input;
				}
			}

			// Validação de CPF - só valida se for obrigatório ou se houver valor preenchido
			if (fieldId === 'billing_cpf' && value) {
				const cpf = value.replace(/\D/g, '');
				if (cpf.length !== 11) {
					isValid = false;
					$fieldWrapper.addClass('woocommerce-invalid');
					if (!$firstError) $firstError = $input;
				}
			}
			// Se CPF não é obrigatório e está vazio, não valida
			// (não faz nada - já tratado acima com verificação de value)

			// Validação de CEP - só valida se for obrigatório ou se houver valor preenchido
			if (fieldId === 'billing_postcode') {
				// Só valida se houver valor preenchido (se estiver vazio e não for obrigatório, não valida)
				if (value && value.trim() !== '') {
					const cep = value.replace(/\D/g, '');
					if (cep.length !== 8) {
						isValid = false;
						$fieldWrapper.addClass('woocommerce-invalid');
						if (!$firstError) $firstError = $input;
					} else {
						// CEP válido, verifica se frete foi calculado
						if (!calculatedShipping) {
							isValid = false;
							$fieldWrapper.addClass('woocommerce-invalid');
							if (!$firstError) $firstError = $input;
							showNotice('Por favor, aguarde o cálculo do frete ou verifique se o CEP está correto.', 'error');
						}
					}
				} else if (isRequired) {
					// CEP é obrigatório mas está vazio
					isValid = false;
					$fieldWrapper.addClass('woocommerce-invalid woocommerce-invalid-required-field');
					if (!$firstError) $firstError = $input;
				}
			}
		});

		// Foca no primeiro campo com erro
		if ($firstError) {
			$firstError.focus();
			
			// Mostra mensagem de erro apenas se não foi mostrada anteriormente
			if (isValid || !calculatedShipping) {
				// Mensagem já foi mostrada na validação do CEP
			} else {
				showNotice('Por favor, preencha todos os campos obrigatórios corretamente.', 'error');
			}
		}

		return isValid;
	}

	/**
	 * Mostra uma notificação
	 */
	function showNotice(message, type) {
		const $notice = $(`
			<div class="woocommerce-notice woocommerce-notice--${type} woocommerce-${type}" role="alert">
				${message}
			</div>
		`);

		// Remove notificações anteriores
		$('.Gstore-checkout-step.is-active .woocommerce-notice').remove();

		// Adiciona nova notificação
		$('.Gstore-checkout-step.is-active .Gstore-checkout-step__header').after($notice);

		// Remove após 5 segundos
		setTimeout(() => {
			$notice.fadeOut(300, function() {
				$(this).remove();
			});
		}, 5000);
	}

	/**
	 * Avança para a próxima etapa
	 */
	function nextStep() {
		if (!validateCurrentStep()) {
			return;
		}

		if (currentStep < STEPS.length - 1) {
			setActiveStep(currentStep + 1);
			$(document.body).trigger('update_checkout');
		}
	}

	/**
	 * Volta para a etapa anterior
	 */
	function prevStep() {
		if (currentStep > 0) {
			setActiveStep(currentStep - 1);
		}
	}

	/**
	 * Carrega o resumo do carrinho via AJAX
	 */
	function loadCartSummary() {
		$.ajax({
			url: wc_checkout_params.ajax_url,
			type: 'POST',
			data: {
				action: 'gstore_get_cart_summary'
			},
			success: function(response) {
				if (response.success) {
					renderSummary(response.data);
				}
			},
			error: function() {
				// Fallback: extrai do DOM
				extractSummaryFromDOM();
			}
		});
	}

	/**
	 * Renderiza o resumo do carrinho
	 */
	function renderSummary(data) {
		lastCartSummaryData = data;

		// Atualiza contagem de itens
		$('.Gstore-summary-items-count').text(
			`${data.items_count} ${data.items_count === 1 ? 'item' : 'itens'} no carrinho`
		);

		// Atualiza total
		$('.Gstore-checkout-summary-top__total-amount').html(data.total);

		// Renderiza itens
		let itemsHtml = '';
		if (data.items && data.items.length) {
			data.items.forEach(item => {
				itemsHtml += `
					<div class="Gstore-summary-item">
						<img src="${item.image}" alt="${item.name}" class="Gstore-summary-item__image">
						<div class="Gstore-summary-item__info">
							<h4>${item.name}</h4>
							<span>Qtd: ${item.quantity}</span>
						</div>
						<span class="Gstore-summary-item__price">${item.subtotal}</span>
					</div>
				`;
			});
		}
		$('.Gstore-checkout-summary-top__items').html(itemsHtml);

		// Renderiza totais
		let totalsHtml = `
			<div class="Gstore-summary-row">
				<span>Subtotal</span>
				<span>${data.totals.subtotal}</span>
			</div>
		`;

		// Método de pagamento selecionado (Pix, Cartão, etc.)
		if (data.payment_method_title) {
			totalsHtml += `
				<div class="Gstore-summary-row">
					<span>Pagamento</span>
					<span>${data.payment_method_title}</span>
				</div>
			`;
		}

		if (data.totals.shipping) {
			totalsHtml += `
				<div class="Gstore-summary-row">
					<span>Frete</span>
					<span>${data.totals.shipping}</span>
				</div>
			`;
		}

		if (data.totals.discount) {
			totalsHtml += `
				<div class="Gstore-summary-row">
					<span>Desconto</span>
					<span>-${data.totals.discount}</span>
				</div>
			`;
		}

		// Taxas extras (ex: taxa de parcelamento)
		if (data.totals.fees && Array.isArray(data.totals.fees)) {
			data.totals.fees.forEach(fee => {
				if (!fee || !fee.label || !fee.total) return;
				totalsHtml += `
					<div class="Gstore-summary-row">
						<span>${fee.label}</span>
						<span>${fee.total}</span>
					</div>
				`;
			});
		}

		totalsHtml += `
			<div class="Gstore-summary-row Gstore-summary-row--total">
				<span>Total</span>
				<span>${data.total}</span>
			</div>
		`;

		// Informação de parcelamento (apenas informativa)
		if (data.payment_method === 'blu_checkout' && data.installments && data.installments.selected && parseInt(data.installments.selected, 10) > 1) {
			totalsHtml += `
				<div class="Gstore-summary-row Gstore-summary-row--installments">
					<span>${data.installments.selected}x de</span>
					<span>${data.installments.per_installment}</span>
				</div>
			`;
		}

		$('.Gstore-checkout-summary-top__totals').html(totalsHtml);

		updateInstallmentsPreview(data);
		setTimeout(maybeFetchInstallmentQuotes, 0);
	}

	function updateInstallmentsPreview(data) {
		const $preview = $('.Gstore-blu-installments__preview');
		if (!$preview.length) return;

		if (!data || !data.installments || !data.installments.selected) {
			$preview.text('');
			return;
		}

		if (data.payment_method !== 'blu_checkout') {
			$preview.html('');
			return;
		}

		const selected = parseInt(data.installments.selected, 10) || 1;
		if (selected <= 1) {
			$preview.html('');
			return;
		}

		// Detecta se existe “Taxa de parcelamento” na resposta
		let hasFee = false;
		if (data.totals && data.totals.fees && Array.isArray(data.totals.fees)) {
			hasFee = data.totals.fees.some(f => f && f.label && String(f.label).toLowerCase().indexOf('taxa') !== -1);
		}

		const suffix = hasFee ? ' (valores já com taxa — a taxa pode variar conforme as parcelas)' : '';
		$preview.html(`${selected}x de <strong>${data.installments.per_installment}</strong> — total <strong>${data.total}</strong>${suffix}`);
	}

	/**
	 * Extrai resumo do DOM (fallback)
	 */
	function extractSummaryFromDOM() {
		const $orderReview = $('.woocommerce-checkout-review-order-table');
		
		if (!$orderReview.length) return;

		// Conta itens
		const itemsCount = $orderReview.find('.cart_item').length;
		$('.Gstore-summary-items-count').text(
			`${itemsCount} ${itemsCount === 1 ? 'item' : 'itens'} no carrinho`
		);

		// Total
		const total = $orderReview.find('.order-total .amount').html();
		if (total) {
			$('.Gstore-checkout-summary-top__total-amount').html(total);
		}

		// Pagamento (fallback): tenta ler o label do método selecionado
		let paymentTitle = '';
		const $checked = $('input[name="payment_method"]:checked');
		if ($checked.length) {
			// Woo padrão: label dentro do <li> do método
			paymentTitle = $checked.closest('li').find('label').first().text().trim();
			// Layout unificado Blu pode ter label customizado
			if (!paymentTitle) {
				paymentTitle = $checked.closest('.Gstore-blu-payment-option').find('span').first().text().trim();
			}
		}
		if (paymentTitle) {
			const $totals = $('.Gstore-checkout-summary-top__totals');
			if ($totals.length) {
				// Evita duplicar se o resumo já tiver a linha
				const hasRow = $totals.find('.Gstore-summary-row').filter(function() {
					return $(this).find('span').first().text().trim() === 'Pagamento';
				}).length > 0;
				if (!hasRow) {
					$totals.prepend(`
						<div class="Gstore-summary-row">
							<span>Pagamento</span>
							<span>${paymentTitle}</span>
						</div>
					`);
				}
			}
		}
	}

	/**
	 * Vincula eventos
	 */
	function bindEvents() {
		// Navegação entre etapas
		$(document).on('click', '[data-action="next"]', function(e) {
			e.preventDefault();
			nextStep();
		});

		$(document).on('click', '[data-action="prev"]', function(e) {
			e.preventDefault();
			prevStep();
		});

		// Clique no stepper
		$(document).on('click', '.Gstore-checkout-stepper__step', function(e) {
			e.preventDefault();
			const index = parseInt($(this).data('step-index'), 10);
			
			// Só permite ir para etapas anteriores ou validar para ir para próximas
			if (index < currentStep) {
				setActiveStep(index);
			} else if (index === currentStep + 1) {
				nextStep();
			}
		});

		// Toggle do resumo
		$(document).on('click', '.Gstore-checkout-summary-top__toggle', function() {
			const $toggle = $(this);
			const $details = $('.Gstore-checkout-summary-top__details');
			
			$toggle.toggleClass('is-open');
			$details.toggleClass('is-visible');
			
			// Atualiza texto e ícone
			const isOpen = $toggle.hasClass('is-open');
			$toggle.html(
				(isOpen ? 'Ocultar detalhes' : 'Ver detalhes') +
				' <i class="fa-solid fa-chevron-down"></i>'
			);
		});

		// Atualiza resumo quando checkout é atualizado
		$(document.body).on('updated_checkout', function() {
			loadCartSummary();
			// O WooCommerce pode re-renderizar fragments; garante que o DOM continue dentro das etapas
			setTimeout(organizeFields, 0);
			setTimeout(ensureBluInstallmentsUI, 0);
			
			// Garante que o botão "Finalizar pedido" esteja visível apenas na última etapa
			const lastStepIndex = STEPS.length - 1;
			const $placeOrderBtn = $('#place_order, .place-order');
			if ($placeOrderBtn.length) {
				if (currentStep === lastStepIndex) {
					$placeOrderBtn.show();
				} else {
					$placeOrderBtn.hide();
				}
			}
		});

		// Toggle para observações do pedido
		$(document).on('change', '#toggle-order-notes', function() {
			const $container = $('.Gstore-checkout-notes-container');
			if ($(this).is(':checked')) {
				$container.slideDown(200);
			} else {
				$container.slideUp(200);
				// Limpa o campo quando esconde
				$container.find('textarea').val('');
			}
		});

		// Botões de editar no resumo
		$(document).on('click', '.Gstore-checkout-review__edit', function(e) {
			e.preventDefault();
			const stepIndex = parseInt($(this).data('goto-step'), 10);
			setActiveStep(stepIndex);
		});

		// Máscara para CPF
		$(document).on('input', '#billing_cpf', function() {
			let value = $(this).val().replace(/\D/g, '');
			if (value.length > 11) value = value.slice(0, 11);
			
			if (value.length > 9) {
				value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
			} else if (value.length > 6) {
				value = value.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
			} else if (value.length > 3) {
				value = value.replace(/(\d{3})(\d{1,3})/, '$1.$2');
			}
			
			$(this).val(value);
		});

		// Máscara para CEP e cálculo automático de frete
		$(document).on('input', '#billing_postcode', function() {
			let value = $(this).val().replace(/\D/g, '');
			if (value.length > 8) value = value.slice(0, 8);
			
			if (value.length > 5) {
				value = value.replace(/(\d{5})(\d{1,3})/, '$1-$2');
			}
			
			$(this).val(value);
			
			// Limpa resultado anterior quando CEP muda
			if (value.replace(/\D/g, '').length < 8) {
				hideShippingResult();
				calculatedShipping = null;
			}
		});

		// Calcula frete quando CEP perde o foco e está completo
		$(document).on('blur', '#billing_postcode', function() {
			const cep = $(this).val().replace(/\D/g, '');
			if (cep.length === 8) {
				// Aguarda um pouco para garantir que a máscara foi aplicada
				setTimeout(function() {
					calculateShipping($('#billing_postcode').val());
				}, 300);
			} else {
				hideShippingResult();
				calculatedShipping = null;
			}
		});

		// Máscara para telefone
		$(document).on('input', '#billing_phone', function() {
			let value = $(this).val().replace(/\D/g, '');
			if (value.length > 11) value = value.slice(0, 11);
			
			if (value.length > 10) {
				value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
			} else if (value.length > 6) {
				value = value.replace(/(\d{2})(\d{4,5})(\d{0,4})/, '($1) $2-$3');
			} else if (value.length > 2) {
				value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
			}
			
			$(this).val(value);
		});

		// Garante que o botão de finalizar pedido funcione corretamente
		$(document).on('click', '#place_order', function(e) {
			const lastStepIndex = STEPS.length - 1;
			if (currentStep !== lastStepIndex) {
				e.preventDefault();
				setActiveStep(lastStepIndex);
				return false;
			}
			
			const $paymentMethod = $('input[name="payment_method"]:checked');
			if (!$paymentMethod.length) {
				e.preventDefault();
				showNotice('Por favor, selecione um método de pagamento.', 'error');
				return false;
			}
			
			if ($checkoutForm.hasClass('processing')) {
				e.preventDefault();
				return false;
			}
			
			e.preventDefault();
			
			if (typeof showProcessingModal === 'function') {
				showProcessingModal();
			}
			
			setTimeout(function() {
				submitCheckoutDirectly();
			}, 200);
		});

		/**
		 * Mostra o modal de processamento
		 */
		function showProcessingModal() {
			// Remove modal existente se houver
			$('.Gstore-processing-modal').remove();
			
			const modalHtml = `
				<div class="Gstore-processing-modal">
					<div class="Gstore-processing-modal__backdrop"></div>
					<div class="Gstore-processing-modal__content">
						<button class="Gstore-processing-modal__close" aria-label="Fechar modal">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M18 6L6 18M6 6l12 12"/>
							</svg>
						</button>
						<div class="Gstore-processing-modal__spinner">
							<div class="Gstore-spinner"></div>
						</div>
						<div class="Gstore-processing-modal__text">
							<h3>Processando seu pedido...</h3>
							<p>Aguarde enquanto preparamos seu pagamento seguro.</p>
						</div>
						<div class="Gstore-processing-modal__steps">
							<div class="Gstore-processing-step is-active" data-step="1">
								<i class="fa-solid fa-circle-check"></i>
								<span>Validando dados</span>
							</div>
							<div class="Gstore-processing-step" data-step="2">
								<i class="fa-solid fa-circle"></i>
								<span>Criando pedido</span>
							</div>
							<div class="Gstore-processing-step" data-step="3">
								<i class="fa-solid fa-circle"></i>
								<span>Redirecionando para pagamento</span>
							</div>
						</div>
					</div>
				</div>
			`;
			
			$('body').append(modalHtml);
			
			// Adiciona event listener para o botão de fechar
			$('.Gstore-processing-modal__close').on('click', function() {
				hideProcessingModal();
			});
			
			// Anima a entrada
			setTimeout(function() {
				$('.Gstore-processing-modal').addClass('is-visible');
			}, 10);
			
			// Avança os passos automaticamente para dar feedback visual
			setTimeout(function() {
				updateProcessingStep(2);
			}, 800);
		}

		/**
		 * Atualiza o passo do modal de processamento
		 */
		function updateProcessingStep(step) {
			$('.Gstore-processing-step').each(function() {
				const $step = $(this);
				const stepNum = parseInt($step.data('step'));
				
				if (stepNum < step) {
					$step.removeClass('is-active').addClass('is-complete');
					$step.find('i').removeClass('fa-circle fa-circle-notch fa-spin').addClass('fa-circle-check');
				} else if (stepNum === step) {
					$step.addClass('is-active');
					$step.find('i').removeClass('fa-circle fa-circle-check').addClass('fa-circle-notch fa-spin');
				}
			});
		}

		/**
		 * Mostra sucesso no modal antes de redirecionar
		 */
		function showProcessingSuccess() {
			updateProcessingStep(4); // Marca todos como completos
			
			$('.Gstore-processing-modal__text h3').text('Pedido criado com sucesso!');
			$('.Gstore-processing-modal__text p').text('Redirecionando para o pagamento seguro...');
			$('.Gstore-processing-modal__spinner .Gstore-spinner').replaceWith(
				'<i class="fa-solid fa-circle-check Gstore-success-icon"></i>'
			);
		}

		/**
		 * Esconde o modal de processamento
		 */
		function hideProcessingModal() {
			$('.Gstore-processing-modal').removeClass('is-visible');
			setTimeout(function() {
				$('.Gstore-processing-modal').remove();
			}, 300);
		}

		/**
		 * Submete o checkout diretamente
		 */
		function refreshAndSubmit() {
			submitCheckoutDirectly();
		}

		/**
		 * Submit direto do checkout via AJAX
		 */
		function submitCheckoutDirectly() {
			const $form = $('form.checkout');
			if (!$form.length) return;
			if ($form.hasClass('processing')) return;
			
			const requiredFields = ['billing_email'];
			
			let missingFields = [];
			
			requiredFields.forEach(function(field) {
				const $field = $form.find('#' + field);
				// Verifica se o campo existe e se é obrigatório
				if ($field.length) {
					const $fieldWrapper = $field.closest('.form-row, .woocommerce-input-wrapper, #' + field + '_field');
					const isFieldRequired = $fieldWrapper.hasClass('validate-required') || 
					                        $field.prop('required') ||
					                        $field.attr('aria-required') === 'true';
					
					// Só considera obrigatório se o campo realmente estiver marcado como obrigatório
					if (isFieldRequired && (!$field.val() || $field.val().trim() === '')) {
						missingFields.push(field);
					}
				}
			});
			
			$form.addClass('processing');

			// Bloqueia o formulário visualmente
			$form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			// Coleta campos do formulário
			const formDataObj = {};
			
			// 1. Coleta campos de billing com valor
			$('[id^="billing_"]').each(function() {
				const $input = $(this);
				const name = $input.attr('name') || $input.attr('id');
				if (!name) return;
				
				const value = $input.val() ? $input.val().trim() : '';
				if (value) {
					formDataObj[name] = $input.val();
				}
			});
			
			// 2. Coleta campos de shipping (se houver)
			$('[id^="shipping_"]').each(function() {
				const $input = $(this);
				const name = $input.attr('name') || $input.attr('id');
				if (name && $input.val()) {
					formDataObj[name] = $input.val();
				}
			});
			
			// 3. Coleta payment_method - procura em qualquer lugar
			const $paymentRadio = $('input[name="payment_method"]:checked');
			if ($paymentRadio.length) {
				formDataObj['payment_method'] = $paymentRadio.val();
			} else {
				// Fallback: usa o primeiro método disponível
				const $firstPayment = $('input[name="payment_method"]').first();
				if ($firstPayment.length) {
					formDataObj['payment_method'] = $firstPayment.val();
				}
			}
			
			// 4. Coleta o nonce - procura em TODOS os lugares possíveis
			let nonceValue = null;
			
			// Procura em toda a página (os campos podem estar em qualquer lugar)
			const nonceSelectors = [
				'#woocommerce-process-checkout-nonce',
				'input[name="woocommerce-process-checkout-nonce"]',
				'#_wpnonce',
				'input[name="_wpnonce"]'
			];
			
			for (let selector of nonceSelectors) {
				const $el = $(selector);
				if ($el.length && $el.val()) {
					nonceValue = $el.val();
					break;
				}
			}
			
			// Fallback: procura qualquer input com "nonce" no nome
			if (!nonceValue) {
				$('input').each(function() {
					const name = $(this).attr('name') || '';
					const id = $(this).attr('id') || '';
					if ((name.indexOf('nonce') !== -1 || id.indexOf('nonce') !== -1) && $(this).val()) {
						nonceValue = $(this).val();
						return false;
					}
				});
			}

			// Fallback preferencial: nonce específico para process_checkout exposto pelo tema
			if (!nonceValue && window.gstoreCheckout && window.gstoreCheckout.processCheckoutNonce) {
				nonceValue = window.gstoreCheckout.processCheckoutNonce;
			}
			
			// Último fallback: nonce de update_order_review (não é o ideal para process_checkout)
			if (!nonceValue && typeof wc_checkout_params !== 'undefined') {
				if (wc_checkout_params.update_order_review_nonce) {
					nonceValue = wc_checkout_params.update_order_review_nonce;
				}
			}

			if (nonceValue) {
				formDataObj['woocommerce-process-checkout-nonce'] = nonceValue;
				formDataObj['_wpnonce'] = nonceValue;
			}
			
			// 5. Coleta campos hidden importantes
			$('input[type="hidden"]').each(function() {
				const $input = $(this);
				const name = $input.attr('name');
				if (name && $input.val()) {
					// Inclui apenas campos relevantes para o checkout
					if (name.indexOf('wc_') === 0 || 
					    name.indexOf('woocommerce') === 0 || 
					    name.indexOf('_wp') === 0 ||
					    name === 'terms' ||
					    name === 'terms-field' ||
					    name === 'ship_to_different_address') {
						formDataObj[name] = $input.val();
					}
				}
			});
			
			// 6. Coleta campos do formulário original que ainda existem
			$form.find('input, select, textarea').each(function() {
				const $input = $(this);
				const name = $input.attr('name');
				if (!name || formDataObj[name]) return;
				
				if ($input.is(':checkbox')) {
					if ($input.is(':checked')) {
						formDataObj[name] = $input.val() || '1';
					}
				} else if ($input.is(':radio')) {
					if ($input.is(':checked')) {
						formDataObj[name] = $input.val();
					}
				} else {
					const val = $input.val();
					if (val) formDataObj[name] = val;
				}
			});
			
			// 7. Garante campos obrigatórios para o WooCommerce
			formDataObj['woocommerce_checkout_place_order'] = '1';
			
			// Converte para query string
			let formData = $.param(formDataObj);

			$.ajax({
				type: 'POST',
				url: wc_checkout_params.checkout_url,
				data: formData,
				dataType: 'json',
				success: function(response) {
					updateProcessingStep(3);
					
					if (response.result === 'success') {
						setTimeout(function() { showProcessingSuccess(); }, 500);
						if (isBluCheckoutSelected()) {
							setTimeout(function() {
								hideProcessingModal();
								openBluCheckoutModal(response.redirect);
							}, 900);
						} else {
							setTimeout(function() { window.location.href = response.redirect; }, 1500);
						}
					} else if (response.result === 'failure') {
						hideProcessingModal();
						$form.removeClass('processing').unblock();
						
						if (response.refresh) {
							$(document.body).trigger('update_checkout');
						}
						
						if (response.messages) {
							$('.woocommerce-notices-wrapper, .woocommerce-error').remove();
							const $activeStep = $('.Gstore-checkout-step.is-active');
							if ($activeStep.length) {
								$activeStep.find('.Gstore-checkout-step__header').after(
									'<div class="woocommerce-notices-wrapper">' + response.messages + '</div>'
								);
							} else {
								$form.prepend('<div class="woocommerce-notices-wrapper">' + response.messages + '</div>');
							}
							$('html, body').animate({
								scrollTop: $('.Gstore-checkout-steps__content').offset().top - 100
							}, 500);
						}
						
						if (response.reload) {
							setTimeout(function() { window.location.reload(); }, 2000);
						}
					}
				},
				error: function() {
					hideProcessingModal();
					$form.removeClass('processing').unblock();
					showNotice('Ocorreu um erro ao processar o pedido. Por favor, tente novamente.', 'error');
				}
			});
		}

	}

	/**
	 * === MODAL: Checkout Blu (Link Externo) ===
	 * Mantém o usuário na página e abre o checkout da Blu em um iframe quando possível.
	 * Se o embed for bloqueado (X-Frame-Options/CSP), o botão "Abrir em nova aba" funciona como fallback.
	 */
	function isBluCheckoutSelected() {
		const selected = $('input[name="payment_method"]:checked').val();
		return selected === 'blu_checkout';
	}

	function ensureBluCheckoutModal() {
		if ($('#gstore-blu-checkout-modal').length) return;

		$('body').append(`
			<div id="gstore-blu-checkout-modal" class="Gstore-blu-checkout-modal" aria-hidden="true">
				<div class="Gstore-blu-checkout-modal__backdrop" data-action="close"></div>
				<div class="Gstore-blu-checkout-modal__content" role="dialog" aria-modal="true" aria-label="Checkout Blu">
					<button type="button" class="Gstore-blu-checkout-modal__close" data-action="close" aria-label="Fechar">
						<i class="fa-solid fa-xmark" aria-hidden="true"></i>
					</button>
					<div class="Gstore-blu-checkout-modal__header">
						<div class="Gstore-blu-checkout-modal__title">
							Finalize seu pagamento na Blu
						</div>
						<div class="Gstore-blu-checkout-modal__actions">
							<a class="Gstore-btn Gstore-btn--submit Gstore-blu-checkout-modal__open" href="#" target="_blank" rel="noopener noreferrer">
								Abrir em nova aba
							</a>
						</div>
					</div>
					<div class="Gstore-blu-checkout-modal__hint" aria-live="polite">
						Se o checkout não carregar aqui, use “Abrir em nova aba”.
					</div>
					<div class="Gstore-blu-checkout-modal__frame-wrap">
						<iframe class="Gstore-blu-checkout-modal__frame" title="Checkout Blu" loading="eager" referrerpolicy="no-referrer-when-downgrade" allow="payment; clipboard-write" allowpaymentrequest></iframe>
					</div>
				</div>
			</div>
		`);

		// Fechar ao clicar no backdrop ou no botão
		$(document).on('click', '#gstore-blu-checkout-modal [data-action="close"]', function() {
			closeBluCheckoutModal();
		});

		// Fechar com ESC
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape') {
				const $modal = $('#gstore-blu-checkout-modal');
				if ($modal.length && $modal.hasClass('is-visible')) {
					closeBluCheckoutModal();
				}
			}
		});
	}

	function openBluCheckoutModal(url) {
		if (!url) return;
		ensureBluCheckoutModal();

		const $modal = $('#gstore-blu-checkout-modal');
		const $iframe = $modal.find('.Gstore-blu-checkout-modal__frame');
		const $open = $modal.find('.Gstore-blu-checkout-modal__open');

		$open.attr('href', url);

		// Reset para evitar manter estado antigo
		$iframe.attr('src', 'about:blank');
		setTimeout(function() {
			$iframe.attr('src', url);
		}, 50);

		$modal.addClass('is-visible').attr('aria-hidden', 'false');
		$('body').addClass('gstore-blu-checkout-modal-open');

		// Foco inicial no botão de abrir em nova aba
		setTimeout(function() {
			$open.trigger('focus');
		}, 50);
	}

	function closeBluCheckoutModal() {
		const $modal = $('#gstore-blu-checkout-modal');
		if (!$modal.length) return;

		$modal.removeClass('is-visible').attr('aria-hidden', 'true');
		$('body').removeClass('gstore-blu-checkout-modal-open');

		// Limpa o iframe para parar carregamentos/sons
		$modal.find('.Gstore-blu-checkout-modal__frame').attr('src', 'about:blank');
	}

	// Inicializa quando o DOM estiver pronto
	$(document).ready(function() {
		// Aguarda um momento para o WooCommerce carregar
		setTimeout(init, 100);
	});

	// Variável para armazenar o método selecionado antes do update
	let lastSelectedPaymentMethod = null;
	
	// Armazena a seleção antes do update
	$(document.body).on('update_checkout', function() {
		const $selected = $('input[name="payment_method"]:checked');
		if ($selected.length) {
			lastSelectedPaymentMethod = $selected.val();
		}
	});
	
	// Reinicializa quando o checkout é atualizado via AJAX
	$(document.body).on('init_checkout updated_checkout', function() {
		// Evita processar se já estamos atualizando o pagamento
		if (isUpdatingPayment) {
			return;
		}
		
		// Restaura a seleção após o update
		if (lastSelectedPaymentMethod) {
			setTimeout(function() {
				const $radio = $(`input[name="payment_method"][value="${lastSelectedPaymentMethod}"]`);
				if ($radio.length && !$radio.is(':checked')) {
					// Não dispara change para evitar loops
					$radio.prop('checked', true);
				}
			}, 50);
		}
		
		// Re-aplica unificação dos métodos Blu após atualização
		setTimeout(function() {
			unifyBluPaymentMethods();
		}, 200);
		
		if (!initialized) {
			setTimeout(init, 100);
		}
	});

	// Intercepta a resposta do checkout para garantir redirect
	$(document).ajaxComplete(function(event, xhr, settings) {
		if (settings.url && settings.url.indexOf('wc-ajax=checkout') !== -1) {
			try {
				const response = JSON.parse(xhr.responseText);
				if (response.result === 'success' && response.redirect) {
					if (isBluCheckoutSelected()) {
						openBluCheckoutModal(response.redirect);
					} else {
						window.location.href = response.redirect;
					}
				}
			} catch (e) {
				// Não é JSON - normal para outras respostas
			}
		}
	});

	/**
	 * Verifica se algum gateway Blu está disponível no DOM
	 * @return {boolean}
	 */
	function isBluGatewayAvailable() {
		const $bluCheckout = $('.payment_method_blu_checkout');
		const $bluPix = $('.payment_method_blu_pix');
		return $bluCheckout.length > 0 || $bluPix.length > 0;
	}

	// Garante que os estilos do card Blu sejam mantidos após atualizações do checkout
	function ensureBluCardStyles() {
		const $bluPaymentBox = $('.payment_method_blu_checkout .payment_box');
		if ($bluPaymentBox.length) {
			// Verifica se os badges já existem
			if (!$bluPaymentBox.find('.Gstore-blu-trust-badges').length) {
				$bluPaymentBox.append(`
					<div class="Gstore-blu-trust-badges">
						<span class="Gstore-blu-trust-badge">
							<i class="fa-solid fa-lock"></i> 256-bit SSL
						</span>
						<span class="Gstore-blu-trust-badge">
							<i class="fa-solid fa-shield-halved"></i> Anti-fraude
						</span>
						<span class="Gstore-blu-trust-badge">
							<i class="fa-solid fa-credit-card"></i> PCI DSS
						</span>
						<span class="Gstore-blu-trust-badge">
							<i class="fa-solid fa-user-shield"></i> LGPD
						</span>
					</div>
				`);
			}
			
			// Força a aplicação dos estilos adicionando uma classe se necessário
			const $bluCard = $('.payment_method_blu_checkout');
			if ($bluCard.length && !$bluCard.hasClass('gstore-blu-styled')) {
				$bluCard.addClass('gstore-blu-styled');
			}
		}
		
		// Garante que o Pix esteja visível quando estiver ativo (especialmente no pré-checkout)
		const $pixGateway = $('.payment_method_blu_pix');
		if ($pixGateway.length && isBluGatewayAvailable()) {
			$pixGateway.show();
		}
	}

	// Listener para quando o checkout é atualizado pelo WooCommerce
	$(document.body).on('updated_checkout', function() {
		// Garante que os estilos do card Blu sejam mantidos
		setTimeout(ensureBluCardStyles, 100);
	});

	// Também executa após o carregamento completo da página
	$(document).ready(function() {
		setTimeout(ensureBluCardStyles, 500);
	});

	// Executa quando os métodos de pagamento são carregados
	$(document.body).on('payment_method_selected', function() {
		setTimeout(ensureBluCardStyles, 100);
	});

})(jQuery);

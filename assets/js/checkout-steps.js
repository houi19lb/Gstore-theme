/**
 * Checkout em 3 Etapas - Gstore
 * 
 * Transforma o checkout clássico do WooCommerce em um fluxo de 3 etapas:
 * - Etapa 1: Dados Pessoais
 * - Etapa 2: Endereço de Entrega  
 * - Etapa 3: Pagamento
 * 
 * Quando o gateway Blu está selecionado, simplifica para pré-checkout:
 * - Apenas email e telefone
 * - Dados completos serão coletados no checkout da Blu
 */

(function($) {
	'use strict';

	// Configuração padrão das etapas (3 etapas completas)
	const STEPS_FULL = [
		{
			id: 'personal',
			name: 'Dados Pessoais',
			icon: 'fa-user',
			title: 'Seus Dados',
			description: 'Informe seus dados pessoais para identificação do pedido.',
			fields: [
				'billing_first_name',
				'billing_last_name', 
				'billing_cpf',
				'billing_email',
				'billing_phone'
			]
		},
		{
			id: 'shipping',
			name: 'Entrega',
			icon: 'fa-truck',
			title: 'Endereço de Entrega',
			description: 'Preencha o endereço onde deseja receber sua compra.',
			fields: [
				'billing_postcode',
				'billing_address_1',
				'billing_number',
				'billing_address_2',
				'billing_neighborhood',
				'billing_city',
				'billing_state'
			]
		},
		{
			id: 'payment',
			name: 'Pagamento',
			icon: 'fa-credit-card',
			title: 'Pagamento',
			description: 'Escolha a forma de pagamento e finalize seu pedido com segurança.',
			fields: []
		}
	];

	// Configuração simplificada para pré-checkout Blu (apenas email e telefone)
	const STEPS_BLU_PRECHECKOUT = [
		{
			id: 'precheckout',
			name: 'Dados Básicos',
			icon: 'fa-envelope',
			title: 'Pré-Checkout',
			description: 'Informe seu email e telefone. Você será redirecionado para finalizar os dados e pagamento na Blu.',
			fields: [
				'billing_email',
				'billing_phone'
			]
		},
		{
			id: 'payment',
			name: 'Pagamento',
			icon: 'fa-credit-card',
			title: 'Gerar Link de Pagamento',
			description: 'Clique no botão abaixo para gerar o link e ser redirecionado para o checkout seguro da Blu.',
			fields: []
		}
	];

	let STEPS = STEPS_FULL; // Inicializa com etapas completas

	let currentStep = 0;
	let $checkoutForm = null;
	let $stepsContainer = null;
	let initialized = false;

	/**
	 * Verifica se o gateway Blu está disponível ou selecionado
	 */
	function isBluGatewayAvailable() {
		// Verifica se o gateway Blu existe na página
		const $bluGateway = $('.payment_method_blu_checkout');
		return $bluGateway.length > 0;
	}

	/**
	 * Verifica se o gateway Pix está disponível
	 */
	function isPixGatewayAvailable() {
		// Verifica se o gateway Pix existe na página
		const $pixGateway = $('.payment_method_blu_pix');
		return $pixGateway.length > 0;
	}

	/**
	 * Verifica se o gateway Blu está atualmente selecionado
	 */
	function isBluGatewaySelected() {
		const $bluRadio = $('input[name="payment_method"][value="blu_checkout"]:checked');
		return $bluRadio.length > 0;
	}

	/**
	 * Verifica se o gateway Pix está atualmente selecionado
	 */
	function isPixGatewaySelected() {
		const $pixRadio = $('input[name="payment_method"][value="blu_pix"]:checked');
		return $pixRadio.length > 0;
	}

	/**
	 * Determina se deve usar o pré-checkout simplificado
	 */
	function shouldUseBluPrecheckout() {
		// Se o gateway Blu está disponível, usa pré-checkout
		// Isso permite que tanto Blu quanto Pix apareçam juntos na segunda etapa
		if (!isBluGatewayAvailable()) {
			return false;
		}
		
		// Se Blu está selecionado, usa pré-checkout
		if (isBluGatewaySelected()) {
			return true;
		}
		
		// Se Pix está selecionado e Blu está disponível, também usa pré-checkout
		// (ambos podem aparecer juntos na segunda etapa)
		if (isPixGatewaySelected() && isBluGatewayAvailable()) {
			return true;
		}
		
		// Se Blu está disponível (mesmo que não selecionado), usa pré-checkout
		// Isso permite que o usuário veja e escolha entre Blu e Pix na segunda etapa
		// Verifica se há outros métodos de pagamento além de Blu/Pix
		const $allPaymentMethods = $('input[name="payment_method"]');
		const hasOtherMethods = $allPaymentMethods.filter(function() {
			const value = $(this).val();
			return value !== 'blu_checkout' && value !== 'blu_pix';
		}).length > 0;
		
		// Se não há outros métodos além de Blu/Pix, usa pré-checkout
		// Se há outros métodos, só usa pré-checkout se Blu ou Pix estiverem selecionados
		if (!hasOtherMethods) {
			return true;
		}
		
		return false;
	}

	/**
	 * Inicializa o checkout de etapas
	 */
	function init() {
		if (initialized) return;
		
		$checkoutForm = $('form.checkout.woocommerce-checkout');
		
		if (!$checkoutForm.length) {
			// console.log('Gstore Steps: Aguardando formulário de checkout...');
			return;
		}

		// Verifica se já foi inicializado
		if ($('.Gstore-checkout-steps').length) {
			return;
		}

		// Define as etapas baseado no gateway selecionado
		if (shouldUseBluPrecheckout()) {
			STEPS = STEPS_BLU_PRECHECKOUT;
		} else {
			STEPS = STEPS_FULL;
		}

		buildStepsUI();
		bindEvents();
		loadCartSummary();
		
		initialized = true;
		// console.log('Gstore Checkout Steps: Inicializado com sucesso', STEPS.length, 'etapas');
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
				${isLast ? '<div class="Gstore-checkout-step__payment-container"></div>' : ''}
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
			// Esconde os elementos originais mas mantém no DOM para o WooCommerce
			$bluCheckout.css({ position: 'absolute', left: '-9999px', opacity: 0, pointerEvents: 'none' });
			$bluPix.css({ position: 'absolute', left: '-9999px', opacity: 0, pointerEvents: 'none' });
			
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
			function updatePaymentContent(skipCheckoutUpdate) {
				let selectedMethod = null;
				
				// Verifica qual radio original está selecionado
				if ($checkoutRadio.is(':checked')) {
					selectedMethod = 'blu_checkout';
					$checkoutRadioClone.prop('checked', true);
					$pixRadioClone.prop('checked', false);
				} else if ($pixRadio.is(':checked')) {
					selectedMethod = 'blu_pix';
					$pixRadioClone.prop('checked', true);
					$checkoutRadioClone.prop('checked', false);
				}
				
				if (!selectedMethod) return;
				
				$content.empty();
				
				if (selectedMethod === 'blu_checkout') {
					const $checkoutBox = $bluCheckout.find('.payment_box').clone();
					$content.append($checkoutBox);
				} else if (selectedMethod === 'blu_pix') {
					const $pixBox = $bluPix.find('.payment_box').clone();
					$content.append($pixBox);
				}
				
				// Trigger update do WooCommerce apenas se não for para pular
				if (!skipCheckoutUpdate) {
					// Usa um pequeno delay para evitar loop infinito
					setTimeout(function() {
						$(document.body).trigger('update_checkout');
					}, 50);
				}
			}
			
			// Sincroniza cliques no label com o radio original
			$checkoutOption.find('label').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$checkoutRadio.prop('checked', true);
				$checkoutRadioClone.prop('checked', true);
				$pixRadio.prop('checked', false);
				$pixRadioClone.prop('checked', false);
				// Dispara evento de mudança no WooCommerce
				$checkoutRadio.trigger('change');
				updatePaymentContent();
			});
			
			// Sincroniza cliques no label com o radio original
			$pixOption.find('label').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$pixRadio.prop('checked', true);
				$pixRadioClone.prop('checked', true);
				$checkoutRadio.prop('checked', false);
				$checkoutRadioClone.prop('checked', false);
				// Dispara evento de mudança no WooCommerce
				$pixRadio.trigger('change');
				updatePaymentContent();
			});
			
			// Sincroniza mudanças nos radios originais
			$checkoutRadio.off('change.gstore-unify').on('change.gstore-unify', function() {
				updatePaymentContent();
			});
			$pixRadio.off('change.gstore-unify').on('change.gstore-unify', function() {
				updatePaymentContent();
			});
			
			// Listener para quando o checkout é atualizado - mantém a seleção
			$(document.body).on('updated_checkout.gstore-unify', function() {
				setTimeout(function() {
					// Restaura a seleção após o update
					if ($pixRadio.is(':checked')) {
						$pixRadioClone.prop('checked', true);
						$checkoutRadioClone.prop('checked', false);
						updatePaymentContent(true);
					} else if ($checkoutRadio.is(':checked')) {
						$checkoutRadioClone.prop('checked', true);
						$pixRadioClone.prop('checked', false);
						updatePaymentContent(true);
					}
				}, 100);
			});
			
			// Mostra conteúdo inicial
			setTimeout(function() {
				if ($checkoutRadio.is(':checked') || $pixRadio.is(':checked')) {
					updatePaymentContent();
				} else {
					// Seleciona o primeiro por padrão
					$checkoutRadio.prop('checked', true).trigger('change');
				}
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
	 * Organiza os campos nas etapas corretas
	 */
	function organizeFields() {
		const isPrecheckout = STEPS.length === 2; // Pré-checkout Blu tem 2 etapas

		// Organiza campos da primeira etapa (dados pessoais ou pré-checkout)
		const $firstStep = $(`[data-step="${STEPS[0].id}"] .Gstore-checkout-step__fields`);
		if ($firstStep.length) {
			STEPS[0].fields.forEach(fieldId => {
				const $field = $(`#${fieldId}_field`);
				if ($field.length) {
					$firstStep.append($field.detach());
				}
			});
		}

		// Se não for pré-checkout, organiza etapa de endereço
		if (!isPrecheckout && STEPS.length > 1) {
			const $shippingStep = $('[data-step="shipping"] .Gstore-checkout-step__fields');
			if ($shippingStep.length && STEPS[1]) {
				STEPS[1].fields.forEach(fieldId => {
					const $field = $(`#${fieldId}_field`);
					if ($field.length) {
						$shippingStep.append($field.detach());
					}
				});

				// Adiciona container de opções de envio na etapa 2
				const $shippingMethods = $('#shipping_method, .woocommerce-shipping-methods').closest('tr, .woocommerce-shipping-totals');
				if ($shippingMethods.length) {
					$shippingStep.append(`
						<div class="Gstore-checkout-section">
							<h3 class="Gstore-checkout-section__title">
								<i class="fa-solid fa-truck-fast"></i>
								Opções de Envio
							</h3>
							<div class="Gstore-shipping-container"></div>
						</div>
					`);
				}
			}
		}

		// Etapa de pagamento (última etapa)
		const $paymentStep = $('[data-step="payment"] .Gstore-checkout-step__payment-container');
		
		// 1. Adiciona resumo dos dados do cliente (apenas se não for pré-checkout)
		if (!isPrecheckout) {
			$paymentStep.append(`
				<div class="Gstore-checkout-review">
					<div class="Gstore-checkout-review__section">
						<div class="Gstore-checkout-review__header">
							<i class="fa-solid fa-user"></i>
							<span>Dados Pessoais</span>
							<button type="button" class="Gstore-checkout-review__edit" data-goto-step="0">
								<i class="fa-solid fa-pen"></i> Editar
							</button>
						</div>
						<div class="Gstore-checkout-review__content" id="review-personal"></div>
					</div>
					<div class="Gstore-checkout-review__section">
						<div class="Gstore-checkout-review__header">
							<i class="fa-solid fa-location-dot"></i>
							<span>Endereço de Entrega</span>
							<button type="button" class="Gstore-checkout-review__edit" data-goto-step="1">
								<i class="fa-solid fa-pen"></i> Editar
							</button>
						</div>
						<div class="Gstore-checkout-review__content" id="review-shipping"></div>
					</div>
				</div>
			`);
		} else {
			// No pré-checkout, mensagem simplificada removida - informação será mostrada no card de pagamento
		}

		// DEBUG: Log para verificar se chegamos até aqui
		// console.log('Gstore Steps: Organizando etapa de pagamento');

		// 2. Adiciona toggle para observações
		$paymentStep.append(`
			<div class="Gstore-checkout-notes-toggle">
				<label class="Gstore-toggle">
					<span class="Gstore-toggle__label">Adicionar observações ao pedido</span>
					<input type="checkbox" id="toggle-order-notes">
					<span class="Gstore-toggle__slider"></span>
				</label>
				<div class="Gstore-checkout-notes-container" style="display: none;"></div>
			</div>
		`);

		// Move campos adicionais (notas do pedido) para dentro do container
		const $additionalFields = $('.woocommerce-additional-fields');
		if ($additionalFields.length) {
			$('.Gstore-checkout-notes-container').append($additionalFields.detach());
		}

		// 3. Move seção de pagamento e reorganiza métodos Blu
		const $paymentSection = $('#payment');
		if ($paymentSection.length) {
			$paymentStep.append($paymentSection.detach());
			
			// Chama função para unificar métodos Blu
			setTimeout(function() {
				unifyBluPaymentMethods();
			}, 150);
			
			// Ajusta labels quando apenas um método está disponível
			const $bluCheckout = $('.payment_method_blu_checkout').not('.Gstore-blu-payment-unified .payment_method_blu_checkout');
			const $bluPix = $('.payment_method_blu_pix').not('.Gstore-blu-payment-unified .payment_method_blu_pix');
			
			if ($bluCheckout.length && !$bluPix.length) {
				// Apenas checkout disponível - simplifica badges
				// Apenas checkout disponível - simplifica badges
				const $bluPaymentBox = $bluCheckout.find('.payment_box');
				if ($bluPaymentBox.length) {
					$bluPaymentBox.append(`
						<div class="Gstore-blu-trust-badges-simple">
							<span class="Gstore-blu-trust-badge-simple">
								<i class="fa-solid fa-shield-halved"></i> Pagamento seguro
							</span>
						</div>
					`);
				}
				// Garante que o label mostre o nome correto
				const $checkoutLabel = $bluCheckout.find('label');
				if ($checkoutLabel.length && !$checkoutLabel.find('i').length) {
					const $radio = $checkoutLabel.find('input[type="radio"]').detach();
					$checkoutLabel.empty().append($radio).append('<i class="fa-solid fa-credit-card"></i> Cartão (Link de Pagamento)');
				}
			} else if ($bluPix.length) {
				// Apenas Pix disponível - garante que esteja visível e com estilo correto
				$bluPix.show();
				const $pixLabel = $bluPix.find('label');
				if ($pixLabel.length && !$pixLabel.find('i').length) {
					const $radio = $pixLabel.find('input[type="radio"]').detach();
					$pixLabel.empty().append($radio).append('<i class="fa-solid fa-qrcode"></i> Pix');
				}
			}
			
			// Garante que o botão de finalizar pedido esteja visível
			setTimeout(function() {
				const $placeOrderBtn = $('#place_order');
				if ($placeOrderBtn.length) {
					$placeOrderBtn.show().css({
						'display': 'inline-block',
						'visibility': 'visible',
						'opacity': '1'
					});
				}
			}, 100);
		}

		// Esconde seções vazias do WooCommerce
		$('.woocommerce-billing-fields').hide();
		$('.woocommerce-shipping-fields').hide();

		// 4. Garantias removidas para simplificar a interface
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

		// Atualiza resumo quando entrar na última etapa (pagamento)
		const lastStepIndex = STEPS.length - 1;
		if (index === lastStepIndex) {
			// Só atualiza resumo se não for pré-checkout (checkout completo tem 3 etapas)
			if (STEPS.length === 3) {
				updateReviewData();
			}
			
			// Reinicializa os eventos do WooCommerce na etapa de pagamento
			setTimeout(function() {
				$(document.body).trigger('update_checkout');
				
				// Garante que o botão place_order esteja visível e clicável
				const $placeOrderBtn = $('#place_order');
				if ($placeOrderBtn.length) {
					$placeOrderBtn.prop('disabled', false)
						.removeClass('disabled')
						.show()
						.css({
							'display': 'inline-block',
							'visibility': 'visible',
							'opacity': '1',
							'pointer-events': 'auto'
						});
					// console.log('Gstore Steps: Botão finalizar pedido habilitado na última etapa');
				} else {
					// console.warn('Gstore Steps: Botão #place_order não encontrado na última etapa');
				}
				
				// Remove class 'processing' se existir (pode ter ficado de tentativa anterior)
				$checkoutForm.removeClass('processing');
			}, 200);
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
					}
				}
				// Se CEP não é obrigatório e está vazio, não valida (não faz nada)
			}
		});

		// Foca no primeiro campo com erro
		if ($firstError) {
			$firstError.focus();
			
			// Mostra mensagem de erro
			showNotice('Por favor, preencha todos os campos obrigatórios corretamente.', 'error');
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
			
			// Atualiza cálculos do checkout
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

		totalsHtml += `
			<div class="Gstore-summary-row Gstore-summary-row--total">
				<span>Total</span>
				<span>${data.total}</span>
			</div>
		`;

		$('.Gstore-checkout-summary-top__totals').html(totalsHtml);
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

		// Máscara para CEP
		$(document).on('input', '#billing_postcode', function() {
			let value = $(this).val().replace(/\D/g, '');
			if (value.length > 8) value = value.slice(0, 8);
			
			if (value.length > 5) {
				value = value.replace(/(\d{5})(\d{1,3})/, '$1-$2');
			}
			
			$(this).val(value);
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
			// console.log('Gstore Steps: Botão "Finalizar Pedido" clicado');
			
			// Verifica se estamos na última etapa (pagamento)
			// No checkout completo são 3 etapas (índice 2), no pré-checkout são 2 etapas (índice 1)
			const lastStepIndex = STEPS.length - 1;
			if (currentStep !== lastStepIndex) {
				e.preventDefault();
				// console.warn('Gstore Steps: Usuário não está na etapa de pagamento');
				setActiveStep(lastStepIndex);
				return false;
			}
			
			// Valida se um método de pagamento foi selecionado
			const $paymentMethod = $('input[name="payment_method"]:checked');
			if (!$paymentMethod.length) {
				e.preventDefault();
				showNotice('Por favor, selecione um método de pagamento.', 'error');
				// console.warn('Gstore Steps: Nenhum método de pagamento selecionado');
				return false;
			}
			
			// console.log('Gstore Steps: Método de pagamento selecionado:', $paymentMethod.val());
			
			// Verifica se o formulário está em processamento
			if ($checkoutForm.hasClass('processing')) {
				e.preventDefault();
				// console.log('Gstore Steps: Formulário já está processando, aguarde...');
				return false;
			}
			
			// Verifica se estamos no pré-checkout (2 etapas)
			const isPrecheckout = STEPS.length === 2;
			
			if (isPrecheckout) {
				// No pré-checkout, precisamos fazer o submit via AJAX manualmente
				e.preventDefault();
				// console.log('Gstore Steps: Pré-checkout detectado - executando submit manual via AJAX');
				
				// Mostra o modal de processamento
				if (typeof showProcessingModal === 'function') {
					showProcessingModal();
				}
				
				// Chama submitCheckoutDirectly com um pequeno delay para garantir que o modal apareça
				setTimeout(function() {
					submitCheckoutDirectly();
				}, 200);
			} else {
				// No checkout completo, permite que o WooCommerce faça o submit padrão
				// Apenas mostra o modal de carregamento se existir
				if (typeof showProcessingModal === 'function') {
					showProcessingModal();
				}
				
				// Não previne o comportamento padrão - deixa o WooCommerce fazer o submit
				// console.log('Gstore Steps: Permitindo submit padrão do WooCommerce');
			}
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
		 * Submete o checkout diretamente sem tentar atualizar primeiro
		 * (O update_order_review está dando 403, então vamos direto)
		 */
		function refreshAndSubmit() {
			// console.log('Gstore Steps: Iniciando submit do checkout...');
			submitCheckoutDirectly();
		}

		/**
		 * Submit direto do checkout via AJAX
		 */
		function submitCheckoutDirectly() {
			// console.log('Gstore Steps: [DEBUG] Executando submit direto do checkout');
			
			const $form = $('form.checkout');
			if (!$form.length) {
				console.error('Gstore Steps: [DEBUG] Formulário de checkout não encontrado');
				return;
			}

			// Verifica se já está processando
			if ($form.hasClass('processing')) {
				// console.log('Gstore Steps: [DEBUG] Já está processando');
				return;
			}

			// Debug: verifica campos importantes - procura em TODA a página
			const nonceInPage = $('#woocommerce-process-checkout-nonce').val() || 
			                    $('input[name="woocommerce-process-checkout-nonce"]').val();
			const paymentMethodInPage = $('input[name="payment_method"]:checked').val();
			
			// console.log('Gstore Steps: === DEBUG DE FORMULÁRIO ===');
			// console.log('Gstore Steps: Nonce na página:', nonceInPage ? 'OK (' + nonceInPage.substring(0, 10) + '...)' : 'FALTANDO!');
			// console.log('Gstore Steps: Payment Method na página:', paymentMethodInPage);
			// console.log('Gstore Steps: Billing First Name:', $('#billing_first_name').val());
			// console.log('Gstore Steps: Billing Email:', $('#billing_email').val());
			
			// Lista todos os campos de nonce na página
			// console.log('Gstore Steps: Campos nonce encontrados:');
			// $('input[id*="nonce"], input[name*="nonce"]').each(function() {
			// 	console.log('  -', $(this).attr('name') || $(this).attr('id'), '=', $(this).val() ? $(this).val().substring(0, 15) + '...' : 'vazio');
			// });
			
			// Verifica campos obrigatórios - diferente para pré-checkout vs checkout completo
			const isPrecheckout = STEPS.length === 2;
			let requiredFields;
			
			if (isPrecheckout) {
				// No pré-checkout, apenas email e telefone são obrigatórios
				requiredFields = ['billing_email', 'billing_phone'];
				// console.log('Gstore Steps: Pré-checkout detectado - validando apenas email e telefone');
			} else {
				// No checkout completo, todos os campos de endereço são obrigatórios
				requiredFields = ['billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone', 
				                  'billing_postcode', 'billing_address_1', 'billing_city', 'billing_state'];
			}
			
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
			
			if (missingFields.length > 0) {
				console.warn('Gstore Steps: Campos obrigatórios vazios:', missingFields);
			}

			$form.addClass('processing');

			// Bloqueia o formulário visualmente
			$form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			// IMPORTANTE: Coleta TODOS os campos da página manualmente
			// Os campos foram movidos para fora do formulário pelo sistema de etapas
			const formDataObj = {};
			// isPrecheckout já foi declarado acima na linha 1142
			
			// Campos de endereço que não devem ser enviados vazios no pré-checkout
			const addressFields = ['billing_postcode', 'billing_address_1', 'billing_number', 
			                       'billing_address_2', 'billing_neighborhood', 'billing_city', 
			                       'billing_state', 'billing_first_name', 'billing_last_name', 'billing_cpf'];
			
			// 1. Coleta campos de billing
			$('[id^="billing_"]').each(function() {
				const $input = $(this);
				const name = $input.attr('name') || $input.attr('id');
				if (!name) return;
				
				const value = $input.val() ? $input.val().trim() : '';
				
				// No pré-checkout, não envia campos de endereço vazios
				if (isPrecheckout && addressFields.indexOf(name) !== -1 && !value) {
					// console.log('Gstore Steps: [DEBUG] Ignorando campo vazio no pré-checkout:', name);
					return; // Não adiciona campos vazios de endereço no pré-checkout
				}
				
				// Adiciona o campo se tiver valor ou se não for pré-checkout
				if (value || !isPrecheckout) {
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
					// console.log('Gstore Steps: Nonce encontrado via:', selector);
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
						// console.log('Gstore Steps: Nonce encontrado em campo:', name || id);
						return false; // break
					}
				});
			}
			
			// Último fallback: variável global do WooCommerce
			if (!nonceValue && typeof wc_checkout_params !== 'undefined') {
				if (wc_checkout_params.update_order_review_nonce) {
					nonceValue = wc_checkout_params.update_order_review_nonce;
					// console.log('Gstore Steps: Usando nonce do wc_checkout_params');
				}
			}
			
			if (nonceValue) {
				formDataObj['woocommerce-process-checkout-nonce'] = nonceValue;
				formDataObj['_wpnonce'] = nonceValue;
			} else {
				// console.error('Gstore Steps: NONCE NÃO ENCONTRADO!');
				// Lista todos os inputs hidden para debug
				// console.log('Gstore Steps: Inputs hidden na página:');
				// $('input[type="hidden"]').each(function() {
				// 	const name = $(this).attr('name');
				// 	if (name) {
				// 		console.log('  -', name, '=', $(this).val() ? $(this).val().substring(0, 20) + '...' : 'vazio');
				// 	}
				// });
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
				if (!name || formDataObj[name]) return; // Já foi coletado ou não tem nome
				
				// No pré-checkout, não adiciona campos de endereço vazios
				if (isPrecheckout && addressFields.indexOf(name) !== -1) {
					const val = $input.val() ? $input.val().trim() : '';
					if (!val) {
						// console.log('Gstore Steps: [DEBUG] Ignorando campo vazio do formulário no pré-checkout:', name);
						return; // Não adiciona campos vazios de endereço no pré-checkout
					}
				}
				
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
			
			// console.log('Gstore Steps: [DEBUG] Enviando dados para:', wc_checkout_params.checkout_url);
			// console.log('Gstore Steps: [DEBUG] Campos coletados:', Object.keys(formDataObj).length);
			// console.log('Gstore Steps: [DEBUG] billing_email:', formDataObj['billing_email'] || 'FALTANDO');
			// console.log('Gstore Steps: [DEBUG] billing_phone:', formDataObj['billing_phone'] || 'FALTANDO');
			// console.log('Gstore Steps: [DEBUG] payment_method:', formDataObj['payment_method'] || 'FALTANDO');
			// console.log('Gstore Steps: [DEBUG] nonce:', formDataObj['woocommerce-process-checkout-nonce'] ? 'OK' : 'FALTANDO');
			
			// Debug: lista todos os campos coletados
			// console.log('Gstore Steps: [DEBUG] Todos os campos coletados:', Object.keys(formDataObj));

			$.ajax({
				type: 'POST',
				url: wc_checkout_params.checkout_url,
				data: formData,
				dataType: 'json',
				beforeSend: function() {
					// console.log('Gstore Steps: [DEBUG] Requisição AJAX sendo enviada...');
				},
				success: function(response) {
					// console.log('Gstore Steps: [DEBUG] Resposta recebida:', response);
					
					// Atualiza para passo 3 (criando pedido)
					updateProcessingStep(3);
					
					if (response.result === 'success') {
						// console.log('Gstore Steps: [DEBUG] Sucesso! Redirecionando para:', response.redirect);
						
						// Mostra sucesso no modal
						setTimeout(function() {
							showProcessingSuccess();
						}, 500);
						
						// Aguarda um momento para o usuário ver a mensagem de sucesso
						setTimeout(function() {
							window.location.href = response.redirect;
						}, 1500);
					} else if (response.result === 'failure') {
						console.warn('Gstore Steps: [DEBUG] Falha no checkout');
						console.warn('Gstore Steps: [DEBUG] Refresh:', response.refresh);
						console.warn('Gstore Steps: [DEBUG] Reload:', response.reload);
						
						// Esconde o modal de processamento
						hideProcessingModal();
						
						// Remove bloqueio
						$form.removeClass('processing').unblock();
						
						// Se precisa refresh, atualiza o checkout primeiro
						if (response.refresh) {
							// console.log('Gstore Steps: Atualizando checkout...');
							$(document.body).trigger('update_checkout');
						}
						
						// Mostra mensagens de erro
						if (response.messages) {
							$('.woocommerce-notices-wrapper, .woocommerce-error').remove();
							
							// Mostra na etapa ativa
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
						
						// Recarrega o checkout se necessário
						if (response.reload) {
							setTimeout(function() {
								window.location.reload();
							}, 2000);
						}
					}
				},
				error: function(xhr, status, error) {
					console.error('Gstore Steps: [DEBUG] Erro no submit:', error);
					console.error('Gstore Steps: [DEBUG] Status:', status);
					console.error('Gstore Steps: [DEBUG] Response:', xhr.responseText);
					
					// Esconde o modal
					hideProcessingModal();
					
					$form.removeClass('processing').unblock();
					showNotice('Ocorreu um erro ao processar o pedido. Por favor, tente novamente.', 'error');
				}
			});
		}

		// Monitora o evento de submit do formulário do checkout
		$checkoutForm.on('submit', function(e) {
			// console.log('Gstore Steps: Formulário de checkout sendo submetido');
			// console.log('Gstore Steps: Etapa atual:', currentStep);
			// console.log('Gstore Steps: Form action:', $checkoutForm.attr('action'));
			// console.log('Gstore Steps: Nonce presente:', $checkoutForm.find('#woocommerce-process-checkout-nonce').length > 0);
		});

		// Handler alternativo: se o WooCommerce não processar, fazemos via AJAX manual
		$(document).on('checkout_place_order', function(event) {
			// console.log('Gstore Steps: Evento checkout_place_order disparado');
		});

		$(document).on('checkout_place_order_blu_checkout', function(event) {
			// console.log('Gstore Steps: Evento checkout_place_order_blu_checkout disparado');
		});
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
		// Restaura a seleção após o update
		if (lastSelectedPaymentMethod) {
			setTimeout(function() {
				const $radio = $(`input[name="payment_method"][value="${lastSelectedPaymentMethod}"]`);
				if ($radio.length && !$radio.is(':checked')) {
					$radio.prop('checked', true).trigger('change');
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

	// Monitora eventos de checkout para debug
	$(document.body).on('checkout_error', function(event, error_message) {
		// console.error('Gstore Steps: Erro no checkout:', error_message);
	});

	// Monitora quando o pagamento é processado
	$(document.body).on('payment_method_selected', function() {
		// console.log('Gstore Steps: Método de pagamento selecionado');
		
		// Se mudou para Blu ou Pix e está no checkout completo, recarregar com pré-checkout
		if ((isBluGatewaySelected() || isPixGatewaySelected()) && STEPS.length === 3 && initialized && isBluGatewayAvailable()) {
			// Recarrega o checkout com pré-checkout simplificado
			$('.Gstore-checkout-steps').remove();
			initialized = false;
			STEPS = STEPS_BLU_PRECHECKOUT;
			init();
		}
		// Se mudou para outro gateway (que não seja Blu nem Pix) e está no pré-checkout, recarregar com checkout completo
		else if (!isBluGatewaySelected() && !isPixGatewaySelected() && STEPS.length === 2 && initialized) {
			$('.Gstore-checkout-steps').remove();
			initialized = false;
			STEPS = STEPS_FULL;
			init();
		}
		// Se mudou entre Blu e Pix dentro do pré-checkout, não precisa recarregar
		// (ambos devem aparecer juntos na segunda etapa)
	});

	// Intercepta a resposta do checkout para garantir redirect
	$(document).ajaxComplete(function(event, xhr, settings) {
		// Verifica se é a requisição do checkout
		if (settings.url && settings.url.indexOf('wc-ajax=checkout') !== -1) {
			// console.log('Gstore Steps: Resposta do checkout recebida');
			
			try {
				const response = JSON.parse(xhr.responseText);
				// console.log('Gstore Steps: Resposta:', response);
				
				// Se houver redirect, executa
				if (response.result === 'success' && response.redirect) {
					// console.log('Gstore Steps: Redirecionando para:', response.redirect);
					window.location.href = response.redirect;
				}
			} catch (e) {
				// console.log('Gstore Steps: Não foi possível parsear resposta (normal se não for JSON)');
			}
		}
	});

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

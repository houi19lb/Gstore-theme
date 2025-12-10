/**
 * Cart JavaScript - Gstore Theme
 * Melhora o seletor de quantidade do carrinho com botões + e -
 */
(function () {
	'use strict';

	/**
	 * Adiciona botões de incremento/decremento ao seletor de quantidade
	 */
	function enhanceQuantityField(quantityContainer) {
		// Verifica se já foi aprimorado
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

		// Marca como aprimorado
		quantityContainer.dataset.gstoreQtyEnhanced = 'true';

		// Remove setas padrão do input number
		input.style.appearance = 'none';
		input.style.MozAppearance = 'textfield';

		// Cria wrapper para controles
		const controlsWrapper = document.createElement('div');
		controlsWrapper.className = 'Gstore-cart-card__quantity-controls';

		// Cria botão de decremento
		const minusBtn = document.createElement('button');
		minusBtn.type = 'button';
		minusBtn.className = 'quantity-button quantity-button--minus';
		minusBtn.setAttribute('aria-label', 'Diminuir quantidade');
		minusBtn.textContent = '−';
		minusBtn.setAttribute('tabindex', '0');

		// Cria botão de incremento
		const plusBtn = document.createElement('button');
		plusBtn.type = 'button';
		plusBtn.className = 'quantity-button quantity-button--plus';
		plusBtn.setAttribute('aria-label', 'Aumentar quantidade');
		plusBtn.textContent = '+';
		plusBtn.setAttribute('tabindex', '0');

		// Cria aviso de última unidade
		const lastUnitWarning = document.createElement('span');
		lastUnitWarning.className = 'gstore-last-unit-warning';
		lastUnitWarning.textContent = 'Última unidade';
		lastUnitWarning.style.display = 'none';

		// Move o input para o wrapper de controles
		controlsWrapper.appendChild(minusBtn);
		controlsWrapper.appendChild(input);
		controlsWrapper.appendChild(plusBtn);

		// Substitui o wrapper original pelo novo
		quantityWrapper.replaceWith(controlsWrapper);

		// Adiciona o aviso após o wrapper de controles
		controlsWrapper.parentNode.insertBefore(lastUnitWarning, controlsWrapper.nextSibling);

		// Função para obter valores min/max
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

		// Função para atualizar botões (disabled/enabled) e aviso
		const updateButtons = () => {
			const current = getCurrentValue();
			const min = getMin();
			const max = getMax();

			// Quando há apenas 1 unidade (max < 2), esconde todo o seletor
			if (max < 2) {
				controlsWrapper.style.display = 'none';
				lastUnitWarning.style.display = 'inline-block';
			} else {
				// Mostra o seletor quando há mais de 1 unidade
				controlsWrapper.style.display = 'inline-flex';
				lastUnitWarning.style.display = 'none';

				// Esconde o botão - quando necessário
				minusBtn.style.display = 'inline-flex';
				minusBtn.disabled = current <= min;
				plusBtn.disabled = current >= max;
			}
		};

		// Função para atualizar o carrinho automaticamente
		const updateCartAutomatically = () => {
			// Verifica se jQuery está disponível
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

			// Bloqueia o formulário durante a atualização
			if (typeof block === 'function') {
				block($form);
				block(jQuery('div.cart_totals'));
			}

			// Cria um input hidden temporário para simular o botão de atualizar
			const updateInput = document.createElement('input');
			updateInput.type = 'hidden';
			updateInput.name = 'update_cart';
			updateInput.value = 'Update Cart';
			form.appendChild(updateInput);

			// Obtém a URL de ação do formulário ou usa a URL atual
			const actionUrl = form.action || (typeof wc_cart_params !== 'undefined' ? wc_cart_params.cart_url : null) || window.location.href;

			// Faz a requisição AJAX
			jQuery.ajax({
				type: form.method || 'POST',
				url: actionUrl,
				data: jQuery(form).serialize(),
				dataType: 'html',
				success: function (response) {
					// Usa a função do WooCommerce para atualizar a div se disponível
					if (typeof update_wc_div === 'function') {
						update_wc_div(response);
					} else {
						// Fallback: atualiza manualmente a área do carrinho
						const $response = jQuery(response);
						const $cartContent = $response.find('.woocommerce-cart-form, .Gstore-cart-form');
						const $cartTotals = $response.find('.cart_totals, .Gstore-cart-sidebar');

						if ($cartContent.length > 0) {
							$form.replaceWith($cartContent);
						}
						if ($cartTotals.length > 0) {
							jQuery('.cart_totals, .Gstore-cart-sidebar').replaceWith($cartTotals);
						}

						// Reinicializa os seletores de quantidade após atualização
						setTimeout(() => {
							initQuantitySelectors();
						}, 100);
					}

					// Dispara evento do WooCommerce
					jQuery(document.body).trigger('updated_wc_div');
				},
				complete: function () {
					// Remove o input temporário
					if (form.contains(updateInput)) {
						form.removeChild(updateInput);
					}

					// Desbloqueia o formulário
					if (typeof unblock === 'function') {
						unblock($form);
						unblock(jQuery('div.cart_totals'));
					}

					// Scroll para notificações se a função existir
					if (typeof jQuery.scroll_to_notices === 'function') {
						jQuery.scroll_to_notices(jQuery('[role="alert"]'));
					}
				},
			});
		};

		// Debounce para evitar múltiplas chamadas
		let updateTimeout = null;
		const debouncedUpdateCart = () => {
			clearTimeout(updateTimeout);
			updateTimeout = setTimeout(updateCartAutomatically, 500); // Aguarda 500ms após a última mudança
		};

		// Função para definir valor
		const setValue = (newValue) => {
			const min = getMin();
			const max = getMax();
			const step = getStep();

			// Garante que o valor esteja dentro dos limites
			let value = Math.max(min, Math.min(max, newValue));

			// Alinha ao step
			value = Math.round(value / step) * step;

			// Garante que não ultrapasse os limites após o arredondamento
			value = Math.max(min, Math.min(max, value));

			const oldValue = parseFloat(input.value) || 0;
			input.value = value;
			updateButtons();

			// Dispara eventos para que o WooCommerce detecte a mudança
			input.dispatchEvent(new Event('change', { bubbles: true }));
			input.dispatchEvent(new Event('input', { bubbles: true }));

			// Atualiza o carrinho automaticamente se o valor mudou
			if (value !== oldValue) {
				debouncedUpdateCart();
			}
		};

		// Event listeners para botões
		minusBtn.addEventListener('click', (e) => {
			e.preventDefault();
			const current = getCurrentValue();
			const step = getStep();
			setValue(current - step);
		});

		plusBtn.addEventListener('click', (e) => {
			e.preventDefault();
			const current = getCurrentValue();
			const step = getStep();
			setValue(current + step);
		});

		// Validação no input
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
				// Atualiza o carrinho automaticamente quando o valor é válido
				debouncedUpdateCart();
			}
		});

		// Validação ao perder foco
		input.addEventListener('blur', () => {
			const value = getCurrentValue();
			const min = getMin();

			if (isNaN(value) || value < min) {
				setValue(min);
			} else {
				setValue(value);
			}
			// Garante que o carrinho seja atualizado ao perder o foco
			clearTimeout(updateTimeout);
			updateCartAutomatically();
		});

		// Suporte para teclado
		input.addEventListener('keydown', (e) => {
			if (e.key === 'ArrowUp') {
				e.preventDefault();
				plusBtn.click();
			} else if (e.key === 'ArrowDown') {
				e.preventDefault();
				minusBtn.click();
			}
		});

		// Atualiza botões inicialmente
		updateButtons();

		// Observa mudanças no input (caso seja alterado externamente)
		const observer = new MutationObserver(() => {
			updateButtons();
		});

		observer.observe(input, {
			attributes: true,
			attributeFilter: ['value', 'min', 'max', 'step', 'disabled'],
		});

		// Observa mudanças no atributo max para atualizar visibilidade do botão -
		const maxObserver = new MutationObserver(() => {
			updateButtons();
		});

		maxObserver.observe(input, {
			attributes: true,
			attributeFilter: ['max'],
		});
	}

	/**
	 * Inicializa todos os seletores de quantidade
	 */
	function initQuantitySelectors() {
		const quantityContainers = document.querySelectorAll(
			'.Gstore-cart-card__quantity'
		);

		quantityContainers.forEach(enhanceQuantityField);
	}

	/**
	 * Observa mudanças dinâmicas no DOM (para AJAX)
	 */
	function setupMutationObserver() {
		const cartForm = document.querySelector('.Gstore-cart-form, .woocommerce-cart-form');
		if (!cartForm) {
			return;
		}

		const observer = new MutationObserver(() => {
			initQuantitySelectors();
		});

		observer.observe(cartForm, {
			childList: true,
			subtree: true,
		});
	}

	/**
	 * Inicializa tudo
	 */
	function init() {
		initQuantitySelectors();
		setupMutationObserver();
	}

	// Inicializar quando o DOM estiver pronto
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	// Também inicializar após atualizações AJAX do WooCommerce
	if (typeof jQuery !== 'undefined') {
		jQuery(document).on('updated_wc_div updated_cart_totals', function () {
			setTimeout(init, 100);
		});
	}
})();


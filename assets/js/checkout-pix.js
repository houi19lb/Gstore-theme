/**
 * Checkout Pix Blu
 * 
 * Exibe QR Code e código copia e cola do Pix após o pedido ser criado.
 * 
 * @package Gstore
 */

(function($) {
	'use strict';

	/**
	 * Inicializa o checkout Pix
	 */
	function initCheckoutPix() {
		// Verifica se estamos na página de checkout
		if (!$('body').hasClass('woocommerce-checkout')) {
			return;
		}

		// Verifica se o gateway Pix está selecionado
		if (!isPixGatewaySelected()) {
			return;
		}

		// Aguarda o pedido ser processado
		$(document.body).on('checkout_place_order', function() {
			// O pedido será processado, mas não podemos exibir o Pix aqui
			// porque os dados só estarão disponíveis após o process_payment
			// O Pix será exibido na página de obrigado
		});

		// Se já existe um pedido na URL (thankyou page), tenta carregar o Pix
		const urlParams = new URLSearchParams(window.location.search);
		const orderId = urlParams.get('order-received');
		if (orderId) {
			loadPixData(orderId);
		}
	}

	/**
	 * Verifica se o gateway Pix está selecionado
	 */
	function isPixGatewaySelected() {
		const $pixRadio = $('input[name="payment_method"][value="blu_pix"]:checked');
		return $pixRadio.length > 0;
	}

	/**
	 * Carrega dados do Pix via AJAX
	 */
	function loadPixData(orderId) {
		if (!orderId) {
			return;
		}

		// Verifica se o objeto gstorePix está disponível
		if (typeof gstorePix === 'undefined') {
			console.warn('Gstore Pix: Objeto gstorePix não está disponível');
			return;
		}

		$.ajax({
			url: gstorePix.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gstore_get_pix_data',
				order_id: orderId,
				nonce: gstorePix.nonce
			},
			success: function(response) {
				if (response.success && response.data) {
					displayPixInstructions(response.data);
				}
			},
			error: function() {
				console.error('Erro ao carregar dados do Pix');
			}
		});
	}

	/**
	 * Exibe instruções do Pix na página
	 */
	function displayPixInstructions(data) {
		// Remove instruções existentes se houver
		$('.Gstore-pix-checkout-instructions').remove();

		let html = '<div class="woocommerce-info Gstore-pix-instructions Gstore-pix-checkout-instructions">';
		html += '<h3>Pagamento via Pix</h3>';
		html += '<p>Escaneie o QR Code ou copie o código abaixo para realizar o pagamento.</p>';

		// QR Code
		if (data.qr_code_base64) {
			html += '<div class="Gstore-pix-qr-code">';
			html += '<img src="data:image/png;base64,' + data.qr_code_base64 + '" alt="QR Code Pix" />';
			html += '</div>';
		}

		// Código EMV
		if (data.emv) {
			html += '<div class="Gstore-pix-emv">';
			html += '<label><strong>Código Pix (copia e cola):</strong></label>';
			html += '<div class="Gstore-pix-emv-wrapper">';
			html += '<textarea id="gstore-pix-emv-checkout" class="Gstore-pix-emv-code" readonly>' + data.emv + '</textarea>';
			html += '<button type="button" class="button Gstore-pix-copy-btn" data-target="gstore-pix-emv-checkout">Copiar</button>';
			html += '</div>';
			html += '</div>';
		}

		// Informações
		html += '<ul class="Gstore-pix-info">';
		if (data.transaction_token) {
			html += '<li>Token: ' + data.transaction_token + '</li>';
		}
		if (data.expires_at) {
			html += '<li>Válido até: ' + data.expires_at + '</li>';
		}
		if (data.status) {
			html += '<li>Status: ' + data.status + '</li>';
		}
		html += '</ul>';
		html += '</div>';

		// Insere antes do botão de finalizar pedido ou após o método de pagamento
		const $paymentMethod = $('.payment_method_blu_pix');
		if ($paymentMethod.length) {
			$paymentMethod.after(html);
		} else {
			// Tenta inserir antes do botão de finalizar
			const $placeOrder = $('button[name="woocommerce_checkout_place_order"]');
			if ($placeOrder.length) {
				$placeOrder.before(html);
			} else {
				// Fallback: insere no final do formulário
				$('form.checkout').append(html);
			}
		}

		// Inicializa botão de copiar
		initCopyButton();
	}

	/**
	 * Inicializa funcionalidade de copiar código
	 */
	function initCopyButton() {
		// Handler para novo formato (pix-box__copy)
		$(document).on('click', '.pix-box__copy', function() {
			const $btn = $(this);
			const selector = $btn.data('copy-target');
			const $textarea = $(selector);

			if (!$textarea.length) {
				return;
			}

			const text = $textarea.val();

			// Tenta usar Clipboard API
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					showCopySuccess($btn);
				}).catch(function() {
					fallbackCopy(text, $textarea, $btn);
				});
			} else {
				fallbackCopy(text, $textarea, $btn);
			}
		});

		// Handler para formato antigo (Gstore-pix-copy-btn) - compatibilidade
		$(document).on('click', '.Gstore-pix-copy-btn', function() {
			const $btn = $(this);
			const targetId = $btn.data('target');
			const $textarea = $('#' + targetId);

			if (!$textarea.length) {
				return;
			}

			const text = $textarea.val();

			// Tenta usar Clipboard API
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					showCopySuccess($btn);
				}).catch(function() {
					fallbackCopy(text, $textarea, $btn);
				});
			} else {
				fallbackCopy(text, $textarea, $btn);
			}
		});
	}

	/**
	 * Método fallback para copiar
	 */
	function fallbackCopy(text, $textarea, $btn) {
		$textarea.select();
		$textarea[0].setSelectionRange(0, 99999); // Para mobile

		try {
			document.execCommand('copy');
			showCopySuccess($btn);
		} catch (err) {
			console.error('Erro ao copiar:', err);
			alert('Não foi possível copiar. Por favor, selecione e copie manualmente.');
		}
	}

	/**
	 * Mostra feedback visual de cópia bem-sucedida
	 */
	function showCopySuccess($btn) {
		const originalText = $btn.text();
		$btn.text('Copiado!');
		
		setTimeout(function() {
			$btn.text(originalText);
		}, 1500);
	}

	// Inicializa quando o DOM estiver pronto
	$(document).ready(function() {
		// Verifica se jQuery está disponível
		if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
			console.error('Gstore Pix: jQuery não está disponível');
			return;
		}
		
		// Aguarda um pouco para garantir que tudo está carregado
		setTimeout(function() {
			initCheckoutPix();
			initCopyButton();
		}, 100);
	});

	// Também inicializa após atualização do checkout (AJAX)
	$(document.body).on('updated_checkout', function() {
		initCheckoutPix();
	});

})(jQuery);


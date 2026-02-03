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
	 * Inicializa countdown do Pix Box.
	 */
	function initPixCountdown() {
		document.querySelectorAll('.pix-box[data-expires-at]').forEach(function(root) {
			if (root.getAttribute('data-pix-countdown-init') === '1') {
				return;
			}
			root.setAttribute('data-pix-countdown-init', '1');

			const expiresAt = parseInt(root.getAttribute('data-expires-at'), 10);
			const orderStatus = String(root.getAttribute('data-order-status') || '').toLowerCase();
			const countdownEl = root.querySelector('[data-role="pix-countdown"]');
			const expiredMessageEl = root.querySelector('[data-role="pix-expired-message"]');

			if (!expiresAt || !countdownEl) {
				return;
			}
			if (['cancelled', 'failed', 'refunded', 'completed', 'processing'].includes(orderStatus)) {
				return;
			}

			let timer = null;

			function updateCountdown() {
				const now = Math.floor(Date.now() / 1000);
				let diff = expiresAt - now;

				if (diff <= 0) {
					root.classList.add('pix-box--client-expired');
					countdownEl.textContent = 'PIX expirado';
					countdownEl.classList.remove('pix-box__countdown--warning', 'pix-box__countdown--critical');
					if (expiredMessageEl) {
						expiredMessageEl.style.display = 'block';
					}
					if (timer) {
						clearInterval(timer);
						timer = null;
					}
					return;
				}

				const minutes = Math.floor(diff / 60);
				const seconds = diff % 60;
				countdownEl.textContent = 'Expira em: ' + minutes + ':' + String(seconds).padStart(2, '0');

				countdownEl.classList.remove('pix-box__countdown--warning', 'pix-box__countdown--critical');
				if (diff <= 60) {
					countdownEl.classList.add('pix-box__countdown--critical');
				} else if (diff <= 180) {
					countdownEl.classList.add('pix-box__countdown--warning');
				}
			}

			updateCountdown();
			timer = setInterval(updateCountdown, 1000);
		});
	}

	/**
	 * Inicializa funcionalidade de copiar código
	 */
	function initCopyButton() {
		const $doc = $(document);
		if ($doc.data('gstorePixCopyInit')) {
			return;
		}
		$doc.data('gstorePixCopyInit', true);

		// Handler para Pix Box v2 (.pix-box .btn[data-copy-target])
		$doc.on('click', '.pix-box .btn[data-copy-target]', function() {
			const $btn = $(this);
			const selector = $btn.data('copy-target');
			const $textarea = $(selector);
			if (!$textarea.length) return;
			const text = $textarea.val();
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() { showCopySuccess($btn); }).catch(function() { fallbackCopy(text, $textarea, $btn); });
			} else {
				fallbackCopy(text, $textarea, $btn);
			}
		});

		// Handler para formato (pix-box__copy)
		$doc.on('click', '.pix-box__copy', function() {
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
		$doc.on('click', '.Gstore-pix-copy-btn', function() {
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
			initCopyButton();
			initPixCountdown();
		}, 100);
	});

	// Também inicializa após atualização do checkout (AJAX)
	$(document.body).on('updated_checkout', function() {
		initPixCountdown();
	});

})(jQuery);


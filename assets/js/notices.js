/**
 * ==========================================
 * GSTORE NOTICES - AUTO DISMISS & ANIMATIONS
 * ==========================================
 * Gerencia avisos do WooCommerce com animações de slide-in
 * e auto-dismiss automático
 */

(function() {
	'use strict';

	// Configurações
	const CONFIG = {
		autoDismissDelay: 5000, // 5 segundos
		animationDuration: 300, // 300ms para animações
		headerSelector: '.Gstore-header-shell',
		noticeSelector: '.wc-block-components-notice-banner'
	};

	/**
	 * Calcula a altura do header para posicionar os avisos corretamente
	 */
	function getHeaderHeight() {
		const header = document.querySelector(CONFIG.headerSelector);
		if (!header) {
			return 0;
		}
		return header.offsetHeight || 0;
	}

	/**
	 * Aplica estilos de posicionamento e animação aos avisos
	 */
	function setupNotice(notice) {
		// Evita processar o mesmo aviso duas vezes
		if (notice.dataset.gstoreNoticeProcessed === 'true') {
			return;
		}

		// Marca como processado
		notice.dataset.gstoreNoticeProcessed = 'true';

		// Adiciona classe para animação
		notice.classList.add('gstore-notice-animated');

		// Calcula posição do topo (abaixo do header)
		const headerHeight = getHeaderHeight();
		const topPosition = headerHeight + 16; // 16px de margem

		// Aplica estilos inline para posicionamento
		notice.style.position = 'fixed';
		notice.style.top = topPosition + 'px';
		notice.style.left = '50%';
		notice.style.transform = 'translateX(-50%) translateY(-100px)'; // Inicia fora da tela
		notice.style.zIndex = '1100'; // Maior que o header (1000)
		notice.style.maxWidth = '600px';
		notice.style.width = 'calc(100% - 32px)';
		notice.style.margin = '0 auto';
		notice.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';

		// Trigger para animação de entrada após um pequeno delay
		requestAnimationFrame(() => {
			setTimeout(() => {
				notice.classList.add('gstore-notice-slide-in');
			}, 10);
		});

		// Auto-dismiss após o delay configurado
		const dismissTimer = setTimeout(() => {
			dismissNotice(notice);
		}, CONFIG.autoDismissDelay);

		// Salva o timer para poder cancelar se necessário
		notice.dataset.dismissTimer = dismissTimer;

		// Permite cancelar o auto-dismiss ao passar o mouse
		notice.addEventListener('mouseenter', function() {
			if (notice.dataset.dismissTimer) {
				clearTimeout(parseInt(notice.dataset.dismissTimer));
				notice.dataset.dismissTimer = '';
			}
		});

		// Retoma o auto-dismiss ao sair do mouse
		notice.addEventListener('mouseleave', function() {
			const newTimer = setTimeout(() => {
				dismissNotice(notice);
			}, CONFIG.autoDismissDelay);
			notice.dataset.dismissTimer = newTimer;
		});
	}

	/**
	 * Remove o aviso com animação de saída
	 */
	function dismissNotice(notice) {
		// Cancela timer se ainda estiver ativo
		if (notice.dataset.dismissTimer) {
			clearTimeout(parseInt(notice.dataset.dismissTimer));
		}

		// Adiciona classe de saída
		notice.classList.remove('gstore-notice-slide-in');
		notice.classList.add('gstore-notice-slide-out');

		// Remove após a animação
		setTimeout(() => {
			if (notice.parentNode) {
				notice.remove();
			}
		}, CONFIG.animationDuration);
	}

	/**
	 * Processa todos os avisos existentes na página
	 */
	function processExistingNotices() {
		const notices = document.querySelectorAll(CONFIG.noticeSelector);
		notices.forEach(setupNotice);
	}

	/**
	 * Observa novos avisos adicionados ao DOM
	 */
	function observeNewNotices() {
		// Usa MutationObserver para detectar novos avisos
		const observer = new MutationObserver(function(mutations) {
			mutations.forEach(function(mutation) {
				mutation.addedNodes.forEach(function(node) {
					// Verifica se o nó adicionado é um aviso
					if (node.nodeType === 1) { // Element node
						if (node.matches && node.matches(CONFIG.noticeSelector)) {
							setupNotice(node);
						}
						// Verifica também avisos dentro do nó adicionado
						const nestedNotices = node.querySelectorAll 
							? node.querySelectorAll(CONFIG.noticeSelector)
							: [];
						nestedNotices.forEach(setupNotice);
					}
				});
			});
		});

		// Observa mudanças no body
		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
	}

	/**
	 * Inicializa o sistema de avisos
	 */
	function init() {
		// Processa avisos existentes
		processExistingNotices();

		// Observa novos avisos
		observeNewNotices();

		// Recalcula posição quando a janela é redimensionada
		window.addEventListener('resize', function() {
			const notices = document.querySelectorAll(CONFIG.noticeSelector + '.gstore-notice-animated');
			notices.forEach(function(notice) {
				const headerHeight = getHeaderHeight();
				const topPosition = headerHeight + 16;
				notice.style.top = topPosition + 'px';
				// Mantém o transform de centralização
				if (!notice.classList.contains('gstore-notice-slide-out')) {
					notice.style.transform = 'translateX(-50%) translateY(0)';
				}
			});
		});
	}

	// Inicializa quando o DOM estiver pronto
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();


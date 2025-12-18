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
		noticeSelector: '.wc-block-components-notice-banner',
		classicNoticeSelector: '.woocommerce-message, .woocommerce-error, .woocommerce-info'
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
	 * Cria o botão de fechar com SVG
	 */
	function createCloseButton(notice) {
		const closeBtn = document.createElement('button');
		closeBtn.className = 'gstore-notice-close';
		closeBtn.setAttribute('aria-label', 'Fechar aviso');
		closeBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';
		
		closeBtn.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			dismissNotice(notice);
		});
		
		return closeBtn;
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

		// Adiciona botão de fechar com SVG
		const closeBtn = createCloseButton(notice);
		notice.appendChild(closeBtn);

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
	 * Adiciona botão de fechar aos notices clássicos do WooCommerce
	 */
	function setupClassicNotice(notice) {
		// Evita processar o mesmo aviso duas vezes
		if (notice.dataset.gstoreNoticeProcessed === 'true') {
			return;
		}

		// Marca como processado
		notice.dataset.gstoreNoticeProcessed = 'true';

		// Adiciona botão de fechar com SVG
		const closeBtn = document.createElement('button');
		closeBtn.className = 'gstore-notice-close';
		closeBtn.setAttribute('aria-label', 'Fechar aviso');
		closeBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';
		
		closeBtn.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			// Animação de saída
			notice.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
			notice.style.opacity = '0';
			notice.style.transform = 'translateY(-10px)';
			
			// Remove após a animação
			setTimeout(function() {
				if (notice.parentNode) {
					notice.remove();
				}
			}, CONFIG.animationDuration);
		});
		
		notice.appendChild(closeBtn);
	}

	/**
	 * Processa todos os avisos existentes na página
	 */
	function processExistingNotices() {
		// Notices do WooCommerce Blocks
		const notices = document.querySelectorAll(CONFIG.noticeSelector);
		notices.forEach(setupNotice);
		
		// Notices clássicos do WooCommerce
		const classicNotices = document.querySelectorAll(CONFIG.classicNoticeSelector);
		classicNotices.forEach(setupClassicNotice);
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
						// Notices do WooCommerce Blocks
						if (node.matches && node.matches(CONFIG.noticeSelector)) {
							setupNotice(node);
						}
						// Notices clássicos do WooCommerce
						if (node.matches && node.matches(CONFIG.classicNoticeSelector)) {
							setupClassicNotice(node);
						}
						// Verifica também avisos dentro do nó adicionado
						if (node.querySelectorAll) {
							const nestedNotices = node.querySelectorAll(CONFIG.noticeSelector);
							nestedNotices.forEach(setupNotice);
							
							const nestedClassicNotices = node.querySelectorAll(CONFIG.classicNoticeSelector);
							nestedClassicNotices.forEach(setupClassicNotice);
						}
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


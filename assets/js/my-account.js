/**
 * ==========================================
 * GSTORE MY ACCOUNT PAGE SCRIPTS
 * ==========================================
 * Scripts específicos para a página "Minha Conta" do WooCommerce
 */

(function() {
	'use strict';

	/**
	 * Controla o modal de informações sobre cadastro
	 */
	function initRegisterInfoModal() {
		const modal = document.getElementById('gstore-register-info-modal');
		const openBtn = document.querySelector('.gstore-register-info-btn');
		const closeBtn = document.querySelector('.gstore-modal__close');
		const overlay = document.querySelector('.gstore-modal__overlay');

		if (!modal || !openBtn) {
			return;
		}

		/**
		 * Abre o modal
		 */
		function openModal() {
			modal.setAttribute('aria-hidden', 'false');
			document.body.style.overflow = 'hidden';
			
			// Foca no primeiro elemento focável do modal
			const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
			if (firstFocusable) {
				firstFocusable.focus();
			}
		}

		/**
		 * Fecha o modal
		 */
		function closeModal() {
			modal.setAttribute('aria-hidden', 'true');
			document.body.style.overflow = '';
			
			// Retorna o foco para o botão que abriu o modal
			if (openBtn) {
				openBtn.focus();
			}
		}

		// Abre o modal ao clicar no botão
		openBtn.addEventListener('click', function(e) {
			e.preventDefault();
			openModal();
		});

		// Fecha o modal ao clicar no botão de fechar
		if (closeBtn) {
			closeBtn.addEventListener('click', closeModal);
		}

		// Fecha o modal ao clicar no overlay
		if (overlay) {
			overlay.addEventListener('click', closeModal);
		}

		// Fecha o modal ao pressionar ESC
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
				closeModal();
			}
		});

		// Previne que o clique dentro do conteúdo do modal feche o modal
		const modalContent = document.querySelector('.gstore-modal__content');
		if (modalContent) {
			modalContent.addEventListener('click', function(e) {
				e.stopPropagation();
			});
		}
	}

	/**
	 * Animações de entrada para os elementos do dashboard
	 */
	function initDashboardAnimations() {
		const dashboard = document.querySelector('.gstore-dashboard');
		if (!dashboard) return;

		// Elementos para animar
		const elements = dashboard.querySelectorAll(
			'.gstore-dashboard__welcome, ' +
			'.gstore-dashboard__stat-card, ' +
			'.gstore-dashboard__action-card, ' +
			'.gstore-dashboard__order-card'
		);

		if (!elements.length) return;

		// Configuração do Intersection Observer
		const observerOptions = {
			root: null,
			rootMargin: '0px',
			threshold: 0.1
		};

		const observer = new IntersectionObserver((entries) => {
			entries.forEach((entry, index) => {
				if (entry.isIntersecting) {
					// Adiciona delay escalonado
					setTimeout(() => {
						entry.target.classList.add('is-visible');
					}, index * 50);
					
					observer.unobserve(entry.target);
				}
			});
		}, observerOptions);

		// Adiciona estilos iniciais e observa elementos
		elements.forEach((el) => {
			el.style.opacity = '0';
			el.style.transform = 'translateY(20px)';
			el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
			observer.observe(el);
		});

		// Adiciona o estilo para quando o elemento está visível
		const style = document.createElement('style');
		style.textContent = `
			.gstore-dashboard .is-visible {
				opacity: 1 !important;
				transform: translateY(0) !important;
			}
		`;
		document.head.appendChild(style);
	}

	/**
	 * Navegação mobile melhorada
	 */
	function initMobileNavigation() {
		const shell = document.querySelector('.gstore-myaccount-nav-shell');
		const nav = shell?.querySelector('.gstore-myaccount-nav');
		const toggle = shell?.querySelector('.gstore-myaccount-nav-toggle');
		const closeButton = shell?.querySelector('.gstore-myaccount-nav__close');
		const backdrop = shell?.querySelector('.gstore-myaccount-nav-backdrop');

		if (!shell || !nav || !toggle || !closeButton || !backdrop) return;

		const mobileQuery = window.matchMedia('(max-width: 768px)');
		let isOpen = false;

		function getFocusableElements() {
			return Array.from(nav.querySelectorAll(
				'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
			)).filter((element) => !element.hasAttribute('hidden'));
		}

		function setOpen(nextOpen, restoreFocus = true) {
			isOpen = mobileQuery.matches && nextOpen;
			shell.classList.toggle('is-open', isOpen);
			toggle.setAttribute('aria-expanded', String(isOpen));
			backdrop.hidden = !isOpen;
			document.body.classList.toggle('gstore-account-nav-open', isOpen);

			if (mobileQuery.matches) {
				nav.setAttribute('aria-hidden', String(!isOpen));
				if (isOpen) {
					nav.removeAttribute('inert');
					closeButton.focus();
				} else {
					nav.setAttribute('inert', '');
					if (restoreFocus) toggle.focus();
				}
			} else {
				nav.removeAttribute('aria-hidden');
				nav.removeAttribute('inert');
			}
		}

		function syncViewport() {
			if (mobileQuery.matches) {
				setOpen(false, false);
			} else {
				isOpen = false;
				shell.classList.remove('is-open');
				toggle.setAttribute('aria-expanded', 'false');
				backdrop.hidden = true;
				document.body.classList.remove('gstore-account-nav-open');
				nav.removeAttribute('aria-hidden');
				nav.removeAttribute('inert');
			}
		}

		shell.classList.add('is-enhanced');
		syncViewport();

		toggle.addEventListener('click', () => setOpen(true, false));
		closeButton.addEventListener('click', () => setOpen(false));
		backdrop.addEventListener('click', () => setOpen(false));

		nav.addEventListener('click', (event) => {
			if (mobileQuery.matches && event.target.closest('a[href]')) {
				setOpen(false, false);
			}
		});

		document.addEventListener('keydown', (event) => {
			if (!isOpen) return;

			if (event.key === 'Escape') {
				event.preventDefault();
				setOpen(false);
				return;
			}

			if (event.key !== 'Tab') return;

			const focusableElements = getFocusableElements();
			if (!focusableElements.length) return;

			const firstElement = focusableElements[0];
			const lastElement = focusableElements[focusableElements.length - 1];

			if (event.shiftKey && document.activeElement === firstElement) {
				event.preventDefault();
				lastElement.focus();
			} else if (!event.shiftKey && document.activeElement === lastElement) {
				event.preventDefault();
				firstElement.focus();
			}
		});

		if (typeof mobileQuery.addEventListener === 'function') {
			mobileQuery.addEventListener('change', syncViewport);
		} else {
			mobileQuery.addListener(syncViewport);
		}
	}

	/**
	 * Feedback visual para ações
	 */
	function initActionFeedback() {
		// Adiciona ripple effect aos botões de ação
		const actionCards = document.querySelectorAll('.gstore-dashboard__action-card');
		
		actionCards.forEach(card => {
			card.addEventListener('click', function(e) {
				// Cria o efeito ripple
				const ripple = document.createElement('span');
				const rect = card.getBoundingClientRect();
				
				ripple.style.cssText = `
					position: absolute;
					width: 100px;
					height: 100px;
					background: rgba(255, 255, 255, 0.3);
					border-radius: 50%;
					transform: translate(-50%, -50%) scale(0);
					animation: ripple 0.6s ease-out;
					pointer-events: none;
					left: ${e.clientX - rect.left}px;
					top: ${e.clientY - rect.top}px;
				`;
				
				card.style.position = 'relative';
				card.style.overflow = 'hidden';
				card.appendChild(ripple);
				
				setTimeout(() => ripple.remove(), 600);
			});
		});

		// Adiciona estilo de animação ripple
		if (!document.querySelector('#gstore-ripple-style')) {
			const style = document.createElement('style');
			style.id = 'gstore-ripple-style';
			style.textContent = `
				@keyframes ripple {
					to {
						transform: translate(-50%, -50%) scale(4);
						opacity: 0;
					}
				}
			`;
			document.head.appendChild(style);
		}
	}

	/**
	 * Máscara para campos de CPF
	 */
	function initCPFMask() {
		const cpfInputs = document.querySelectorAll('input[name="billing_cpf"], input[id*="cpf"], input[id*="CPF"]');
		
		cpfInputs.forEach(input => {
			input.addEventListener('input', function(e) {
				let value = e.target.value.replace(/\D/g, '');
				
				if (value.length > 11) {
					value = value.slice(0, 11);
				}
				
				// Formata o CPF: 000.000.000-00
				if (value.length > 9) {
					value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
				} else if (value.length > 6) {
					value = value.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
				} else if (value.length > 3) {
					value = value.replace(/(\d{3})(\d{1,3})/, '$1.$2');
				}
				
				e.target.value = value;
			});

			// Permite apenas números
			input.addEventListener('keypress', function(e) {
				if (!/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'Tab') {
					e.preventDefault();
				}
			});
		});
	}

	/**
	 * Validação de formulários
	 */
	function initFormValidation() {
		const forms = document.querySelectorAll('.woocommerce-form, .woocommerce-EditAccountForm');
		
		forms.forEach(form => {
			const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
			
			inputs.forEach(input => {
				// Validação em tempo real
				input.addEventListener('blur', function() {
					validateField(this);
				});
				
				input.addEventListener('input', function() {
					// Remove erro quando começa a digitar
					const wrapper = this.closest('.form-row');
					if (wrapper && wrapper.classList.contains('woocommerce-invalid')) {
						wrapper.classList.remove('woocommerce-invalid');
						wrapper.classList.add('woocommerce-validated');
					}
				});
			});
		});
		
		function validateField(field) {
			const wrapper = field.closest('.form-row');
			if (!wrapper) return;
			
			const isValid = field.checkValidity();
			
			wrapper.classList.remove('woocommerce-invalid', 'woocommerce-validated');
			wrapper.classList.add(isValid ? 'woocommerce-validated' : 'woocommerce-invalid');
		}
	}

	/**
	 * Contador de caracteres para campos de texto
	 */
	function initCharacterCounters() {
		const textareas = document.querySelectorAll('textarea[maxlength]');
		
		textareas.forEach(textarea => {
			const maxLength = textarea.getAttribute('maxlength');
			if (!maxLength) return;
			
			// Cria o contador
			const counter = document.createElement('span');
			counter.className = 'gstore-char-counter';
			counter.style.cssText = `
				display: block;
				text-align: right;
				font-size: 12px;
				color: var(--gstore-color-text-secondary, #6b6b6b);
				margin-top: 4px;
			`;
			
			updateCounter();
			textarea.parentNode.appendChild(counter);
			
			textarea.addEventListener('input', updateCounter);
			
			function updateCounter() {
				const remaining = maxLength - textarea.value.length;
				counter.textContent = `${textarea.value.length}/${maxLength}`;
				counter.style.color = remaining < 20 ? 'var(--gstore-color-warning, #b5a642)' : 'var(--gstore-color-text-secondary, #6b6b6b)';
			}
		});
	}

	/**
	 * Loading states para formulários
	 */
	function initFormLoadingStates() {
		const forms = document.querySelectorAll('.woocommerce-form, .woocommerce-EditAccountForm');
		
		forms.forEach(form => {
			form.addEventListener('submit', function(e) {
				const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
				if (!submitBtn) return;
				
				// Verifica se o formulário é válido
				if (!form.checkValidity()) return;
				
				// Usa setTimeout para não bloquear o envio do formulário
				setTimeout(() => {
					// Adiciona estado de loading (após o submit iniciar)
					submitBtn.classList.add('is-loading');
					
					if (submitBtn.tagName === 'BUTTON') {
						submitBtn.innerHTML = '<span class="gstore-spinner"></span> Processando...';
					} else {
						submitBtn.value = 'Processando...';
					}
				}, 10);
				
				// Adiciona estilo do spinner se não existir
				if (!document.querySelector('#gstore-spinner-style')) {
					const style = document.createElement('style');
					style.id = 'gstore-spinner-style';
					style.textContent = `
						.gstore-spinner {
							display: inline-block;
							width: 14px;
							height: 14px;
							border: 2px solid rgba(255, 255, 255, 0.3);
							border-top-color: #fff;
							border-radius: 50%;
							animation: gstore-spin 0.8s linear infinite;
							vertical-align: middle;
							margin-right: 8px;
						}
						@keyframes gstore-spin {
							to { transform: rotate(360deg); }
						}
						.is-loading {
							opacity: 0.8;
							cursor: wait;
						}
					`;
					document.head.appendChild(style);
				}
			});
		});
	}

	/**
	 * Mostra/esconde o formulário de cadastro
	 */
	function initRegisterButton() {
		const registerBtn = document.querySelector('.gstore-btn-register');
		const registerColumn = document.querySelector('.u-column2.col-2');
		const columnsWrapper = document.querySelector('.u-columns.col2-set');
		
		if (!registerBtn || !registerColumn) return;

		registerBtn.addEventListener('click', function(e) {
			e.preventDefault();
			
			// Toggle visibilidade do formulário de cadastro
			const isVisible = registerColumn.classList.contains('is-visible');
			
			if (isVisible) {
				// Esconde o formulário
				registerColumn.classList.remove('is-visible');
				columnsWrapper?.classList.remove('show-register');
				registerBtn.textContent = 'Cadastrar-se';
			} else {
				// Mostra o formulário
				registerColumn.classList.add('is-visible');
				columnsWrapper?.classList.add('show-register');
				registerBtn.textContent = 'Voltar ao login';
				
				// Scroll suave até o formulário
				setTimeout(() => {
					registerColumn.scrollIntoView({
						behavior: 'smooth',
						block: 'start'
					});
					
					// Foca no primeiro campo
					const firstInput = registerColumn.querySelector('input:not([type="hidden"])');
					if (firstInput) {
						firstInput.focus();
					}
				}, 100);
			}
		});
	}

	/**
	 * Fallback para fluxo de conta já existente:
	 * - Captura e-mail no submit do cadastro
	 * - Se WooCommerce exibir erro de e-mail já cadastrado, redireciona para lost-password
	 * - Pré-preenche o campo de recuperação via sessionStorage
	 */
	function initExistingAccountRecoveryFallback() {
		const storageKey = 'gstore_existing_account_email';

		function isLostPasswordPage() {
			return window.location.pathname.indexOf('lost-password') !== -1;
		}

		function getLostPasswordUrl() {
			const lostPasswordAnchor = document.querySelector('.woocommerce-LostPassword a[href]');
			if (lostPasswordAnchor && lostPasswordAnchor.href) {
				return lostPasswordAnchor.href;
			}

			if (window.gstoreAccountUrls && window.gstoreAccountUrls.myAccount) {
				const base = String(window.gstoreAccountUrls.myAccount).replace(/\/+$/, '/');
				return `${base}lost-password/`;
			}

			return '/minha-conta/lost-password/';
		}

		function readStoredEmail() {
			try {
				return sessionStorage.getItem(storageKey) || '';
			} catch (e) {
				return '';
			}
		}

		function writeStoredEmail(email) {
			if (!email) return;
			try {
				sessionStorage.setItem(storageKey, email);
			} catch (e) {
				// no-op
			}
		}

		function clearStoredEmail() {
			try {
				sessionStorage.removeItem(storageKey);
			} catch (e) {
				// no-op
			}
		}

		const registerForm = document.querySelector('form.woocommerce-form-register');
		const registerEmailInput = document.querySelector('#reg_email');

		if (registerForm && registerEmailInput) {
			registerForm.addEventListener('submit', function() {
				const email = (registerEmailInput.value || '').trim();
				if (email) {
					writeStoredEmail(email);
				}
			});
		}

		if (!isLostPasswordPage()) {
			const duplicateNoticeEl = document.querySelector('.woocommerce-error, .wc-block-components-notice-banner.is-error');
			const duplicateText = duplicateNoticeEl ? (duplicateNoticeEl.textContent || '').toLowerCase() : '';
			const isDuplicateEmailError =
				duplicateText.indexOf('já existe uma conta cadastrada') !== -1 ||
				duplicateText.indexOf('already registered') !== -1 ||
				duplicateText.indexOf('account is already registered') !== -1;

			if (isDuplicateEmailError) {
				const matchedEmail = duplicateText.match(/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i);
				if (matchedEmail && matchedEmail[0]) {
					writeStoredEmail(matchedEmail[0]);
				} else if (registerEmailInput && registerEmailInput.value) {
					writeStoredEmail(registerEmailInput.value.trim());
				}

				const target = new URL(getLostPasswordUrl(), window.location.origin);
				target.searchParams.set('gstore_existing_account', '1');
				window.location.replace(target.toString());
			}
		}

		if (isLostPasswordPage()) {
			const params = new URLSearchParams(window.location.search);
			const hasExistingAccountFlag = params.get('gstore_existing_account') === '1';
			if (hasExistingAccountFlag) {
				const lostForm = document.querySelector('form.woocommerce-ResetPassword');
				const hasBanner = !!document.querySelector('.gstore-lost-password__notice');
				if (lostForm && !hasBanner) {
					const notice = document.createElement('div');
					notice.className = 'gstore-lost-password__notice';
					notice.setAttribute('role', 'status');
					notice.setAttribute('aria-live', 'polite');
					notice.innerHTML = '<p>Este e-mail já está cadastrado. Para acessar sua conta, recupere sua senha abaixo. Depois de clicar em "Redefinir senha", você receberá um e-mail com o link para criar uma nova senha.</p>';
					lostForm.insertBefore(notice, lostForm.firstChild);
				}
			}

			const loginInput = document.querySelector('#user_login');
			const storedEmail = readStoredEmail();
			if (loginInput && !loginInput.value && storedEmail) {
				loginInput.value = storedEmail;
				clearStoredEmail();
			}
		}
	}

	// Inicializa quando o DOM estiver pronto
	function init() {
		initRegisterInfoModal();
		initDashboardAnimations();
		initMobileNavigation();
		initActionFeedback();
		initCPFMask();
		initFormValidation();
		initCharacterCounters();
		initFormLoadingStates();
		initRegisterButton();
		initExistingAccountRecoveryFallback();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

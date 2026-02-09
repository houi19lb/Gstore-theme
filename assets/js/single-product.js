document.addEventListener('DOMContentLoaded', () => {
	// #region agent log
	console.warn('[GSTORE_BN_DEBUG] === SCRIPT LOADED v3 ===');
	// #endregion
	const reviewTriggers = document.querySelectorAll('[data-gstore-tab-target="reviews"]');

	const focusReviewTab = () => {
		// Tabs customizados do tema
		const reviewsTabButton = document.querySelector('[data-gstore-tabs] [data-gstore-tab="reviews"]');
		if (reviewsTabButton) {
			reviewsTabButton.click();
		}

		const reviewsPanel = document.querySelector('#gstore-tab-reviews');
		if (reviewsPanel) {
			const preferredOffset = Number(document.body.dataset.gstoreStickyOffset || 120);
			window.scrollTo({
				top: reviewsPanel.getBoundingClientRect().top + window.scrollY - preferredOffset,
				behavior: 'smooth',
			});
		}
	};

	reviewTriggers.forEach((trigger) => {
		trigger.addEventListener('click', focusReviewTab);
	});

	/**
	 * Avaliações: carrega todas ao clicar no botão (AJAX).
	 */
	const initLoadAllReviews = () => {
		const button = document.querySelector('[data-gstore-load-reviews="1"]');
		if (!button) {
			return;
		}

		const controlsId = String(button.getAttribute('aria-controls') || '');
		const list =
			(controlsId ? document.getElementById(controlsId) : null) ||
			document.querySelector('.Gstore-reviews-list');

		const actions = button.closest('.Gstore-reviews-actions');
		const statusEl = actions ? actions.querySelector('.Gstore-reviews-status') : null;

		const resolveAjaxUrl = () => {
			// Preferência: config já presente no tema (favorites-core).
			if (typeof gstoreFavoritesConfig !== 'undefined' && gstoreFavoritesConfig?.ajaxUrl) {
				return String(gstoreFavoritesConfig.ajaxUrl);
			}
			if (typeof gstoreSettings !== 'undefined' && gstoreSettings?.ajax_url) {
				return String(gstoreSettings.ajax_url);
			}
			if (typeof ajaxurl !== 'undefined' && ajaxurl) {
				return String(ajaxurl);
			}
			return '/wp-admin/admin-ajax.php';
		};

		const ajaxUrl = resolveAjaxUrl();
		if (!ajaxUrl) {
			return;
		}

		let isLoading = false;

		button.addEventListener('click', async () => {
			if (isLoading) {
				return;
			}

			const productId = String(button.dataset.productId || '');
			const offset = String(button.dataset.offset || '0');
			const nonce = String(button.dataset.nonce || '');

			if (!productId || !nonce) {
				return;
			}

			isLoading = true;
			button.disabled = true;
			button.classList.add('is-loading');
			button.setAttribute('aria-busy', 'true');
			if (statusEl) {
				statusEl.textContent = 'Carregando comentários...';
			}

			const body = new URLSearchParams();
			body.set('action', 'gstore_load_product_reviews');
			body.set('product_id', productId);
			body.set('offset', offset);
			body.set('nonce', nonce);

			try {
				const response = await fetch(ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					},
					credentials: 'same-origin',
					body: body.toString(),
				});

				const payload = await response.json();
				if (!response.ok) {
					throw new Error(payload?.data?.message || 'Falha ao carregar comentários.');
				}
				if (!payload?.success) {
					throw new Error(payload?.data?.message || 'Falha ao carregar comentários.');
				}

				const html = String(payload?.data?.html || '');
				if (html && list) {
					list.insertAdjacentHTML('beforeend', html);
					// Mantém scroll interno, mas leva o usuário para a área recém adicionada.
					list.scrollTo({ top: list.scrollHeight, behavior: 'smooth' });
				}

				if (statusEl) {
					statusEl.textContent = '';
				}
				if (actions) {
					actions.remove();
				} else {
					button.remove();
				}
			} catch (err) {
				button.disabled = false;
				button.classList.remove('is-loading');
				button.removeAttribute('aria-busy');
				if (statusEl) {
					statusEl.textContent = 'Não foi possível carregar os comentários. Tente novamente.';
				}
			} finally {
				isLoading = false;
			}
		});
	};

	initLoadAllReviews();

	/**
	 * Parcelamento do produto (AJAX Blu).
	 */
	const gstoreInstallmentCache = new Map();
	const gstoreInstallmentQuotesCache = new Map();
	const gstoreInstallmentInFlight = new Map();

	const resolveInstallmentAjaxUrl = () => {
		if (typeof gstoreSingleProductInstallments !== 'undefined' && gstoreSingleProductInstallments?.ajaxUrl) {
			return String(gstoreSingleProductInstallments.ajaxUrl);
		}
		// Preferência: config já presente no tema (favorites-core).
		if (typeof gstoreFavoritesConfig !== 'undefined' && gstoreFavoritesConfig?.ajaxUrl) {
			return String(gstoreFavoritesConfig.ajaxUrl);
		}
		if (typeof gstoreSettings !== 'undefined' && gstoreSettings?.ajax_url) {
			return String(gstoreSettings.ajax_url);
		}
		if (typeof ajaxurl !== 'undefined' && ajaxurl) {
			return String(ajaxurl);
		}
		return '/wp-admin/admin-ajax.php';
	};

	const resolveInstallmentAction = () => {
		if (typeof gstoreSingleProductInstallments !== 'undefined' && gstoreSingleProductInstallments?.action) {
			return String(gstoreSingleProductInstallments.action);
		}
		return 'gstore_blu_get_product_installment_quotes';
	};

	const formatCurrency = (value) => {
		if (!Number.isFinite(value)) return '';
		try {
			return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
		} catch (err) {
			return `R$ ${value.toFixed(2).replace('.', ',')}`;
		}
	};

	const chooseQuote = (quotes, preferred) => {
		if (!quotes || typeof quotes !== 'object') return null;
		if (quotes[preferred]) return quotes[preferred];
		const keys = Object.keys(quotes)
			.map((key) => parseInt(key, 10))
			.filter((key) => Number.isFinite(key))
			.sort((a, b) => b - a);
		if (!keys.length) return null;
		return quotes[String(keys[0])] || null;
	};

	const buildInstallmentSelect = (select, quotes, selectedInstallments) => {
		if (!select) return;

		// Verificar se o select está dentro de um card - não criar dropdown nos cards
		const wrapper = select.closest('[data-gstore-installment-wrapper]');
		if (wrapper) {
			const scope = wrapper.querySelector('[data-gstore-installment-scope="card"]');
			if (scope) {
				select.style.display = 'none';
				return; // Não criar dropdown nos cards
			}
		}

		select.innerHTML = '';

		if (!quotes || typeof quotes !== 'object') {
			select.style.display = 'none';
			return;
		}

		const quoteKeys = Object.keys(quotes)
			.map((key) => parseInt(key, 10))
			.filter((key) => Number.isFinite(key))
			.sort((a, b) => b - a);

		if (!quoteKeys.length) {
			select.style.display = 'none';
			return;
		}

		const defaultOption = document.createElement('option');
		defaultOption.value = '';
		defaultOption.textContent = 'Selecione as parcelas';
		select.appendChild(defaultOption);

		quoteKeys.forEach((installments) => {
			const quote = quotes[String(installments)];
			if (!quote || !quote.per_installment_text) return;

			const option = document.createElement('option');
			option.value = String(installments);

			let optionText = `${installments}x de ${quote.per_installment_text}`;
			const totalRaw = Number(quote.total_raw ?? quote.total);
			const totalText = quote.total_text || quote.totalText || (Number.isFinite(totalRaw) ? formatCurrency(totalRaw) : '');
			const originalTotal = Number(quote.original_total ?? quote.originalTotal);
			const jurosValue = Number.isFinite(totalRaw) && Number.isFinite(originalTotal)
				? totalRaw - originalTotal
				: NaN;
			const details = [];
			if (totalText) {
				details.push(`total: ${totalText}`);
			}
			// Removido: exibição de juros conforme solicitado
			// if (Number.isFinite(jurosValue) && jurosValue > 0) {
			// 	const jurosText = formatCurrency(jurosValue);
			// 	if (jurosText) {
			// 		details.push(`juros: ${jurosText}`);
			// 	}
			// }
			if (details.length) {
				optionText += ` (${details.join(', ')})`;
			}

			option.textContent = optionText;
			option.selected = Number(selectedInstallments) === installments;
			select.appendChild(option);
		});

		select.style.display = 'block';
	};

	const initProductInstallmentQuotes = () => {
		const targets = Array.from(document.querySelectorAll('[data-gstore-installment-target="1"]'));
		if (!targets.length) {
			return;
		}

		const baseProductId =
			String(targets[0]?.dataset?.productId || '') ||
			String(gstoreSingleProductInstallments?.productId || '') ||
			'';
		let currentProductId = baseProductId;

		const getQtyInput = () =>
			document.querySelector('.Gstore-single-product__add-to-cart input.qty') ||
			document.querySelector('form.cart input.qty') ||
			document.querySelector('.cart input.qty');

		const getQuantity = () => {
			const input = getQtyInput();
			if (!input) return 1;
			const raw = parseFloat(String(input.value || '1'));
			return Number.isFinite(raw) && raw > 0 ? raw : 1;
		};

		const getMaxInstallments = () => {
			const targetWithMax = targets.find((target) => target?.dataset?.maxInstallments);
			const raw =
				targetWithMax?.dataset?.maxInstallments ||
				gstoreSingleProductInstallments?.max ||
				'21';
			const parsed = parseInt(String(raw), 10);
			const result = Number.isFinite(parsed) && parsed > 0 ? parsed : 21;			return result;
		};

		const applyText = (text) => {
			targets.forEach((target) => {
				target.textContent = text;
				target.hidden = !text;
			});
		};

		const applyFallback = () => {
			targets.forEach((target) => {
				const fallback = String(target.dataset.initialText || '').trim();
				if (fallback) {
					target.textContent = fallback;
					target.hidden = false;
				} else {
					target.hidden = true;
				}
			});
		};

		const updateTargetsProductId = (productId) => {
			targets.forEach((target) => {
				target.dataset.productId = productId;
			});
		};
		const populateInstallmentSelect = (quotes, selectedInstallments) => {
			targets.forEach((target) => {
				const wrapper = target.closest('[data-gstore-installment-wrapper]');
				if (!wrapper) return;

				const select = wrapper.querySelector('[data-gstore-installment-select]');
				if (!select) return;

				buildInstallmentSelect(select, quotes, selectedInstallments);
			});
		};

		const setupInstallmentSelectListeners = () => {
			targets.forEach((target) => {
				const wrapper = target.closest('[data-gstore-installment-wrapper]');
				if (!wrapper) return;

				const select = wrapper.querySelector('[data-gstore-installment-select]');
				if (!select) return;

				select.onchange = () => {};
			});
		};

		const requestQuotes = async () => {			if (!currentProductId) {				applyFallback();
				return;
			}

			const max = getMaxInstallments();
			const quantity = getQuantity();
			const signature = `${currentProductId}|${quantity}|${max}`;
			if (gstoreInstallmentCache.has(signature)) {
				const cachedText = gstoreInstallmentCache.get(signature);				applyText(cachedText);
				const cachedQuotes = gstoreInstallmentQuotesCache.get(signature);
				if (cachedQuotes) {
					populateInstallmentSelect(cachedQuotes);
					setupInstallmentSelectListeners();
				}
				return;
			}
			if (gstoreInstallmentInFlight.has(signature)) {				return;
			}

			const ajaxUrl = resolveInstallmentAjaxUrl();
			const action = resolveInstallmentAction();			if (!ajaxUrl) {				applyFallback();
				return;
			}

			const body = new URLSearchParams();
			body.set('action', action);
			body.set('product_id', currentProductId);
			body.set('quantity', String(quantity));
			body.set('max', String(max));

			const fetchPromise = fetch(ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				credentials: 'same-origin',
				body: body.toString(),
			})
				.then(async (response) => {					let payload;
					try {
						payload = await response.json();
					} catch (parseError) {						throw new Error('Resposta inválida do servidor (JSON parse error).');
					}					if (!response.ok || !payload?.success) {
						const errorMsg = payload?.data?.message || 'Falha ao obter parcelas.';						throw new Error(errorMsg);
					}
					if (!payload?.data || typeof payload.data !== 'object') {						throw new Error('Dados de resposta inválidos.');
					}
					if (!payload.data.quotes || typeof payload.data.quotes !== 'object') {						throw new Error('Quotes não encontrados na resposta.');
					}
					// Tenta usar o max da resposta, senão usa o max solicitado, senão 21
					const preferredMax = payload.data.max || max || 21;
					const quote = chooseQuote(payload.data.quotes, String(preferredMax));					if (!quote || !quote.installments || !quote.per_installment_text) {						throw new Error('Parcelas indisponíveis.');
					}
					const text = `ou ${quote.installments}x de ${quote.per_installment_text}`;
					gstoreInstallmentCache.set(signature, text);
					gstoreInstallmentQuotesCache.set(signature, payload.data.quotes);					applyText(text);
					populateInstallmentSelect(payload.data.quotes, quote.installments);
					setupInstallmentSelectListeners();
				})
				.catch((error) => {					applyFallback();
				})
				.finally(() => {
					gstoreInstallmentInFlight.delete(signature);
				});

			gstoreInstallmentInFlight.set(signature, fetchPromise);
		};

		let qtyTimer = null;
		const scheduleRequest = () => {
			clearTimeout(qtyTimer);
			qtyTimer = setTimeout(requestQuotes, 200);
		};

		const qtyInput = getQtyInput();
		if (qtyInput) {
			qtyInput.addEventListener('change', scheduleRequest);
			qtyInput.addEventListener('input', scheduleRequest);
		}

		if (typeof jQuery !== 'undefined') {
			const $form = jQuery('.variations_form');
			if ($form.length) {
				$form.on('found_variation', (event, variation) => {
					const variationId = variation?.variation_id ? String(variation.variation_id) : '';
					if (variationId) {
						currentProductId = variationId;
						updateTargetsProductId(variationId);
						scheduleRequest();
					}
				});
				$form.on('reset_data', () => {
					currentProductId = baseProductId;
					updateTargetsProductId(baseProductId);
					scheduleRequest();
				});
			}
		}		requestQuotes();
	};

	/**
	 * Tabs (Descrição / Especificações / Avaliações)
	 */
	const initTabs = () => {
		const root = document.querySelector('[data-gstore-tabs]');
		if (!root) {
			return;
		}

		const buttons = Array.from(root.querySelectorAll('[data-gstore-tab]'));
		const panels = Array.from(root.querySelectorAll('.Gstore-single-product__tab-panel'));
		if (buttons.length === 0 || panels.length === 0) {
			return;
		}

		const isMobileQuery = window.matchMedia('(max-width: 1024px)');
		const getPanelId = (tab) => `gstore-tab-${tab}`;
		const getTabFromPanel = (panel) => String(panel.id || '').replace(/^gstore-tab-/, '');

		const ensureAccordionMarkup = () => {
			if (root.dataset.gstoreAccordionReady === '1') {
				return;
			}

			panels.forEach((panel) => {
				const tab = getTabFromPanel(panel);
				if (!tab) {
					return;
				}

				// Já processado?
				if (panel.querySelector('.Gstore-single-product__accordion-content')) {
					return;
				}

				const label =
					buttons.find((b) => b.dataset.gstoreTab === tab)?.textContent?.trim() ||
					panel.querySelector('.Gstore-single-product__tab-title')?.textContent?.trim() ||
					tab;

				const header = document.createElement('button');
				header.type = 'button';
				header.className = 'Gstore-single-product__accordion-header';
				header.dataset.gstoreAccordionTab = tab;
				header.setAttribute('aria-controls', panel.id);
				header.setAttribute('aria-expanded', 'false');

				const title = document.createElement('span');
				title.className = 'Gstore-single-product__accordion-title';
				title.textContent = label;

				const icon = document.createElement('span');
				icon.className = 'Gstore-single-product__accordion-icon';
				icon.setAttribute('aria-hidden', 'true');
				icon.textContent = '+';

				header.appendChild(title);
				header.appendChild(icon);

				const content = document.createElement('div');
				content.className = 'Gstore-single-product__accordion-content';

				// Move todo o conteúdo atual do panel para dentro do wrapper de conteúdo.
				while (panel.firstChild) {
					content.appendChild(panel.firstChild);
				}

				panel.appendChild(header);
				panel.appendChild(content);
			});

			root.dataset.gstoreAccordionReady = '1';
		};

		// Estado inicial: baseado em markup (fallback: 1º)
		const defaultBtn = buttons.find((b) => b.classList.contains('is-active')) || buttons[0];
		let activeTab = defaultBtn?.dataset?.gstoreTab ? String(defaultBtn.dataset.gstoreTab) : '';

		const normalizeForMode = () => {
			const isMobile = isMobileQuery.matches;
			if (!activeTab && !isMobile) {
				activeTab = String(buttons[0]?.dataset?.gstoreTab || '');
			}
		};

		const updateUI = () => {
			const isMobile = isMobileQuery.matches;
			normalizeForMode();

			buttons.forEach((btn) => {
				const isActive = !!activeTab && btn.dataset.gstoreTab === activeTab;
				btn.classList.toggle('is-active', isActive);
				btn.setAttribute('aria-selected', String(isActive));
			});

			panels.forEach((panel) => {
				const tab = getTabFromPanel(panel);
				const isActive = !!activeTab && tab === activeTab;

				const header = panel.querySelector('.Gstore-single-product__accordion-header');
				const icon = panel.querySelector('.Gstore-single-product__accordion-icon');
				const content = panel.querySelector('.Gstore-single-product__accordion-content');

				if (isMobile) {
					// No mobile, todos os itens aparecem (header sempre visível).
					panel.hidden = false;
					panel.classList.toggle('is-open', isActive);
					panel.classList.toggle('is-active', isActive);

					if (header) header.setAttribute('aria-expanded', String(isActive));
					if (content) content.hidden = !isActive;
					if (icon) icon.textContent = isActive ? '−' : '+';
				} else {
					// No desktop, mantém o comportamento clássico de tabs (1 painel por vez).
					panel.classList.toggle('is-open', false);
					panel.classList.toggle('is-active', isActive);
					panel.hidden = !isActive;

					if (header) header.setAttribute('aria-expanded', 'false');
					if (content) content.hidden = false;
					if (icon) icon.textContent = '+';
				}
			});
		};

		const setActiveTab = (tabOrEmpty) => {
			activeTab = tabOrEmpty ? String(tabOrEmpty) : '';
			updateUI();
		};

		// Tabs (desktop)
		buttons.forEach((btn) => {
			btn.addEventListener('click', () => {
				const tab = btn.dataset.gstoreTab;
				if (!tab) return;
				setActiveTab(tab);
			});
		});

		// Accordions (mobile)
		ensureAccordionMarkup();
		root.addEventListener('click', (event) => {
			const header = event.target?.closest?.('.Gstore-single-product__accordion-header');
			if (!header || !root.contains(header)) {
				return;
			}

			// Só trata como accordion no mobile.
			if (!isMobileQuery.matches) {
				return;
			}

			const tab = header.dataset.gstoreAccordionTab;
			if (!tab) {
				return;
			}

			// Permite “fechar” o item ativo (fica tudo recolhido).
			setActiveTab(activeTab === tab ? '' : tab);
		});

		// Atualiza ao trocar breakpoint (ex.: resize)
		if (typeof isMobileQuery.addEventListener === 'function') {
			isMobileQuery.addEventListener('change', updateUI);
		} else if (typeof isMobileQuery.addListener === 'function') {
			// Safari antigo
			isMobileQuery.addListener(updateUI);
		}

		// Estado inicial consistente
		updateUI();
	};

	/**
	 * Favoritar (compatível com o storage usado nos cards)
	 */
	const initFavoriteButton = () => {
		const button = document.querySelector('[data-gstore-favorite-product]');
		if (!button) {
			return;
		}

		const productId = String(button.dataset.gstoreFavoriteProduct || '').trim();
		if (!productId) {
			return;
		}

		const icon = button.querySelector('i');

		const setUI = (isActive) => {
			button.classList.toggle('is-favorited', isActive);
			button.setAttribute('aria-pressed', String(isActive));
			if (icon) {
				icon.classList.toggle('fa-solid', isActive);
				icon.classList.toggle('fa-regular', !isActive);
			}
		};

		const hasCore = () => typeof window.GstoreFavorites?.toggle === 'function';

		const syncFromCore = async () => {
			if (!hasCore()) {
				return;
			}
			try {
				await window.GstoreFavorites.ready;
				const active = await window.GstoreFavorites.isFavorited(productId);
				setUI(active);
			} catch (e) {
				// ignore
			}
		};

		// Estado inicial
		syncFromCore();

		button.addEventListener('click', async (e) => {
			e.preventDefault();
			if (!hasCore()) {
				return;
			}

			const prev = button.classList.contains('is-favorited');
			setUI(!prev);
			try {
				const result = await window.GstoreFavorites.toggle(productId);
				setUI(Boolean(result?.isFavorited));
			} catch (err) {
				setUI(prev);
			}
		});

		// Sincroniza se o favorito mudar em outro lugar (ex.: lista /favoritos/)
		window.addEventListener('gstore:favorites-changed', (ev) => {
			const ids = ev?.detail?.ids;
			if (!Array.isArray(ids)) return;
			setUI(ids.includes(productId));
		});
	};

	/**
	 * Botão "Limpar" (reset de variações + qty)
	 */
	const initResetButton = () => {
		const resetButton = document.querySelector('[data-gstore-reset-purchase]');
		if (!resetButton) {
			return;
		}

		resetButton.addEventListener('click', (e) => {
			e.preventDefault();

			const form = document.querySelector('.variations_form') || document.querySelector('form.cart');
			if (!form) {
				return;
			}

			// Se for variável, tenta resetar via link nativo do WooCommerce e força selects para vazio.
			if (form.classList.contains('variations_form')) {
				const resetLink = form.querySelector('.reset_variations');
				if (resetLink) {
					resetLink.click();
				}

				form.querySelectorAll('select').forEach((select) => {
					select.value = '';
					select.dispatchEvent(new Event('change', { bubbles: true }));
				});
			}

			// Quantidade volta pro mínimo (fallback: 1)
			const qty = form.querySelector('input.qty') || document.querySelector('.cart input.qty');
			if (qty) {
				const min = parseFloat(qty.min);
				qty.value = String(isNaN(min) ? 1 : min);
				qty.dispatchEvent(new Event('change', { bubbles: true }));
			}
		});
	};

	/**
	 * Preview + gating do "Comprar agora" para produtos variáveis
	 */
	const initVariationsState = () => {
		const form = document.querySelector('.variations_form');
		if (!form) {
			return;
		}

		const buybox = document.querySelector('.buybox');
		const selects = Array.from(form.querySelectorAll('select'));
		const preview = document.querySelector('[data-gstore-variation-preview]');
		const warning = document.querySelector('[data-gstore-variation-warning]');
		const buyNowButton = form.querySelector('.Gstore-single-product__buy-now');
		const addToCartButton = form.querySelector('.single_add_to_cart_button');
		const priceEl = document.querySelector('[data-gstore-price]');
		const initialPriceHtml = priceEl ? priceEl.innerHTML : '';
		const getPreviewText = () => {
			const parts = selects
				.map((select) => {
					const value = String(select.value || '').trim();
					if (!value) return '';
					const option = select.selectedOptions?.[0];
					return String(option?.textContent || '').trim();
				})
				.filter(Boolean);

			return parts.length ? parts.join(' • ') : '—';
		};

		const update = () => {
			if (preview) {
				preview.textContent = getPreviewText();
			}

			const allSelected = selects.length > 0 && selects.every((s) => String(s.value || '').trim().length > 0);
			const canAddProp = addToCartButton ? !addToCartButton.disabled : allSelected;
			const canAddClass = addToCartButton ? !addToCartButton.classList.contains('disabled') : allSelected;
			const atcEnabled = !!form.querySelector('.woocommerce-variation-add-to-cart.woocommerce-variation-add-to-cart-enabled');
			const isOos = buybox ? buybox.classList.contains('is-out-of-stock') : false;
			const canAdd = canAddProp && canAddClass && !isOos;
			const ok = allSelected && canAdd;

			if (buyNowButton) {
				buyNowButton.disabled = !ok;
			}
			if (warning) {
				warning.hidden = isOos ? true : ok;
			}		};

		// Eventos nativos
		selects.forEach((select) => {
			select.addEventListener('change', () => {
				window.requestAnimationFrame(update);
				setTimeout(update, 0);
			});
		});

		// Observa mudança de disabled no botão de add-to-cart (WooCommerce varia isso)
		if (addToCartButton) {
			const observer = new MutationObserver(() => update());
			observer.observe(addToCartButton, { attributes: true, attributeFilter: ['disabled', 'class'] });
		}

		// Eventos do WooCommerce (se jQuery existir)
		if (typeof jQuery !== 'undefined') {
			const $form = jQuery(form);
			$form.on('found_variation', (event, variation) => {				if (priceEl && variation && typeof variation.price_html === 'string' && variation.price_html.trim().length) {
					priceEl.innerHTML = variation.price_html;
				}
				setTimeout(update, 0);
			});
			$form.on('reset_data', () => {				if (priceEl && initialPriceHtml) {
					priceEl.innerHTML = initialPriceHtml;
				}
				setTimeout(update, 0);
			});
			$form.on('found_variation reset_data woocommerce_variation_has_changed', () => {
				setTimeout(update, 0);
			});
		}

		update();
	};

	initTabs();
	initProductInstallmentQuotes();
	initFavoriteButton();
	initResetButton();
	initVariationsState();

	/**
	 * Controla o estado de indisponibilidade por variação selecionada.
	 * Quando o usuário seleciona uma variação sem estoque, mostra o card "Produto indisponível"
	 * e esconde os CTAs de compra. Quando seleciona uma com estoque, faz o inverso.
	 */
	const initVariationStockState = () => {
		const form = document.querySelector('.variations_form');
		if (!form) {
			return;
		}

		const buybox = document.querySelector('.buybox');
		const oosCard = document.querySelector('[data-gstore-oos-card]');
		const stockBlock = document.querySelector('[data-gstore-stock-block]');
		const stockTitle = stockBlock?.querySelector('[data-gstore-stock-title]');
		const stockSubtitle = stockBlock?.querySelector('[data-gstore-stock-subtitle]');
		const warning = document.querySelector('[data-gstore-variation-warning]');

		// Se não tem o card de indisponível, não há o que controlar
		if (!oosCard || !buybox) {
			return;
		}

		// Dados padrão do bloco de estoque
		const defaultClass = stockBlock?.dataset?.defaultClass || 'is-in-stock';
		const defaultTitle = stockBlock?.dataset?.defaultTitle || 'Disponível';
		const defaultSubtitle = stockBlock?.dataset?.defaultSubtitle || 'Pronta entrega';
		const oosTitle = stockBlock?.dataset?.oosTitle || 'Indisponível';
		const oosSubtitle = stockBlock?.dataset?.oosSubtitle || 'Sem estoque no momento';

		const resolveVariationStock = (variation) => {
			if (!variation) {
				return null;
			}
			if (typeof variation.gstore_is_in_stock !== 'undefined') {
				return Boolean(variation.gstore_is_in_stock);
			}
			if (typeof variation.is_in_stock !== 'undefined') {
				return variation.is_in_stock === true;
			}
			if (typeof variation.is_purchasable !== 'undefined') {
				return variation.is_purchasable === true;
			}
			return null;
		};

		const setOutOfStockState = (isOos) => {
			if (isOos) {
				// Mostrar card de indisponível
				oosCard.hidden = false;

				// Adicionar classe is-out-of-stock ao buybox (esconde CTAs via CSS)
				buybox.classList.remove('is-in-stock', 'is-on-order');
				buybox.classList.add('is-out-of-stock');

				// Atualizar bloco de estoque
				if (stockBlock) {
					stockBlock.classList.remove('is-in-stock', 'is-on-order');
					stockBlock.classList.add('is-out-of-stock');
				}
				if (stockTitle) {
					stockTitle.textContent = oosTitle;
				}
				if (stockSubtitle) {
					stockSubtitle.textContent = oosSubtitle;
				}

				// Esconder o warning de variações (não faz sentido mostrar "selecione para liberar" se não tem estoque)
				if (warning) {
					warning.hidden = true;
				}
			} else {
				// Esconder card de indisponível
				oosCard.hidden = true;

				// Remover classe is-out-of-stock do buybox
				buybox.classList.remove('is-out-of-stock');
				buybox.classList.add(defaultClass);

				// Restaurar bloco de estoque
				if (stockBlock) {
					stockBlock.classList.remove('is-out-of-stock');
					stockBlock.classList.add(defaultClass);
				}
				if (stockTitle) {
					stockTitle.textContent = defaultTitle;
				}
				if (stockSubtitle) {
					stockSubtitle.textContent = defaultSubtitle;
				}
			}
		};

		// Estado inicial: se o buybox já está com is-out-of-stock (todas variações sem estoque), manter
		const initiallyOos = buybox.classList.contains('is-out-of-stock');

		if (typeof jQuery !== 'undefined') {
			const $form = jQuery(form);

			// Capturar dados de todas as variações disponíveis
			const variations = $form.data('product_variations') || [];

			// Função para obter os atributos selecionados atualmente
			const getSelectedAttributes = () => {
				const attrs = {};
				$form.find('select[name^="attribute_"]').each(function () {
					const name = jQuery(this).attr('name');
					const value = jQuery(this).val() || '';
					attrs[name] = value;
				});
				return attrs;
			};

			// Função para encontrar variação correspondente aos atributos selecionados
			const findMatchingVariation = (selectedAttrs) => {
				if (!variations || !variations.length) {
					return null;
				}

				// Verificar se todos os atributos estão selecionados
				const selectedValues = Object.values(selectedAttrs);
				const allSelected = selectedValues.length > 0 && selectedValues.every((v) => v && v.trim().length > 0);
				if (!allSelected) {
					return null;
				}

				// Procurar variação que corresponde a todos os atributos
				for (const variation of variations) {
					if (!variation.attributes) {
						continue;
					}

					let matches = true;
					for (const [attrName, attrValue] of Object.entries(selectedAttrs)) {
						const variationAttrValue = variation.attributes[attrName];

						// Se a variação tem valor vazio para esse atributo, significa "any" (qualquer valor)
						if (variationAttrValue === '' || variationAttrValue === undefined) {
							continue;
						}

						// Comparar valores (case-insensitive e trim)
						if (variationAttrValue.toLowerCase().trim() !== attrValue.toLowerCase().trim()) {
							matches = false;
							break;
						}
					}

					if (matches) {
						return variation;
					}
				}

				return null;
			};

			$form.on('found_variation', (event, variation) => {
				// Verificar se a variação selecionada está em estoque
				const variationInStock = resolveVariationStock(variation);

				if (variationInStock === null) {
					return;
				}
				setOutOfStockState(!variationInStock);
			});

			$form.on('reset_data', () => {
				// Ao resetar, voltar ao estado inicial
				setOutOfStockState(initiallyOos);
			});

			$form.on('hide_variation', () => {
				// Verificar se há atributos selecionados
				const selectedAttrs = getSelectedAttributes();
				const selectedValues = Object.values(selectedAttrs);
				const allEmpty = selectedValues.every((v) => !v || v.trim().length === 0);

				if (allEmpty) {
					// Nenhuma seleção -> voltar ao estado inicial
					setOutOfStockState(initiallyOos);
					return;
				}

				// Alguma seleção existe -> verificar se existe variação correspondente
				const matchedVariation = findMatchingVariation(selectedAttrs);

				if (matchedVariation) {
					// Variação existe mas WooCommerce ocultou (provavelmente sem estoque)
					const inStock = resolveVariationStock(matchedVariation);

					if (inStock !== null) {
						setOutOfStockState(!inStock);
					} else {
						// Se não conseguimos determinar, mostrar indisponível (seguro)
						setOutOfStockState(true);
					}
				} else {
					// Combinação inválida ou não encontrada -> voltar ao estado inicial
					setOutOfStockState(initiallyOos);
				}
			});
		}
	};

	initVariationStockState();

	/**
	 * Estrutura visual do buybox (mock): qty + add-to-cart na mesma linha.
	 */
	const initBuyboxQtyRow = () => {
		const buybox = document.querySelector('.buybox');
		if (!buybox) {
			return;
		}

		const wrapInQtyRow = (container) => {
			if (!container || container.querySelector('.qty-row')) {
				return;
			}

			const qty = container.querySelector('.quantity');
			const addBtn = container.querySelector('.single_add_to_cart_button');
			if (!qty || !addBtn) {
				return;
			}

			const row = document.createElement('div');
			row.className = 'qty-row';
			qty.parentNode.insertBefore(row, qty);
			row.appendChild(qty);
			row.appendChild(addBtn);
		};

		// Produto simples
		const simpleForm = buybox.querySelector('form.cart:not(.variations_form)');
		if (simpleForm) {
			wrapInQtyRow(simpleForm);
		}

		// Produto variável
		const variationsForm = buybox.querySelector('form.variations_form');
		if (variationsForm) {
			const addToCartContainer =
				variationsForm.querySelector('.woocommerce-variation-add-to-cart') ||
				variationsForm.querySelector('.variations_button');
			wrapInQtyRow(addToCartContainer);
		}
	};

	initBuyboxQtyRow();

	/**
	 * "Comprar agora" em produto simples: redireciona via GET para garantir que
	 * o WooCommerce processe o add-to-cart e então vá para o checkout.
	 *
	 * Isso evita dependência de redirect PHP que pode ser bloqueado por cache/output.
	 */
	const initBuyNowRedirect = () => {
		const form = document.querySelector('form.cart:not(.variations_form)');
		if (!form) {
			return;
		}

		const buyNowBtn = form.querySelector('.Gstore-single-product__buy-now');
		if (!buyNowBtn) {
			return;
		}

		buyNowBtn.addEventListener('click', (e) => {
			e.preventDefault();

			// Tenta pegar o product ID de várias fontes
			const productIdInput = form.querySelector('input[name="add-to-cart"]');
			const productIdButton = form.querySelector('button[name="add-to-cart"]');
			const productId =
				productIdInput?.value ||
				productIdButton?.value ||
				form.querySelector('input[name="product_id"]')?.value ||
				'';

			const qtyInput = form.querySelector('input[name="quantity"]');
			const qty = qtyInput?.value || '1';

			if (!productId) {
				// Fallback: deixa o form submeter normalmente
				form.submit();
				return;
			}

			// Monta a URL GET que comprovadamente funciona
			const url = new URL(window.location.href);
			url.searchParams.set('add-to-cart', productId);
			url.searchParams.set('quantity', qty);
			url.searchParams.set('gstore_buy_now', '1');

			window.location.href = url.toString();
		});
	};

	initBuyNowRedirect();

	/**
	 * "Comprar agora" em produto variável: redireciona via GET com variation_id e
	 * atributos, para o backend processar add-to-cart e redirecionar ao checkout.
	 */
	const initBuyNowVariableRedirect = () => {
		const form = document.querySelector('form.cart.variations_form');
		// #region agent log
		console.log('[GSTORE_BN_DEBUG] H1 init', {formFound:!!form, allForms:document.querySelectorAll('form.cart').length, variationForms:document.querySelectorAll('.variations_form').length});
		// #endregion
		if (!form) return;

		const buyNowBtn = form.querySelector('.Gstore-single-product__buy-now');
		// #region agent log
		console.log('[GSTORE_BN_DEBUG] H1 btn', {btnFound:!!buyNowBtn, btnOutsideForm:!!document.querySelector('.Gstore-single-product__buy-now'), btnType:buyNowBtn?.tagName, btnDisabled:buyNowBtn?.disabled});
		// #endregion
		if (!buyNowBtn) return;

		buyNowBtn.addEventListener('click', (e) => {
			e.preventDefault();
			e.stopPropagation();

			const productId = form.querySelector('input[name="product_id"]')?.value || '';
			const variationId = form.querySelector('input[name="variation_id"]')?.value || '';
			const qty = form.querySelector('input[name="quantity"]')?.value || '1';

			// #region agent log
			console.log('[GSTORE_BN_DEBUG] H2 click', {productId, variationId, qty, willReturn:(!productId||!variationId||variationId==='0')});
			// #endregion

			if (!productId || !variationId || variationId === '0') {
				return; // variação não selecionada
			}

			const url = new URL(window.location.href);
			url.searchParams.set('add-to-cart', productId);
			url.searchParams.set('variation_id', variationId);
			url.searchParams.set('quantity', qty);
			url.searchParams.set('gstore_buy_now', '1');

			// Coleta todos os attribute_*
			form.querySelectorAll('select[name^="attribute_"], input[name^="attribute_"]').forEach((el) => {
				if (el.name && el.value) {
					url.searchParams.set(el.name, el.value);
				}
			});

			// #region agent log
			console.log('[GSTORE_BN_DEBUG] H2 redirect', {redirectUrl:url.toString()});
			// #endregion

			window.location.href = url.toString();
		});
	};
	initBuyNowVariableRedirect();

	/**
	 * Remove parâmetros de add-to-cart da URL para evitar reprocessamento.
	 */
	const cleanAddToCartParams = () => {
		const url = new URL(window.location.href);
		const shouldClean =
			url.searchParams.has('add-to-cart') ||
			url.searchParams.has('gstore_buy_now') ||
			url.searchParams.has('variation_id');

		if (!shouldClean) {
			return;
		}

		url.searchParams.delete('add-to-cart');
		url.searchParams.delete('quantity');
		url.searchParams.delete('gstore_buy_now');
		url.searchParams.delete('variation_id');

		// Remove atributos de variação (attribute_*)
		const attrKeys = [...url.searchParams.keys()].filter((k) => k.startsWith('attribute_'));
		attrKeys.forEach((k) => url.searchParams.delete(k));

		if (!url.searchParams.toString()) {
			url.search = '';
		}

		window.history.replaceState(null, document.title, url.toString());
	};

	cleanAddToCartParams();

	const enhanceQuantityField = (field) => {
		if (field.dataset.gstoreQtyEnhanced) {
			return;
		}

		const input = field.querySelector('input.qty');
		if (!input) {
			return;
		}

		field.dataset.gstoreQtyEnhanced = 'true';

		const wrapper = document.createElement('div');
		wrapper.className = 'Gstore-quantity-controls';

		const minus = document.createElement('button');
		minus.type = 'button';
		minus.className = 'Gstore-quantity-button Gstore-quantity-button--minus';
		minus.setAttribute('aria-label', input.dataset.gstoreMinusLabel || 'Diminuir quantidade');
		minus.textContent = '−';

		const plus = document.createElement('button');
		plus.type = 'button';
		plus.className = 'Gstore-quantity-button Gstore-quantity-button--plus';
		plus.setAttribute('aria-label', input.dataset.gstorePlusLabel || 'Aumentar quantidade');
		plus.textContent = '+';

		// Cria aviso de última unidade
		const lastUnitWarning = document.createElement('span');
		lastUnitWarning.className = 'gstore-last-unit-warning';
		lastUnitWarning.textContent = 'Última unidade';
		lastUnitWarning.style.display = 'none';

		input.parentNode.insertBefore(wrapper, input);
		wrapper.appendChild(minus);
		wrapper.appendChild(input);
		wrapper.appendChild(plus);

		// Adiciona o aviso após o wrapper
		wrapper.parentNode.insertBefore(lastUnitWarning, wrapper.nextSibling);

		const getStep = () => parseFloat(input.step) || 1;

		const getMin = () => {
			const min = parseFloat(input.min);
			return isNaN(min) ? 1 : min;
		};

		const getMax = () => {
			const max = parseFloat(input.max);
			return isNaN(max) || max <= 0 ? Number.MAX_SAFE_INTEGER : max;
		};

		const getCurrentValue = () => {
			const value = parseFloat(input.value);
			return isNaN(value) ? getMin() : value;
		};

		// Função para atualizar botões e aviso
		const updateButtons = () => {
			const current = getCurrentValue();
			const min = getMin();
			const max = getMax();

			// Quando há apenas 1 unidade (max < 2), esconde todo o seletor
			if (max < 2) {
				wrapper.style.display = 'none';
				lastUnitWarning.style.display = 'inline-block';
			} else {
				// Mostra o seletor quando há mais de 1 unidade
				wrapper.style.display = 'inline-flex';
				lastUnitWarning.style.display = 'none';

				// Esconde o botão - quando necessário
				minus.style.display = 'inline-flex';
				minus.disabled = current <= min;
				plus.disabled = current >= max;
			}
		};

		minus.addEventListener('click', () => {
			const min = getMin();
			const currentValue = getCurrentValue();
			const step = getStep();
			const nextValue = Math.max(currentValue - step, min);
			input.value = nextValue;
			updateButtons();
			input.dispatchEvent(new Event('change', { bubbles: true }));
		});

		plus.addEventListener('click', () => {
			const max = getMax();
			const currentValue = getCurrentValue();
			const step = getStep();
			const nextValue = Math.min(currentValue + step, max);
			input.value = nextValue;
			updateButtons();
			input.dispatchEvent(new Event('change', { bubbles: true }));
		});

		// Atualiza quando o input muda
		input.addEventListener('input', () => {
			updateButtons();
		});

		input.addEventListener('change', () => {
			updateButtons();
		});

		// Observa mudanças no atributo max
		const maxObserver = new MutationObserver(() => {
			updateButtons();
		});

		maxObserver.observe(input, {
			attributes: true,
			attributeFilter: ['max', 'value'],
		});

		// Atualiza inicialmente
		updateButtons();
	};

	const quantityFields = document.querySelectorAll('.Gstore-single-product__add-to-cart .quantity');
	quantityFields.forEach(enhanceQuantityField);

	// Garantir que o FlexSlider da galeria funcione corretamente com layout horizontal
	const gallery = document.querySelector('.Gstore-single-product__gallery .woocommerce-product-gallery');
	if (gallery) {
		let thumbsResizeTimeout;
		const productCard = gallery.closest('.Gstore-single-product__product-card') || document;
		const thumbsTarget = productCard.querySelector('[data-gstore-gallery-thumbs]');
		const zoomButton = productCard.querySelector('[data-gstore-gallery-zoom]');

		if (zoomButton) {
			zoomButton.addEventListener('click', (e) => {
				e.preventDefault();

				// 1. Tentar o gatilho nativo do WooCommerce (ícone de lupa padrão)
				const nativeTrigger = gallery.querySelector('.woocommerce-product-gallery__trigger');
				if (nativeTrigger) {
					nativeTrigger.click();
					return;
				}

				// 2. Tentar o link da imagem ativa no FlexSlider
				const activeLink = gallery.querySelector('.flex-active-slide a');
				if (activeLink) {
					activeLink.click();
					return;
				}

				// 3. Fallback: encontrar o primeiro link de imagem disponível
				const fallbackLink =
					gallery.querySelector('.woocommerce-product-gallery__image a') ||
					gallery.querySelector('.woocommerce-product-gallery__wrapper a') ||
					gallery.querySelector('a');

				if (fallbackLink) {
					fallbackLink.click();
				}
			});
		}

		/**
		 * Quando existe apenas 1 imagem, o Woo/FlexSlider pode não gerar a lista
		 * `.flex-control-thumbs`, deixando a coluna de thumbs vazia. Aqui garantimos
		 * pelo menos 1 thumbnail usando a primeira imagem disponível na galeria.
		 */
		const ensureSingleThumb = () => {
			// Se já existe lista de thumbs, não faz nada
			const existingThumbs =
				productCard.querySelector('.flex-control-thumbs') || gallery.querySelector('.flex-control-thumbs');
			if (existingThumbs) {
				return;
			}

			// Encontrar a primeira imagem renderizada pelo WooCommerce
			const firstImg =
				gallery.querySelector('.woocommerce-product-gallery__image img') ||
				gallery.querySelector('img');
			if (!firstImg) {
				return;
			}

			const src = firstImg.currentSrc || firstImg.src;
			if (!src) {
				return;
			}

			const thumbsList = document.createElement('ol');
			thumbsList.className = 'flex-control-nav flex-control-thumbs';
			thumbsList.setAttribute('data-gstore-single-thumb', 'true');

			const li = document.createElement('li');
			const img = document.createElement('img');
			img.src = src;
			img.alt = firstImg.getAttribute('alt') || '';
			img.className = 'flex-active';
			img.dataset.slide = '0';
			img.loading = 'lazy';

			li.appendChild(img);
			thumbsList.appendChild(li);

			const target = thumbsTarget || gallery;
			target.appendChild(thumbsList);

			// Se o FlexSlider existir, garantir que click na thumb mantém o slide 0
			img.addEventListener('click', () => {
				try {
					if (typeof jQuery !== 'undefined' && jQuery.fn.flexslider) {
						const $gallery = jQuery(gallery);
						const instance = $gallery.data('flexslider');
						if (instance && typeof instance.flexAnimate === 'function') {
							instance.flexAnimate(0);
						}
					}
				} catch (_) {
					// noop
				}
			});
		};

		// Se em algum cenário o FlexSlider criar thumbs depois, remove a thumb "fallback" duplicada
		const removeSingleThumbIfDuplicated = () => {
			const scope = thumbsTarget || productCard || gallery;
			const lists = Array.from(scope.querySelectorAll('.flex-control-thumbs'));
			if (lists.length <= 1) {
				return;
			}
			lists
				.filter((list) => list?.dataset?.gstoreSingleThumb === 'true')
				.forEach((list) => list.remove());
		};

		// Transformar thumbnails em "carrossel" quando houver mais de 4 imagens
		const setupThumbsCarousel = () => {
			const thumbsList = productCard.querySelector('.flex-control-thumbs') || gallery.querySelector('.flex-control-thumbs');
			if (!thumbsList) {
				return;
			}

			// Se existir o container da coluna de thumbs (layout do mock), move o <ol> pra lá
			if (thumbsTarget && thumbsList.parentElement !== thumbsTarget) {
				thumbsTarget.appendChild(thumbsList);
			}

			const isDesktop = window.matchMedia('(min-width: 1025px)').matches;

			// Layout do mock (coluna .gallery-thumbs): manter visual original e só adicionar setas (cima/baixo) quando houver 7+ imagens no desktop.
			if (thumbsTarget) {
				// Se houver wrapper antigo (versão anterior), desmonta pra não quebrar layout.
				const legacyWrapper = thumbsList.closest('.Gstore-thumbs-carousel--vertical');
				if (legacyWrapper && legacyWrapper.parentNode) {
					legacyWrapper.parentNode.insertBefore(thumbsList, legacyWrapper);
					legacyWrapper.remove();
				}

				const getAllLis = () => Array.from(thumbsList.querySelectorAll('li'));
				const getTotal = () => getAllLis().length;
				const shouldEnableNav = isDesktop && getTotal() > 6;

				const prevBtnSelector = '.Gstore-thumbs-nav-btn--prev';
				const nextBtnSelector = '.Gstore-thumbs-nav-btn--next';
				const existingPrev = thumbsTarget.querySelector(prevBtnSelector);
				const existingNext = thumbsTarget.querySelector(nextBtnSelector);

				const VISIBLE = 6;
				const HIDDEN_CLASS = 'is-gstore-thumb-hidden';
				const clamp = (n, a, b) => Math.max(a, Math.min(b, n));
				const setLastAction = (value) => {
					thumbsTarget.dataset.gstoreThumbsLastAction = value;
				};
				const getLastAction = () => String(thumbsTarget.dataset.gstoreThumbsLastAction || '');

				const getActiveIndex = () => {
					const activeImg = thumbsList.querySelector('img.flex-active');
					if (!activeImg) return 0;
					const activeLi = activeImg.closest('li');
					if (!activeLi) return 0;
					const all = getAllLis();
					const idx = all.indexOf(activeLi);
					return idx >= 0 ? idx : 0;
				};

				const showAllThumbs = () => {
					Array.from(thumbsList.querySelectorAll('li')).forEach((li) => {
						li.classList.remove(HIDDEN_CLASS);
					});
				};

				const teardown = () => {
					thumbsTarget.removeAttribute('data-gstore-thumbs-nav');
					if (existingPrev) existingPrev.remove();
					if (existingNext) existingNext.remove();

					delete thumbsTarget.dataset.gstoreThumbsStart;
					delete thumbsTarget.dataset.gstoreThumbsLastAction;
					showAllThumbs();

					// Mobile: garantir que a lista de thumbs começa totalmente no início (evita 1ª thumb “cortada”).
					try {
						thumbsTarget.scrollLeft = 0;
					} catch (_) {
						// noop
					}
				};

				if (!shouldEnableNav) {
					teardown();
					return;
				}

				thumbsTarget.setAttribute('data-gstore-thumbs-nav', '1');

				const ensureButtons = () => {
					let prevBtn = thumbsTarget.querySelector(prevBtnSelector);
					let nextBtn = thumbsTarget.querySelector(nextBtnSelector);

					if (!prevBtn) {
						prevBtn = document.createElement('button');
						prevBtn.type = 'button';
						prevBtn.className = 'Gstore-thumbs-nav-btn Gstore-thumbs-nav-btn--prev';
						prevBtn.setAttribute('aria-label', 'Miniaturas anteriores');
						prevBtn.textContent = '↑';
						thumbsTarget.insertBefore(prevBtn, thumbsList);
					}

					if (!nextBtn) {
						nextBtn = document.createElement('button');
						nextBtn.type = 'button';
						nextBtn.className = 'Gstore-thumbs-nav-btn Gstore-thumbs-nav-btn--next';
						nextBtn.setAttribute('aria-label', 'Próximas miniaturas');
						nextBtn.textContent = '↓';
						thumbsTarget.appendChild(nextBtn);
					}

					// Garantir ordem correta no DOM: prev antes da lista, next depois da lista
					try {
						if (prevBtn && prevBtn.nextSibling !== thumbsList) {
							thumbsTarget.insertBefore(prevBtn, thumbsList);
						}
						if (nextBtn && nextBtn.previousSibling !== thumbsList) {
							thumbsTarget.appendChild(nextBtn);
						}
					} catch (_) {
						// noop
					}

					return { prevBtn, nextBtn };
				};

				const { prevBtn, nextBtn } = ensureButtons();

				const getStart = () => {
					const raw = parseInt(thumbsTarget.dataset.gstoreThumbsStart || '0', 10);
					return Number.isFinite(raw) ? raw : 0;
				};

				const setStart = (nextStart) => {
					const maxStart = Math.max(0, getTotal() - VISIBLE);
					const clamped = clamp(nextStart, 0, maxStart);
					thumbsTarget.dataset.gstoreThumbsStart = String(clamped);
					return clamped;
				};

				const applyWindow = () => {
					const allLis = getAllLis();
					const total = allLis.length;
					if (total <= VISIBLE) {
						showAllThumbs();
						prevBtn.disabled = true;
						nextBtn.disabled = true;
						return;
					}

					const active = clamp(getActiveIndex(), 0, total - 1);
					let start = getStart();

					// Se a mudança veio de “troca de imagem”, ajusta a janela para incluir a ativa.
					// Se veio de navegação (setas/scroll/drag), respeita o start escolhido pelo usuário,
					// mesmo que a imagem ativa fique fora da janela visível (como no exemplo de referência).
					if (getLastAction() !== 'nav') {
						if (active < start) start = active;
						if (active >= start + VISIBLE) start = active - (VISIBLE - 1);
					}

					start = setStart(start);
					const end = Math.min(start + VISIBLE, total);

					allLis.forEach((li, idx) => {
						const isVisible = idx >= start && idx < end;
						li.classList.toggle(HIDDEN_CLASS, !isVisible);
					});

					prevBtn.disabled = start <= 0;
					nextBtn.disabled = end >= total;
				};

				const moveWindow = (delta) => {
					setLastAction('nav');
					const current = getStart();
					setStart(current + delta);
					applyWindow();
				};

				const updateButtons = () => {
					applyWindow();
				};

				// Click handlers das setas: sempre reatribui (evita closures com contagem antiga)
				prevBtn.onclick = () => moveWindow(-1);
				nextBtn.onclick = () => moveWindow(1);

				// Listeners: inicializa só uma vez por thumbsTarget
				if (!thumbsTarget.dataset.gstoreThumbsNavInit) {
					thumbsTarget.dataset.gstoreThumbsNavInit = 'true';

					// Quando o usuário clica numa thumb (ou o Woo altera a ativa), recalcula a janela.
					thumbsList.addEventListener(
						'click',
						() => {
							setLastAction('active');
							setTimeout(updateButtons, 0);
						},
						true
					);

					// Wheel para “girar” o carrossel (sem depender de scroll/overflow)
					let wheelLock = false;
					thumbsTarget.addEventListener(
						'wheel',
						(e) => {
							if (!window.matchMedia('(min-width: 1025px)').matches) return;
							if (getTotal() <= VISIBLE) return;

							// Só captura wheel quando o mouse está sobre a coluna de thumbs
							if (!thumbsTarget.contains(e.target)) return;

							e.preventDefault();
							if (wheelLock) return;
							wheelLock = true;

							const dir = e.deltaY > 0 ? 1 : -1;
							moveWindow(dir);

							setTimeout(() => {
								wheelLock = false;
							}, 120);
						},
						{ passive: false }
					);

					// Drag vertical simples para “girar” (arrastar pra cima/baixo)
					let dragStartY = 0;
					let dragging = false;
					thumbsTarget.addEventListener('pointerdown', (e) => {
						if (!window.matchMedia('(min-width: 1025px)').matches) return;
						if (getTotal() <= VISIBLE) return;
						if (e.button !== undefined && e.button !== 0) return;
						// Não iniciar drag ao clicar diretamente em uma thumb (não atrapalhar troca de imagem)
						if (e.target && e.target.closest && e.target.closest('li')) return;
						// Não iniciar drag ao clicar nos botões (não atrapalhar o click das setas)
						if (e.target && e.target.closest && e.target.closest('.Gstore-thumbs-nav-btn')) return;
						dragging = true;
						dragStartY = e.clientY;
						try {
							thumbsTarget.setPointerCapture(e.pointerId);
						} catch (_) {
							// noop
						}
					});
					thumbsTarget.addEventListener('pointerup', (e) => {
						if (!dragging) return;
						dragging = false;
						const delta = e.clientY - dragStartY;
						if (Math.abs(delta) < 28) return;
						moveWindow(delta > 0 ? -1 : 1);
					});
					thumbsTarget.addEventListener('pointercancel', () => {
						dragging = false;
					});

					window.addEventListener('resize', () => {
						clearTimeout(thumbsResizeTimeout);
						thumbsResizeTimeout = setTimeout(() => {
							updateButtons();
						}, 100);
					});
				}

				updateButtons();
				return;
			}

			const items = thumbsList.querySelectorAll('li');
			const shouldEnable = items.length > 4;
			const existingWrapper = thumbsList.closest('.Gstore-thumbs-carousel');

			// Se não precisa de carrossel, desfaz (caso já tenha sido aplicado)
			if (!shouldEnable) {
				if (existingWrapper && existingWrapper.parentNode) {
					existingWrapper.parentNode.insertBefore(thumbsList, existingWrapper);
					existingWrapper.remove();
				}
				return;
			}

			// Envolve a lista com botões (somente uma vez)
			if (!existingWrapper) {
				const wrapper = document.createElement('div');
				wrapper.className = 'Gstore-thumbs-carousel';
				wrapper.setAttribute('data-gstore-thumbs-carousel', 'true');

				const prevBtn = document.createElement('button');
				prevBtn.type = 'button';
				prevBtn.className = 'Gstore-thumbs-carousel__btn Gstore-thumbs-carousel__btn--prev';
				prevBtn.setAttribute('aria-label', 'Miniaturas anteriores');
				prevBtn.textContent = '‹';

				const nextBtn = document.createElement('button');
				nextBtn.type = 'button';
				nextBtn.className = 'Gstore-thumbs-carousel__btn Gstore-thumbs-carousel__btn--next';
				nextBtn.setAttribute('aria-label', 'Próximas miniaturas');
				nextBtn.textContent = '›';

				const parent = thumbsList.parentNode;
				if (!parent) {
					return;
				}

				parent.insertBefore(wrapper, thumbsList);
				wrapper.appendChild(prevBtn);
				wrapper.appendChild(thumbsList);
				wrapper.appendChild(nextBtn);
			}

			const wrapper = thumbsList.closest('.Gstore-thumbs-carousel');
			if (!wrapper) {
				return;
			}

			const prevBtn = wrapper.querySelector('.Gstore-thumbs-carousel__btn--prev');
			const nextBtn = wrapper.querySelector('.Gstore-thumbs-carousel__btn--next');
			if (!prevBtn || !nextBtn) {
				return;
			}

			const getScrollStep = () => {
				const firstItem = thumbsList.querySelector('li');
				if (!firstItem) return 0;

				const itemWidth = firstItem.getBoundingClientRect().width || firstItem.offsetWidth || 0;
				const styles = window.getComputedStyle(thumbsList);
				const gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;

				return Math.max(1, Math.round(itemWidth + gap));
			};

			const updateButtons = () => {
				const maxScrollLeft = thumbsList.scrollWidth - thumbsList.clientWidth;
				const current = thumbsList.scrollLeft;

				const atStart = current <= 1;
				const atEnd = current >= maxScrollLeft - 1;

				prevBtn.disabled = atStart;
				nextBtn.disabled = atEnd;
			};

			const scrollByStep = (direction) => {
				const step = getScrollStep();
				if (!step) return;

				thumbsList.scrollBy({
					left: direction * step,
					behavior: 'smooth',
				});
			};

			// Inicializar listeners uma única vez por wrapper
			if (!wrapper.dataset.gstoreThumbsCarouselInit) {
				wrapper.dataset.gstoreThumbsCarouselInit = 'true';

				prevBtn.addEventListener('click', () => scrollByStep(-1));
				nextBtn.addEventListener('click', () => scrollByStep(1));

				thumbsList.addEventListener(
					'scroll',
					() => {
						window.requestAnimationFrame(updateButtons);
					},
					{ passive: true }
				);

				window.addEventListener('resize', () => {
					clearTimeout(thumbsResizeTimeout);
					thumbsResizeTimeout = setTimeout(() => {
						updateButtons();
					}, 100);
				});
			}

			updateButtons();
		};

		const refreshFlexSliderLayout = () => {
			if (typeof jQuery === 'undefined' || !jQuery.fn.flexslider) {
				return;
			}

			const $gallery = jQuery(gallery);
			const flexsliderInstance = $gallery.data('flexslider');
			if (!flexsliderInstance) {
				return;
			}

			// Não reinicializa o plugin (isso quebra o zoom e o estado do slider).
			// Apenas pede um resize/refresh quando disponível.
			try {
				if (typeof flexsliderInstance.resize === 'function') {
					flexsliderInstance.resize();
				} else if (typeof flexsliderInstance.doMath === 'function' && typeof flexsliderInstance.update === 'function') {
					flexsliderInstance.doMath();
					flexsliderInstance.update();
				}
			} catch (_) {
				// noop
			}
		};

		const resetZoomForActiveSlide = () => {
			// Só aplica o zoom nativo se o Woo marcou a galeria como "with zoom"
			if (!gallery.classList.contains('woocommerce-product-gallery--with-zoom')) {
				return;
			}

			if (typeof jQuery === 'undefined' || typeof jQuery.fn.zoom !== 'function') {
				return;
			}

			const $gallery = jQuery(gallery);

			const run = () => {
				// Destruir qualquer zoom anterior
				try {
					$gallery.find('.woocommerce-product-gallery__image').trigger('zoom.destroy');
				} catch (_) {
					// noop
				}

				$gallery.find('.zoomImg, .zoomContainer').remove();

				// Encontrar slide ativo (FlexSlider adiciona .flex-active-slide)
				const $active = $gallery.find('.flex-viewport .flex-active-slide').first();

				const $target = ($active && $active.length ? $active : $gallery.find('.woocommerce-product-gallery__image').first()).first();
				if (!$target.length) return;

				// Inicializar zoom só no slide ativo com opções otimizadas
				const zoomOptions = {
					touch: false,
					duration: 0,
					...(window?.wc_single_product_params?.zoom_options || {})
				};

				try {
					$target.trigger('zoom.destroy');
					$target.zoom(zoomOptions);
				} catch (_) {
					// noop
				}
			};

			// Garante que a imagem atual está carregada antes de iniciar (evita offsets errados)
			const $img = $gallery.find('.flex-viewport .flex-active-slide img, .woocommerce-product-gallery__image img').first();
			const imgEl = $img && $img.length ? $img[0] : null;
			if (imgEl && (!imgEl.complete || imgEl.naturalWidth === 0)) {
				$img.one('load', () => setTimeout(run, 0));
				return;
			}

			setTimeout(run, 0);
		};

		const bindZoomResetOnThumbClicks = () => {
			const root = gallery.closest('.Gstore-single-product__gallery') || gallery;
			if (root.dataset.gstoreZoomResetInit === 'true') {
				return;
			}
			root.dataset.gstoreZoomResetInit = 'true';

			// Não permite o comportamento padrão do Woo/FlexSlider; só reseta o zoom após a troca.
			root.addEventListener(
				'click',
				(e) => {
					const thumb = e.target?.closest?.('.flex-control-thumbs a, .flex-control-thumbs img, .flex-control-nav a, .flex-control-nav img');
					if (!thumb) return;
					setTimeout(resetZoomForActiveSlide, 60);
				},
				true
			);
		};

		// Aguardar inicialização do FlexSlider pelo WooCommerce
		const waitForFlexSlider = () => {
			if (typeof jQuery !== 'undefined' && jQuery.fn.flexslider) {
				const $gallery = jQuery(gallery);
				
				// Aguardar até que o FlexSlider seja inicializado
				const checkInit = setInterval(() => {
					const flexsliderInstance = $gallery.data('flexslider');
					if (flexsliderInstance) {
						clearInterval(checkInit);

						// Garantir thumb mesmo quando há apenas 1 imagem
						ensureSingleThumb();
						removeSingleThumbIfDuplicated();

						// Ajustes leves de layout + zoom (sem reinicializar o slider)
						refreshFlexSliderLayout();
						bindZoomResetOnThumbClicks();
						resetZoomForActiveSlide();

						// Ativar carrossel de thumbnails quando necessário
						setTimeout(() => {
							ensureSingleThumb();
							removeSingleThumbIfDuplicated();
							setupThumbsCarousel();
							resetZoomForActiveSlide();
						}, 120);
						
						// Observar mudanças no FlexSlider
						$gallery.off('flexslider:after.gstoreZoomFix').on('flexslider:after.gstoreZoomFix', () => {
							setTimeout(() => {
								refreshFlexSliderLayout();
								setupThumbsCarousel();
								resetZoomForActiveSlide();
							}, 0);
						});
					}
				}, 100);

				// Timeout após 5 segundos
				setTimeout(() => {
					clearInterval(checkInit);

					// Se o FlexSlider não inicializou (comum em produtos simples com 1 imagem),
					// forçamos a inicialização das funcionalidades de suporte.
					const flexsliderInstance = $gallery.data('flexslider');
					if (!flexsliderInstance) {
						ensureSingleThumb();
						removeSingleThumbIfDuplicated();
						setupThumbsCarousel();
						resetZoomForActiveSlide();
					}
				}, 5000);
			}
		};

		// Iniciar após um pequeno delay para garantir que o WooCommerce inicializou
		setTimeout(waitForFlexSlider, 200);
		// Fallback extra: em alguns casos o FlexSlider não inicializa com 1 imagem
		setTimeout(() => {
			ensureSingleThumb();
			removeSingleThumbIfDuplicated();
			setupThumbsCarousel();
		}, 450);

		// Recalcular ao redimensionar a janela
		let resizeTimeout;
		window.addEventListener('resize', () => {
			clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(() => {
				refreshFlexSliderLayout();
				setupThumbsCarousel();
				resetZoomForActiveSlide();
			}, 100);
		});
	}
});

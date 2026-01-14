document.addEventListener('DOMContentLoaded', () => {
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

		const selects = Array.from(form.querySelectorAll('select'));
		const preview = document.querySelector('[data-gstore-variation-preview]');
		const warning = document.querySelector('[data-gstore-variation-warning]');
		const buyNowButton = form.querySelector('.Gstore-single-product__buy-now');
		const addToCartButton = form.querySelector('.single_add_to_cart_button');
		const priceEl = document.querySelector('[data-gstore-price]');
		const initialPriceHtml = priceEl ? priceEl.innerHTML : '';
		const dbgRunId = 'warn1';

		// #region agent log
		fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:dbgRunId,hypothesisId:'W1',location:'assets/js/single-product.js:initVariationsState',message:'initVariationsState init (warning visibility)',data:{selectCount:selects.length,selectNames:selects.slice(0,6).map(s=>s.name||null),warningFound:!!warning,warningId:warning?.id||null,warningHidden:warning?warning.hidden:null,warningCountAll:document.querySelectorAll('[data-gstore-variation-warning]').length,warningCountInForm:form.querySelectorAll('[data-gstore-variation-warning]').length,atcContainerClass:form.querySelector('.woocommerce-variation-add-to-cart')?.className||null,addBtnFound:!!addToCartButton,addBtnDisabledProp:addToCartButton?addToCartButton.disabled:null,addBtnHasDisabledClass:addToCartButton?addToCartButton.classList.contains('disabled'):null,buyNowFound:!!buyNowButton,buyNowDisabled:buyNowButton?buyNowButton.disabled:null},timestamp:Date.now()})}).catch(()=>{});
		// #endregion

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

		let dbgLastOk = null;
		let dbgCount = 0;
		const update = () => {
			if (preview) {
				preview.textContent = getPreviewText();
			}

			const allSelected = selects.length > 0 && selects.every((s) => String(s.value || '').trim().length > 0);
			const canAddProp = addToCartButton ? !addToCartButton.disabled : allSelected;
			const canAddClass = addToCartButton ? !addToCartButton.classList.contains('disabled') : allSelected;
			const atcEnabled = !!form.querySelector('.woocommerce-variation-add-to-cart.woocommerce-variation-add-to-cart-enabled');
			const canAdd = canAddProp && canAddClass;
			const ok = allSelected && canAdd;

			if (buyNowButton) {
				buyNowButton.disabled = !ok;
			}
			if (warning) {
				warning.hidden = ok;
			}

			// #region agent log
			if (dbgCount < 8 && (dbgLastOk === null || ok !== dbgLastOk || (warning && warning.hidden !== ok))) {
				dbgCount += 1;
				dbgLastOk = ok;
				const warningCs = warning ? window.getComputedStyle(warning) : null;
				const qtyRow = form.querySelector('.qty-row');
				const qtyRowCs = qtyRow ? window.getComputedStyle(qtyRow) : null;
				const atc = form.querySelector('.woocommerce-variation-add-to-cart');
				const atcCs = atc ? window.getComputedStyle(atc) : null;
				const singleWrap = form.querySelector('.single_variation_wrap');
				const singleWrapCs = singleWrap ? window.getComputedStyle(singleWrap) : null;
				const singleWrapRect = singleWrap ? singleWrap.getBoundingClientRect() : null;
				const wooVariation = form.querySelector('.woocommerce-variation');
				const wooVariationCs = wooVariation ? window.getComputedStyle(wooVariation) : null;
				const wooVariationRect = wooVariation ? wooVariation.getBoundingClientRect() : null;
				fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:dbgRunId,hypothesisId:'W2',location:'assets/js/single-product.js:update',message:'variation warning update',data:{allSelected,selectValues:selects.slice(0,6).map(s=>({name:s.name||null,value:String(s.value||'')})),canAddProp,canAddClass,atcEnabled,addBtnDisabledProp:addToCartButton?addToCartButton.disabled:null,addBtnHasDisabledClass:addToCartButton?addToCartButton.classList.contains('disabled'):null,addBtnClass:addToCartButton?.className||null,buyNowDisabled:buyNowButton?buyNowButton.disabled:null,warningHidden:warning?warning.hidden:null,warningDisplay:warningCs?warningCs.display:null,warningMarginTop:warningCs?warningCs.marginTop:null,qtyRowFound:!!qtyRow,qtyRowMarginTop:qtyRowCs?qtyRowCs.marginTop:null,atcFound:!!atc,atcMarginTop:atcCs?atcCs.marginTop:null,singleWrapFound:!!singleWrap,singleWrapDisplay:singleWrapCs?singleWrapCs.display:null,singleWrapGap:singleWrapCs?(singleWrapCs.rowGap||singleWrapCs.gap):null,singleWrapMarginTop:singleWrapCs?singleWrapCs.marginTop:null,singleWrapHeight:singleWrapRect?singleWrapRect.height:null,wooVariationFound:!!wooVariation,wooVariationDisplay:wooVariationCs?wooVariationCs.display:null,wooVariationMarginTop:wooVariationCs?wooVariationCs.marginTop:null,wooVariationMarginBottom:wooVariationCs?wooVariationCs.marginBottom:null,wooVariationHeight:wooVariationRect?wooVariationRect.height:null},timestamp:Date.now()})}).catch(()=>{});
			}
			// #endregion
		};

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
			$form.on('found_variation', (event, variation) => {
				// #region agent log
				fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:dbgRunId,hypothesisId:'W3',location:'assets/js/single-product.js:found_variation',message:'found_variation fired',data:{atcContainerClass:form.querySelector('.woocommerce-variation-add-to-cart')?.className||null,addBtnClass:addToCartButton?.className||null,addBtnDisabledProp:addToCartButton?addToCartButton.disabled:null,addBtnHasDisabledClass:addToCartButton?addToCartButton.classList.contains('disabled'):null},timestamp:Date.now()})}).catch(()=>{});
				// #endregion
				if (priceEl && variation && typeof variation.price_html === 'string' && variation.price_html.trim().length) {
					priceEl.innerHTML = variation.price_html;
				}
				setTimeout(update, 0);
			});
			$form.on('reset_data', () => {
				// #region agent log
				fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:dbgRunId,hypothesisId:'W3',location:'assets/js/single-product.js:reset_data',message:'reset_data fired',data:{atcContainerClass:form.querySelector('.woocommerce-variation-add-to-cart')?.className||null},timestamp:Date.now()})}).catch(()=>{});
				// #endregion
				if (priceEl && initialPriceHtml) {
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

			$form.on('found_variation', (event, variation) => {
				// Verificar se a variação selecionada está em estoque
				const variationInStock = variation && variation.is_in_stock === true;
				setOutOfStockState(!variationInStock);
			});

			$form.on('reset_data', () => {
				// Ao resetar, voltar ao estado inicial
				setOutOfStockState(initiallyOos);
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

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

		const getPanelId = (tab) => `gstore-tab-${tab}`;

		const activate = (tab) => {
			buttons.forEach((btn) => {
				const isActive = btn.dataset.gstoreTab === tab;
				btn.classList.toggle('is-active', isActive);
				btn.setAttribute('aria-selected', String(isActive));
			});

			panels.forEach((panel) => {
				const isActive = panel.id === getPanelId(tab);
				panel.classList.toggle('is-active', isActive);
				panel.hidden = !isActive;
			});
		};

		// Ativa o default baseado em markup (fallback: 1º)
		const defaultBtn = buttons.find((b) => b.classList.contains('is-active')) || buttons[0];
		if (defaultBtn?.dataset?.gstoreTab) {
			activate(defaultBtn.dataset.gstoreTab);
		}

		buttons.forEach((btn) => {
			btn.addEventListener('click', () => {
				const tab = btn.dataset.gstoreTab;
				if (!tab) return;
				activate(tab);
			});
		});
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
		const storageKey = 'gstore_favorites';

		const readFavorites = () => {
			try {
				const raw = localStorage.getItem(storageKey);
				return raw ? JSON.parse(raw) : [];
			} catch (e) {
				return [];
			}
		};

		const writeFavorites = (favorites) => {
			try {
				localStorage.setItem(storageKey, JSON.stringify(favorites));
			} catch (e) {
				// Ignora
			}
		};

		const setUI = (isActive) => {
			button.classList.toggle('is-favorited', isActive);
			button.setAttribute('aria-pressed', String(isActive));
			if (icon) {
				icon.classList.toggle('fa-solid', isActive);
				icon.classList.toggle('fa-regular', !isActive);
			}
		};

		const isFavorited = () => {
			const favorites = readFavorites();
			return Array.isArray(favorites) && favorites.includes(productId);
		};

		// Estado inicial
		setUI(isFavorited());

		button.addEventListener('click', (e) => {
			e.preventDefault();
			const favorites = readFavorites();
			const current = Array.isArray(favorites) ? favorites : [];
			const active = current.includes(productId);
			const next = active ? current.filter((id) => id !== productId) : [...new Set([...current, productId])];
			writeFavorites(next);
			setUI(!active);
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

		// #region agent log
		fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H2',location:'assets/js/single-product.js:initVariationsState',message:'initVariationsState: form detected',data:{formClass:form.className,hasQtyInput:!!form.querySelector('input.qty'),qtyType:form.querySelector('input.qty')?.type || null,hasQtyWrapper:!!form.querySelector('.Gstore-quantity-controls')},timestamp:Date.now()})}).catch(()=>{});
		// #endregion

		const selects = Array.from(form.querySelectorAll('select'));
		const preview = document.querySelector('[data-gstore-variation-preview]');
		const warning = document.querySelector('[data-gstore-variation-warning]');
		const buyNowButton = form.querySelector('.Gstore-single-product__buy-now');
		const addToCartButton = form.querySelector('.single_add_to_cart_button');
		const priceEl = document.querySelector('[data-gstore-price]');
		const initialPriceHtml = priceEl ? priceEl.innerHTML : '';

		// Observa mudanças de estado do container add-to-cart (enabled/disabled) para debug.
		const ensureAtcObserver = () => {
			const container = form.querySelector('.woocommerce-variation-add-to-cart');
			if (!container) return;
			if (container.dataset.gstoreDbgObserved === 'true') return;
			container.dataset.gstoreDbgObserved = 'true';

			const logState = (reason) => {
				const state = container.classList.contains('woocommerce-variation-add-to-cart-enabled')
					? 'enabled'
					: container.classList.contains('woocommerce-variation-add-to-cart-disabled')
						? 'disabled'
						: 'unknown';

				const last = container.dataset.gstoreDbgAtcState || '';
				if (last === state && reason !== 'first') return;
				container.dataset.gstoreDbgAtcState = state;

				const qtyInput = container.querySelector('input.qty');
				const wrapper = container.querySelector('.Gstore-quantity-controls');
				const plusBtn = container.querySelector('.Gstore-quantity-button--plus');
				const minusBtn = container.querySelector('.Gstore-quantity-button--minus');
				const addBtn = container.querySelector('.single_add_to_cart_button');
				const buyNowBtn = container.querySelector('.Gstore-single-product__buy-now');
				const csQty = qtyInput ? window.getComputedStyle(qtyInput) : null;
				const csPlus = plusBtn ? window.getComputedStyle(plusBtn) : null;
				const csMinus = minusBtn ? window.getComputedStyle(minusBtn) : null;
				const csAdd = addBtn ? window.getComputedStyle(addBtn) : null;
				const csBuyNow = buyNowBtn ? window.getComputedStyle(buyNowBtn) : null;
				const rectQty = qtyInput ? qtyInput.getBoundingClientRect() : null;
				const rectWrap = wrapper ? wrapper.getBoundingClientRect() : null;
				const rectPlus = plusBtn ? plusBtn.getBoundingClientRect() : null;
				const rectMinus = minusBtn ? minusBtn.getBoundingClientRect() : null;
				const rectAdd = addBtn ? addBtn.getBoundingClientRect() : null;
				const rectBuyNow = buyNowBtn ? buyNowBtn.getBoundingClientRect() : null;

				// #region agent log
				fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H6',location:'assets/js/single-product.js:atc_state',message:'variation add-to-cart state',data:{reason,state,containerClass:container.className,qtyExists:!!qtyInput,qtyType:qtyInput?.type||null,qtyValue:qtyInput?.value||null,qtyDisplay:csQty?.display||null,qtyVisibility:csQty?.visibility||null,qtyOpacity:csQty?.opacity||null,qtyFontSize:csQty?.fontSize||null,qtyWidth:rectQty?.width||null,wrapperExists:!!wrapper,wrapperWidth:rectWrap?.width||null,wrapperLeft:rectWrap?.left||null,wrapperRight:rectWrap?.right||null,minusExists:!!minusBtn,minusFontSize:csMinus?.fontSize||null,minusLineHeight:csMinus?.lineHeight||null,minusWidth:rectMinus?.width||null,minusHeight:rectMinus?.height||null,plusExists:!!plusBtn,plusDisplay:csPlus?.display||null,plusVisibility:csPlus?.visibility||null,plusOpacity:csPlus?.opacity||null,plusFontSize:csPlus?.fontSize||null,plusLineHeight:csPlus?.lineHeight||null,plusLeft:rectPlus?.left||null,plusRight:rectPlus?.right||null,plusWidth:rectPlus?.width||null,plusHeight:rectPlus?.height||null,addBtnExists:!!addBtn,addBtnDisplay:csAdd?.display||null,addBtnWidth:rectAdd?.width||null,addBtnHeight:rectAdd?.height||null,addBtnPadding:csAdd?`${csAdd.paddingTop} ${csAdd.paddingRight} ${csAdd.paddingBottom} ${csAdd.paddingLeft}`:null,buyNowExists:!!buyNowBtn,buyNowDisplay:csBuyNow?.display||null,buyNowHeightProp:csBuyNow?.height||null,buyNowLineHeight:csBuyNow?.lineHeight||null,buyNowPadding:csBuyNow?`${csBuyNow.paddingTop} ${csBuyNow.paddingRight} ${csBuyNow.paddingBottom} ${csBuyNow.paddingLeft}`:null,buyNowWidth:rectBuyNow?.width||null,buyNowHeight:rectBuyNow?.height||null},timestamp:Date.now()})}).catch(()=>{});
				// #endregion
			};

			const observer = new MutationObserver(() => logState('class_change'));
			observer.observe(container, { attributes: true, attributeFilter: ['class'] });
			logState('first');
		};

		ensureAtcObserver();
		const formObserver = new MutationObserver(() => ensureAtcObserver());
		formObserver.observe(form, { childList: true, subtree: true });

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
			const canAdd = addToCartButton ? !addToCartButton.disabled : allSelected;
			const ok = allSelected && canAdd;

			if (buyNowButton) {
				buyNowButton.disabled = !ok;
			}
			if (warning) {
				warning.hidden = ok;
			}
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
				fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H2',location:'assets/js/single-product.js:found_variation',message:'found_variation fired',data:{hasQtyInput:!!form.querySelector('input.qty'),qtyType:form.querySelector('input.qty')?.type || null,hasQtyWrapper:!!form.querySelector('.Gstore-quantity-controls'),wrapperHasInput:!!form.querySelector('.Gstore-quantity-controls input.qty'),addBtnDisabled:!!form.querySelector('.single_add_to_cart_button')?.disabled},timestamp:Date.now()})}).catch(()=>{});
				// #endregion
				// #region agent log
				{
					const qtyInput = form.querySelector('input.qty');
					const cs = qtyInput ? window.getComputedStyle(qtyInput) : null;
					const rect = qtyInput ? qtyInput.getBoundingClientRect() : null;
					fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H3',location:'assets/js/single-product.js:found_variation:qty',message:'found_variation qty computed',data:{qtyExists:!!qtyInput,qtyType:qtyInput?.type||null,qtyValue:qtyInput?.value||null,qtyDisplay:cs?.display||null,qtyVisibility:cs?.visibility||null,qtyOpacity:cs?.opacity||null,qtyColor:cs?.color||null,qtyWidth:rect?.width||null,containerClass:form.querySelector('.woocommerce-variation-add-to-cart')?.className||null},timestamp:Date.now()})}).catch(()=>{});
				}
				// #endregion

				if (priceEl && variation && typeof variation.price_html === 'string' && variation.price_html.trim().length) {
					priceEl.innerHTML = variation.price_html;
				}
				setTimeout(update, 0);
			});
			$form.on('reset_data', () => {
				// #region agent log
				fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H2',location:'assets/js/single-product.js:reset_data',message:'reset_data fired',data:{hasQtyInput:!!form.querySelector('input.qty'),qtyType:form.querySelector('input.qty')?.type || null,hasQtyWrapper:!!form.querySelector('.Gstore-quantity-controls'),wrapperHasInput:!!form.querySelector('.Gstore-quantity-controls input.qty'),addBtnDisabled:!!form.querySelector('.single_add_to_cart_button')?.disabled},timestamp:Date.now()})}).catch(()=>{});
				// #endregion
				// #region agent log
				{
					const qtyInput = form.querySelector('input.qty');
					const cs = qtyInput ? window.getComputedStyle(qtyInput) : null;
					const rect = qtyInput ? qtyInput.getBoundingClientRect() : null;
					fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H3',location:'assets/js/single-product.js:reset_data:qty',message:'reset_data qty computed',data:{qtyExists:!!qtyInput,qtyType:qtyInput?.type||null,qtyValue:qtyInput?.value||null,qtyDisplay:cs?.display||null,qtyVisibility:cs?.visibility||null,qtyOpacity:cs?.opacity||null,qtyColor:cs?.color||null,qtyWidth:rect?.width||null,containerClass:form.querySelector('.woocommerce-variation-add-to-cart')?.className||null},timestamp:Date.now()})}).catch(()=>{});
				}
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
				// #region agent log
				fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H5',location:'assets/js/single-product.js:wrapInQtyRow',message:'wrapInQtyRow: missing qty or addBtn',data:{containerClass:container?.className || null,hasQty:!!qty,hasAddBtn:!!addBtn,qtyInputType:container?.querySelector('input.qty')?.type || null},timestamp:Date.now()})}).catch(()=>{});
				// #endregion
				return;
			}

			// #region agent log
			fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H5',location:'assets/js/single-product.js:wrapInQtyRow',message:'wrapInQtyRow: before wrap',data:{containerClass:container.className,qtyInputType:qty.querySelector('input.qty')?.type || null,qtyInputValue:qty.querySelector('input.qty')?.value || null},timestamp:Date.now()})}).catch(()=>{});
			// #endregion

			const row = document.createElement('div');
			row.className = 'qty-row';
			qty.parentNode.insertBefore(row, qty);
			row.appendChild(qty);
			row.appendChild(addBtn);

			// #region agent log
			fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H5',location:'assets/js/single-product.js:wrapInQtyRow',message:'wrapInQtyRow: after wrap',data:{hasQtyRow:!!container.querySelector('.qty-row'),qtyRowChildren:Array.from(container.querySelector('.qty-row')?.children || []).map(el=>el.className),qtyInputType:container.querySelector('input.qty')?.type || null},timestamp:Date.now()})}).catch(()=>{});
			// #endregion
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
			// #region agent log
			fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H5',location:'assets/js/single-product.js:enhanceQuantityField',message:'enhanceQuantityField: no input.qty found',data:{fieldClass:field.className,fieldHtml:field.outerHTML?.slice(0,200) || null},timestamp:Date.now()})}).catch(()=>{});
			// #endregion
			return;
		}

		// #region agent log
		fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H1',location:'assets/js/single-product.js:enhanceQuantityField',message:'enhanceQuantityField: input found',data:{fieldClass:field.className,inputType:input.type,inputValue:input.value,inputMin:input.min,inputMax:input.max,inputStep:input.step},timestamp:Date.now()})}).catch(()=>{});
		// #endregion

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

		// #region agent log
		fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H3',location:'assets/js/single-product.js:enhanceQuantityField',message:'enhanceQuantityField: wrapper built',data:{wrapperChildren:Array.from(wrapper.children).map(el=>({tag:el.tagName,cls:el.className,type:el.type||null,value:el.value||null})),wrapperHasInput:!!wrapper.querySelector('input.qty'),wrapperInputType:wrapper.querySelector('input.qty')?.type || null},timestamp:Date.now()})}).catch(()=>{});
		// #endregion
		// #region agent log
		{
			const cs = window.getComputedStyle(input);
			const rect = input.getBoundingClientRect();
			const wRect = wrapper.getBoundingClientRect();
			const minusRect = minus.getBoundingClientRect();
			const plusRect = plus.getBoundingClientRect();
			const addToCartContainer = field.closest('.woocommerce-variation-add-to-cart');
			fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H3',location:'assets/js/single-product.js:enhanceQuantityField:styles',message:'qty styles after enhance',data:{inBuybox:!!field.closest('.buybox'),containerClass:addToCartContainer?.className||null,inputType:input.type,inputValue:input.value,inputDisplay:cs.display,inputVisibility:cs.visibility,inputOpacity:cs.opacity,inputColor:cs.color,inputWidth:rect.width,wrapperWidth:wRect.width,minusWidth:minusRect.width,plusWidth:plusRect.width},timestamp:Date.now()})}).catch(()=>{});
		}
		// #endregion
		// #region agent log
		{
			const inBuybox = !!field.closest('.buybox');
			const buybox = field.closest('.buybox');
			const qtyRow = field.closest('.qty-row');
			const addBtn = qtyRow?.querySelector('.single_add_to_cart_button') || buybox?.querySelector('.single_add_to_cart_button');
			const buyNow = buybox?.querySelector('.Gstore-single-product__buy-now');

			const csMinus = window.getComputedStyle(minus);
			const csPlus = window.getComputedStyle(plus);
			const csAdd = addBtn ? window.getComputedStyle(addBtn) : null;
			const csBuyNow = buyNow ? window.getComputedStyle(buyNow) : null;
			const rectRow = qtyRow ? qtyRow.getBoundingClientRect() : null;
			const rectAdd = addBtn ? addBtn.getBoundingClientRect() : null;
			const rectBuyNow = buyNow ? buyNow.getBoundingClientRect() : null;

			fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H6',location:'assets/js/single-product.js:buybox_cta_styles',message:'buybox CTA computed styles',data:{inBuybox,minusText:minus.textContent,plusText:plus.textContent,minusFontSize:csMinus.fontSize,plusFontSize:csPlus.fontSize,minusLineHeight:csMinus.lineHeight,plusLineHeight:csPlus.lineHeight,minusFontWeight:csMinus.fontWeight,plusFontWeight:csPlus.fontWeight,minusWidth:minusRect.width,minusHeight:minusRect.height,plusWidth:plusRect.width,plusHeight:plusRect.height,qtyRowDisplay:qtyRow?window.getComputedStyle(qtyRow).display:null,qtyRowWidth:rectRow?.width||null,addBtnExists:!!addBtn,addBtnWidth:rectAdd?.width||null,addBtnHeight:rectAdd?.height||null,addBtnDisplay:csAdd?.display||null,addBtnFlex:csAdd?.flex||null,addBtnPadding:csAdd?`${csAdd.paddingTop} ${csAdd.paddingRight} ${csAdd.paddingBottom} ${csAdd.paddingLeft}`:null,buyNowExists:!!buyNow,buyNowClass:buyNow?.className||null,buyNowHasBtnOutline:buyNow?.classList?.contains('btn-outline')||false,buyNowInsideBuybox:!!buyNow?.closest('.buybox'),buyNowWidth:rectBuyNow?.width||null,buyNowHeight:rectBuyNow?.height||null,buyNowDisplay:csBuyNow?.display||null,buyNowComputedHeight:csBuyNow?.height||null,buyNowPadding:csBuyNow?`${csBuyNow.paddingTop} ${csBuyNow.paddingRight} ${csBuyNow.paddingBottom} ${csBuyNow.paddingLeft}`:null,buyNowLineHeight:csBuyNow?.lineHeight||null,buyNowFontSize:csBuyNow?.fontSize||null},timestamp:Date.now()})}).catch(()=>{});
		}
		// #endregion

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

			if (!wrapper.dataset.gstoreDbgUpdateLogged) {
				wrapper.dataset.gstoreDbgUpdateLogged = 'true';
				// #region agent log
				fetch('http://127.0.0.1:7242/ingest/2e9bdb26-956d-44fb-8061-6eba8efc208f',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({sessionId:'debug-session',runId:'run1',hypothesisId:'H4',location:'assets/js/single-product.js:updateButtons',message:'updateButtons first run',data:{current,min,max,wrapperDisplay:wrapper.style.display,lastUnitDisplay:lastUnitWarning.style.display,inputType:input.type},timestamp:Date.now()})}).catch(()=>{});
				// #endregion
			}

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
				const firstImageLink = gallery.querySelector('.woocommerce-product-gallery__image a') || gallery.querySelector('a');
				if (firstImageLink) {
					firstImageLink.click();
				}
			});
		}

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

			// No layout do mock, não usa o wrapper com botões (deixa scroll vertical via CSS)
			if (thumbsTarget) {
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

		// Função para configurar o FlexSlider corretamente
		const configureFlexSlider = () => {
			if (typeof jQuery === 'undefined' || !jQuery.fn.flexslider) {
				return;
			}

			const $gallery = jQuery(gallery);
			const flexsliderInstance = $gallery.data('flexslider');

			if (!flexsliderInstance) {
				// Se ainda não foi inicializado, tentar novamente
				setTimeout(configureFlexSlider, 100);
				return;
			}

			// Reconfigurar o FlexSlider para garantir direção horizontal
			const options = flexsliderInstance.vars || {};
			
			// Garantir configuração horizontal
			if (options.direction !== 'horizontal') {
				options.direction = 'horizontal';
			}
			
			// Garantir animação de slide horizontal
			if (options.animation !== 'slide') {
				options.animation = 'slide';
			}

			// Atualizar o FlexSlider
			$gallery.flexslider(options);

			const viewport = gallery.querySelector('.flex-viewport');
			if (viewport) {
				const slides = viewport.querySelector('.flex-slides');
				if (slides) {
					const images = slides.querySelectorAll('.woocommerce-product-gallery__image');
					if (images.length > 0) {
						const viewportWidth = viewport.offsetWidth;
						if (viewportWidth > 0) {
							// Configurar largura do container de slides
							slides.style.width = `${viewportWidth * images.length}px`;
							
							// Configurar cada imagem para ocupar 100% da largura do viewport
							images.forEach((img) => {
								img.style.width = `${viewportWidth}px`;
								img.style.minWidth = `${viewportWidth}px`;
								img.style.maxWidth = `${viewportWidth}px`;
								img.style.flex = `0 0 ${viewportWidth}px`;
							});

							// Garantir que o slide atual seja exibido corretamente
							const currentSlide = flexsliderInstance.currentSlide || 0;
							const translateX = -currentSlide * viewportWidth;
							slides.style.transform = `translateX(${translateX}px)`;
							slides.style.transition = 'transform 0.3s ease';
						}
					}
				}
			}
		};

		// Função para corrigir cliques nas thumbnails e garantir exibição correta
		const fixThumbnailClicks = () => {
			// Função para garantir que a imagem seja exibida corretamente após clique
			const ensureImageDisplay = (slideIndex) => {
				setTimeout(() => {
					const viewport = gallery.querySelector('.flex-viewport');
					if (!viewport) return;
					
					const slides = viewport.querySelector('.flex-slides');
					if (!slides) return;
					
					const images = slides.querySelectorAll('.woocommerce-product-gallery__image');
					if (images.length === 0) return;
					
					const viewportWidth = viewport.offsetWidth;
					if (viewportWidth <= 0) return;
					
					// Garantir que o índice seja válido
					const validIndex = Math.max(0, Math.min(slideIndex, images.length - 1));
					
					// Garantir larguras corretas
					slides.style.width = `${viewportWidth * images.length}px`;
					images.forEach((img) => {
						img.style.width = `${viewportWidth}px`;
						img.style.minWidth = `${viewportWidth}px`;
						img.style.maxWidth = `${viewportWidth}px`;
						img.style.flex = `0 0 ${viewportWidth}px`;
					});
					
					// Mover para o slide correto
					const translateX = -validIndex * viewportWidth;
					slides.style.transform = `translateX(${translateX}px)`;
					slides.style.transition = 'transform 0.3s ease';
					
					// Garantir que a imagem seja visível e carregada
					const targetImage = images[validIndex];
					if (targetImage) {
						targetImage.style.opacity = '1';
						targetImage.style.visibility = 'visible';
						targetImage.style.display = 'block';
						
						const img = targetImage.querySelector('img');
						if (img) {
							img.style.display = 'block';
							img.style.opacity = '1';
							img.style.visibility = 'visible';
							
							// Se a imagem não foi carregada, forçar carregamento
							if (!img.complete || img.naturalHeight === 0) {
								const originalSrc = img.src;
								if (originalSrc) {
									img.src = '';
									img.src = originalSrc;
								}
							}
						}
					}
				}, 100);
			};
			
			// Encontrar todas as thumbnails e adicionar listeners
			const thumbnailsScope = gallery.closest('.Gstore-single-product__gallery') || gallery;
			const thumbnails = thumbnailsScope.querySelectorAll('.flex-control-nav li a, .flex-control-thumbs li a, .flex-control-thumbs li img');
			
			thumbnails.forEach((thumbnail, index) => {
				thumbnail.addEventListener('click', (e) => {
					// Determinar o índice do slide
					let slideIndex = index;
					
					// Tentar obter do atributo data-slide
					const dataSlide = thumbnail.dataset.slide || thumbnail.closest('li')?.dataset.slide;
					if (dataSlide !== undefined) {
						slideIndex = parseInt(dataSlide, 10);
					} else {
						// Tentar obter pela posição na lista
						const parentList = thumbnail.closest('ul, ol');
						if (parentList) {
							const items = Array.from(parentList.querySelectorAll('li'));
							const currentItem = thumbnail.closest('li');
							if (currentItem) {
								slideIndex = items.indexOf(currentItem);
							}
						}
					}
					
					// Garantir exibição da imagem após um pequeno delay
					setTimeout(() => ensureImageDisplay(slideIndex), 50);
				});
			});
			
			// Também monitorar mudanças no FlexSlider para garantir exibição correta
			if (typeof jQuery !== 'undefined' && jQuery.fn.flexslider) {
				const $gallery = jQuery(gallery);
				
				// Escutar eventos do FlexSlider
				$gallery.on('flexslider:after', function(event, slider) {
					setTimeout(() => {
						const viewport = gallery.querySelector('.flex-viewport');
						if (viewport) {
							const slides = viewport.querySelector('.flex-slides');
							const images = slides?.querySelectorAll('.woocommerce-product-gallery__image');
							const viewportWidth = viewport.offsetWidth;
							
							if (slides && images && images.length > 0 && viewportWidth > 0) {
								const currentSlide = slider.currentSlide || 0;
								
								// Garantir que todas as imagens tenham largura correta
								slides.style.width = `${viewportWidth * images.length}px`;
								images.forEach((img) => {
									img.style.width = `${viewportWidth}px`;
									img.style.minWidth = `${viewportWidth}px`;
									img.style.maxWidth = `${viewportWidth}px`;
									img.style.flex = `0 0 ${viewportWidth}px`;
								});
								
								// Garantir posição horizontal correta
								const translateX = -currentSlide * viewportWidth;
								slides.style.transform = `translateX(${translateX}px)`;
								
								// Garantir que a imagem atual seja visível
								const targetImage = images[currentSlide];
								if (targetImage) {
									const img = targetImage.querySelector('img');
									if (img) {
										img.style.display = 'block';
										img.style.opacity = '1';
										img.style.visibility = 'visible';
									}
								}
							}
						}
					}, 10);
				});
			}
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
						
						// Configurar o FlexSlider
						configureFlexSlider();
						
						// Corrigir cliques nas thumbnails
						setTimeout(fixThumbnailClicks, 100);

						// Ativar carrossel de thumbnails quando necessário
						setTimeout(setupThumbsCarousel, 120);
						
						// Observar mudanças no FlexSlider
						$gallery.on('flexslider:after', () => {
							setTimeout(configureFlexSlider, 50);
							setTimeout(setupThumbsCarousel, 60);
						});
					}
				}, 100);

				// Timeout após 5 segundos
				setTimeout(() => {
					clearInterval(checkInit);
				}, 5000);
			}
		};

		// Iniciar após um pequeno delay para garantir que o WooCommerce inicializou
		setTimeout(waitForFlexSlider, 200);

		// Recalcular ao redimensionar a janela
		let resizeTimeout;
		window.addEventListener('resize', () => {
			clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(() => {
				configureFlexSlider();
				setupThumbsCarousel();
			}, 100);
		});
	}
});




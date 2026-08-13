/**
 * Interacoes dos produtos complementares configurados pela GStore.
 */
(function ($) {
	'use strict';

	var config = window.gstoreProductUpsells || {};
	var miniRefreshTimer = null;
	var miniRequest = null;
	var lastMiniFooter = null;

	function parsePrice(value) {
		var parsed = parseFloat(value);
		return Number.isFinite(parsed) ? parsed : 0;
	}

	function formatPrice(value) {
		try {
			return new Intl.NumberFormat('pt-BR', {
				style: 'currency',
				currency: 'BRL',
			}).format(value);
		} catch (error) {
			return 'R$ ' + value.toFixed(2).replace('.', ',');
		}
	}

	function setStatus(scope, message, isError) {
		var container = scope && scope.closest ? scope.closest('[data-gstore-product-upsells]') : null;
		var status = container ? container.querySelector('[data-gstore-upsell-status]') : null;
		if (!status) {
			return;
		}

		status.textContent = message || '';
		status.classList.toggle('is-error', Boolean(isError));
	}

	function post(action, data) {
		var params = new URLSearchParams();
		params.append('action', action);
		params.append('nonce', config.nonce || '');
		Object.keys(data || {}).forEach(function (key) {
			params.append(key, data[key]);
		});

		return window.fetch(config.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: params.toString(),
		}).then(function (response) {
			return response.json().catch(function () {
				return { success: false, data: { message: 'Nao foi possivel atualizar o carrinho.' } };
			});
		});
	}

	function emitCartUpdated(payload, button) {
		var detail = {
			preserveCartData: false,
			fragments: {},
			cartHash: payload && payload.cart_hash ? payload.cart_hash : null,
		};

		if (typeof $ === 'function') {
			$(document.body).trigger('added_to_cart', [detail.fragments, detail.cartHash, $(button)]);
			$(document.body).trigger('wc_fragment_refresh');
		}

		try {
			document.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', {
				bubbles: true,
				detail: detail,
			}));
		} catch (error) {
			// Browsers sem CustomEvent nao precisam de atualizacao adicional aqui.
		}

		document.dispatchEvent(new CustomEvent('gstore:product-upsell-added', {
			bubbles: true,
			detail: detail,
		}));
	}

	function refreshBundle(bundle) {
		if (!bundle) {
			return;
		}

		var total = parsePrice(bundle.getAttribute('data-base-price'));
		var checked = bundle.querySelectorAll('[data-gstore-upsell-checkbox]:checked');
		checked.forEach(function (input) {
			total += parsePrice(input.getAttribute('data-price'));
		});

		var output = bundle.querySelector('[data-gstore-upsell-total]');
		if (output) {
			output.textContent = formatPrice(total);
		}

		var submit = bundle.querySelector('[data-gstore-upsell-bundle-add]');
		if (submit) {
			submit.disabled = checked.length === 0;
		}
	}

	function bindBundles() {
		document.querySelectorAll('[data-gstore-upsell-bundle]').forEach(function (bundle) {
			bundle.setAttribute('data-initial-base-price', bundle.getAttribute('data-base-price') || '0');
			bundle.addEventListener('change', function (event) {
				if (event.target.matches('[data-gstore-upsell-checkbox]')) {
					refreshBundle(bundle);
				}
			});
			refreshBundle(bundle);
		});

		if (typeof $ !== 'function') {
			return;
		}

		$('.variations_form')
			.on('found_variation.gstoreProductUpsells', function (event, variation) {
				var form = event.currentTarget;
				form.querySelectorAll('[data-gstore-upsell-bundle]').forEach(function (bundle) {
					if (variation && typeof variation.display_price !== 'undefined') {
						bundle.setAttribute('data-base-price', String(variation.display_price));
					}
					refreshBundle(bundle);
				});
			})
			.on('reset_data.gstoreProductUpsells', function (event) {
				var form = event.currentTarget;
				form.querySelectorAll('[data-gstore-upsell-bundle]').forEach(function (bundle) {
					bundle.setAttribute('data-base-price', bundle.getAttribute('data-initial-base-price') || '0');
					refreshBundle(bundle);
				});
			});
	}

	function findMiniFooter() {
		return document.querySelector('.wc-block-mini-cart__drawer .wc-block-mini-cart__footer');
	}

	function placeMiniSlot(footer) {
		var slot = footer.querySelector('[data-gstore-mini-upsell-slot]');
		if (!slot) {
			slot = document.createElement('div');
			slot.setAttribute('data-gstore-mini-upsell-slot', '');
			var subtotal = footer.querySelector('.wc-block-mini-cart__totals');
			footer.insertBefore(slot, subtotal || footer.firstChild);
		}
		return slot;
	}

	function refreshMiniModule() {
		var footer = findMiniFooter();
		if (!footer || miniRequest) {
			return;
		}

		lastMiniFooter = footer;
		var slot = placeMiniSlot(footer);
		miniRequest = post('gstore_render_cart_product_upsells', {})
			.then(function (response) {
				if (!response || !response.success) {
					return;
				}
				slot.innerHTML = response.data && response.data.html ? response.data.html : '';
				slot.hidden = slot.innerHTML.trim() === '';
			})
			.catch(function () {
				// O mini-carrinho continua funcional se a recomendacao falhar.
			})
			.finally(function () {
				miniRequest = null;
			});
	}

	function scheduleMiniRefresh() {
		window.clearTimeout(miniRefreshTimer);
		miniRefreshTimer = window.setTimeout(refreshMiniModule, 120);
	}

	function watchMiniDrawer() {
		if (!window.MutationObserver || !document.documentElement) {
			return;
		}

		new MutationObserver(function () {
			var footer = findMiniFooter();
			if (footer && (footer !== lastMiniFooter || !footer.querySelector('[data-gstore-mini-upsell-slot]'))) {
				scheduleMiniRefresh();
			}
		}).observe(document.documentElement, { childList: true, subtree: true });
	}

	function bindCartEvents() {
		window.addEventListener('gstore:mini-cart-loader:ready', scheduleMiniRefresh);
		document.addEventListener('wc-blocks_added_to_cart', scheduleMiniRefresh);
		document.addEventListener('wc-blocks_removed_from_cart', scheduleMiniRefresh);
		document.addEventListener('gstore:product-upsell-added', scheduleMiniRefresh);

		if (typeof $ === 'function') {
			$(document.body).on('added_to_cart.gstoreProductUpsells removed_from_cart.gstoreProductUpsells wc_fragments_refreshed.gstoreProductUpsells', scheduleMiniRefresh);
		}

		watchMiniDrawer();
		scheduleMiniRefresh();
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-gstore-upsell-add]');
		if (!button || button.disabled) {
			return;
		}

		event.preventDefault();
		var originalText = button.textContent;
		button.disabled = true;
		button.setAttribute('aria-busy', 'true');
		button.classList.add('is-loading');
		setStatus(button, 'Adicionando...');

		post('gstore_add_product_upsell', {
			product_id: button.getAttribute('data-product-id') || '',
			source_product_id: button.getAttribute('data-source-product-id') || '',
		}).then(function (response) {
			if (!response || !response.success) {
				throw new Error(response && response.data && response.data.message ? response.data.message : 'Nao foi possivel adicionar este produto.');
			}

			button.textContent = 'Adicionado';
			setStatus(button, response.data && response.data.message ? response.data.message : 'Produto adicionado ao carrinho.');
			emitCartUpdated(response.data || {}, button);
			scheduleMiniRefresh();

			if (config.isCart) {
				window.location.reload();
			}
		}).catch(function (error) {
			button.disabled = false;
			button.textContent = originalText;
			setStatus(button, error && error.message ? error.message : 'Nao foi possivel adicionar este produto.', true);
		}).finally(function () {
			button.removeAttribute('aria-busy');
			button.classList.remove('is-loading');
		});
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			bindBundles();
			bindCartEvents();
		});
	} else {
		bindBundles();
		bindCartEvents();
	}
})(window.jQuery);

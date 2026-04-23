/**
 * Garante que o mini-cart block reaja aos eventos AJAX legados do WooCommerce.
 *
 * Em alguns ambientes com cache/otimização, o bridge nativo do bloco não
 * propaga `added_to_cart` para `wc-blocks_added_to_cart`, deixando o drawer
 * e o badge desatualizados até o próximo refresh.
 */
(function ($) {
	'use strict';

	function dispatchMiniCartEvent(eventName, detail) {
		var payload = detail || { preserveCartData: false };

		try {
			document.dispatchEvent(
				new CustomEvent(eventName, {
					bubbles: true,
					detail: payload
				})
			);
			return;
		} catch (error) {
			if (!document.createEvent) {
				return;
			}
		}

		var legacyEvent = document.createEvent('CustomEvent');
		legacyEvent.initCustomEvent(eventName, true, false, payload);
		document.dispatchEvent(legacyEvent);
	}

	function bindWooBridge() {
		if (typeof $ !== 'function' || !document.body) {
			return;
		}

		var $body = $(document.body);

		if (!$body.length || $body.data('gstoreMiniCartBridgeBound')) {
			return;
		}

		$body.data('gstoreMiniCartBridgeBound', true);

		$body.on('added_to_cart.gstoreMiniCartBridge', function (event, fragments, cartHash) {
			dispatchMiniCartEvent('wc-blocks_added_to_cart', {
				preserveCartData: false,
				fragments: fragments || null,
				cartHash: cartHash || null
			});
		});

		$body.on('removed_from_cart.gstoreMiniCartBridge', function (event, fragments, cartHash) {
			dispatchMiniCartEvent('wc-blocks_removed_from_cart', {
				preserveCartData: false,
				fragments: fragments || null,
				cartHash: cartHash || null
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bindWooBridge);
	} else {
		bindWooBridge();
	}
})(window.jQuery);

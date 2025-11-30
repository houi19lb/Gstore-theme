<?php
/**
 * Filtra os gateways de pagamento para deixar apenas a Blu disponível.
 *
 * @param array $available_gateways Gateways disponíveis.
 * @return array
 */
function gstore_blu_only_gateway( $available_gateways ) {
	if ( is_admin() ) {
		return $available_gateways;
	}



	if ( isset( $available_gateways['blu_checkout'] ) ) {
		return array( 'blu_checkout' => $available_gateways['blu_checkout'] );
	}

	return $available_gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'gstore_blu_only_gateway' );

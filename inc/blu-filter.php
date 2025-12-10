<?php
/**
 * Filtra os gateways de pagamento para deixar apenas a Blu disponível.
 * Permite tanto o gateway Blu Checkout quanto o Pix Blu.
 *
 * @param array $available_gateways Gateways disponíveis.
 * @return array
 */
function gstore_blu_only_gateway( $available_gateways ) {
	if ( is_admin() ) {
		return $available_gateways;
	}

	// Retorna apenas os gateways Blu (Checkout e Pix)
	$blu_gateways = array();
	
	if ( isset( $available_gateways['blu_checkout'] ) ) {
		$blu_gateways['blu_checkout'] = $available_gateways['blu_checkout'];
	}
	
	if ( isset( $available_gateways['blu_pix'] ) ) {
		$blu_gateways['blu_pix'] = $available_gateways['blu_pix'];
	}

	// Se encontrou algum gateway Blu, retorna apenas eles
	if ( ! empty( $blu_gateways ) ) {
		return $blu_gateways;
	}

	return $available_gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'gstore_blu_only_gateway' );

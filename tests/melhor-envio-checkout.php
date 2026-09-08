<?php
/** Regression test for cart/checkout rate persistence, without WordPress or a database. */
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
$start = strpos( $source, "if ( ! function_exists( 'gstore_get_cart_item_shipping_label' ) )" );
$end = strpos( $source, "if ( ! function_exists( 'gstore_restore_cart_item_shipping_mode' ) )", $start );
if ( false === $start || false === $end ) {
	throw new RuntimeException( 'Shipping callbacks not found.' );
}
function __( $text, $domain ) { return $text; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return sanitize_text_field( $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_kses_post( $value ) { return $value; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wc_price( $value ) { return 'R$ ' . number_format( $value, 2, ',', '.' ); }
eval( substr( $source, $start, $end - $start ) );
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	echo 'PASS ' . $message . PHP_EOL;
}
$rates = array(
	array( 'rate_id' => 'gstore_custom_shipping:service:example:land', 'mode' => 'land', 'label' => 'Transportadora', 'cost' => 50 ),
	array( 'rate_id' => 'gstore_custom_shipping:melhor_envio:1', 'mode' => 'melhor_envio', 'label' => 'Correios PAC', 'cost' => 29.9,
		'provider' => 'melhor_envio', 'package_key' => 'package-helmet', 'applicable_cart_item_keys' => array( 'helmet-a', 'helmet-b' ),
		'delivery_time_min' => 4, 'delivery_time_max' => 6, 'rate_kind' => 'melhor_envio', 'pricing_type' => 'package' ),
	array( 'rate_id' => 'gstore_custom_shipping:melhor_envio:2', 'mode' => 'melhor_envio', 'label' => 'Correios SEDEX', 'cost' => 45,
		'provider' => 'melhor_envio', 'package_key' => 'package-helmet', 'applicable_cart_item_keys' => array( 'helmet-a', 'helmet-b' ),
		'delivery_time_min' => 2, 'delivery_time_max' => 3 ),
);
$normalized = gstore_normalize_cart_rates( $rates );
check( count( $normalized ) === 3, 'manual, PAC and SEDEX survive checkout normalization' );
check( 'melhor_envio' === gstore_normalize_shipping_mode( 'melhor-envio' ), 'provider alias is recognized' );
check( 'Melhor Envio' === gstore_get_cart_item_shipping_label( 'melhor_envio' ), 'provider has its own label' );
check( $normalized[1]['package_key'] === 'package-helmet' && $normalized[1]['applicable_cart_item_keys'] === array( 'helmet-a', 'helmet-b' ), 'package membership is preserved' );
check( $normalized[1]['delivery_time_min'] === 4 && $normalized[1]['delivery_time_max'] === 6, 'delivery window survives' );
check( $normalized[1]['provider'] === 'melhor_envio' && $normalized[1]['pricing_type'] === 'package', 'provider and pricing metadata survive' );
check( gstore_normalize_cart_rates( $normalized ) === $normalized, 'repeated session normalization is stable' );
check( $normalized[0]['cost'] === 50.0 && $normalized[2]['cost'] === 45.0, 'separate service prices remain intact' );
check( array() === gstore_normalize_cart_rates( array( array( 'mode' => 'unknown' ) ) ), 'unknown modes remain rejected' );
foreach ( array( 'land', 'air', 'pickup', 'other' ) as $mode ) {
	check( $mode === gstore_normalize_shipping_mode( $mode ), $mode . ' remains supported' );
}

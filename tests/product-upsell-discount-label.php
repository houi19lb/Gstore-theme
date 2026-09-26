<?php
/** Regression for fixed upsell discount labels using WooCommerce price markup. */
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
function __( $text, $domain ) { return $text; }
function wc_price( $value ) {
	return '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol" translate="no">R$</span>'
		. number_format( $value, 2, ',', '.' ) . '</bdi></span>';
}
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function number_format_i18n( $value, $decimals ) { return number_format( $value, $decimals, '.', ',' ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function wp_kses_post( $value ) { return $value; }
function esc_html_e( $value, $domain ) { echo esc_html( $value ); }

require dirname( __DIR__ ) . '/inc/gstore-product-upsells.php';

$fixed_label = gstore_get_product_upsell_discount_label( array( 'type' => 'fixed', 'value' => 50.0 ) );
if ( 'R$50,00 de desconto ao comprar junto' !== $fixed_label ) {
	throw new RuntimeException( 'Fixed discount label contains markup or wrong amount: ' . $fixed_label );
}

$percent_label = gstore_get_product_upsell_discount_label( array( 'type' => 'percent', 'value' => 12.5 ) );
if ( '12.50% de desconto ao comprar junto' !== $percent_label ) {
	throw new RuntimeException( 'Percent discount label changed: ' . $percent_label );
}

ob_start();
gstore_render_product_upsell_card(
	array(
		'id'                => 22,
		'source_product_id' => 11,
		'name'              => 'Produto complementar',
		'image_html'        => '<img alt="Produto complementar">',
		'price_html'        => wc_price( 505.44 ),
		'discount_label'    => $fixed_label,
	),
	'single'
);
$html = ob_get_clean();
if ( ! preg_match( '/<p class="Gstore-product-upsells__discount-note">(.*?)<\/p>/', $html, $matches ) ) {
	throw new RuntimeException( 'Discount note not rendered.' );
}
if ( 'R$50,00 de desconto ao comprar junto' !== $matches[1] ) {
	throw new RuntimeException( 'Fixed discount note leaked price markup: ' . $matches[1] );
}
if ( ! str_contains( $html, '<span class="woocommerce-Price-amount amount">' ) ) {
	throw new RuntimeException( 'Product price markup was unexpectedly removed.' );
}

echo 'PASS fixed and percent discount labels, with price markup preserved' . PHP_EOL;

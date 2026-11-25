<?php
/**
 * Verify the storefront option against the theme preview and block renderer.
 * Run: php tests/storefront-installments.php
 */
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

class WC_Product {
	public function get_id() { return 1; }
	public function get_price() { return 210; }
	public function get_regular_price() { return 210; }
	public function is_on_sale() { return false; }
}

$options = array();
$quote_calls = 0;
$product = new WC_Product();

function get_option( $key, $default ) { return $GLOBALS['options'][ $key ] ?? $default; }
function gstore_product_hides_price() { return false; }
function gstore_resolve_installment_product_id() { return 1; }
function is_wp_error() { return false; }
function absint( $value ) { return abs( (int) $value ); }
function wc_price( $price ) { return 'R$ ' . number_format( $price, 2, ',', '.' ); }
function gstore_blu_pix_get_discounted_price() { return 199; }
function gstore_blu_get_product_installment_quotes_data() {
	++$GLOBALS['quote_calls'];
	return array( 'quotes' => array( 21 => array( 'installments' => 21, 'per_installment' => 'R$ 10,00' ) ) );
}
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
foreach ( array(
	'gstore_storefront_shows_installment_amounts',
	'gstore_get_preferred_installment_quote',
	'gstore_get_product_installment_preview_data',
	'gstore_add_payment_info_to_price',
) as $callback ) {
	$start = strpos( $source, 'function ' . $callback . '(' );
	check( false !== $start, 'Missing callback: ' . $callback );
	$end = strpos( $source, "\n}", $start );
	eval( substr( $source, $start, $end + 2 - $start ) );
}

$block = '<div><div class="wc-block-components-product-price"><span class="wc-block-components-product-price__value"><span class="woocommerce-Price-amount">R$ 210,00</span></span></div></div>';

check( 'R$ 10,00' === gstore_get_product_installment_preview_data( $product )['per_installment_html'], 'An existing store must still show its quotes by default.' );
check( str_contains( gstore_add_payment_info_to_price( $block, array(), (object) array() ), 'Gstore-installment-text' ), 'The block must still display the installment row when enabled.' );

$options['gstore_show_storefront_installment_amounts'] = 'no';
$quote_calls = 0;
$preview = gstore_get_product_installment_preview_data( $product, 21, 1, 'single', true );
check( '' === $preview['per_installment_html'] && array() === $preview['quotes'], 'The disabled option must remove both the amount and the quote table data.' );
$rendered = gstore_add_payment_info_to_price( $block, array(), (object) array() );
check( ! str_contains( $rendered, 'Gstore-installment-text' ) && ! str_contains( $rendered, '21x' ), 'The block must not render an installment row or its fallback.' );
check( str_contains( $rendered, 'Gstore-payment-label' ) && str_contains( $rendered, '<ins>R$ 199,00</ins>' ), 'Disabling installments must preserve the Pix price and its label.' );
check( 0 === $quote_calls, 'The storefront must not request quotes when their display is disabled.' );

$options['gstore_show_storefront_installment_amounts'] = 'yes';
check( str_contains( gstore_add_payment_info_to_price( $block, array(), (object) array() ), 'ou em até 21x de R$ 10,00' ), 'Re-enabling the setting must restore the block amount.' );
echo "Storefront installment display OK\n";

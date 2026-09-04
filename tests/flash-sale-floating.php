<?php
/** Isolated regression checks for the real theme callbacks; no WordPress/database needed. */
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
$start  = strpos( $source, 'function gstore_theme_get_floating_flash_sale_product(' );
$end    = strpos( $source, "add_action( 'wp_enqueue_scripts', 'gstore_enqueue_flash_sale_assets', 25 );", $start );
if ( false === $start || false === $end ) {
	throw new RuntimeException( 'Flash sale callbacks not found.' );
}

$state = array();
function add_action( ...$args ) {}
function is_front_page() { return 'home' === $GLOBALS['state']['page']; }
function is_product() { return 'product' === $GLOBALS['state']['page']; }
function is_page( $slug ) { return 'catalog' === $GLOBALS['state']['page']; }
function get_queried_object_id() { return 42; }
function absint( $value ) { return abs( (int) $value ); }
function gstore_theme_get_active_flash_sale() { return $GLOBALS['state']['campaign']; }
function gstore_theme_get_product_flash_sale_campaign( $id ) { return $GLOBALS['state']['inline'] ? $GLOBALS['state']['campaign'] : null; }
function wc_get_product( $id ) { return $id && $GLOBALS['state']['exists'] ? new FlashSaleTestProduct() : false; }
function wp_get_theme() { return new class { public function get( $key ) { return 'test'; } }; }
function gstore_theme_asset_uri( $path ) { return $path; }
function gstore_theme_asset_version( $path, $version ) { return $version; }
function wp_enqueue_style( ...$args ) { $GLOBALS['state']['css'] = true; }
function wp_enqueue_script( ...$args ) { $GLOBALS['state']['js'] = true; }
function __( $text, $domain ) { return $text; }
function esc_attr__( $text, $domain ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $text, $domain ) { echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $text ) { return esc_attr( $text ); }
function esc_url( $text ) { return esc_attr( $text ); }
function wp_kses_post( $text ) { return $text; }
function wc_price( $value ) { return 'R$ ' . $value; }
class FlashSaleTestProduct {
	public function get_id() { return 42; }
	public function is_visible() { return $GLOBALS['state']['visible']; }
	public function is_in_stock() { return $GLOBALS['state']['stock']; }
	public function get_price() { return 80; }
	public function get_regular_price() { return 100; }
	public function get_permalink() { return '/produto/item-em-oferta/'; }
	public function get_name() { return 'Produto de teste'; }
	public function get_image( ...$args ) { return '<img alt="Produto de teste">'; }
}
eval( substr( $source, $start, $end - $start ) );

$campaign = array( 'items' => array( array( 'product_id' => 42 ) ), 'ends_at' => '2026-09-05 23:59:59' );
$defaults = array( 'page' => 'product', 'campaign' => $campaign, 'inline' => false, 'visible' => true, 'stock' => true, 'exists' => true, 'css' => false, 'js' => false );
if ( in_array( '--render', $argv, true ) ) {
	$state = $defaults;
	gstore_render_single_flash_sale_floating_card();
	exit;
}
$cases = array(
	'homepage' => array( array( 'page' => 'home' ), true, true, true ),
	'promoted product' => array( array( 'inline' => true ), true, true, true ),
	'other product' => array( array(), true, true, true ),
	'catalog unchanged' => array( array( 'page' => 'catalog' ), false, true, true ),
	'checkout excluded' => array( array( 'page' => 'checkout' ), false, false, false ),
	'no active campaign' => array( array( 'campaign' => null ), false, false, false ),
	'multiple products' => array( array( 'campaign' => array( 'items' => array( array( 'product_id' => 42 ), array( 'product_id' => 43 ) ), 'ends_at' => $campaign['ends_at'] ) ), false, false, false ),
	'multiple products inline timer preserved' => array( array( 'inline' => true, 'campaign' => array( 'items' => array( array( 'product_id' => 42 ), array( 'product_id' => 43 ) ), 'ends_at' => $campaign['ends_at'] ) ), false, false, true ),
	'no deadline' => array( array( 'campaign' => array( 'items' => $campaign['items'] ) ), false, false, false ),
	'out of stock' => array( array( 'stock' => false ), false, false, false ),
	'hidden product' => array( array( 'visible' => false ), false, false, false ),
	'missing product' => array( array( 'exists' => false ), false, false, false ),
);
foreach ( $cases as $name => [ $overrides, $card, $css, $js ] ) {
	$state = array_merge( $defaults, $overrides );
	ob_start();
	gstore_render_single_flash_sale_floating_card();
	$html = ob_get_clean();
	gstore_enqueue_flash_sale_assets();
	$actual = array( str_contains( $html, '<aside ' ), $state['css'], $state['js'] );
	if ( array( $card, $css, $js ) !== $actual ) {
		throw new RuntimeException( $name . ': unexpected card/CSS/JS state ' . json_encode( $actual ) );
	}
	if ( $card && ( ! str_contains( $html, ' hidden ' ) || ! str_contains( $html, 'data-gstore-flash-sale-key="42:2026-09-05 23:59:59"' ) ) ) {
		throw new RuntimeException( $name . ': missing initial visibility/session key.' );
	}
	echo 'PASS ' . $name . PHP_EOL;
}

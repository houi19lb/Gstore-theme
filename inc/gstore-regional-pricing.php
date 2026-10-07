<?php
/** Presentation only. Rules, GeoIP and checkout calculations belong to GSTORE. */
defined( 'ABSPATH' ) || exit;

function gstore_regional_pricing_active() {
	return class_exists( '\GStore\Services\Regional_Pricing_Service' ) && \GStore\Services\Regional_Pricing_Service::enabled();
}

/** Fragment caches containing prices must use the same UF as product getters. */
function gstore_regional_price_cache_key( $base ) {
	if ( ! gstore_regional_pricing_active() ) { return $base; }
	$context = array( \GStore\Services\Regional_Pricing_Service::instance()->current_state(), get_option( 'gstore_regional_pricing_revision', '' ), get_option( 'gstore_regional_display_revision', '' ) );
	return $base . '_r' . substr( md5( wp_json_encode( $context ) ), 0, 16 );
}

function gstore_regional_invalidate_display_prices() {
	if ( gstore_regional_pricing_active() ) { update_option( 'gstore_regional_display_revision', wp_generate_uuid4(), false ); }
}

add_filter( 'rest_post_dispatch', function ( $response, $server, $request ) {
	if ( gstore_regional_pricing_active() && '/gstore/v1/search-suggest' === $request->get_route() ) {
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		do_action( 'litespeed_control_set_nocache', 'Regional search suggestions' );
	}
	return $response;
}, 10, 3 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! gstore_regional_pricing_active() ) { return; }
	foreach ( array( 'css', 'js' ) as $type ) {
		$file = 'assets/' . $type . '/gstore-regional-pricing.min.' . $type;
		$version = (string) filemtime( get_theme_file_path( $file ) );
		if ( 'css' === $type ) { wp_enqueue_style( 'gstore-regional-pricing', get_theme_file_uri( $file ), array(), $version ); }
		else { wp_enqueue_script( 'gstore-regional-pricing', get_theme_file_uri( $file ), array(), $version, true ); }
	}
	wp_localize_script( 'gstore-regional-pricing', 'gstoreRegionalConfig', array(
		'endpoint' => esc_url_raw( rest_url( 'gstore/v1/regional-context' ) ),
		'states' => \GStore\Services\Regional_Pricing_Service::states(),
	) );
} );

// Works with the shipped block header and does not leave an inactive control when disabled.
add_filter( 'render_block', function ( $content ) {
	if ( ! gstore_regional_pricing_active() || false === strpos( $content, '<div class="Gstore-header__actions">' ) || false !== strpos( $content, 'data-gstore-region-trigger' ) ) { return $content; }
	$state = \GStore\Services\Regional_Pricing_Service::instance()->current_state();
	$button = '<button type="button" class="Gstore-region-trigger" data-gstore-region-trigger aria-haspopup="dialog">Região: <span data-gstore-region-label>' . esc_html( $state ?: 'Selecionar' ) . '</span></button>';
	return str_replace( '<div class="Gstore-header__actions">', '<div class="Gstore-header__actions">' . $button, $content );
}, 20 );

function gstore_region_fields( $id ) {
	?>
	<div class="Gstore-region-fields" data-gstore-region-fields>
		<label for="<?php echo esc_attr( $id ); ?>">Qual é o seu estado?</label>
		<select id="<?php echo esc_attr( $id ); ?>" data-gstore-region-select>
			<option value="">Continuar sem informar</option>
			<?php foreach ( \GStore\Services\Regional_Pricing_Service::states() as $uf => $name ) : ?>
				<option value="<?php echo esc_attr( $uf ); ?>"><?php echo esc_html( $name . ' (' . $uf . ')' ); ?></option>
			<?php endforeach; ?>
		</select>
		<p>Os preços podem variar por estado. Na finalização, vale o destino da entrega; para retirada, o estado da loja.</p>
		<p class="Gstore-region-status" data-gstore-region-status role="status" aria-live="polite"></p>
	</div>
	<?php
}

add_action( 'gstore_age_region_fields', function () {
	if ( gstore_regional_pricing_active() ) { gstore_region_fields( 'gstore-age-region' ); }
} );

add_action( 'wp_footer', function () {
	if ( ! gstore_regional_pricing_active() ) { return; }
	?>
	<dialog id="gstore-region-dialog" class="Gstore-region-dialog" aria-labelledby="gstore-region-title">
		<form method="dialog" data-gstore-region-form>
			<h2 id="gstore-region-title">Sua região de compra</h2>
			<?php gstore_region_fields( 'gstore-header-region' ); ?>
			<div class="Gstore-region-actions">
				<button type="button" data-gstore-region-close>Fechar</button>
				<button type="submit" class="Gstore-region-save">Confirmar região</button>
			</div>
		</form>
	</dialog>
	<?php
}, 998 );

add_action( 'woocommerce_single_product_summary', function () {
	if ( ! gstore_regional_pricing_active() ) { return; }
	$state = \GStore\Services\Regional_Pricing_Service::instance()->current_state();
	echo '<p class="Gstore-region-price-note">' . esc_html( $state ? 'Preços para ' . $state . '. O destino informado no checkout determina o valor final.' : 'Preço geral de referência. Selecione seu estado para consultar os preços da sua região.' ) . ' <button type="button" data-gstore-region-trigger>Alterar região</button></p>';
}, 11 );

add_action( 'woocommerce_review_order_before_cart_contents', function () {
	if ( gstore_regional_pricing_active() ) { echo '<tr class="Gstore-region-checkout-note"><td colspan="2">Os preços consideram o destino da entrega. Na retirada, usamos o estado da loja. Confira o total após alterar o endereço.</td></tr>'; }
} );

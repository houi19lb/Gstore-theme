<?php
/**
 * Google tag / GA4 tracking for the public storefront.
 *
 * @package Gstore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the configured Google tag measurement ID.
 *
 * @return string
 */
function gstore_google_tag_id() {
	$measurement_id = defined( 'GSTORE_GOOGLE_TAG_ID' ) ? GSTORE_GOOGLE_TAG_ID : 'G-GNEKJ6CFTK';
	$measurement_id = is_scalar( $measurement_id ) ? trim( (string) $measurement_id ) : '';

	/**
	 * Filters the Google tag measurement ID used by the theme.
	 *
	 * @param string $measurement_id Google tag or GA4 measurement ID.
	 */
	$measurement_id = apply_filters( 'gstore_google_tag_id', $measurement_id );
	$measurement_id = is_scalar( $measurement_id ) ? trim( (string) $measurement_id ) : '';

	return preg_replace( '/[^A-Za-z0-9_-]/', '', $measurement_id );
}

/**
 * Prints the Google tag script on public pages.
 */
function gstore_print_google_tag() {
	if ( is_admin() ) {
		return;
	}

	$measurement_id = gstore_google_tag_id();
	if ( '' === $measurement_id ) {
		return;
	}

	/**
	 * Enables or disables the Google tag output.
	 *
	 * @param bool   $enabled        Whether to print the tag.
	 * @param string $measurement_id Google tag or GA4 measurement ID.
	 */
	$enabled = apply_filters( 'gstore_google_tag_enabled', true, $measurement_id );
	if ( ! $enabled ) {
		return;
	}

	$src = add_query_arg(
		array(
			'id' => $measurement_id,
		),
		'https://www.googletagmanager.com/gtag/js'
	);
	?>
	<!-- GStore Google tag -->
	<script async src="<?php echo esc_url( $src ); ?>"></script>
	<script id="gstore-google-tag">
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		gtag('config', <?php echo wp_json_encode( $measurement_id ); ?>);
	</script>
	<?php
}
add_action( 'wp_head', 'gstore_print_google_tag', 1 );

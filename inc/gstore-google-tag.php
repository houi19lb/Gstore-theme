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
	gstore_print_bigboar_floating_support_analytics( $measurement_id );
}
add_action( 'wp_head', 'gstore_print_google_tag', 1 );

/**
 * Tracks the floating support button and the channel selected on Big Boar.
 *
 * @param string $measurement_id Google tag configured for the storefront.
 */
function gstore_print_bigboar_floating_support_analytics( $measurement_id ) {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! in_array( $host, array( 'bigboar.com.br', 'www.bigboar.com.br' ), true ) ) {
		return;
	}
	?>
	<script id="gstore-floating-support-analytics">
	(function () {
		const destination = <?php echo wp_json_encode( array( 'send_to' => $measurement_id ) ); ?>;
		const channelEvents = new Map([
			['whatsapp', 'whatsapp_floating_click'],
			['telegram', 'telegram_floating_click'],
			['chat_site', 'chat_floating_click'],
		]);

		function track(name) {
			if (typeof window.gtag === 'function') {
				window.gtag('event', name, destination);
			}
		}

		// Count the original user interaction before the support loader can replay it.
		window.addEventListener('click', function (event) {
			if (event.isTrusted && event.target?.closest?.('button#gstore-support-float')) {
				track('support_floating_click');
			}
		}, { capture: true, passive: true });

		window.addEventListener('gstore:support:channel-selected', function (event) {
			const name = channelEvents.get(event.detail?.channel);
			if (name) {
				track(name);
			}
		});
	}());
	</script>
	<?php
}

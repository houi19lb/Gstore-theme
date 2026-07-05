<?php
/**
 * Prints visual snapshot route suggestions from a local WordPress install.
 *
 * Usage:
 * php scripts/inspect-wordpress-routes.php "C:/path/to/wordpress" http://127.0.0.1:19005/
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$wp_root  = isset( $argv[1] ) ? rtrim( $argv[1], "/\\" ) : '';
$base_url = isset( $argv[2] ) ? rtrim( $argv[2], '/' ) . '/' : '';

if ( '' === $wp_root || ! is_file( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php scripts/inspect-wordpress-routes.php \"C:/path/to/wordpress\" http://127.0.0.1:19005/\n" );
	exit( 1 );
}

require $wp_root . '/wp-load.php';

function gstore_visual_route_path( $url ) {
	$path  = wp_parse_url( $url, PHP_URL_PATH );
	$query = wp_parse_url( $url, PHP_URL_QUERY );

	if ( ! is_string( $path ) || '' === $path ) {
		$path = '/';
	}

	return $query ? $path . '?' . $query : $path;
}

function gstore_visual_first_post_route( $post_type ) {
	$items = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	return $items ? gstore_visual_route_path( get_permalink( $items[0] ) ) : '';
}

function gstore_visual_first_term_route( $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'number'     => 1,
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	$term = reset( $terms );
	$link = get_term_link( $term );
	return is_wp_error( $link ) ? '' : gstore_visual_route_path( $link );
}

$routes = array(
	'product-category' => gstore_visual_first_term_route( 'product_cat' ),
	'product-tag'      => gstore_visual_first_term_route( 'product_tag' ),
	'single-product'   => gstore_visual_first_post_route( 'product' ),
	'single-post'      => gstore_visual_first_post_route( 'post' ),
	'generic-page'     => gstore_visual_first_post_route( 'page' ),
);

$routes = array_filter( $routes );

$output = array(
	'baseUrl' => $base_url ? $base_url : home_url( '/' ),
	'routes'  => $routes,
	'meta'    => array(
		'name'       => get_bloginfo( 'name' ),
		'homeUrl'    => home_url( '/' ),
		'stylesheet' => get_stylesheet(),
		'template'   => get_template(),
		'theme'      => wp_get_theme()->get( 'Name' ),
		'version'    => wp_get_theme()->get( 'Version' ),
	),
);

echo wp_json_encode( $output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;

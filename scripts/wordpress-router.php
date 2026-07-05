<?php
/**
 * Router for PHP's built-in server when capturing WordPress visual snapshots.
 *
 * Usage:
 * php -S 127.0.0.1:19005 -t "C:/path/to/wordpress" scripts/wordpress-router.php
 */

$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = rtrim( $_SERVER['DOCUMENT_ROOT'], '/\\' ) . $path;

if ( $path !== '/' && is_file( $file ) ) {
	return false;
}

$scheme         = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
$current_origin = $scheme . '://' . $_SERVER['HTTP_HOST'];

if ( ! defined( 'WP_HOME' ) ) {
	define( 'WP_HOME', $current_origin );
}

if ( ! defined( 'WP_SITEURL' ) ) {
	define( 'WP_SITEURL', $current_origin );
}

ob_start();
require rtrim( $_SERVER['DOCUMENT_ROOT'], '/\\' ) . '/index.php';
$content = ob_get_clean();

$aliases = array();

if ( function_exists( 'home_url' ) ) {
	$aliases[] = untrailingslashit( home_url() );
}

if ( function_exists( 'site_url' ) ) {
	$aliases[] = untrailingslashit( site_url() );
}

$env_aliases = getenv( 'GSTORE_CAPTURE_URL_ALIASES' );
if ( is_string( $env_aliases ) && '' !== trim( $env_aliases ) ) {
	$aliases = array_merge( $aliases, array_map( 'trim', explode( ',', $env_aliases ) ) );
}

$aliases = array_unique( array_filter( $aliases ) );
foreach ( $aliases as $alias ) {
	if ( $alias !== $current_origin ) {
		$content = str_replace( $alias, $current_origin, $content );
	}
}

echo $content;

<?php
/**
 * Public pages for the Programa Atleta.
 *
 * @package GStore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'gstore_athlete_account_program_url' ) ) {
	function gstore_athlete_account_program_url() {
		return home_url( user_trailingslashit( 'atletas' ) );
	}
}

if ( ! function_exists( 'gstore_athlete_account_restricted_url' ) ) {
	function gstore_athlete_account_restricted_url() {
		return home_url( user_trailingslashit( 'produto-exclusivo-atleta' ) );
	}
}

if ( ! function_exists( 'gstore_athlete_account_register_pages' ) ) {
	function gstore_athlete_account_register_pages() {
		add_rewrite_rule( '^atletas/?$', 'index.php?gstore_athlete_program_page=1', 'top' );
		add_rewrite_rule( '^produto-exclusivo-atleta/?$', 'index.php?gstore_athlete_restricted_page=1', 'top' );
	}
}
add_action( 'init', 'gstore_athlete_account_register_pages', 6 );

if ( ! function_exists( 'gstore_athlete_account_query_vars' ) ) {
	function gstore_athlete_account_query_vars( $vars ) {
		$vars[] = 'gstore_athlete_program_page';
		$vars[] = 'gstore_athlete_restricted_page';
		return $vars;
	}
}
add_filter( 'query_vars', 'gstore_athlete_account_query_vars' );

if ( ! function_exists( 'gstore_athlete_account_maybe_flush_pages' ) ) {
	function gstore_athlete_account_maybe_flush_pages() {
		$version = '20260827-atletas';
		if ( get_option( 'gstore_athlete_account_pages_version' ) === $version ) {
			return;
		}

		gstore_athlete_account_register_pages();
		flush_rewrite_rules( false );
		update_option( 'gstore_athlete_account_pages_version', $version, true );
	}
}
add_action( 'init', 'gstore_athlete_account_maybe_flush_pages', 25 );

if ( ! function_exists( 'gstore_athlete_account_is_enabled' ) ) {
	function gstore_athlete_account_is_enabled() {
		return function_exists( 'gstore_athlete_is_enabled' ) && gstore_athlete_is_enabled();
	}
}

if ( ! function_exists( 'gstore_athlete_account_template' ) ) {
	function gstore_athlete_account_template( $template ) {
		$program = (bool) get_query_var( 'gstore_athlete_program_page' );
		$restricted = (bool) get_query_var( 'gstore_athlete_restricted_page' );
		if ( ! $program && ! $restricted ) {
			return $template;
		}

		if ( $program && ! gstore_athlete_account_is_enabled() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return get_404_template() ?: $template;
		}

		$file = $program ? 'templates/gstore-athlete-program-page.php' : 'templates/gstore-athlete-restricted-product-page.php';
		$athlete_template = get_theme_file_path( $file );
		return file_exists( $athlete_template ) ? $athlete_template : $template;
	}
}
add_filter( 'template_include', 'gstore_athlete_account_template', 20 );

if ( ! function_exists( 'gstore_athlete_account_document_title' ) ) {
	function gstore_athlete_account_document_title( $title ) {
		if ( get_query_var( 'gstore_athlete_program_page' ) ) {
			return __( 'Programa Atleta', 'gstore' );
		}
		if ( get_query_var( 'gstore_athlete_restricted_page' ) ) {
			return __( 'Produto exclusivo para atletas', 'gstore' );
		}
		return $title;
	}
}
add_filter( 'pre_get_document_title', 'gstore_athlete_account_document_title', 20 );

if ( ! function_exists( 'gstore_athlete_account_enqueue_assets' ) ) {
	function gstore_athlete_account_enqueue_assets() {
		if ( ! get_query_var( 'gstore_athlete_program_page' ) && ! get_query_var( 'gstore_athlete_restricted_page' ) ) {
			return;
		}
		if ( function_exists( 'gstore_enqueue_theme_style' ) ) {
			gstore_enqueue_theme_style( 'gstore-athlete-program-css', 'assets/css/athlete-program.css', array(), '20260828-atletas-svg-steps' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'gstore_athlete_account_enqueue_assets', 30 );

if ( ! function_exists( 'gstore_athlete_account_restricted_product_url' ) ) {
	function gstore_athlete_account_restricted_product_url( $url ) {
		return gstore_athlete_account_restricted_url();
	}
}
add_filter( 'gstore_athlete_restricted_product_url', 'gstore_athlete_account_restricted_product_url' );

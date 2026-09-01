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

if ( ! function_exists( 'gstore_athlete_account_products_url' ) ) {
	/**
	 * URL pública da vitrine exclusiva do programa.
	 *
	 * A vitrine continua protegida no template; esta função só centraliza a
	 * URL para os links internos e para a paginação.
	 *
	 * @return string
	 */
	function gstore_athlete_account_products_url() {
		return home_url( user_trailingslashit( 'para-atletas' ) );
	}
}

if ( ! function_exists( 'gstore_athlete_account_register_pages' ) ) {
	function gstore_athlete_account_register_pages() {
		add_rewrite_rule( '^atletas/?$', 'index.php?gstore_athlete_program_page=1', 'top' );
		add_rewrite_rule( '^produto-exclusivo-atleta/?$', 'index.php?gstore_athlete_restricted_page=1', 'top' );
		add_rewrite_rule( '^para-atletas/page/([0-9]+)/?$', 'index.php?gstore_athlete_products_page=1&paged=$matches[1]', 'top' );
		add_rewrite_rule( '^para-atletas/?$', 'index.php?gstore_athlete_products_page=1', 'top' );
	}
}
add_action( 'init', 'gstore_athlete_account_register_pages', 6 );

if ( ! function_exists( 'gstore_athlete_account_query_vars' ) ) {
	function gstore_athlete_account_query_vars( $vars ) {
		$vars[] = 'gstore_athlete_program_page';
		$vars[] = 'gstore_athlete_restricted_page';
		$vars[] = 'gstore_athlete_products_page';
		return $vars;
	}
}
add_filter( 'query_vars', 'gstore_athlete_account_query_vars' );

if ( ! function_exists( 'gstore_athlete_account_maybe_flush_pages' ) ) {
	function gstore_athlete_account_maybe_flush_pages() {
		$version = '20260901-atletas-products';
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
		$products = (bool) get_query_var( 'gstore_athlete_products_page' );
		if ( ! $program && ! $restricted && ! $products ) {
			return $template;
		}

		if ( ( $program || $products ) && ! gstore_athlete_account_is_enabled() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return get_404_template() ?: $template;
		}

		if ( $products ) {
			$is_athlete = is_user_logged_in()
				&& function_exists( 'gstore_athlete_user_is_athlete' )
				&& gstore_athlete_user_is_athlete( get_current_user_id() );
			$file       = $is_athlete ? 'templates/gstore-athlete-products-page.php' : 'templates/gstore-athlete-restricted-product-page.php';
		} else {
			$file = $program ? 'templates/gstore-athlete-program-page.php' : 'templates/gstore-athlete-restricted-product-page.php';
		}
		$athlete_template = get_theme_file_path( $file );
		return file_exists( $athlete_template ) ? $athlete_template : $template;
	}
}
add_filter( 'template_include', 'gstore_athlete_account_template', 20 );

if ( ! function_exists( 'gstore_athlete_account_products_query' ) ) {
	/**
	 * Mantém a vitrine /para-atletas no mesmo shortcode de catálogo, exibindo
	 * apenas produtos exclusivos que o atleta atual pode acessar.
	 *
	 * @param array<string,mixed> $query_args Argumentos do shortcode WooCommerce.
	 * @return array<string,mixed>
	 */
	function gstore_athlete_account_products_query( $query_args ) {
		if ( ! get_query_var( 'gstore_athlete_products_page' ) ) {
			return $query_args;
		}

		$meta_query   = isset( $query_args['meta_query'] ) && is_array( $query_args['meta_query'] ) ? $query_args['meta_query'] : array();
		$meta_query[] = array(
			'key'     => '_gstore_athlete_exclusive',
			'value'   => '1',
			'compare' => '=',
		);

		if ( ! ( class_exists( '\\GStore\\Services\\VIP_Service' ) && \GStore\Services\VIP_Service::user_is_vip() ) ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_gstore_vip_exclusive',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_gstore_vip_exclusive',
					'value'   => '1',
					'compare' => '!=',
				),
			);
		}

		$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		return $query_args;
	}
}
add_filter( 'woocommerce_shortcode_products_query', 'gstore_athlete_account_products_query', 20 );

if ( ! function_exists( 'gstore_athlete_account_document_title' ) ) {
	function gstore_athlete_account_document_title( $title ) {
		if ( get_query_var( 'gstore_athlete_program_page' ) ) {
			return __( 'Programa Atleta', 'gstore' );
		}
		if ( get_query_var( 'gstore_athlete_products_page' ) ) {
			return __( 'Produtos para atletas', 'gstore' );
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
		if ( ! get_query_var( 'gstore_athlete_program_page' ) && ! get_query_var( 'gstore_athlete_restricted_page' ) && ! get_query_var( 'gstore_athlete_products_page' ) ) {
			return;
		}
		if ( function_exists( 'gstore_enqueue_theme_style' ) ) {
			gstore_enqueue_theme_style( 'gstore-athlete-program-css', 'assets/css/athlete-program.css', array(), '20260901-atletas-products' );
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

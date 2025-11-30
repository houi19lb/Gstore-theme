<?php
/**
 * Template para listar produtos (Loja, categorias, tags)
 *
 * Mantém o loop oficial do WooCommerce para garantir compatibilidade, mas
 * utiliza o template `content-product.php` personalizado (cards Brass).
 *
 * @package Gstore
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

/**
 * Hook: woocommerce_before_main_content.
 *
 * @hooked woocommerce_output_content_wrapper - 10
 * @hooked woocommerce_breadcrumb - 20
 */
do_action( 'woocommerce_before_main_content' );

/**
 * Hook: woocommerce_shop_loop_header.
 *
 * @since 8.6.0
 * @hooked woocommerce_product_taxonomy_archive_header - 10
 */
do_action( 'woocommerce_shop_loop_header' );

echo '<div class="Gstore-products-shell">';

if ( woocommerce_product_loop() ) {

	echo '<div class="Gstore-products-grid">';

	/**
	 * Hook: woocommerce_before_shop_loop.
	 *
	 * @hooked woocommerce_output_all_notices - 10
	 * @hooked woocommerce_result_count - 20
	 * @hooked woocommerce_catalog_ordering - 30
	 */
	do_action( 'woocommerce_before_shop_loop' );

	woocommerce_product_loop_start();

	while ( have_posts() ) {
		the_post();

		/**
		 * Hook: woocommerce_shop_loop.
		 */
		do_action( 'woocommerce_shop_loop' );

		wc_get_template_part( 'content', 'product' );
	}

	woocommerce_product_loop_end();

	echo '</div>'; // .Gstore-products-grid

	/**
	 * Hook: woocommerce_after_shop_loop.
	 *
	 * @hooked woocommerce_pagination - 10
	 */
	do_action( 'woocommerce_after_shop_loop' );
} else {
	/**
	 * Hook: woocommerce_no_products_found.
	 *
	 * @hooked wc_no_products_found - 10
	 */
	do_action( 'woocommerce_no_products_found' );
}

echo '</div>'; // .Gstore-products-shell

/**
 * Hook: woocommerce_after_main_content.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10
 */
do_action( 'woocommerce_after_main_content' );

/**
 * Hook: woocommerce_sidebar.
 *
 * @hooked woocommerce_get_sidebar - 10
 */
do_action( 'woocommerce_sidebar' );

get_footer( 'shop' );



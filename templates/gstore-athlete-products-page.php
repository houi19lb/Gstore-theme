<?php
/**
 * Athlete-only products catalogue.
 *
 * @package GStore
 */

defined( 'ABSPATH' ) || exit;

$paged    = max( 1, absint( get_query_var( 'paged' ) ) );
$per_page = max( 1, (int) apply_filters( 'loop_shop_per_page', get_option( 'posts_per_page', 12 ) ) );
$is_vip   = class_exists( '\\GStore\\Services\\VIP_Service' ) && \GStore\Services\VIP_Service::user_is_vip();
$meta_query = array(
	array(
		'key'     => '_gstore_athlete_exclusive',
		'value'   => '1',
		'compare' => '=',
	),
);

// Produtos que também são VIP permanecem disponíveis somente para quem tem
// os dois selos, preservando a regra de independência entre os programas.
if ( ! $is_vip ) {
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

$products = new WP_Query(
	array(
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'posts_per_page'         => $per_page,
		'paged'                  => $paged,
		'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => false,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
	)
);

$product_count = (int) $products->found_posts;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'gstore-athlete-program-body' ); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
?>
<main class="gstore-athlete-products-page">
	<section class="gstore-athlete-products-hero">
		<div class="gstore-athlete-container">
			<span class="gstore-athlete-eyebrow"><?php esc_html_e( 'Acesso de atleta', 'gstore' ); ?></span>
			<h1><?php esc_html_e( 'Produtos para atletas', 'gstore' ); ?></h1>
			<p><?php esc_html_e( 'Uma seleção exclusiva de produtos marcada para o Programa Atleta.', 'gstore' ); ?></p>
			<span class="gstore-athlete-products-count">
				<?php
				printf(
					/* translators: %s: number of athlete-only products. */
					esc_html( _n( '%s produto exclusivo', '%s produtos exclusivos', $product_count, 'gstore' ) ),
					esc_html( number_format_i18n( $product_count ) )
				);
				?>
			</span>
		</div>
	</section>

	<section class="Gstore-products-shell gstore-athlete-products-catalogue" aria-label="<?php esc_attr_e( 'Produtos exclusivos para atletas', 'gstore' ); ?>">
		<div class="Gstore-products-grid">
			<?php if ( $products->have_posts() && function_exists( 'woocommerce_product_loop_start' ) ) : ?>
				<?php
				wc_set_loop_prop( 'columns', 4 );
				woocommerce_product_loop_start();
				while ( $products->have_posts() ) :
					$products->the_post();
					wc_setup_product_data( get_post() );
					do_action( 'woocommerce_shop_loop' );
					wc_get_template_part( 'content', 'product' );
				endwhile;
				woocommerce_product_loop_end();
				?>
			<?php else : ?>
				<p class="gstore-athlete-products-empty"><?php esc_html_e( 'Ainda não há produtos exclusivos para atletas disponíveis.', 'gstore' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		if ( $products->max_num_pages > 1 ) {
			$pagination = paginate_links(
				array(
					'base'      => trailingslashit( gstore_athlete_account_products_url() ) . 'page/%#%/',
					'format'    => '',
					'current'   => $paged,
					'total'     => $products->max_num_pages,
					'type'      => 'list',
					'prev_text' => __( 'Anterior', 'gstore' ),
					'next_text' => __( 'Próxima', 'gstore' ),
				)
			);
			if ( $pagination ) {
				printf( '<nav class="woocommerce-pagination" aria-label="%s">%s</nav>', esc_attr__( 'Paginação dos produtos para atletas', 'gstore' ), $pagination ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		?>
	</section>
</main>
<?php
wp_reset_postdata();
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
wp_footer();
?>
</body>
</html>

<?php
/**
 * Template Name: Cart (Gstore)
 * Template Post Type: page
 *
 * Template responsável por renderizar o carrinho clássico do WooCommerce
 * utilizando o layout customizado definido em woocommerce/cart/cart.php.
 *
 * @package Gstore
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="site-content" class="gstore-cart-page" role="main">
	<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
		<div class="gstore-cart-page__notice">
			<p><?php esc_html_e( 'WooCommerce precisa estar ativo para exibir o carrinho.', 'gstore' ); ?></p>
		</div>
	<?php else : ?>
		<?php if ( function_exists( 'woocommerce_output_all_notices' ) ) : ?>
			<div class="gstore-cart-page__notices">
				<?php woocommerce_output_all_notices(); ?>
			</div>
		<?php endif; ?>

		<?php
		while ( have_posts() ) :
			the_post();

			/**
			 * Permite injetar conteúdo antes do shortcode do carrinho.
			 */
			do_action( 'gstore_before_cart_page' );

			echo do_shortcode( '[woocommerce_cart]' );

			do_action( 'gstore_after_cart_page' );
		endwhile;
		?>
	<?php endif; ?>
</main>

<?php
get_footer();





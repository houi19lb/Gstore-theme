<?php
/**
 * The template for displaying product content within loops (Gstore Custom)
 *
 * @package Gstore
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

// Ensure visibility.
if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

$is_variable_product   = $product->is_type( array( 'variable', 'variation' ) );
$regular_price_amount  = $is_variable_product ? (float) $product->get_variation_regular_price( 'min', true ) : (float) $product->get_regular_price();
$sale_price_amount     = $is_variable_product ? (float) $product->get_variation_sale_price( 'min', true ) : (float) $product->get_sale_price();
$current_price_amount  = $is_variable_product ? (float) $product->get_variation_price( 'min', true ) : (float) $product->get_price();
$has_sale_value        = $product->is_on_sale() && $regular_price_amount > 0 && $sale_price_amount > 0;
$display_price_amount  = $has_sale_value ? $sale_price_amount : ( $current_price_amount > 0 ? $current_price_amount : $regular_price_amount );
$installment_amount    = $display_price_amount > 0 ? $display_price_amount / 12 : 0;
$regular_price_html    = $regular_price_amount > 0 ? wc_price( $regular_price_amount ) : '';
$display_price_html    = $display_price_amount > 0 ? wc_price( $display_price_amount ) : $product->get_price_html();
$installment_price_html = $installment_amount > 0 ? wc_price( $installment_amount ) : '';
?>
<li <?php wc_product_class( 'Gstore-product-card', $product ); ?>>
	<div class="Gstore-product-card__inner">
		<div class="Gstore-product-card__top">
			<?php
			// Discount Badge.
			if ( $product->is_on_sale() ) {
				$regular_price = (float) $product->get_regular_price();
				$sale_price    = (float) $product->get_sale_price();
				if ( $regular_price > 0 ) {
					$discount_percentage = round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 );
					?>
					<div class="Gstore-product-card__badge">
						<?php echo esc_html( $discount_percentage ); ?>% <?php esc_html_e( 'OFF', 'gstore' ); ?>
					</div>
					<?php
				}
			}
			?>
			<button type="button" class="Gstore-product-card__favorite" aria-pressed="false">
				<i class="fa-regular fa-heart Gstore-product-card__favorite-icon" aria-hidden="true"></i>
				<span class="Gstore-sr-only"><?php esc_html_e( 'Adicionar aos favoritos', 'gstore' ); ?></span>
			</button>

			<div class="Gstore-product-card__image">
				<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="Gstore-product-card__image-link">
					<?php echo $product->get_image( 'woocommerce_thumbnail' ); ?>
				</a>
			</div>
		</div>
		<div class="Gstore-product-card__body">
			<div class="Gstore-product-card__meta">
				<div class="Gstore-product-card__rating">
					<?php
					$rating_count = $product->get_rating_count();
					$average      = $product->get_average_rating();
					?>
					<div class="Gstore-product-card__stars">
						<?php
						for ( $i = 1; $i <= 5; $i++ ) {
							if ( $i <= $average ) {
								echo '<span class="Gstore-product-card__star Gstore-product-card__star--filled">★</span>';
							} else {
								echo '<span class="Gstore-product-card__star">★</span>';
							}
						}
						?>
						<?php if ( $rating_count > 0 ) : ?>
							<span class="Gstore-product-card__rating-count">(<?php echo esc_html( $rating_count ); ?>)</span>
						<?php endif; ?>
					</div>
				</div>
				<h3 class="Gstore-product-card__title">
					<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
						<?php echo esc_html( $product->get_name() ); ?>
					</a>
				</h3>
			</div>
			<div class="Gstore-product-card__price-section">
				<?php if ( $has_sale_value && $regular_price_html ) : ?>
					<div class="Gstore-product-card__price-original">
						<?php echo wp_kses_post( $regular_price_html ); ?>
					</div>
				<?php else : ?>
					<div class="Gstore-product-card__price-original Gstore-product-card__price-original--placeholder" aria-hidden="true">
						&nbsp;
					</div>
				<?php endif; ?>
				<?php if ( $display_price_html ) : ?>
					<div class="Gstore-product-card__price-row">
						<?php echo wp_kses_post( $display_price_html ); ?>
					</div>
				<?php endif; ?>
				<?php if ( $installment_price_html ) : ?>
					<div class="Gstore-product-card__price-details">
						<strong class="Gstore-product-card__price-details-label"><?php esc_html_e( 'à vista no Pix', 'gstore' ); ?></strong>
						<span class="Gstore-product-card__installments">
							<?php
							/* translators: %s: installment value */
							printf(
								wp_kses_post( __( 'ou 12x de %s', 'gstore' ) ),
								wp_kses_post( $installment_price_html )
							);
							?>
						</span>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<div class="Gstore-product-card__footer">
			<?php
			woocommerce_template_loop_add_to_cart();
			?>
		</div>
	</div>
</li>


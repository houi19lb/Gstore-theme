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
$stock_status          = (string) $product->get_stock_status();

// Para produtos variáveis, verificar se TODAS as variações estão sem estoque.
$all_variations_out_of_stock = false;
if ( $is_variable_product && method_exists( $product, 'get_children' ) ) {
	$children = $product->get_children();
	if ( ! empty( $children ) ) {
		$all_variations_out_of_stock = true;
		foreach ( $children as $child_id ) {
			$child_stock_status = (string) get_post_meta( (int) $child_id, '_stock_status', true );
			if ( 'instock' === $child_stock_status ) {
				$all_variations_out_of_stock = false;
				break;
			}
		}
	}
}

// Para variáveis: se TODAS estão sem estoque, forçar outofstock.
if ( $is_variable_product && $all_variations_out_of_stock ) {
	$stock_status = 'outofstock';
}

$is_out_of_stock = 'outofstock' === $stock_status;
$show_price_oos = 'yes' === (string) get_option( 'gstore_show_price_out_of_stock', 'no' );

$regular_price_amount  = $is_variable_product ? (float) $product->get_variation_regular_price( 'min', true ) : (float) $product->get_regular_price();
$sale_price_amount     = $is_variable_product ? (float) $product->get_variation_sale_price( 'min', true ) : (float) $product->get_sale_price();
$current_price_amount  = $is_variable_product ? (float) $product->get_variation_price( 'min', true ) : (float) $product->get_price();
$has_sale_value        = $product->is_on_sale() && $regular_price_amount > 0 && $sale_price_amount > 0;
$display_price_amount  = $has_sale_value ? $sale_price_amount : ( $current_price_amount > 0 ? $current_price_amount : $regular_price_amount );
// Para variáveis, resolver o ID da variação com menor preço para o AJAX de parcelas.
$installment_product_id = gstore_get_product_id( $product );
if ( $is_variable_product && method_exists( $product, 'get_variation_prices' ) ) {
	$variation_prices = $product->get_variation_prices( true );
	if ( ! empty( $variation_prices['price'] ) ) {
		reset( $variation_prices['price'] );
		$cheapest_variation_id = (int) key( $variation_prices['price'] );
		if ( $cheapest_variation_id > 0 ) {
			$installment_product_id = $cheapest_variation_id;
		}
	}
}
// Calcula parcela sem juros (taxa efetiva só no checkout).
$installments          = (int) apply_filters( 'armastore_single_product_installments', 21, $product );
$installment_amount    = $display_price_amount > 0 ? gstore_calculate_installment_amount( $display_price_amount, $installments ) : 0;
$regular_price_html    = $regular_price_amount > 0 ? wc_price( $regular_price_amount ) : '';
$display_price_html    = $display_price_amount > 0 ? wc_price( $display_price_amount ) : $product->get_price_html();
$installment_price_html = $installment_amount > 0 ? wc_price( $installment_amount ) : '';
$installment_label       = $installment_price_html
	? sprintf(
		/* translators: 1: installments count, 2: installment value */
		__( 'ou %1$dx de %2$s', 'gstore' ),
		$installments,
		$installment_price_html
	)
	: '';

// Desconto Pix (apenas visual — não altera preço real, parcelas ou carrinho).
$pix_discount_config     = function_exists( 'gstore_blu_pix_get_discount_config' ) ? gstore_blu_pix_get_discount_config() : array( 'enabled' => false );
$pix_discount_active     = ! empty( $pix_discount_config['enabled'] ) && $display_price_amount > 0;
$pix_discount_price      = $pix_discount_active && function_exists( 'gstore_blu_pix_get_discounted_price' ) ? gstore_blu_pix_get_discounted_price( $display_price_amount, $product->get_id() ) : false;
$pix_discount_price_html = $pix_discount_price ? wc_price( $pix_discount_price ) : '';
$pix_discount_percent    = $pix_discount_active ? (float) $pix_discount_config['percent'] : 0;
?>
<li <?php wc_product_class( 'Gstore-product-card', $product ); ?>>
	<div class="Gstore-product-card__inner">
		<div class="Gstore-product-card__top">
			<?php
			// Discount Badge.
			if ( $is_variable_product ) {
				// Variáveis: usar preços já calculados (linhas 44-48).
				if ( $has_sale_value ) {
					$discount_percentage = round( ( ( $regular_price_amount - $display_price_amount ) / $regular_price_amount ) * 100 );
					if ( $discount_percentage > 0 ) {
						?>
						<div class="Gstore-product-card__badge">
							<?php echo esc_html( $discount_percentage ); ?>% <?php esc_html_e( 'OFF', 'gstore' ); ?>
						</div>
						<?php
					}
				}
			} elseif ( $product->is_on_sale() ) {
				// Simples: código original intacto.
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
			<?php if ( ! $is_out_of_stock || $show_price_oos ) : ?>
				<div class="Gstore-product-card__price-section">
					<?php if ( $pix_discount_price_html ) : ?>
						<?php
						// Com desconto Pix: preço riscado = regular (se promoção) ou display (se sem promoção).
						// Preço principal = preço Pix com desconto.
						$strikethrough_html = $has_sale_value ? $regular_price_html : $display_price_html;
						?>
						<?php if ( $strikethrough_html ) : ?>
							<div class="Gstore-product-card__price-original">
								<?php echo wp_kses_post( $strikethrough_html ); ?>
							</div>
						<?php endif; ?>
						<div class="Gstore-product-card__price-row">
							<?php echo wp_kses_post( $pix_discount_price_html ); ?>
						</div>
					<?php elseif ( $has_sale_value && $regular_price_html ) : ?>
						<div class="Gstore-product-card__price-original">
							<?php echo wp_kses_post( $regular_price_html ); ?>
						</div>
						<?php if ( $display_price_html ) : ?>
							<div class="Gstore-product-card__price-row">
								<?php echo wp_kses_post( $display_price_html ); ?>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="Gstore-product-card__price-original Gstore-product-card__price-original--placeholder" aria-hidden="true">
							&nbsp;
						</div>
						<?php if ( $display_price_html ) : ?>
							<div class="Gstore-product-card__price-row">
								<?php echo wp_kses_post( $display_price_html ); ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( $installment_label ) : ?>
						<div class="Gstore-product-card__price-details">
							<strong class="Gstore-product-card__price-details-label"><?php esc_html_e( 'à vista no Pix', 'gstore' ); ?></strong>
						<div class="Gstore-product-card__installments" data-gstore-installment-wrapper>
							<span
								class="Gstore-product-card__installments-text"
								data-gstore-installment-target="1"
								data-gstore-installment-scope="card"
								data-product-id="<?php echo esc_attr( (string) $installment_product_id ); ?>"
								data-max-installments="<?php echo esc_attr( $installments ); ?>"
								data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
								data-initial-text="<?php echo esc_attr( wp_strip_all_tags( (string) $installment_label ) ); ?>"
							>
									<?php echo wp_kses_post( $installment_label ); ?>
								</span>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( $is_out_of_stock && $show_price_oos ) : ?>
						<div class="Gstore-product-card__price-details">
							<strong class="Gstore-product-card__price-details-label"><?php esc_html_e( 'Sem estoque no momento', 'gstore' ); ?></strong>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<div class="Gstore-product-card__footer">
			<?php
			woocommerce_template_loop_add_to_cart();
			?>
		</div>
	</div>
</li>

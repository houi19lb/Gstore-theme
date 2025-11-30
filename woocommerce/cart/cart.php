<?php
/**
 * Cart Page
 *
 * @package Gstore\WooCommerce
 * @version 10.1.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

$cart_url     = wc_get_cart_url();
$shop_url     = wc_get_page_permalink( 'shop' );
$shop_url     = $shop_url ? $shop_url : home_url( '/' );
$att_page     = get_page_by_path( 'atendimento' );
$att_page_id  = $att_page instanceof WP_Post ? $att_page->ID : 0;
$att_link     = $att_page_id ? get_permalink( $att_page_id ) : $shop_url;
$att_title    = $att_page_id ? get_the_title( $att_page_id ) : esc_html__( 'Central de Atendimento', 'gstore' );
$button_class = 'Gstore-cart-btn Gstore-cart-btn--ghost';

if ( function_exists( 'wc_wp_theme_get_element_class_name' ) ) {
	$element_class = wc_wp_theme_get_element_class_name( 'button' );
	if ( $element_class ) {
		$button_class .= ' ' . $element_class;
	}
}
?>

<section class="Gstore-cart-shell">
	<div class="Gstore-cart-container">
		<header class="Gstore-cart-header">
			<span class="Gstore-cart-eyebrow"><?php esc_html_e( 'Carrinho em andamento', 'gstore' ); ?></span>
			<h1><?php esc_html_e( 'Revise seus itens antes de finalizar', 'gstore' ); ?></h1>
		</header>

		<form class="woocommerce-cart-form Gstore-cart-form" action="<?php echo esc_url( $cart_url ); ?>" method="post">
			<?php do_action( 'woocommerce_before_cart_table' ); ?>

			<div class="Gstore-cart-layout">
				<div class="Gstore-cart-main">
					<div class="Gstore-cart-list" role="list">
						<?php do_action( 'woocommerce_before_cart_contents' ); ?>

						<?php
						foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
							$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
							$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

							if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
								$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
								$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );
								$cart_item_class   = implode( ' ', array_map( 'sanitize_html_class', explode( ' ', apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ) ) );
								?>

								<article class="Gstore-cart-card <?php echo esc_attr( $cart_item_class ); ?>" role="listitem" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>">
									<div class="Gstore-cart-card__media">
										<?php
										if ( ! $product_permalink ) {
											echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										} else {
											printf(
												'<a class="Gstore-cart-card__thumb" href="%1$s">%2$s</a>',
												esc_url( $product_permalink ),
												$thumbnail // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											);
										}
										?>
									</div>

									<div class="Gstore-cart-card__body">
										<div class="Gstore-cart-card__top">
											<div>
												<?php
												if ( ! $product_permalink ) {
													echo wp_kses_post(
														apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key )
													);
												} else {
													echo wp_kses_post(
														apply_filters(
															'woocommerce_cart_item_name',
															sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ),
															$cart_item,
															$cart_item_key
														)
													);
												}

												do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );

												echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

												if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
													echo wp_kses_post( '<p class="Gstore-cart-card__notice">' . esc_html__( 'Disponível sob encomenda', 'gstore' ) . '</p>' );
												}
												?>
											</div>

											<?php
											echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
												'woocommerce_cart_item_remove_link',
												sprintf(
													'<a href="%s" class="remove Gstore-cart-card__remove" aria-label="%s" data-product_id="%s" data-product_sku="%s"><span aria-hidden="true">&times;</span><span class="Gstore-sr-only">%s</span></a>',
													esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
													esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $_product->get_name() ) ) ),
													esc_attr( $product_id ),
													esc_attr( $_product->get_sku() ),
													esc_html__( 'Remover item', 'gstore' )
												),
												$cart_item_key
											);
											?>
										</div>

										<div class="Gstore-cart-card__details">
											<div class="Gstore-cart-card__price">
												<span class="Gstore-cart-card__label"><?php esc_html_e( 'Preço unitário', 'gstore' ); ?></span>
												<?php
												echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
													'woocommerce_cart_item_price',
													WC()->cart->get_product_price( $_product ),
													$cart_item,
													$cart_item_key
												);
												?>
											</div>

											<div class="Gstore-cart-card__quantity">
												<span class="Gstore-cart-card__label"><?php esc_html_e( 'Quantidade', 'gstore' ); ?></span>
												<?php
												if ( $_product->is_sold_individually() ) {
													$min_quantity = 1;
													$max_quantity = 1;
												} else {
													$min_quantity = 0;
													$max_quantity = $_product->get_max_purchase_quantity();
												}

												$product_quantity = woocommerce_quantity_input(
													array(
														'input_name'   => "cart[{$cart_item_key}][qty]",
														'input_value'  => $cart_item['quantity'],
														'max_value'    => $max_quantity,
														'min_value'    => $min_quantity,
														'product_name' => $_product->get_name(),
													),
													$_product,
													false
												);

												echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
												?>
											</div>

											<div class="Gstore-cart-card__subtotal">
												<span class="Gstore-cart-card__label"><?php esc_html_e( 'Subtotal', 'gstore' ); ?></span>
												<?php
												echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
													'woocommerce_cart_item_subtotal',
													WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ),
													$cart_item,
													$cart_item_key
												);
												?>
											</div>
										</div>
									</div>
								</article>
								<?php
							}
						}
						?>

						<?php do_action( 'woocommerce_cart_contents' ); ?>
					</div>

					<?php do_action( 'woocommerce_after_cart_contents' ); ?>
				</div>

				<aside class="Gstore-cart-sidebar">
					<?php if ( wc_coupons_enabled() ) : ?>
						<div class="Gstore-cart-card Gstore-cart-coupon">
							<div class="Gstore-cart-card__title">
								<h3><?php esc_html_e( 'Tem cupom de desconto?', 'gstore' ); ?></h3>
								<p><?php esc_html_e( 'Ative sua condição especial e veja o total atualizar automaticamente.', 'gstore' ); ?></p>
							</div>
							<label class="Gstore-cart-coupon__label" for="coupon_code"><?php esc_html_e( 'Código do cupom', 'gstore' ); ?></label>
							<div class="Gstore-cart-coupon__controls">
								<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'INSIRA AQUI', 'gstore' ); ?>" />
								<button type="submit" class="Gstore-cart-btn" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Aplicar', 'gstore' ); ?></button>
							</div>
							<?php do_action( 'woocommerce_cart_coupon' ); ?>
						</div>
					<?php endif; ?>

					<div class="Gstore-cart-card Gstore-cart-summary-card">
						<?php woocommerce_cart_totals(); ?>
					</div>

					<div class="Gstore-cart-card Gstore-cart-support-card">
						<span class="Gstore-cart-eyebrow"><?php esc_html_e( 'Atendimento dedicado', 'gstore' ); ?></span>
						<h3><?php echo esc_html( $att_title ); ?></h3>
						<p><?php esc_html_e( 'Nosso time acompanha seu pedido e tira dúvidas sobre entregas, pagamentos e personalizações. Conte conosco para um checkout seguro.', 'gstore' ); ?></p>
						<?php if ( $att_link ) : ?>
							<a class="Gstore-cart-support-card__link" href="<?php echo esc_url( $att_link ); ?>">
								<span><?php esc_html_e( 'Falar com o atendimento', 'gstore' ); ?></span>
								<svg class="Gstore-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
									<path d="M5 12h12.17l-4.58-4.59L13 6l7 6-7 6-1.41-1.41L17.17 13H5z" />
								</svg>
							</a>
						<?php endif; ?>
					</div>

					<?php
					$cart_totals_priority = has_action( 'woocommerce_cart_collaterals', 'woocommerce_cart_totals' );
					$cross_sell_priority  = has_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );

					if ( false !== $cart_totals_priority ) {
						remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cart_totals', $cart_totals_priority );
					}

					if ( false !== $cross_sell_priority ) {
						remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display', $cross_sell_priority );
					}

					ob_start();
					do_action( 'woocommerce_cart_collaterals' );
					$additional_collaterals = trim( ob_get_clean() );

					if ( false !== $cart_totals_priority ) {
						add_action( 'woocommerce_cart_collaterals', 'woocommerce_cart_totals', $cart_totals_priority );
					}

					if ( false !== $cross_sell_priority ) {
						add_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display', $cross_sell_priority );
					}

					if ( $additional_collaterals ) :
						?>
						<div class="Gstore-cart-card Gstore-cart-extra-card">
							<?php echo $additional_collaterals; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endif; ?>
				</aside>
			</div>

			<div class="Gstore-cart-form__actions">
				<button type="submit" class="<?php echo esc_attr( $button_class ); ?>" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Atualizar carrinho', 'gstore' ); ?></button>
				<a class="Gstore-cart-btn Gstore-cart-btn--link" href="<?php echo esc_url( $shop_url ); ?>">
					<?php esc_html_e( 'Continuar comprando', 'gstore' ); ?>
				</a>
				<?php do_action( 'woocommerce_cart_actions' ); ?>
				<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
			</div>

			<?php do_action( 'woocommerce_after_cart_table' ); ?>
		</form>

		<div class="Gstore-cart-cross-sells">
			<?php woocommerce_cross_sell_display(); ?>
		</div>
	</div>
</section>

<?php do_action( 'woocommerce_after_cart' ); ?>




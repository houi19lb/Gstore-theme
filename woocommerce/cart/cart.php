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
// Redireciona para catálogo ao invés de loja
$catalogo_page = get_page_by_path( 'catalogo' );
$shop_url     = function_exists( 'gstore_get_catalog_url' )
	? gstore_get_catalog_url()
	: ( $catalogo_page ? get_permalink( $catalogo_page->ID ) : home_url( '/catalogo/' ) );
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

		<?php
		$gstore_mixed_cart = function_exists( 'gstore_get_cart_checkout_groups' )
			? gstore_get_cart_checkout_groups()
			: ( function_exists( 'gstore_blu_get_cart_token_groups' ) ? gstore_blu_get_cart_token_groups() : array( 'is_mixed' => false ) );

		if ( ! empty( $gstore_mixed_cart['is_mixed'] ) ) :
			$gstore_mixed_groups      = isset( $gstore_mixed_cart['groups'] ) && is_array( $gstore_mixed_cart['groups'] ) ? $gstore_mixed_cart['groups'] : array();
			$gstore_mixed_headline    = ! empty( $gstore_mixed_cart['headline'] ) ? (string) $gstore_mixed_cart['headline'] : __( 'Produtos do programa não podem ser finalizados junto com outros produtos', 'gstore' );
			$gstore_mixed_description = ! empty( $gstore_mixed_cart['description'] ) ? (string) $gstore_mixed_cart['description'] : __( 'Escolha qual grupo de produtos deseja finalizar agora. Os demais serão removidos do carrinho.', 'gstore' );
		?>
		<div class="Gstore-cart-mixed-warning" role="alert" data-gstore-mixed-cart>
			<div class="Gstore-cart-mixed-warning__content">
				<div class="Gstore-cart-mixed-warning__headline">
					<div class="Gstore-cart-mixed-warning__icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
					</div>
					<h2><?php echo esc_html( $gstore_mixed_headline ); ?></h2>
				</div>
				<p><?php echo esc_html( $gstore_mixed_description ); ?></p>
			</div>
			<form class="Gstore-cart-mixed-warning__actions" method="post" action="<?php echo esc_url( $cart_url ); ?>" data-gstore-mixed-cart-form>
				<input type="hidden" name="gstore_cart_group_action" value="keep" />
				<?php wp_nonce_field( 'gstore_cart_token_group', 'nonce', false ); ?>
				<?php foreach ( $gstore_mixed_groups as $gstore_group_key => $gstore_group ) : ?>
					<?php
					if ( empty( $gstore_group['cart_item_keys'] ) || ! is_array( $gstore_group['cart_item_keys'] ) ) {
						continue;
					}
					$gstore_group_tone   = ! empty( $gstore_group['tone'] ) ? sanitize_html_class( (string) $gstore_group['tone'] ) : ( 'partner' === $gstore_group_key ? 'partner' : 'store' );
					$gstore_action_label = ! empty( $gstore_group['action_label'] ) ? (string) $gstore_group['action_label'] : ( ! empty( $gstore_group['label'] ) ? (string) $gstore_group['label'] : (string) $gstore_group_key );
					?>
					<button type="submit" name="keep_group" value="<?php echo esc_attr( $gstore_group_key ); ?>" class="Gstore-cart-btn Gstore-cart-btn--<?php echo esc_attr( $gstore_group_tone ); ?>" data-gstore-keep-group="<?php echo esc_attr( $gstore_group_key ); ?>">
						<?php echo esc_html( $gstore_action_label ); ?>
					</button>
				<?php endforeach; ?>
			</form>
		</div>
		<?php endif; ?>

		<form class="woocommerce-cart-form Gstore-cart-form" action="<?php echo esc_url( $cart_url ); ?>" method="post">
			<?php do_action( 'woocommerce_before_cart_table' ); ?>

			<div class="Gstore-cart-layout">
				<div class="Gstore-cart-main">

					<div class="Gstore-cart-list" role="list">
						<?php do_action( 'woocommerce_before_cart_contents' ); ?>

						<?php
						$gstore_freight_variations = array();
						if ( class_exists( '\GStore\Services\Freight_Service' ) ) {
							$gstore_freight_config = \GStore\Services\Freight_Service::get_config();
							$gstore_freight_variations = isset( $gstore_freight_config['variations'] ) && is_array( $gstore_freight_config['variations'] )
								? $gstore_freight_config['variations']
								: array();
						}

						foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
							$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
							$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

							if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
								$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
								$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );
								$cart_item_class   = implode( ' ', array_map( 'sanitize_html_class', explode( ' ', apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ) ) );

								$item_checkout_group = '';
								$item_checkout_data  = array();
								if ( ! empty( $gstore_mixed_cart['is_mixed'] ) ) {
									$checkout_groups = isset( $gstore_mixed_cart['groups'] ) && is_array( $gstore_mixed_cart['groups'] ) ? $gstore_mixed_cart['groups'] : array();
									foreach ( $checkout_groups as $checkout_group_key => $checkout_group_data ) {
										$checkout_group_keys = isset( $checkout_group_data['cart_item_keys'] ) && is_array( $checkout_group_data['cart_item_keys'] ) ? $checkout_group_data['cart_item_keys'] : array();
										if ( in_array( $cart_item_key, $checkout_group_keys, true ) ) {
											$item_checkout_group = (string) $checkout_group_key;
											$item_checkout_data  = $checkout_group_data;
											break;
										}
									}
								}
								$item_checkout_tone  = ! empty( $item_checkout_data['tone'] ) ? sanitize_html_class( (string) $item_checkout_data['tone'] ) : ( 'partner' === $item_checkout_group ? 'partner' : 'store' );
								$item_checkout_label = ! empty( $item_checkout_data['card_label'] ) ? (string) $item_checkout_data['card_label'] : ( ! empty( $item_checkout_data['label'] ) ? (string) $item_checkout_data['label'] : $item_checkout_group );

								$gstore_shipping_profile = array(
									'is_ammo' => false,
									'is_gun'  => false,
								);
								if ( class_exists( '\GStore\Services\Freight_Service' ) ) {
									$gstore_shipping_profile = \GStore\Services\Freight_Service::resolve_shipping_profile_for_cart_item(
										$cart_item,
										(string) $cart_item_key,
										$gstore_freight_variations
									);
								}
								?>

								<article class="Gstore-cart-card <?php echo esc_attr( $cart_item_class ); ?><?php echo '' !== $item_checkout_group ? ' Gstore-cart-card--checkout-group Gstore-cart-card--' . esc_attr( $item_checkout_tone ) : ''; ?>" role="listitem" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-quantity="<?php echo esc_attr( $cart_item['quantity'] ); ?>" data-shipping-is-ammo="<?php echo ! empty( $gstore_shipping_profile['is_ammo'] ) ? '1' : '0'; ?>" data-shipping-is-gun="<?php echo ! empty( $gstore_shipping_profile['is_gun'] ) ? '1' : '0'; ?>"<?php echo '' !== $item_checkout_group ? ' data-checkout-group="' . esc_attr( $item_checkout_group ) . '" data-token-group="' . esc_attr( $item_checkout_group ) . '"' : ''; ?>>
									<div class="Gstore-cart-card__content">
										<div class="Gstore-cart-card__media-column">
											<?php if ( '' !== $item_checkout_group ) : ?>
												<div class="Gstore-cart-card__group-label Gstore-cart-card__group-label--<?php echo esc_attr( $item_checkout_tone ); ?>">
													<?php echo esc_html( $item_checkout_label ); ?>
												</div>
											<?php endif; ?>
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
													'<a href="%s" class="remove Gstore-cart-card__remove" aria-label="%s" data-product_id="%s" data-product_sku="%s"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg><span class="Gstore-sr-only">%s</span></a>',
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

										<?php
										$freight_rates = isset( $cart_item['gstore_shipping_rates'] ) ? $cart_item['gstore_shipping_rates'] : array();
										if ( function_exists( 'gstore_normalize_cart_rates' ) ) {
											$freight_rates = gstore_normalize_cart_rates( $freight_rates );
										}
										$freight_options = array();
										foreach ( $freight_rates as $rate ) {
											if ( ! empty( $rate['mode'] ) ) {
												$freight_options[] = $rate['mode'];
											}
										}
										$selected_mode = function_exists( 'gstore_get_cart_item_shipping_mode' )
											? gstore_get_cart_item_shipping_mode( $cart_item )
											: 'land';
										$selected_rate_id = function_exists( 'gstore_get_cart_item_selected_shipping_rate' )
											? gstore_get_cart_item_selected_shipping_rate( $cart_item )
											: '';

										if ( is_cart() && isset( $_POST['gstore_shipping_mode'][ $cart_item_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
											$posted_mode = sanitize_text_field( wp_unslash( $_POST['gstore_shipping_mode'][ $cart_item_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
											if ( function_exists( 'gstore_normalize_shipping_mode' ) ) {
												$selected_mode = gstore_normalize_shipping_mode( $posted_mode );
												if ( '' === $selected_mode ) {
													$selected_mode = 'land';
												}
											} else {
												$selected_mode = in_array( $posted_mode, array( 'air', 'pickup' ), true ) ? $posted_mode : 'land';
											}
										}
										if ( is_cart() && isset( $_POST['gstore_selected_shipping_rate'][ $cart_item_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
											$selected_rate_id = sanitize_text_field( wp_unslash( $_POST['gstore_selected_shipping_rate'][ $cart_item_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
										}
										$freight_costs = array();
										foreach ( $freight_rates as $rate ) {
											$mode = isset( $rate['mode'] ) ? $rate['mode'] : '';
											$freight_costs[ $mode ] = isset( $rate['cost_formatted'] ) ? $rate['cost_formatted'] : '';
										}

										if ( ! empty( $freight_options ) && ! in_array( $selected_mode, $freight_options, true ) ) {
											$selected_mode = $freight_options[0];
										}

										$has_shipping_postcode = function_exists( 'WC' ) && WC()->customer && WC()->customer->get_shipping_postcode();
										?>

										<div class="Gstore-cart-card__shipping" data-gstore-shipping-item>
											<span class="Gstore-cart-card__label"><?php esc_html_e( 'Frete', 'gstore' ); ?></span>
											<input type="hidden" name="gstore_shipping_rates[<?php echo esc_attr( $cart_item_key ); ?>]" value="<?php echo esc_attr( wp_json_encode( $freight_rates ) ); ?>" />

											<?php if ( ! empty( $freight_options ) ) : ?>
												<?php if ( count( $freight_options ) > 1 ) : ?>
													<div class="Gstore-cart-card__shipping-options" data-gstore-shipping-options>
														<?php foreach ( $freight_rates as $rate ) : ?>
															<?php
															$mode = isset( $rate['mode'] ) ? $rate['mode'] : 'land';
															$rate_id = isset( $rate['rate_id'] ) ? (string) $rate['rate_id'] : ( isset( $rate['id'] ) ? (string) $rate['id'] : '' );
															$label = isset( $rate['label'] ) ? $rate['label'] : ( function_exists( 'gstore_get_cart_item_shipping_label' ) ? gstore_get_cart_item_shipping_label( $mode ) : $mode );
															$is_checked = '' !== $selected_rate_id
																? $selected_rate_id === $rate_id
																: $selected_mode === $mode;
															?>
															<label class="Gstore-cart-card__shipping-option">
																<input
																	type="radio"
																	name="gstore_selected_shipping_rate[<?php echo esc_attr( $cart_item_key ); ?>]"
																	value="<?php echo esc_attr( $rate_id ); ?>"
																	data-gstore-mode="<?php echo esc_attr( $mode ); ?>"
																	<?php checked( $is_checked, true ); ?>
																/>
																<span class="Gstore-cart-card__shipping-text"><?php echo esc_html( $label ); ?></span>
																<span class="Gstore-cart-card__shipping-price">
																	<?php echo $has_shipping_postcode && ! empty( $freight_costs[ $mode ] ) ? wp_kses_post( $freight_costs[ $mode ] ) : '-'; ?>
																</span>
															</label>
														<?php endforeach; ?>
													</div>
												<?php else : ?>
													<?php
													$only_rate = reset( $freight_rates );
													$only_mode = isset( $only_rate['mode'] ) ? $only_rate['mode'] : 'land';
													$only_label = isset( $only_rate['label'] ) ? $only_rate['label'] : ( function_exists( 'gstore_get_cart_item_shipping_label' ) ? gstore_get_cart_item_shipping_label( $only_mode ) : $only_mode );
													?>
													<div class="Gstore-cart-card__shipping-fixed" data-gstore-shipping-fixed>
														<span class="Gstore-cart-card__shipping-text"><?php echo esc_html( $only_label ); ?></span>
														<span class="Gstore-cart-card__shipping-price">
															<?php echo $has_shipping_postcode && ! empty( $freight_costs[ $only_mode ] ) ? wp_kses_post( $freight_costs[ $only_mode ] ) : '-'; ?>
														</span>
														<input type="hidden" name="gstore_selected_shipping_rate[<?php echo esc_attr( $cart_item_key ); ?>]" value="<?php echo esc_attr( isset( $only_rate['rate_id'] ) ? $only_rate['rate_id'] : ( $only_rate['id'] ?? '' ) ); ?>" />
														<input type="hidden" name="gstore_shipping_mode[<?php echo esc_attr( $cart_item_key ); ?>]" value="<?php echo esc_attr( $only_mode ); ?>" />
													</div>
												<?php endif; ?>
											<?php else : ?>
												<div class="Gstore-cart-card__shipping-fixed" data-gstore-shipping-fixed>
													<span class="Gstore-cart-card__shipping-text"><?php esc_html_e( 'Calcule o frete para ver os valores.', 'gstore' ); ?></span>
												</div>
											<?php endif; ?>
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

					<?php gstore_render_cart_product_upsells( 'cart' ); ?>

					<?php do_action( 'woocommerce_after_cart_contents' ); ?>
				</div>

				<aside class="Gstore-cart-sidebar">
					<div class="Gstore-cart-card gstore-shipping-calculator gstore-shipping-calculator--cart">
						<h3 class="gstore-shipping-calculator__title">
							<svg class="gstore-shipping-calculator__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
								<path d="M3 7h11v10H3z" fill="currentColor" opacity="0.2"></path>
								<path d="M14 7h4l3 4v6h-7V7z" fill="currentColor"></path>
								<circle cx="7" cy="19" r="2" fill="currentColor"></circle>
								<circle cx="17" cy="19" r="2" fill="currentColor"></circle>
							</svg>
							<?php esc_html_e( 'Calcular frete', 'gstore' ); ?>
						</h3>
						<div class="gstore-shipping-calculator__form">
							<input
								type="text"
								class="gstore-shipping-calculator__cep"
								placeholder="<?php esc_attr_e( '00000-000', 'gstore' ); ?>"
								maxlength="9"
								aria-label="<?php esc_attr_e( 'CEP para cálculo de frete', 'gstore' ); ?>"
							/>
							<button type="button" class="gstore-shipping-calculator__button">
								<?php esc_html_e( 'Calcular frete', 'gstore' ); ?>
							</button>
						</div>
						<div class="gstore-shipping-calculator__result" role="region" aria-live="polite"></div>
						<div class="gstore-shipping-calculator__error" role="alert"></div>
					</div>
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

	</div>
</section>

<?php do_action( 'woocommerce_after_cart' ); ?>

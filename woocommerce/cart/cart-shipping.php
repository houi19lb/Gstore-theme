<?php
/**
 * Shipping Methods Display - Gstore layout.
 *
 * @package WooCommerce\Templates
 * @version 8.8.0
 */

defined( 'ABSPATH' ) || exit;

$formatted_destination    = isset( $formatted_destination ) ? $formatted_destination : WC()->countries->get_formatted_address( $package['destination'], ', ' );
$has_calculated_shipping  = ! empty( $has_calculated_shipping );
$show_shipping_calculator = ! empty( $show_shipping_calculator );
$calculator_text          = '';

/*
 * Na página do carrinho, o tema Gstore gerencia o frete por item (per-item shipping)
 * com sua própria calculadora de CEP customizada (.gstore-shipping-calculator--cart).
 * Por isso, não renderizamos a UI padrão do WooCommerce (radio buttons de métodos de envio,
 * destino formatado e formulário shipping-calculator-form) para evitar duplicação.
 *
 * Apenas mantemos os inputs hidden dos métodos de envio selecionados para que o WooCommerce
 * continue rastreando o shipping method na sessão.
 */
if ( function_exists( 'is_cart' ) && is_cart() ) :
?>
<div class="gstore-shipping-totals woocommerce-shipping-totals shipping" data-title="<?php echo esc_attr( $package_name ); ?>" style="display:none;">
	<?php if ( ! empty( $available_methods ) && is_array( $available_methods ) ) : ?>
		<?php foreach ( $available_methods as $method ) : ?>
			<?php if ( 1 < count( $available_methods ) ) : ?>
				<input type="radio" name="shipping_method[<?php echo esc_attr( $index ); ?>]" data-index="<?php echo esc_attr( $index ); ?>" id="shipping_method_<?php echo esc_attr( $index . '_' . sanitize_title( $method->id ) ); ?>" value="<?php echo esc_attr( $method->id ); ?>" class="shipping_method" <?php checked( $method->id, $chosen_method ); ?> style="display:none;" />
			<?php else : ?>
				<input type="hidden" name="shipping_method[<?php echo esc_attr( $index ); ?>]" data-index="<?php echo esc_attr( $index ); ?>" id="shipping_method_<?php echo esc_attr( $index . '_' . sanitize_title( $method->id ) ); ?>" value="<?php echo esc_attr( $method->id ); ?>" class="shipping_method" />
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
<?php
	return;
endif;
?>

<div class="gstore-shipping-totals woocommerce-shipping-totals shipping" data-title="<?php echo esc_attr( $package_name ); ?>">
	<div class="gstore-shipping-totals__header">
		<span class="gstore-shipping-totals__title"><?php echo wp_kses_post( $package_name ); ?></span>
		<?php if ( $show_package_details && ! empty( $package_details ) ) : ?>
			<span class="gstore-shipping-totals__meta"><?php echo esc_html( $package_details ); ?></span>
		<?php endif; ?>
	</div>

	<div class="gstore-shipping-totals__content" data-title="<?php echo esc_attr( $package_name ); ?>">
		<?php if ( ! empty( $available_methods ) && is_array( $available_methods ) ) : ?>
			<ul id="shipping_method" class="woocommerce-shipping-methods gstore-shipping-methods" role="list">
				<?php foreach ( $available_methods as $method ) : ?>
					<li class="gstore-shipping-method">
						<div class="gstore-shipping-method__option">
							<?php if ( 1 < count( $available_methods ) ) : ?>
								<input type="radio" name="shipping_method[<?php echo esc_attr( $index ); ?>]" data-index="<?php echo esc_attr( $index ); ?>" id="shipping_method_<?php echo esc_attr( $index . '_' . sanitize_title( $method->id ) ); ?>" value="<?php echo esc_attr( $method->id ); ?>" class="shipping_method" <?php checked( $method->id, $chosen_method ); ?> />
							<?php else : ?>
								<input type="hidden" name="shipping_method[<?php echo esc_attr( $index ); ?>]" data-index="<?php echo esc_attr( $index ); ?>" id="shipping_method_<?php echo esc_attr( $index . '_' . sanitize_title( $method->id ) ); ?>" value="<?php echo esc_attr( $method->id ); ?>" class="shipping_method" />
							<?php endif; ?>
							<label class="gstore-shipping-method__label" for="shipping_method_<?php echo esc_attr( $index . '_' . sanitize_title( $method->id ) ); ?>">
								<?php echo wp_kses_post( wc_cart_totals_shipping_method_label( $method ) ); ?>
							</label>
							<?php do_action( 'woocommerce_after_shipping_rate', $method, $index ); ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>

		<?php elseif ( ! $has_calculated_shipping || ! $formatted_destination ) : ?>

			<?php if ( 'no' === get_option( 'woocommerce_enable_shipping_calc' ) ) : ?>
				<p class="woocommerce-shipping-notices"><?php echo wp_kses_post( apply_filters( 'woocommerce_shipping_not_enabled_on_cart_html', __( 'Os custos de envio são calculados no checkout.', 'woocommerce' ) ) ); ?></p>
			<?php else : ?>
				<p class="woocommerce-shipping-notices"><?php echo wp_kses_post( apply_filters( 'woocommerce_shipping_may_be_available_html', __( 'Informe seu endereço para ver as opções de envio.', 'woocommerce' ) ) ); ?></p>
			<?php endif; ?>

		<?php else : ?>

			<p class="woocommerce-shipping-notices">
				<?php echo wp_kses_post( apply_filters( 'woocommerce_no_shipping_available_html', __( 'Não há opções de envio disponíveis para o endereço informado. Verifique os dados ou fale conosco.', 'woocommerce' ) ) ); ?>
			</p>

		<?php endif; ?>

		<?php if ( $show_shipping_calculator ) : ?>
			<?php woocommerce_shipping_calculator( $calculator_text ); ?>
		<?php endif; ?>
	</div>
</div>


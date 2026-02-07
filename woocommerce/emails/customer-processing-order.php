<?php
/**
 * Customer processing order email (template do tema)
 *
 * Conteúdo adicional exibido logo abaixo do cabeçalho ("Obrigado pelo seu pedido").
 * Copiado e ajustado a partir do template do WooCommerce.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email_improvements_enabled = false;
if ( class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
	$email_improvements_enabled = \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'email_improvements' );
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php
/**
 * Conteúdo adicional (definido em WooCommerce > Configurações > E-mails > Processando pedido) — logo abaixo do heading.
 */
if ( ! empty( $additional_content ) ) : ?>
	<div style="margin: 0 0 24px;">
		<?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?>
	</div>
<?php endif; ?>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>
<p>
<?php
if ( ! empty( $order->get_billing_first_name() ) ) {
	/* translators: %s: Customer first name */
	printf( esc_html__( 'Olá %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) );
} else {
	printf( esc_html__( 'Olá,', 'woocommerce' ) );
}
?>
</p>
<?php if ( $email_improvements_enabled ) : ?>
	<p><?php esc_html_e( 'Só para você saber — recebemos seu pedido e ele está sendo processado.', 'woocommerce' ); ?></p>
	<p><?php esc_html_e( 'Aqui está um lembrete do que você pediu:', 'woocommerce' ); ?></p>
<?php else : ?>
	<?php /* translators: %s: Order number */ ?>
	<p><?php printf( esc_html__( 'Só para você saber — recebemos seu pedido #%s e ele está sendo processado:', 'woocommerce' ), esc_html( $order->get_order_number() ) ); ?></p>
<?php endif; ?>
<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );

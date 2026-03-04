<?php
/**
 * Lost password confirmation message.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/lost-password-confirmation.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$default_confirmation_message = __(
	'Enviamos as instrucoes para redefinir sua senha. Verifique sua caixa de entrada e tambem o spam.',
	'gstore'
);

$confirmation_message = $default_confirmation_message;

if ( isset( $lost_password_confirmation_message ) && is_string( $lost_password_confirmation_message ) && '' !== trim( $lost_password_confirmation_message ) ) {
	$confirmation_message = $lost_password_confirmation_message;
}

do_action( 'woocommerce_before_lost_password_confirmation_message' );
?>

<div class="gstore-lost-password gstore-lost-password--confirmation">
	<section class="gstore-lost-password__confirmation-card" role="status" aria-live="polite">
		<h2 class="gstore-lost-password__confirmation-title">
			<?php esc_html_e( 'E-mail enviado com sucesso', 'gstore' ); ?>
		</h2>

		<p class="gstore-lost-password__confirmation-text">
			<?php echo esc_html( wp_strip_all_tags( $confirmation_message ) ); ?>
		</p>

		<p class="gstore-lost-password__confirmation-text">
			<?php esc_html_e( 'Se nao chegar em alguns minutos, tente novamente.', 'gstore' ); ?>
		</p>

		<div class="gstore-lost-password__confirmation-actions">
			<a class="button gstore-lost-password__home-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php esc_html_e( 'Ir para a pagina inicial', 'gstore' ); ?>
			</a>
		</div>
	</section>
</div>

<?php do_action( 'woocommerce_after_lost_password_confirmation_message' ); ?>

<?php
/**
 * Lost Password Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-lost-password.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$existing_account_flow = false;

if ( isset( $_GET['gstore_existing_account'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$existing_account_flow = '1' === sanitize_text_field( wp_unslash( $_GET['gstore_existing_account'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

$prefilled_user_login = '';

if ( ! empty( $_POST['user_login'] ) && is_string( $_POST['user_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$prefilled_user_login = wp_unslash( $_POST['user_login'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
} elseif ( function_exists( 'WC' ) && WC()->session ) {
	$session_email = WC()->session->get( 'gstore_existing_account_email' );

	if ( is_string( $session_email ) && is_email( $session_email ) ) {
		$prefilled_user_login = $session_email;
	}
}

if ( function_exists( 'WC' ) && WC()->session ) {
	WC()->session->set( 'gstore_existing_account_email', null );
}

do_action( 'woocommerce_before_lost_password_form' );
?>

<div class="gstore-lost-password">
	<form method="post" class="woocommerce-ResetPassword lost_reset_password gstore-lost-password__form">
		<?php if ( $existing_account_flow ) : ?>
			<div class="gstore-lost-password__notice" role="status" aria-live="polite">
				<p>
					<?php esc_html_e( 'Este e-mail já está cadastrado. Para acessar sua conta, recupere sua senha abaixo. Depois de clicar em "Redefinir senha", você receberá um e-mail com o link para criar uma nova senha.', 'gstore' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<p class="gstore-lost-password__intro">
			<?php echo wp_kses_post( apply_filters( 'woocommerce_lost_password_message', __( 'Perdeu sua senha? Informe seu nome de usuário ou endereço de e-mail. Você receberá um link por e-mail para criar uma nova senha.', 'woocommerce' ) ) ); ?>
		</p>

		<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
			<label for="user_login"><?php esc_html_e( 'Nome de usuário ou e-mail', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Obrigatorio', 'gstore' ); ?></span></label>
			<input class="woocommerce-Input woocommerce-Input--text input-text" type="text" name="user_login" id="user_login" autocomplete="username" value="<?php echo esc_attr( $prefilled_user_login ); ?>" required aria-required="true" />
		</p>

		<div class="clear"></div>

		<?php do_action( 'woocommerce_lostpassword_form' ); ?>

		<p class="woocommerce-form-row form-row gstore-lost-password__actions">
			<button type="submit" class="woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" value="<?php esc_attr_e( 'Reset password', 'woocommerce' ); ?>"><?php esc_html_e( 'Redefinir senha', 'gstore' ); ?></button>
		</p>

		<input type="hidden" name="wc_reset_password" value="true" />

		<?php wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' ); ?>
	</form>
</div>

<?php do_action( 'woocommerce_after_lost_password_form' ); ?>

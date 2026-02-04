<?php
/**
 * E-mail de redefinição de senha (cliente).
 *
 * Este template pode ser editado em seu tema em:
 * yourtheme/woocommerce/emails/customer-reset-password.php
 *
 * Variáveis disponíveis: $user_login, $reset_url, $blogname, $email_heading, $email, $additional_content.
 *
 * @package GStore
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer username */ ?>
<p><?php printf( esc_html__( 'Olá %s,', 'gstore' ), esc_html( $user_login ) ); ?></p>
<p><?php printf( esc_html__( 'Foi solicitada a redefinição da senha da sua conta em %s.', 'gstore' ), esc_html( $blogname ) ); ?></p>
<p><?php esc_html_e( 'Clique no link abaixo para definir uma nova senha:', 'gstore' ); ?></p>
<p><a href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Redefinir senha', 'gstore' ); ?></a></p>
<p><?php esc_html_e( 'Se você não solicitou isso, ignore este e-mail. O link expira em breve.', 'gstore' ); ?></p>

<?php
if ( ! empty( $additional_content ) ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>

<?php
/**
 * Pix payment instructions in checkout and My Account.
 *
 * @package Gstore
 */

defined( 'ABSPATH' ) || exit;

$order_status      = $args['order_status'];
$status            = strtolower( (string) $args['payment_status'] );
$expires_at_ts     = (int) $args['expires_at'];
$qr_code_base64    = $args['qr_code_base64'];
$emv               = $args['emv'];
$transaction_token = $args['transaction_token'];

$code_expired = ( $expires_at_ts && $expires_at_ts <= time() ) || 'expired' === $status;
$status_class = 'pix-box--pending';
$status_text  = __( 'Aguardando pagamento', 'gstore' );
$closed_texts = array(
	'cancelled' => __( 'Pedido cancelado', 'gstore' ),
	'failed'    => __( 'Falha no pagamento', 'gstore' ),
	'refunded'  => __( 'Pedido reembolsado', 'gstore' ),
);

if ( isset( $closed_texts[ $order_status ] ) ) {
	$status_class = 'pix-box--expired';
	$status_text  = $closed_texts[ $order_status ];
} elseif ( $args['paid'] || 'paid' === $status ) {
	$status_class = 'pix-box--processed';
	$status_text  = __( 'Pagamento aprovado', 'gstore' );
} elseif ( $code_expired ) {
	$status_class .= ' pix-box--client-expired';
	$status_text   = __( 'Aguardando confirmação do pagamento', 'gstore' );
} elseif ( empty( $qr_code_base64 ) && empty( $emv ) ) {
	echo '<div class="woocommerce-info">' . esc_html__( 'Seu pedido foi recebido. Os dados do Pix ainda não estão disponíveis; nossa equipe está verificando e você pode acompanhar por esta página.', 'gstore' ) . '</div>';
	return;
}

$show_code         = 'pix-box--pending' === $status_class;
$formatted_total   = wc_price( $args['total'], array( 'decimals' => 2 ) );
$formatted_expires = $expires_at_ts ? wp_date( 'd/m/Y H:i', $expires_at_ts ) : '';
$emv_id            = 'pixCode-' . $args['order_id'];
?>
<div class="pix-box <?php echo esc_attr( $status_class ); ?>" data-expires-at="<?php echo esc_attr( $show_code ? $expires_at_ts : 0 ); ?>" data-order-status="<?php echo esc_attr( $order_status ); ?>">
	<div class="pix-box__header">
		<div>
			<h2 class="pix-box__title"><?php esc_html_e( 'Pagamento via Pix', 'gstore' ); ?></h2>
			<?php if ( $show_code ) : ?>
				<p class="pix-box__subtitle">
					<?php esc_html_e( 'Escaneie o QR Code ou use o código Pix Copia e Cola abaixo para concluir seu pagamento.', 'gstore' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( $status || ! $show_code ) : ?>
			<span class="pix-box__status">
				<?php echo esc_html( $status_text ); ?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( $show_code && is_order_received_page() ) : ?>
		<?php $args['render_confirmation_notice'](); ?>
	<?php endif; ?>

	<div class="pix-box__countdown" data-role="pix-countdown"></div>
	<div class="pix-box__expired-message" data-role="pix-expired-message">
		<?php esc_html_e( 'O código Pix expirou. Se você já pagou, aguarde a confirmação do pagamento.', 'gstore' ); ?>
	</div>

	<div class="pix-box__content">
		<?php if ( $show_code && $qr_code_base64 ) : ?>
			<div class="pix-box__qr">
				<img src="data:image/png;base64,<?php echo esc_attr( $qr_code_base64 ); ?>" alt="<?php esc_attr_e( 'QR Code para pagamento Pix', 'gstore' ); ?>">
				<?php if ( $formatted_expires ) : ?>
					<p class="pix-box__expires">
						<?php echo wp_kses_post( sprintf( __( 'Válido até: %s', 'gstore' ), '<strong>' . esc_html( $formatted_expires ) . '</strong>' ) ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="pix-box__details">
			<div class="pix-box__amount">
				<span><?php esc_html_e( 'Total do pedido', 'gstore' ); ?></span>
				<strong><?php echo wp_kses_post( $formatted_total ); ?></strong>
			</div>

			<?php if ( $show_code && $emv ) : ?>
				<label class="pix-box__label" for="<?php echo esc_attr( $emv_id ); ?>">
					<?php esc_html_e( 'Código Pix (copiar e colar)', 'gstore' ); ?>
				</label>

				<div class="pix-box__code-group">
					<textarea id="<?php echo esc_attr( $emv_id ); ?>" class="pix-box__code" readonly wrap="off" spellcheck="false" autocapitalize="off" autocomplete="off" autocorrect="off"><?php echo esc_textarea( $emv ); ?></textarea>

					<button type="button" class="pix-box__copy" data-copy-target="#<?php echo esc_attr( $emv_id ); ?>">
						<?php esc_html_e( 'Copiar código', 'gstore' ); ?>
					</button>
				</div>

				<p class="pix-box__meta pix-box__meta--muted">
					<?php esc_html_e( 'Esse código não é um link do navegador. Cole-o no app do banco em Pix Copia e Cola.', 'gstore' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $show_code && $transaction_token ) : ?>
				<p class="pix-box__meta">
					<?php echo esc_html( sprintf( __( 'Token: %s', 'gstore' ), $transaction_token ) ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $show_code && $status ) : ?>
				<p class="pix-box__meta pix-box__meta--muted">
					<?php echo esc_html( sprintf( __( 'Status: %s', 'gstore' ), $status ) ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>

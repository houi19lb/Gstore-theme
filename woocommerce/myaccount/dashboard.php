<?php
/** Customer dashboard; all order actions lead to the existing view-order endpoint. */
defined( 'ABSPATH' ) || exit;
if ( gstore_account_is_support() ) {
	wc_get_template( 'myaccount/account-support.php' );
	return;
}
$current_user = wp_get_current_user();
$orders = gstore_account_dashboard_orders();
$latest = $orders[0] ?? null;
$counts = gstore_account_order_counts();
$name = $current_user->first_name ?: $current_user->display_name;
?>
<div class="gstore-account-dashboard">
	<div class="gstore-account-heading">
		<p class="gstore-account-eyebrow"><?php esc_html_e( 'Sua conta', 'gstore' ); ?></p>
		<h1><?php printf( esc_html__( 'Bom ter você por aqui, %s.', 'gstore' ), esc_html( $name ) ); ?></h1>
		<p><?php esc_html_e( 'Tudo sobre sua conta, em um só lugar.', 'gstore' ); ?></p>
	</div>
	<div class="gstore-account-dashboard-grid">
		<div class="gstore-account-primary">
			<section class="gstore-account-card gstore-account-latest">
				<div class="gstore-account-card-top"><h2><?php esc_html_e( 'Seu pedido mais recente', 'gstore' ); ?></h2></div>
				<?php if ( $latest ) : $progress = gstore_account_order_progress( $latest ); ?>
					<div class="gstore-account-order-title"><h3><?php printf( esc_html__( 'Pedido #%s', 'gstore' ), esc_html( $latest->get_order_number() ) ); ?></h3><span class="gstore-account-status"><?php echo esc_html( $progress['label'] ); ?></span></div>
					<p class="gstore-account-order-meta"><?php if ( $latest->get_date_created() ) : ?><time datetime="<?php echo esc_attr( $latest->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $latest->get_date_created() ) ); ?></time> · <?php endif; ?><?php echo wp_kses_post( $latest->get_formatted_order_total() ); ?></p>
					<?php if ( $progress['show_timeline'] ) : ?>
					<ol class="gstore-account-progress" aria-label="<?php esc_attr_e( 'Etapas do pedido', 'gstore' ); ?>">
						<?php $i = 0; foreach ( $progress['stages'] as $stage_label ) : $current = $i === $progress['index']; ?>
						<li class="<?php echo esc_attr( $i < $progress['index'] ? 'is-complete' : ( $current ? 'is-current' : '' ) ); ?>" <?php echo $current ? 'aria-current="step"' : ''; ?>><span class="gstore-account-step-dot" aria-hidden="true"><?php echo $i < $progress['index'] ? '✓' : '•'; ?></span><span><?php echo esc_html( $stage_label ); ?></span></li>
						<?php ++$i; endforeach; ?>
					</ol>
					<?php endif; ?>
					<div class="gstore-account-note"><strong><?php echo esc_html( $progress['label'] ); ?></strong><p><?php echo esc_html( $progress['message'] ); ?></p></div>
					<a class="gstore-account-button" href="<?php echo esc_url( $latest->get_view_order_url() ); ?>"><?php esc_html_e( 'Acompanhar pedido', 'gstore' ); ?> <span aria-hidden="true">→</span></a>
				<?php else : ?>
					<div class="gstore-account-empty"><span class="gstore-account-icon" aria-hidden="true"><i class="fa-solid fa-box-open"></i></span><h3><?php esc_html_e( 'Seus pedidos vão aparecer aqui', 'gstore' ); ?></h3><p><?php esc_html_e( 'Quando você fizer um pedido, poderá acompanhar cada etapa nesta área.', 'gstore' ); ?></p></div>
					<a class="gstore-account-button" href="<?php echo esc_url( gstore_get_catalog_url() ); ?>"><?php esc_html_e( 'Explorar a loja', 'gstore' ); ?> <span aria-hidden="true">→</span></a>
				<?php endif; ?>
			</section>
			<dl class="gstore-account-stats">
				<?php foreach ( array( 'total' => __( 'Total de pedidos', 'gstore' ), 'active' => __( 'Em andamento', 'gstore' ), 'completed' => __( 'Concluídos', 'gstore' ) ) as $key => $label ) : ?>
				<div class="gstore-account-card"><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $counts[ $key ] ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
			<div class="gstore-account-shortcuts">
				<a class="gstore-account-card gstore-account-shortcut" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><span class="gstore-account-icon" aria-hidden="true"><i class="fa-solid fa-box"></i></span><span><strong><?php printf( esc_html__( '%d pedidos', 'gstore' ), wc_get_customer_order_count( $current_user->ID ) ); ?></strong><small><?php esc_html_e( 'Veja seu histórico completo', 'gstore' ); ?></small></span><span aria-hidden="true">→</span></a>
				<a class="gstore-account-card gstore-account-shortcut" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>"><span class="gstore-account-icon" aria-hidden="true"><i class="fa-regular fa-user"></i></span><span><strong><?php esc_html_e( 'Meus dados', 'gstore' ); ?></strong><small><?php esc_html_e( 'Informações, senha e endereços', 'gstore' ); ?></small></span><span aria-hidden="true">→</span></a>
			</div>
		</div>
		<div class="gstore-account-aside">
			<section class="gstore-account-card"><div class="gstore-account-card-top"><h2><?php esc_html_e( 'Seus últimos pedidos', 'gstore' ); ?></h2><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'Ver todos', 'gstore' ); ?></a></div>
				<?php foreach ( $orders as $recent ) : $recent_progress = $recent->get_id() === ( $latest ? $latest->get_id() : 0 ) ? $progress : gstore_account_order_progress( $recent ); ?>
				<a class="gstore-account-update" href="<?php echo esc_url( $recent->get_view_order_url() ); ?>"><i class="fa-solid fa-box" aria-hidden="true"></i><span><strong><?php printf( esc_html__( 'Pedido #%s', 'gstore' ), esc_html( $recent->get_order_number() ) ); ?></strong><small><?php echo esc_html( $recent_progress['label'] ); ?></small></span><span aria-hidden="true">→</span></a>
				<?php endforeach; ?>
				<?php if ( ! $orders ) : ?><p><?php esc_html_e( 'Você ainda não tem pedidos.', 'gstore' ); ?></p><?php endif; ?>
			</section>
			<section class="gstore-account-card gstore-account-help"><span class="gstore-account-icon" aria-hidden="true"><i class="fa-solid fa-headset"></i></span><h2><?php esc_html_e( 'Precisa de ajuda?', 'gstore' ); ?></h2><p><?php esc_html_e( 'Escolha um dos canais da loja. Nossa equipe está aqui para ajudar.', 'gstore' ); ?></p><a class="gstore-account-button gstore-account-button--secondary" href="<?php echo esc_url( gstore_account_support_url() ); ?>"><?php esc_html_e( 'Falar com atendimento', 'gstore' ); ?> <span aria-hidden="true">→</span></a></section>
		</div>
	</div>
	<?php do_action( 'woocommerce_account_dashboard' ); ?>
</div>

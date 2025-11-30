<?php
/**
 * My Account Dashboard - GStore Custom
 *
 * @package GStore
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user = wp_get_current_user();
$customer = new WC_Customer( $current_user->ID );

// Estatísticas
$order_count = wc_get_customer_order_count( $current_user->ID );

// Pegar último pedido
$customer_orders = wc_get_orders( array(
	'customer_id' => $current_user->ID,
	'limit'       => 3,
	'status'      => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' ),
) );
?>

<div class="gstore-dashboard">
	
	<!-- Welcome Section -->
	<div class="gstore-dashboard__welcome">
		<h1 class="gstore-dashboard__title">
			<?php 
			/* translators: %s: customer first name */
			printf( 
				esc_html__( 'Olá, %s!', 'gstore' ), 
				'<span class="gstore-dashboard__name">' . esc_html( $current_user->display_name ) . '</span>' 
			); 
			?>
		</h1>
		<p class="gstore-dashboard__subtitle">
			<?php esc_html_e( 'Gerencie sua conta, acompanhe seus pedidos e atualize suas informações.', 'gstore' ); ?>
		</p>
	</div>

	<!-- Stats Cards -->
	<div class="gstore-dashboard__stats">
		<div class="gstore-dashboard__stat-card">
			<div class="gstore-dashboard__stat-icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
			</div>
			<div class="gstore-dashboard__stat-content">
				<span class="gstore-dashboard__stat-value"><?php echo esc_html( $order_count ); ?></span>
				<span class="gstore-dashboard__stat-label"><?php esc_html_e( 'Pedidos', 'gstore' ); ?></span>
			</div>
		</div>

		<div class="gstore-dashboard__stat-card">
			<div class="gstore-dashboard__stat-icon gstore-dashboard__stat-icon--success">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
			</div>
			<div class="gstore-dashboard__stat-content">
				<span class="gstore-dashboard__stat-value"><?php echo esc_html( $customer->get_date_created() ? $customer->get_date_created()->date_i18n( 'M/Y' ) : '-' ); ?></span>
				<span class="gstore-dashboard__stat-label"><?php esc_html_e( 'Cliente desde', 'gstore' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="gstore-dashboard__section">
		<h2 class="gstore-dashboard__section-title"><?php esc_html_e( 'Ações rápidas', 'gstore' ); ?></h2>
		<div class="gstore-dashboard__actions">
			<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="gstore-dashboard__action-card">
				<div class="gstore-dashboard__action-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
				</div>
				<div class="gstore-dashboard__action-content">
					<span class="gstore-dashboard__action-title"><?php esc_html_e( 'Meus Pedidos', 'gstore' ); ?></span>
					<span class="gstore-dashboard__action-desc"><?php esc_html_e( 'Acompanhe seus pedidos', 'gstore' ); ?></span>
				</div>
				<span class="gstore-dashboard__action-arrow">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
				</span>
			</a>

			<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>" class="gstore-dashboard__action-card">
				<div class="gstore-dashboard__action-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
				</div>
				<div class="gstore-dashboard__action-content">
					<span class="gstore-dashboard__action-title"><?php esc_html_e( 'Endereços', 'gstore' ); ?></span>
					<span class="gstore-dashboard__action-desc"><?php esc_html_e( 'Gerencie seus endereços', 'gstore' ); ?></span>
				</div>
				<span class="gstore-dashboard__action-arrow">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
				</span>
			</a>

			<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>" class="gstore-dashboard__action-card">
				<div class="gstore-dashboard__action-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
				</div>
				<div class="gstore-dashboard__action-content">
					<span class="gstore-dashboard__action-title"><?php esc_html_e( 'Meus Dados', 'gstore' ); ?></span>
					<span class="gstore-dashboard__action-desc"><?php esc_html_e( 'Atualize suas informações', 'gstore' ); ?></span>
				</div>
				<span class="gstore-dashboard__action-arrow">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
				</span>
			</a>

			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="gstore-dashboard__action-card gstore-dashboard__action-card--highlight">
				<div class="gstore-dashboard__action-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
				</div>
				<div class="gstore-dashboard__action-content">
					<span class="gstore-dashboard__action-title"><?php esc_html_e( 'Continuar comprando', 'gstore' ); ?></span>
					<span class="gstore-dashboard__action-desc"><?php esc_html_e( 'Explore nossa loja', 'gstore' ); ?></span>
				</div>
				<span class="gstore-dashboard__action-arrow">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
				</span>
			</a>
		</div>
	</div>

	<!-- Recent Orders -->
	<?php if ( ! empty( $customer_orders ) ) : ?>
	<div class="gstore-dashboard__section">
		<div class="gstore-dashboard__section-header">
			<h2 class="gstore-dashboard__section-title"><?php esc_html_e( 'Últimos pedidos', 'gstore' ); ?></h2>
			<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="gstore-dashboard__view-all">
				<?php esc_html_e( 'Ver todos', 'gstore' ); ?>
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
			</a>
		</div>
		<div class="gstore-dashboard__orders">
			<?php foreach ( $customer_orders as $order ) : ?>
				<?php
				$order_status = $order->get_status();
				$status_labels = array(
					'pending'    => __( 'Pendente', 'gstore' ),
					'processing' => __( 'Processando', 'gstore' ),
					'on-hold'    => __( 'Aguardando', 'gstore' ),
					'completed'  => __( 'Concluído', 'gstore' ),
					'cancelled'  => __( 'Cancelado', 'gstore' ),
					'refunded'   => __( 'Reembolsado', 'gstore' ),
					'failed'     => __( 'Falhou', 'gstore' ),
				);
				$status_label = isset( $status_labels[ $order_status ] ) ? $status_labels[ $order_status ] : $order_status;
				?>
				<div class="gstore-dashboard__order-card">
					<div class="gstore-dashboard__order-header">
						<span class="gstore-dashboard__order-number">
							<?php printf( esc_html__( 'Pedido #%s', 'gstore' ), esc_html( $order->get_order_number() ) ); ?>
						</span>
						<span class="gstore-dashboard__order-status gstore-dashboard__order-status--<?php echo esc_attr( $order_status ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
					</div>
					<div class="gstore-dashboard__order-details">
						<span class="gstore-dashboard__order-date">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
							<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
						</span>
						<span class="gstore-dashboard__order-total">
							<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
						</span>
					</div>
					<div class="gstore-dashboard__order-actions">
						<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="gstore-dashboard__order-link">
							<?php esc_html_e( 'Ver detalhes', 'gstore' ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

</div>


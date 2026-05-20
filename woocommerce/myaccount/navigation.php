<?php
/**
 * My Account Navigation - GStore Custom
 *
 * @package GStore
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user = wp_get_current_user();
$partner_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'painel';
$partner_view = in_array( $partner_view, array( 'painel', 'vendas', 'creditos', 'contrato' ), true ) ? $partner_view : 'painel';
if ( function_exists( 'gstore_partner_account_is_visible' ) && function_exists( 'gstore_partner_account_contract_is_accepted' ) && gstore_partner_account_is_visible() && ! gstore_partner_account_contract_is_accepted( get_current_user_id() ) ) {
	$partner_view = 'contrato';
}
?>

<nav class="gstore-myaccount-nav" aria-label="<?php esc_attr_e( 'Navegação da conta', 'gstore' ); ?>">
	
	<!-- User Profile Card -->
	<div class="gstore-myaccount-nav__user">
		<div class="gstore-myaccount-nav__avatar">
			<?php echo get_avatar( $current_user->ID, 64, '', $current_user->display_name ); ?>
		</div>
		<div class="gstore-myaccount-nav__user-info">
			<span class="gstore-myaccount-nav__user-name"><?php echo esc_html( $current_user->display_name ); ?></span>
			<span class="gstore-myaccount-nav__user-email"><?php echo esc_html( $current_user->user_email ); ?></span>
		</div>
	</div>

	<!-- Navigation Links -->
	<ul class="gstore-myaccount-nav__list">
		<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
			<?php
			$icon = function_exists( 'gstore_get_myaccount_icon' ) ? gstore_get_myaccount_icon( $endpoint ) : '';
			$is_current = wc_is_current_account_menu_item( $endpoint );
			?>
			<li class="gstore-myaccount-nav__item <?php echo $is_current ? 'is-active' : ''; ?>">
				<a 
					href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" 
					class="gstore-myaccount-nav__link"
					<?php echo $is_current ? 'aria-current="page"' : ''; ?>
				>
					<span class="gstore-myaccount-nav__icon" aria-hidden="true">
						<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
					<span class="gstore-myaccount-nav__label"><?php echo esc_html( $label ); ?></span>
					<?php if ( 'orders' === $endpoint ) : ?>
						<?php
						$order_count = wc_get_customer_order_count( $current_user->ID );
						if ( $order_count > 0 ) :
						?>
							<span class="gstore-myaccount-nav__badge"><?php echo esc_html( $order_count ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</a>
				<?php if ( 'revendedor' === $endpoint && $is_current ) : ?>
					<ul class="gstore-myaccount-nav__children">
						<?php
						$partner_children = array(
							'painel'   => __( 'Meu Painel', 'gstore' ),
							'vendas'   => __( 'Minhas Vendas', 'gstore' ),
							'creditos' => __( 'Meus Creditos', 'gstore' ),
							'contrato' => __( 'Contrato', 'gstore' ),
						);
						foreach ( $partner_children as $child_view => $child_label ) :
							$child_url = add_query_arg( 'view', $child_view, wc_get_account_endpoint_url( 'revendedor' ) );
							?>
							<li class="<?php echo esc_attr( $partner_view === $child_view ? 'is-active' : '' ); ?>">
								<a href="<?php echo esc_url( $child_url ); ?>" <?php echo $partner_view === $child_view ? 'aria-current="page"' : ''; ?>>
									<?php echo esc_html( $child_label ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

</nav>

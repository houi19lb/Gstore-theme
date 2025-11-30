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
			</li>
		<?php endforeach; ?>
	</ul>

</nav>

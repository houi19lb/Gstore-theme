<?php
/**
 * My Account page - GStore Custom
 *
 * @package GStore
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="gstore-myaccount <?php echo is_user_logged_in() ? 'gstore-account-shell' : ''; ?>">
	
	<?php if ( is_user_logged_in() ) : ?>
		
		<div class="gstore-myaccount__layout">
			
			<!-- Sidebar Navigation -->
			<aside class="gstore-myaccount__sidebar">
				<?php do_action( 'woocommerce_account_navigation' ); ?>
			</aside>

			<!-- Main Content -->
			<section class="gstore-myaccount__content" aria-label="<?php esc_attr_e( 'Conteúdo da conta', 'gstore' ); ?>">
				<?php
					$is_data_page = is_wc_endpoint_url( 'edit-account' ) || is_wc_endpoint_url( 'edit-address' );
					if ( $is_data_page || is_wc_endpoint_url( 'orders' ) ) {
						wc_get_template( 'myaccount/account-heading.php', array( 'is_data_page' => $is_data_page ) );
					}
					/**
					 * My Account content.
					 *
					 * @since 2.6.0
					 */
					do_action( 'woocommerce_account_content' );
					if ( $is_data_page ) {
						wc_get_template( 'myaccount/account-pagination.php' );
					}
				?>
			</section>

		</div>

	<?php else : ?>

		<?php wc_get_template( 'myaccount/form-login.php' ); ?>

	<?php endif; ?>

</div>




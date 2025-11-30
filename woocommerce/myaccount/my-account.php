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

<div class="gstore-myaccount">
	
	<?php if ( is_user_logged_in() ) : ?>
		
		<div class="gstore-myaccount__layout">
			
			<!-- Sidebar Navigation -->
			<aside class="gstore-myaccount__sidebar">
				<?php do_action( 'woocommerce_account_navigation' ); ?>
			</aside>

			<!-- Main Content -->
			<main class="gstore-myaccount__content">
				<?php
					/**
					 * My Account content.
					 *
					 * @since 2.6.0
					 */
					do_action( 'woocommerce_account_content' );
				?>
			</main>

		</div>

	<?php else : ?>

		<?php wc_get_template( 'myaccount/form-login.php' ); ?>

	<?php endif; ?>

</div>




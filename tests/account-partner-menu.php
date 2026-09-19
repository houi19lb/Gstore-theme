<?php
/** php tests/account-partner-menu.php */
require __DIR__ . '/account-dashboard.php';
function add_action( ...$args ) {}
function gstore_partner_is_enabled() { return $GLOBALS['program_enabled']; }
function gstore_partner_user_is_partner( $id ) { return $GLOBALS['is_partner']; }
require dirname( __DIR__ ) . '/inc/gstore-partner-account.php';
foreach ( array( false, true ) as $program_enabled ) {
 foreach ( array( false, true ) as $is_partner ) {
  $menu = gstore_account_dashboard_menu( gstore_partner_account_add_menu_item( array( 'dashboard' => 'Inicio', 'customer-logout' => 'Sair' ) ) );
  check( isset( $menu['revendedor'] ) === $is_partner, 'Existing partner panel visibility preserved' );
  check( ! isset( $menu['seja-revendedor'] ), 'No application entry in account navigation' );
  check( array_key_last( $menu ) === 'customer-logout', 'Logout stays last' );
 }
}
echo "PASS: partner panel and application menu eligibility.\n";

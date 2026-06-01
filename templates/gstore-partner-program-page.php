<?php
/**
 * Virtual page for the partner application program.
 *
 * @package GStore
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( function_exists( 'gstore_partner_account_render_application_page' ) ) {
	gstore_partner_account_render_application_page();
}

get_footer();

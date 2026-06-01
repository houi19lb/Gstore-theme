<?php
/**
 * Virtual page for the partner application program.
 *
 * @package GStore
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'gstore-partner-program-body' ); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
?>
<?php
if ( function_exists( 'gstore_partner_account_render_application_page' ) ) {
	gstore_partner_account_render_application_page();
}
?>
<?php
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
?>
<?php wp_footer(); ?>
</body>
</html>

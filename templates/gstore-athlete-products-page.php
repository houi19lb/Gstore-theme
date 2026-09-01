<?php
/**
 * Athlete-only products catalogue.
 *
 * @package GStore
 */

defined( 'ABSPATH' ) || exit;

$catalog_template = get_theme_file_path( 'templates/page-catalogo.html' );
$catalog_markup   = is_readable( $catalog_template ) ? file_get_contents( $catalog_template ) : '';

// ponytail: usa o mesmo template do catálogo e muda apenas o título da vitrine exclusiva.
$catalog_markup = preg_replace(
	'/(<h1\\b[^>]*>).*?(<\\/h1>)/s',
	'$1' . esc_html__( 'Produtos para atletas', 'gstore' ) . '$2',
	$catalog_markup,
	1
);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
echo do_shortcode( do_blocks( $catalog_markup ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
wp_footer();
?>
</body>
</html>

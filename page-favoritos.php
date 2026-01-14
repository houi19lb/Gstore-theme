<?php
/**
 * Template Name: Favoritos (Gstore)
 * Template Post Type: page
 *
 * @package Gstore
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="site-content" class="Gstore-catalog-shell Gstore-catalog-shell--light" role="main">
	<div class="wp-block-group alignwide Gstore-catalog-container">
		<header class="Gstore-catalog-header">
			<h1 class="Gstore-catalog-title"><?php echo esc_html__( 'Favoritos', 'gstore' ); ?></h1>
		</header>

		<div id="gstore-favorites-root" class="Gstore-favorites" aria-live="polite"></div>

		<noscript>
			<p><?php echo esc_html__( 'Ative o JavaScript para visualizar seus favoritos.', 'gstore' ); ?></p>
		</noscript>
	</div>
</main>

<?php
get_footer();


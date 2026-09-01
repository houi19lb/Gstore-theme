<?php
/**
 * Generic page used when an athlete-only product is opened directly.
 *
 * @package GStore
 */

defined( 'ABSPATH' ) || exit;

$logged_in          = is_user_logged_in();
$restricted_catalog = (bool) get_query_var( 'gstore_athlete_products_page' );
$title              = $restricted_catalog ? __( 'Esta página é exclusiva para atletas.', 'gstore' ) : __( 'Este produto é exclusivo para atletas.', 'gstore' );
$description        = $restricted_catalog ? __( 'A seleção de produtos para atletas fica disponível apenas para contas aprovadas no Programa Atleta.', 'gstore' ) : __( 'Produtos selecionados ficam disponíveis apenas para contas aprovadas no Programa Atleta.', 'gstore' );
$application_url    = gstore_athlete_account_program_url() . '#gstore-athlete-application';
$account_url        = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url( $application_url );
$account_url        = add_query_arg( 'gstore_athlete_return', '1', $account_url );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'gstore-athlete-program-body' ); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
?>
<main class="gstore-athlete-restricted-page">
	<section class="gstore-athlete-restricted-card">
		<span class="gstore-athlete-restricted-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.6 3 8.8 7 10 4-1.2 7-5.4 7-10V6l-7-3Z"/><path d="M9.5 12h5M12 9.5V14.5"/></svg>
		</span>
		<span class="gstore-athlete-eyebrow gstore-athlete-eyebrow--dark"><?php esc_html_e( 'Acesso exclusivo', 'gstore' ); ?></span>
		<h1><?php echo esc_html( $title ); ?></h1>
		<p><?php echo esc_html( $description ); ?></p>
		<div class="gstore-athlete-restricted-actions">
			<?php if ( $logged_in ) : ?>
				<a class="button gstore-athlete-button" href="<?php echo esc_url( $application_url ); ?>"><?php esc_html_e( 'Solicitar participação', 'gstore' ); ?></a>
			<?php else : ?>
				<a class="button gstore-athlete-button" href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'Entrar ou criar conta', 'gstore' ); ?></a>
			<?php endif; ?>
			<a class="gstore-athlete-link" href="<?php echo esc_url( gstore_athlete_account_program_url() ); ?>"><?php esc_html_e( 'Conhecer o programa', 'gstore' ); ?></a>
		</div>
		<div class="gstore-athlete-restricted-steps" aria-label="<?php esc_attr_e( 'Como participar do Programa Atleta', 'gstore' ); ?>">
			<span><b>1</b><?php esc_html_e( 'Crie sua conta', 'gstore' ); ?></span>
			<span><b>2</b><?php esc_html_e( 'Envie seu CR', 'gstore' ); ?></span>
			<span><b>3</b><?php esc_html_e( 'Aguarde a aprovação', 'gstore' ); ?></span>
		</div>
	</section>
</main>
<?php echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' ); ?>
<?php wp_footer(); ?>
</body>
</html>

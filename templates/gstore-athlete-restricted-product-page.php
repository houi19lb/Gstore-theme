<?php
/**
 * Generic page used when an athlete-only product is opened directly.
 *
 * @package GStore
 */

defined( 'ABSPATH' ) || exit;

$logged_in = is_user_logged_in();
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
		<span class="gstore-athlete-eyebrow gstore-athlete-eyebrow--dark"><?php esc_html_e( 'Acesso exclusivo', 'gstore' ); ?></span>
		<h1><?php esc_html_e( 'Este produto é exclusivo para atletas.', 'gstore' ); ?></h1>
		<p><?php esc_html_e( 'Produtos selecionados ficam disponíveis apenas para contas aprovadas no Programa Atleta.', 'gstore' ); ?></p>
		<?php if ( $logged_in ) : ?>
			<a class="button gstore-athlete-button" href="<?php echo esc_url( gstore_athlete_account_program_url() ); ?>"><?php esc_html_e( 'Ver Programa Atleta', 'gstore' ); ?></a>
		<?php else : ?>
			<button class="button gstore-athlete-button" type="button" data-gstore-athlete-dialog-open><?php esc_html_e( 'Entrar ou criar conta', 'gstore' ); ?></button>
		<?php endif; ?>
	</section>
</main>
<?php if ( ! $logged_in ) : ?>
	<dialog class="gstore-athlete-dialog" data-gstore-athlete-dialog aria-labelledby="gstore-athlete-dialog-title">
		<button class="gstore-athlete-dialog__close" type="button" data-gstore-athlete-dialog-close aria-label="<?php esc_attr_e( 'Fechar', 'gstore' ); ?>">&times;</button>
		<h2 id="gstore-athlete-dialog-title"><?php esc_html_e( 'Acesse sua conta', 'gstore' ); ?></h2>
		<p><?php esc_html_e( 'Conheça o Programa Atleta. Nessa página, você poderá entrar ou criar sua conta antes de enviar a solicitação.', 'gstore' ); ?></p>
		<a class="button gstore-athlete-button" href="<?php echo esc_url( gstore_athlete_account_program_url() ); ?>"><?php esc_html_e( 'Ir para o Programa Atleta', 'gstore' ); ?></a>
	</dialog>
	<script>
	(function () { var dialog = document.querySelector('[data-gstore-athlete-dialog]'); if (!dialog) return; document.querySelectorAll('[data-gstore-athlete-dialog-open]').forEach(function (button) { button.addEventListener('click', function () { dialog.showModal(); }); }); dialog.querySelectorAll('[data-gstore-athlete-dialog-close]').forEach(function (button) { button.addEventListener('click', function () { dialog.close(); }); }); dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); }); })();
	</script>
<?php endif; ?>
<?php echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' ); ?>
<?php wp_footer(); ?>
</body>
</html>

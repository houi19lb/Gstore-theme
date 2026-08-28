<?php
/**
 * Public athlete application page.
 *
 * @package GStore
 */

defined( 'ABSPATH' ) || exit;

$logged_in = is_user_logged_in();
$user      = $logged_in ? wp_get_current_user() : null;
$application = $logged_in && function_exists( 'gstore_athlete_get_latest_application' )
	? gstore_athlete_get_latest_application( get_current_user_id() )
	: null;
$is_athlete = $logged_in && function_exists( 'gstore_athlete_user_is_athlete' ) && gstore_athlete_user_is_athlete( get_current_user_id() );
$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url( gstore_athlete_account_program_url() );
$status = isset( $_GET['athlete_application'] ) ? sanitize_key( wp_unslash( $_GET['athlete_application'] ) ) : '';
$message = isset( $_GET['athlete_application_message'] ) ? sanitize_text_field( wp_unslash( $_GET['athlete_application_message'] ) ) : '';
$public_application_status = is_array( $application ) ? sanitize_key( (string) ( $application['status'] ?? '' ) ) : '';
$public_application_label = 'pending' === $public_application_status
	? __( 'Em análise', 'gstore' )
	: ( 'approved' === $public_application_status ? __( 'Aprovada', 'gstore' ) : __( 'Recusada', 'gstore' ) );
$get_meta = static function ( $key, $fallback = '' ) {
	$value = get_user_meta( get_current_user_id(), $key, true );
	return '' !== (string) $value ? (string) $value : $fallback;
};
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
<main class="gstore-athlete-program-page">
	<section class="gstore-athlete-hero">
		<div class="gstore-athlete-container">
			<span class="gstore-athlete-eyebrow"><?php esc_html_e( 'Programa Atleta', 'gstore' ); ?></span>
			<h1><?php esc_html_e( 'Seu desempenho merece apoio à altura.', 'gstore' ); ?></h1>
			<p><?php esc_html_e( 'Cadastre seu perfil esportivo e apresente sua trajetória para nossa equipe.', 'gstore' ); ?></p>
			<div class="gstore-athlete-actions">
				<?php if ( $logged_in ) : ?>
					<a class="button gstore-athlete-button" href="#gstore-athlete-application"><?php echo esc_html( $is_athlete ? __( 'Ver meu status', 'gstore' ) : __( 'Quero me cadastrar', 'gstore' ) ); ?></a>
				<?php else : ?>
					<button class="button gstore-athlete-button" type="button" data-gstore-athlete-dialog-open><?php esc_html_e( 'Quero me cadastrar', 'gstore' ); ?></button>
				<?php endif; ?>
				<a class="gstore-athlete-link" href="#como-funciona"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i><?php esc_html_e( 'Como funciona', 'gstore' ); ?></a>
			</div>
		</div>
	</section>

	<section class="gstore-athlete-benefits" aria-label="<?php esc_attr_e( 'Benefícios do Programa Atleta', 'gstore' ); ?>">
		<div class="gstore-athlete-container gstore-athlete-benefit-grid">
			<article><i class="fa-solid fa-award" aria-hidden="true"></i><div><strong><?php esc_html_e( 'Acesso exclusivo', 'gstore' ); ?></strong><span><?php esc_html_e( 'Produtos selecionados liberados apenas para atletas aprovados.', 'gstore' ); ?></span></div></article>
			<article><i class="fa-solid fa-chart-line" aria-hidden="true"></i><div><strong><?php esc_html_e( 'Apoio à evolução', 'gstore' ); ?></strong><span><?php esc_html_e( 'Uma parceria para quem vive e evolui no esporte.', 'gstore' ); ?></span></div></article>
			<article><i class="fa-regular fa-user" aria-hidden="true"></i><div><strong><?php esc_html_e( 'Análise individual', 'gstore' ); ?></strong><span><?php esc_html_e( 'Cada inscrição é avaliada pela equipe do programa.', 'gstore' ); ?></span></div></article>
		</div>
	</section>

	<section id="como-funciona" class="gstore-athlete-section">
		<div class="gstore-athlete-container">
			<header class="gstore-athlete-section-heading">
				<span><?php esc_html_e( 'Programa Atleta', 'gstore' ); ?></span>
				<h2><?php esc_html_e( 'Como funciona', 'gstore' ); ?></h2>
			</header>
			<ol class="gstore-athlete-steps">
				<li><span class="gstore-athlete-step-number">01</span><i class="fa-regular fa-clipboard" aria-hidden="true"></i><strong><?php esc_html_e( 'Cadastro', 'gstore' ); ?></strong><span><?php esc_html_e( 'Crie sua conta e preencha o formulário com seus dados.', 'gstore' ); ?></span></li>
				<li><span class="gstore-athlete-step-number">02</span><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><strong><?php esc_html_e( 'Análise de perfil', 'gstore' ); ?></strong><span><?php esc_html_e( 'Nossa equipe confere a solicitação enviada.', 'gstore' ); ?></span></li>
				<li><span class="gstore-athlete-step-number">03</span><i class="fa-regular fa-circle-check" aria-hidden="true"></i><strong><?php esc_html_e( 'Retorno da equipe', 'gstore' ); ?></strong><span><?php esc_html_e( 'Acompanhe nesta página o resultado da análise.', 'gstore' ); ?></span></li>
			</ol>
		</div>
	</section>

	<section class="gstore-athlete-story">
		<div class="gstore-athlete-container gstore-athlete-story__grid">
			<div class="gstore-athlete-story__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/athlete/athlete-program-story.webp' ) ); ?>" alt="<?php esc_attr_e( 'Atleta em treinamento de tiro esportivo', 'gstore' ); ?>"></div>
			<div class="gstore-athlete-story__content">
				<span class="gstore-athlete-eyebrow"><?php esc_html_e( 'Programa Atleta', 'gstore' ); ?></span>
				<h2><?php esc_html_e( 'Mais que equipamento: parceria de performance.', 'gstore' ); ?></h2>
				<p><?php esc_html_e( 'O Programa Atleta foi criado para aproximar quem leva o esporte a sério de uma seleção exclusiva de produtos.', 'gstore' ); ?></p>
				<ul class="gstore-athlete-story__list">
					<li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><strong><?php esc_html_e( 'Perfil esportivo', 'gstore' ); ?></strong><?php esc_html_e( 'Informe a modalidade que faz parte da sua rotina.', 'gstore' ); ?></span></li>
					<li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><strong><?php esc_html_e( 'Acesso de atleta', 'gstore' ); ?></strong><?php esc_html_e( 'Após aprovado, seu selo libera os produtos exclusivos.', 'gstore' ); ?></span></li>
					<li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><strong><?php esc_html_e( 'Status no seu tempo', 'gstore' ); ?></strong><?php esc_html_e( 'Acompanhe a análise diretamente nesta página.', 'gstore' ); ?></span></li>
				</ul>
			</div>
		</div>
	</section>

	<section id="gstore-athlete-application" class="gstore-athlete-section gstore-athlete-section--form">
		<div class="gstore-athlete-container gstore-athlete-application-wrap">
			<div class="gstore-athlete-application__intro">
				<span class="gstore-athlete-eyebrow"><?php esc_html_e( 'Sua conta', 'gstore' ); ?></span>
				<h2><?php esc_html_e( 'Cadastro de atleta', 'gstore' ); ?></h2>
				<p><?php esc_html_e( 'Preencha seus dados com atenção. A modalidade é o único dado esportivo obrigatório nesta etapa.', 'gstore' ); ?></p>
				<ul class="gstore-athlete-application__highlights">
					<li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span><strong><?php esc_html_e( 'Seus dados estão seguros', 'gstore' ); ?></strong><?php esc_html_e( 'As informações são usadas somente na análise do programa.', 'gstore' ); ?></span></li>
					<li><i class="fa-regular fa-user" aria-hidden="true"></i><span><strong><?php esc_html_e( 'Feito para atletas', 'gstore' ); ?></strong><?php esc_html_e( 'Uma inscrição simples, vinculada à sua conta da loja.', 'gstore' ); ?></span></li>
				</ul>
			</div>
			<div class="gstore-athlete-card">
				<?php if ( $status && $message ) : ?>
					<p class="gstore-athlete-notice <?php echo 'error' === $status ? 'is-error' : 'is-success'; ?>" role="status"><?php echo esc_html( $message ); ?></p>
				<?php endif; ?>
				<?php if ( ! $logged_in ) : ?>
					<h3><?php esc_html_e( 'Entre ou crie sua conta para continuar', 'gstore' ); ?></h3>
					<p><?php esc_html_e( 'O Programa Atleta só aceita inscrições vinculadas a uma conta existente.', 'gstore' ); ?></p>
					<button class="button gstore-athlete-button" type="button" data-gstore-athlete-dialog-open><?php esc_html_e( 'Entrar ou criar conta', 'gstore' ); ?></button>
				<?php elseif ( $is_athlete ) : ?>
					<span class="gstore-athlete-status is-approved"><?php esc_html_e( 'Aprovada', 'gstore' ); ?></span>
					<h3><?php esc_html_e( 'Seu selo Atleta está ativo.', 'gstore' ); ?></h3>
					<p><?php esc_html_e( 'Você já pode visualizar e comprar os produtos exclusivos para atletas.', 'gstore' ); ?></p>
				<?php elseif ( is_array( $application ) ) : ?>
					<span class="gstore-athlete-status is-<?php echo esc_attr( $public_application_status ); ?>"><?php echo esc_html( $public_application_label ); ?></span>
					<h3><?php esc_html_e( 'Sua solicitação está registrada.', 'gstore' ); ?></h3>
					<p><?php esc_html_e( 'Acompanhe este status aqui. Não é necessário reenviar seus dados.', 'gstore' ); ?></p>
					<?php if ( ! empty( $application['submittedAt'] ) ) : ?><small><?php printf( esc_html__( 'Enviada em %s.', 'gstore' ), esc_html( mysql2date( get_option( 'date_format' ), $application['submittedAt'] ) ) ); ?></small><?php endif; ?>
				<?php else : ?>
					<form class="gstore-athlete-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="gstore_athlete_submit_application">
						<input type="hidden" name="gstore_athlete_application_redirect" value="<?php echo esc_url( gstore_athlete_account_program_url() ); ?>">
						<input type="hidden" name="gstore_athlete_country" value="BR">
						<?php wp_nonce_field( 'gstore_athlete_application', 'gstore_athlete_application_nonce' ); ?>
						<div class="gstore-athlete-form-grid">
							<label><span><?php esc_html_e( 'Nome completo', 'gstore' ); ?></span><input required name="gstore_athlete_name" value="<?php echo esc_attr( $user->display_name ); ?>"></label>
							<label><span><?php esc_html_e( 'CPF', 'gstore' ); ?></span><input required inputmode="numeric" name="gstore_athlete_cpf" value="<?php echo esc_attr( $get_meta( 'billing_cpf' ) ); ?>"></label>
							<label><span><?php esc_html_e( 'Telefone', 'gstore' ); ?></span><input required type="tel" name="gstore_athlete_phone" value="<?php echo esc_attr( $get_meta( 'billing_phone' ) ); ?>"></label>
							<label><span><?php esc_html_e( 'Modalidade esportiva', 'gstore' ); ?></span><input required name="gstore_athlete_modality" placeholder="<?php esc_attr_e( 'Ex.: tiro esportivo', 'gstore' ); ?>"></label>
							<label><span><?php esc_html_e( 'CEP', 'gstore' ); ?></span><input required inputmode="numeric" name="gstore_athlete_postcode" value="<?php echo esc_attr( $get_meta( 'billing_postcode' ) ); ?>"></label>
							<label class="gstore-athlete-form-grid__wide"><span><?php esc_html_e( 'Endereço', 'gstore' ); ?></span><input required name="gstore_athlete_address_1" value="<?php echo esc_attr( $get_meta( 'billing_address_1' ) ); ?>"></label>
							<label><span><?php esc_html_e( 'Número', 'gstore' ); ?></span><input required name="gstore_athlete_number" value="<?php echo esc_attr( $get_meta( 'billing_number' ) ); ?>"></label>
							<label><span><?php esc_html_e( 'Complemento', 'gstore' ); ?></span><input name="gstore_athlete_address_2" value="<?php echo esc_attr( $get_meta( 'billing_address_2' ) ); ?>"></label>
							<label><span><?php esc_html_e( 'Bairro', 'gstore' ); ?></span><input required name="gstore_athlete_neighborhood" value="<?php echo esc_attr( $get_meta( 'billing_neighborhood' ) ); ?>"></label>
							<label><span><?php esc_html_e( 'Cidade', 'gstore' ); ?></span><input required name="gstore_athlete_city" value="<?php echo esc_attr( $get_meta( 'billing_city' ) ); ?>"></label>
							<label><span><?php esc_html_e( 'Estado', 'gstore' ); ?></span><input required maxlength="2" name="gstore_athlete_state" value="<?php echo esc_attr( $get_meta( 'billing_state' ) ); ?>"></label>
							<label class="gstore-athlete-form-grid__wide"><span><?php esc_html_e( 'Documento de identidade', 'gstore' ); ?></span><input required type="file" accept="image/jpeg,image/png,application/pdf" name="gstore_athlete_identity_document"><small><?php esc_html_e( 'PDF, JPG ou PNG. O arquivo é armazenado em área privada.', 'gstore' ); ?></small></label>
						</div>
						<label class="gstore-athlete-consent"><input required type="checkbox" name="gstore_athlete_privacy_consent" value="1"><span><?php esc_html_e( 'Li e concordo com o uso dos dados para análise do Programa Atleta e com a Política de Privacidade.', 'gstore' ); ?></span></label>
						<button class="button gstore-athlete-button" type="submit"><?php esc_html_e( 'Enviar solicitação', 'gstore' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>

<?php if ( ! $logged_in ) : ?>
	<dialog class="gstore-athlete-dialog" data-gstore-athlete-dialog aria-labelledby="gstore-athlete-dialog-title">
		<button class="gstore-athlete-dialog__close" type="button" data-gstore-athlete-dialog-close aria-label="<?php esc_attr_e( 'Fechar', 'gstore' ); ?>">&times;</button>
		<h2 id="gstore-athlete-dialog-title"><?php esc_html_e( 'Acesse sua conta', 'gstore' ); ?></h2>
		<p><?php esc_html_e( 'Para solicitar o Programa Atleta, primeiro entre em uma conta existente ou crie uma nova conta na loja.', 'gstore' ); ?></p>
		<a class="button gstore-athlete-button" href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'Entrar ou criar conta', 'gstore' ); ?></a>
	</dialog>
	<script>
	(function () {
		var dialog = document.querySelector('[data-gstore-athlete-dialog]');
		if (!dialog) return;
		document.querySelectorAll('[data-gstore-athlete-dialog-open]').forEach(function (button) { button.addEventListener('click', function () { dialog.showModal(); }); });
		dialog.querySelectorAll('[data-gstore-athlete-dialog-close]').forEach(function (button) { button.addEventListener('click', function () { dialog.close(); }); });
		dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
	})();
	</script>
<?php endif; ?>
<?php echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' ); ?>
<?php wp_footer(); ?>
</body>
</html>

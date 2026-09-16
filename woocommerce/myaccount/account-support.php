<?php
defined( 'ABSPATH' ) || exit;
$channels = gstore_account_contact_channels();
?>
<div class="gstore-account-heading">
	<h1><?php esc_html_e( 'Como podemos ajudar?', 'gstore' ); ?></h1>
	<p><?php esc_html_e( 'Escolha o melhor canal para falar com a nossa equipe.', 'gstore' ); ?></p>
</div>
<div class="gstore-account-contacts">
	<?php foreach ( $channels as $channel ) :
        $contact_host = strtolower( (string) wp_parse_url( $channel['url'], PHP_URL_HOST ) );
        $is_whatsapp = in_array( $contact_host, array( 'wa.me', 'api.whatsapp.com', 'web.whatsapp.com', 'whatsapp.com', 'www.whatsapp.com' ), true );
    ?>
	<section class="gstore-account-card gstore-account-contact gstore-account-contact--<?php echo esc_attr( $channel['kind'] ); ?><?php echo $is_whatsapp ? ' gstore-account-contact--whatsapp' : ''; ?>">
		<span class="gstore-account-icon" aria-hidden="true"><i class="<?php echo esc_attr( $channel['icon'] ); ?>"></i></span>
		<h2><?php echo esc_html( $channel['label'] ); ?></h2>
		<p><?php echo esc_html( $channel['description'] ); ?></p>
		<a class="gstore-account-button gstore-account-button--secondary" href="<?php echo esc_url( $channel['url'] ); ?>"><?php printf( esc_html__( 'Abrir %s', 'gstore' ), esc_html( $channel['label'] ) ); ?></a>
	</section>
	<?php endforeach; ?>
	<?php if ( ! $channels ) : ?><section class="gstore-account-card"><h2><?php esc_html_e( 'Nenhum canal disponível no momento', 'gstore' ); ?></h2><p><?php esc_html_e( 'Consulte a página de atendimento da loja para mais orientações.', 'gstore' ); ?></p><a href="<?php echo esc_url( home_url( '/atendimento/' ) ); ?>"><?php esc_html_e( 'Ir para atendimento', 'gstore' ); ?></a></section><?php endif; ?>
</div>
<div class="gstore-account-note"><strong><?php esc_html_e( 'Vai falar sobre um pedido?', 'gstore' ); ?></strong><p><?php esc_html_e( 'Tenha o número em mãos para facilitar o atendimento.', 'gstore' ); ?></p></div>

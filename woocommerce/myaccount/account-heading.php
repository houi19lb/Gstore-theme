<?php
defined( 'ABSPATH' ) || exit;
$address_page = is_wc_endpoint_url( 'edit-address' );
?>
<div class="gstore-account-heading">
	<p class="gstore-account-eyebrow"><?php esc_html_e( 'Sua conta', 'gstore' ); ?></p>
	<h1><?php echo esc_html( $is_data_page ? __( 'Meus dados', 'gstore' ) : __( 'Seus pedidos', 'gstore' ) ); ?></h1>
	<p><?php echo esc_html( $is_data_page ? __( 'Consulte e atualize suas informações e endereços.', 'gstore' ) : __( 'Acompanhe as etapas, a documentação e os detalhes de cada pedido.', 'gstore' ) ); ?></p>
</div>
<?php if ( $is_data_page ) : ?>
<nav class="gstore-account-pages" aria-label="<?php esc_attr_e( 'Páginas de Meus dados', 'gstore' ); ?>">
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>" <?php echo ! $address_page ? 'aria-current="page"' : ''; ?>><span>1</span> <?php esc_html_e( 'Informações gerais', 'gstore' ); ?></a>
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>" <?php echo $address_page ? 'aria-current="page"' : ''; ?>><span>2</span> <?php esc_html_e( 'Endereços', 'gstore' ); ?></a>
</nav>
<h2 class="gstore-account-section-title"><?php echo esc_html( $address_page ? __( 'Endereços', 'gstore' ) : __( 'Informações gerais', 'gstore' ) ); ?></h2>
<?php endif; ?>

<?php
defined( 'ABSPATH' ) || exit;
$address_page = is_wc_endpoint_url( 'edit-address' );
?>
<nav class="gstore-account-pagination" aria-label="<?php esc_attr_e( 'Paginação dos dados', 'gstore' ); ?>">
	<?php if ( $address_page ) : ?><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">← <?php esc_html_e( 'Anterior', 'gstore' ); ?></a><?php else : ?><span aria-disabled="true"><?php esc_html_e( 'Anterior', 'gstore' ); ?></span><?php endif; ?>
	<span><?php printf( esc_html__( 'Página %d de 2', 'gstore' ), $address_page ? 2 : 1 ); ?></span>
	<?php if ( ! $address_page ) : ?><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>"><?php esc_html_e( 'Próxima', 'gstore' ); ?> →</a><?php else : ?><span aria-disabled="true"><?php esc_html_e( 'Próxima', 'gstore' ); ?></span><?php endif; ?>
</nav>

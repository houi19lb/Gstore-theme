<?php
/** Customer cashback presentation. All balances and movements come from the plugin. */
defined( 'ABSPATH' ) || exit;

function gstore_cashback_service_available() {
	return class_exists( '\GStore\Services\Cashback_Rules' ) && class_exists( '\GStore\Services\Cashback_Wallet' ) && \GStore\Services\Cashback_Wallet::ready();
}

function gstore_cashback_account_is_visible() {
	if ( ! is_user_logged_in() || ! gstore_cashback_service_available() ) {
		return false;
	}
	$rules = \GStore\Services\Cashback_Rules::get();
	if ( $rules['enabled'] ) {
		return true;
	}
	$data = \GStore\Services\Cashback_Wallet::get_account_data( get_current_user_id(), 1 );
	return $data['availableCoins'] > 0 || ! empty( $data['events'] );
}

function gstore_cashback_register_account_endpoint() {
	add_rewrite_endpoint( 'minhas-moedas', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'gstore_cashback_register_account_endpoint', 5 );

function gstore_cashback_maybe_flush_account_endpoint() {
	$version = '20260926';
	if ( get_option( 'gstore_cashback_account_endpoint_version' ) !== $version ) {
		flush_rewrite_rules( false );
		update_option( 'gstore_cashback_account_endpoint_version', $version, false );
	}
}
add_action( 'init', 'gstore_cashback_maybe_flush_account_endpoint', 20 );

function gstore_cashback_account_query_var( $vars ) {
	$vars['minhas-moedas'] = 'minhas-moedas';
	return $vars;
}
add_filter( 'woocommerce_get_query_vars', 'gstore_cashback_account_query_var' );

function gstore_cashback_account_menu_item( $items ) {
	if ( ! gstore_cashback_account_is_visible() ) {
		return $items;
	}
	$next = array();
	$added = false;
	foreach ( $items as $endpoint => $label ) {
		if ( 'customer-logout' === $endpoint ) {
			$next['minhas-moedas'] = __( 'Minhas moedas', 'gstore' );
			$added = true;
		}
		$next[ $endpoint ] = $label;
	}
	if ( ! $added ) {
		$next['minhas-moedas'] = __( 'Minhas moedas', 'gstore' );
	}
	return $next;
}
add_filter( 'woocommerce_account_menu_items', 'gstore_cashback_account_menu_item', 31 );

function gstore_cashback_render_account_endpoint() {
	if ( ! is_user_logged_in() || ! gstore_cashback_service_available() ) {
		return;
	}
	$rules = \GStore\Services\Cashback_Rules::get();
	$data = \GStore\Services\Cashback_Wallet::get_account_data( get_current_user_id() );
	$balance = (int) $data['availableCoins'];
	$real = $balance / max( 1, (int) $rules['coinsPerReal'] );
	$labels = array(
		'earn' => __( 'Moedas recebidas', 'gstore' ),
		'reserve' => __( 'Usadas no pedido', 'gstore' ),
		'return' => __( 'Moedas devolvidas', 'gstore' ),
		'reverse' => __( 'Estorno de moedas', 'gstore' ),
		'expire' => __( 'Moedas vencidas', 'gstore' ),
		'reclaim' => __( 'Moedas usadas após confirmação do pedido', 'gstore' ),
		'reclaim_debt' => __( 'Saldo ajustado após confirmação do pedido', 'gstore' ),
		'debt_release' => __( 'Ajuste devolvido', 'gstore' ),
	);
	?>
	<div class="gstore-cashback-account">
		<header class="gstore-cashback-account__heading"><span class="gstore-cashback-account__eyebrow"><?php esc_html_e( 'Programa de benefícios', 'gstore' ); ?></span><h1><?php esc_html_e( 'Minhas moedas', 'gstore' ); ?></h1><p><?php esc_html_e( 'Acompanhe suas moedas e use o saldo em compras elegíveis.', 'gstore' ); ?></p></header>
		<section class="gstore-cashback-account__balance" aria-labelledby="gstore-cashback-balance-title">
			<div><span id="gstore-cashback-balance-title"><?php esc_html_e( 'Saldo disponível', 'gstore' ); ?></span><strong><?php echo esc_html( number_format_i18n( $balance ) ); ?> <?php esc_html_e( 'moedas', 'gstore' ); ?></strong><small><?php printf( esc_html__( 'Equivalente a %s em desconto', 'gstore' ), esc_html( wp_strip_all_tags( wc_price( $real ) ) ) ); ?></small></div>
			<?php if ( $rules['enabled'] ) : ?><a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="gstore-account-button"><?php esc_html_e( 'Usar na próxima compra', 'gstore' ); ?></a><?php endif; ?>
		</section>
		<?php if ( $balance > 0 && ! empty( $data['nextExpiry'] ) ) : ?>
			<p class="gstore-cashback-account__expiry"><?php printf( esc_html__( '%1$s moedas vencem em %2$s.', 'gstore' ), esc_html( number_format_i18n( $data['nextExpiry']['coins'] ) ), esc_html( wp_date( 'd/m/Y', strtotime( $data['nextExpiry']['date'] . ' UTC' ) ) ) ); ?></p>
		<?php endif; ?>
		<section class="gstore-cashback-account__history" aria-labelledby="gstore-cashback-history-title"><h2 id="gstore-cashback-history-title"><?php esc_html_e( 'Histórico de moedas', 'gstore' ); ?></h2>
			<?php if ( empty( $data['events'] ) ) : ?><p><?php esc_html_e( 'Você ainda não tem movimentações de moedas.', 'gstore' ); ?></p>
			<?php else : ?><ol>
				<?php foreach ( $data['events'] as $event ) : ?>
					<li><span><strong><?php echo esc_html( $labels[ $event['type'] ] ?? __( 'Movimentação', 'gstore' ) ); ?></strong><small><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $event['created_at'] . ' UTC' ) ) ); ?> · <?php printf( esc_html__( 'Pedido #%d', 'gstore' ), (int) $event['order_id'] ); ?></small></span><b class="<?php echo (int) $event['coins'] < 0 ? 'is-negative' : 'is-positive'; ?>"><?php echo esc_html( sprintf( '%+d', (int) $event['coins'] ) ); ?></b></li>
				<?php endforeach; ?>
			</ol><?php endif; ?>
		</section>
	</div>
	<?php
}
add_action( 'woocommerce_account_minhas-moedas_endpoint', 'gstore_cashback_render_account_endpoint' );

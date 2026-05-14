<?php
/**
 * VIP area inside WooCommerce My Account.
 *
 * @package GStore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gstore_vip_purchase_service_class' ) ) {
	function gstore_vip_purchase_service_class() {
		return class_exists( 'GStore\\Services\\VIP_Purchase_Service' )
			? 'GStore\\Services\\VIP_Purchase_Service'
			: '';
	}
}

if ( ! function_exists( 'gstore_vip_is_purchase_enabled' ) ) {
	function gstore_vip_is_purchase_enabled() {
		$service = gstore_vip_purchase_service_class();
		if ( $service && is_callable( array( $service, 'is_enabled' ) ) ) {
			return (bool) call_user_func( array( $service, 'is_enabled' ) );
		}

		return 'yes' === get_option( 'gstore_vip_enabled', 'no' );
	}
}

if ( ! function_exists( 'gstore_vip_get_enabled_plans' ) ) {
	function gstore_vip_get_enabled_plans() {
		$service = gstore_vip_purchase_service_class();
		if ( $service && is_callable( array( $service, 'get_enabled_plans' ) ) ) {
			$plans = call_user_func( array( $service, 'get_enabled_plans' ) );
			return is_array( $plans ) ? $plans : array();
		}

		if ( ! gstore_vip_is_purchase_enabled() ) {
			return array();
		}

		$plans = array();
		if ( 'yes' === get_option( 'gstore_vip_monthly_enabled', 'yes' ) ) {
			$plans['monthly'] = array(
				'key'         => 'monthly',
				'label'       => __( 'VIP Mensal', 'gstore' ),
				'shortLabel'  => __( 'Mensal', 'gstore' ),
				'description' => __( 'Acesso VIP por 1 mes.', 'gstore' ),
				'buttonLabel' => __( 'Comprar mensal', 'gstore' ),
				'price'       => (string) get_option( 'gstore_vip_monthly_price', '29.90' ),
			);
		}
		if ( 'yes' === get_option( 'gstore_vip_annual_enabled', 'yes' ) ) {
			$plans['annual'] = array(
				'key'         => 'annual',
				'label'       => __( 'VIP Anual', 'gstore' ),
				'shortLabel'  => __( 'Anual', 'gstore' ),
				'description' => __( 'Acesso VIP por 1 ano.', 'gstore' ),
				'buttonLabel' => __( 'Comprar anual', 'gstore' ),
				'price'       => (string) get_option( 'gstore_vip_annual_price', '299.90' ),
			);
		}

		return $plans;
	}
}

if ( ! function_exists( 'gstore_vip_get_buy_url' ) ) {
	function gstore_vip_get_buy_url( $plan_key ) {
		$service = gstore_vip_purchase_service_class();
		if ( $service && is_callable( array( $service, 'get_buy_url' ) ) ) {
			return (string) call_user_func( array( $service, 'get_buy_url' ), $plan_key );
		}

		return '';
	}
}

if ( ! function_exists( 'gstore_vip_user_is_active' ) ) {
	function gstore_vip_user_is_active( $user_id = null ) {
		$service = gstore_vip_purchase_service_class();
		if ( $service && is_callable( array( $service, 'user_has_active_vip' ) ) ) {
			return (bool) call_user_func( array( $service, 'user_has_active_vip' ), $user_id );
		}

		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		return $user_id > 0 && (bool) get_user_meta( $user_id, '_gstore_user_is_vip', true );
	}
}

if ( ! function_exists( 'gstore_vip_get_user_expires_at' ) ) {
	function gstore_vip_get_user_expires_at( $user_id = null ) {
		$service = gstore_vip_purchase_service_class();
		if ( $service && is_callable( array( $service, 'get_user_expires_at' ) ) ) {
			return absint( call_user_func( array( $service, 'get_user_expires_at' ), $user_id ) );
		}

		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		return $user_id > 0 ? absint( get_user_meta( $user_id, 'gstore_vip_expires_at', true ) ) : 0;
	}
}

if ( ! function_exists( 'gstore_vip_get_user_plan' ) ) {
	function gstore_vip_get_user_plan( $user_id = null ) {
		$service = gstore_vip_purchase_service_class();
		if ( $service && is_callable( array( $service, 'get_user_plan' ) ) ) {
			return (string) call_user_func( array( $service, 'get_user_plan' ), $user_id );
		}

		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		return $user_id > 0 ? sanitize_key( get_user_meta( $user_id, 'gstore_vip_plan', true ) ) : '';
	}
}

if ( ! function_exists( 'gstore_vip_get_category_url' ) ) {
	function gstore_vip_get_category_url() {
		$service = gstore_vip_purchase_service_class();
		if ( $service && is_callable( array( $service, 'get_vip_category_url' ) ) ) {
			return (string) call_user_func( array( $service, 'get_vip_category_url' ) );
		}

		$category_id = absint( get_option( 'gstore_vip_category_id', 0 ) );
		if ( $category_id <= 0 ) {
			return '';
		}

		$link = get_term_link( $category_id, 'product_cat' );
		return is_wp_error( $link ) ? '' : (string) $link;
	}
}

if ( ! function_exists( 'gstore_vip_register_account_endpoint' ) ) {
	function gstore_vip_register_account_endpoint() {
		add_rewrite_endpoint( 'vip', EP_ROOT | EP_PAGES );
	}
}
add_action( 'init', 'gstore_vip_register_account_endpoint', 5 );

if ( ! function_exists( 'gstore_vip_maybe_flush_account_endpoint' ) ) {
	function gstore_vip_maybe_flush_account_endpoint() {
		$version = '20260513';
		if ( get_option( 'gstore_vip_account_endpoint_version' ) === $version ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'gstore_vip_account_endpoint_version', $version, true );
	}
}
add_action( 'init', 'gstore_vip_maybe_flush_account_endpoint', 20 );

if ( ! function_exists( 'gstore_vip_add_account_query_var' ) ) {
	function gstore_vip_add_account_query_var( $vars ) {
		$vars['vip'] = 'vip';
		return $vars;
	}
}
add_filter( 'woocommerce_get_query_vars', 'gstore_vip_add_account_query_var' );

if ( ! function_exists( 'gstore_vip_should_show_account_menu' ) ) {
	function gstore_vip_should_show_account_menu() {
		return gstore_vip_is_purchase_enabled() || gstore_vip_user_is_active();
	}
}

if ( ! function_exists( 'gstore_vip_add_account_menu_item' ) ) {
	function gstore_vip_add_account_menu_item( $items ) {
		if ( ! gstore_vip_should_show_account_menu() ) {
			return $items;
		}

		$label = __( 'VIP', 'gstore' );
		$next  = array();
		$added = false;

		foreach ( $items as $key => $item_label ) {
			if ( 'customer-logout' === $key ) {
				$next['vip'] = $label;
				$added       = true;
			}

			$next[ $key ] = $item_label;
		}

		if ( ! $added ) {
			$next['vip'] = $label;
		}

		return $next;
	}
}
add_filter( 'woocommerce_account_menu_items', 'gstore_vip_add_account_menu_item', 30 );

if ( ! function_exists( 'gstore_vip_format_price' ) ) {
	function gstore_vip_format_price( $price ) {
		$price = (float) $price;
		return function_exists( 'wc_price' ) ? wc_price( $price ) : 'R$ ' . number_format( $price, 2, ',', '.' );
	}
}

if ( ! function_exists( 'gstore_vip_render_account_endpoint' ) ) {
	function gstore_vip_render_account_endpoint() {
		$user_id      = get_current_user_id();
		$is_active    = gstore_vip_user_is_active( $user_id );
		$expires_at   = gstore_vip_get_user_expires_at( $user_id );
		$plans        = gstore_vip_get_enabled_plans();
		$category_url = gstore_vip_get_category_url();
		$enabled      = gstore_vip_is_purchase_enabled();
		?>
		<div class="gstore-vip-account">
			<section class="gstore-vip-hero">
				<div class="gstore-vip-hero__copy">
					<span class="<?php echo esc_attr( 'gstore-vip-status ' . ( $is_active ? 'is-active' : 'is-inactive' ) ); ?>">
						<?php echo esc_html( $is_active ? __( 'VIP ativo', 'gstore' ) : __( 'VIP inativo', 'gstore' ) ); ?>
					</span>
					<h2><?php esc_html_e( 'Clube VIP', 'gstore' ); ?></h2>
					<p><?php esc_html_e( 'Tenha acesso antecipado a produtos de eventos e acompanhe uma area dedicada aos produtos VIP da loja.', 'gstore' ); ?></p>
				</div>
				<div class="gstore-vip-hero__status">
					<strong><?php esc_html_e( 'Seu acesso', 'gstore' ); ?></strong>
					<span>
						<?php
						if ( $is_active && $expires_at > 0 ) {
							printf(
								/* translators: %s: expiration date. */
								esc_html__( 'Valido ate %s', 'gstore' ),
								esc_html( date_i18n( get_option( 'date_format' ), $expires_at ) )
							);
						} elseif ( $is_active ) {
							esc_html_e( 'VIP liberado sem data de expiracao.', 'gstore' );
						} else {
							esc_html_e( 'Escolha um plano para ativar.', 'gstore' );
						}
						?>
					</span>
				</div>
			</section>

			<div class="gstore-vip-grid">
				<section class="gstore-vip-panel">
					<h3><?php esc_html_e( 'Beneficios VIP', 'gstore' ); ?></h3>
					<div class="gstore-vip-benefits">
						<div>
							<strong><?php esc_html_e( 'Acesso antecipado', 'gstore' ); ?></strong>
							<span><?php esc_html_e( 'Produtos de eventos aparecem primeiro para clientes VIP.', 'gstore' ); ?></span>
						</div>
						<div>
							<strong><?php esc_html_e( 'Produtos VIP', 'gstore' ); ?></strong>
							<span><?php esc_html_e( 'Acompanhe uma categoria dedicada aos itens exclusivos.', 'gstore' ); ?></span>
						</div>
						<div>
							<strong><?php esc_html_e( 'Conta atualizada', 'gstore' ); ?></strong>
							<span><?php esc_html_e( 'Depois do pagamento, o VIP fica ativo automaticamente no seu usuario.', 'gstore' ); ?></span>
						</div>
					</div>
					<?php if ( $category_url ) : ?>
						<a class="button gstore-vip-category-button" href="<?php echo esc_url( $category_url ); ?>">
							<?php esc_html_e( 'Ver produtos VIP', 'gstore' ); ?>
						</a>
					<?php endif; ?>
				</section>

				<section class="gstore-vip-panel">
					<h3><?php esc_html_e( 'Planos', 'gstore' ); ?></h3>
					<?php if ( ! $enabled ) : ?>
						<p><?php esc_html_e( 'A compra de VIP esta desativada no momento.', 'gstore' ); ?></p>
					<?php elseif ( empty( $plans ) ) : ?>
						<p><?php esc_html_e( 'Nenhum plano VIP esta disponivel agora.', 'gstore' ); ?></p>
					<?php else : ?>
						<div class="gstore-vip-plans">
							<?php foreach ( $plans as $plan ) : ?>
								<?php
								$plan_key = isset( $plan['key'] ) ? sanitize_key( $plan['key'] ) : '';
								$buy_url  = gstore_vip_get_buy_url( $plan_key );
								if ( ! $buy_url ) {
									continue;
								}
								?>
								<div class="gstore-vip-plan">
									<div>
										<span><?php echo esc_html( isset( $plan['shortLabel'] ) ? $plan['shortLabel'] : $plan['label'] ); ?></span>
										<strong><?php echo wp_kses_post( gstore_vip_format_price( isset( $plan['price'] ) ? $plan['price'] : 0 ) ); ?></strong>
										<small><?php echo esc_html( isset( $plan['description'] ) ? $plan['description'] : '' ); ?></small>
									</div>
									<a class="button gstore-vip-plan__button" href="<?php echo esc_url( $buy_url ); ?>">
										<?php echo esc_html( isset( $plan['buttonLabel'] ) ? $plan['buttonLabel'] : __( 'Comprar VIP', 'gstore' ) ); ?>
									</a>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}
}
add_action( 'woocommerce_account_vip_endpoint', 'gstore_vip_render_account_endpoint' );

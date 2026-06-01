<?php
/**
 * Partner dashboard inside WooCommerce My Account.
 *
 * @package GStore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gstore_partner_account_register_endpoint' ) ) {
	function gstore_partner_account_register_endpoint() {
		add_rewrite_endpoint( 'revendedor', EP_ROOT | EP_PAGES );
	}
}
add_action( 'init', 'gstore_partner_account_register_endpoint', 5 );

if ( ! function_exists( 'gstore_partner_account_maybe_flush_endpoint' ) ) {
	function gstore_partner_account_maybe_flush_endpoint() {
		$version = '20260520';
		if ( get_option( 'gstore_partner_account_endpoint_version' ) === $version ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'gstore_partner_account_endpoint_version', $version, true );
	}
}
add_action( 'init', 'gstore_partner_account_maybe_flush_endpoint', 20 );

if ( ! function_exists( 'gstore_partner_account_add_query_var' ) ) {
	function gstore_partner_account_add_query_var( $vars ) {
		$vars['revendedor'] = 'revendedor';
		return $vars;
	}
}
add_filter( 'woocommerce_get_query_vars', 'gstore_partner_account_add_query_var' );

if ( ! function_exists( 'gstore_partner_account_is_visible' ) ) {
	function gstore_partner_account_is_visible() {
		return function_exists( 'gstore_partner_user_is_partner' ) && gstore_partner_user_is_partner( get_current_user_id() );
	}
}

if ( ! function_exists( 'gstore_partner_account_add_menu_item' ) ) {
	function gstore_partner_account_add_menu_item( $items ) {
		if ( ! gstore_partner_account_is_visible() ) {
			return $items;
		}

		$next  = array();
		$added = false;
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$next['revendedor'] = __( 'Revendedor', 'gstore' );
				$added              = true;
			}
			$next[ $key ] = $label;
		}

		if ( ! $added ) {
			$next['revendedor'] = __( 'Revendedor', 'gstore' );
		}

		return $next;
	}
}
add_filter( 'woocommerce_account_menu_items', 'gstore_partner_account_add_menu_item', 28 );

if ( ! function_exists( 'gstore_partner_account_get_data' ) ) {
	function gstore_partner_account_get_data() {
		if ( function_exists( 'gstore_partner_get_dashboard_data' ) ) {
			$data = gstore_partner_get_dashboard_data( get_current_user_id() );
			return is_array( $data ) ? $data : array();
		}

		if ( class_exists( 'GStore\\Services\\Partner_Program_Service' ) ) {
			return GStore\Services\Partner_Program_Service::get_dashboard_data( get_current_user_id() );
		}

		return array(
			'enabled'   => false,
			'isPartner' => false,
		);
	}
}

if ( ! function_exists( 'gstore_partner_account_request_redemption' ) ) {
	function gstore_partner_account_request_redemption( $amount, $method, $note ) {
		if ( function_exists( 'gstore_partner_request_redemption' ) ) {
			return gstore_partner_request_redemption( get_current_user_id(), $amount, $method, $note, false );
		}

		if ( class_exists( 'GStore\\Services\\Partner_Program_Service' ) ) {
			$amount_cents = GStore\Services\Partner_Program_Service::decimal_to_cents( $amount );
			return GStore\Services\Partner_Program_Service::request_redemption( get_current_user_id(), $amount_cents, $method, $note );
		}

		return new WP_Error( 'partner_service_missing', __( 'Programa de revendedores indisponivel.', 'gstore' ) );
	}
}

if ( ! function_exists( 'gstore_partner_account_contract_version' ) ) {
	function gstore_partner_account_contract_version() {
		return (string) apply_filters( 'gstore_partner_contract_version', '20260520' );
	}
}

if ( ! function_exists( 'gstore_partner_account_store_name' ) ) {
	function gstore_partner_account_store_name() {
		$store_name = '';

		if ( function_exists( 'get_option' ) ) {
			$store_name = (string) get_option( 'woocommerce_email_from_name', '' );
		}

		if ( '' === trim( $store_name ) ) {
			$store_name = (string) get_bloginfo( 'name' );
		}

		$store_name = trim( wp_specialchars_decode( $store_name, ENT_QUOTES ) );

		return (string) apply_filters( 'gstore_partner_account_store_name', '' !== $store_name ? $store_name : __( 'Loja', 'gstore' ) );
	}
}

if ( ! function_exists( 'gstore_partner_account_application_status_label' ) ) {
	function gstore_partner_account_application_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Em analise', 'gstore' ),
			'approved'  => __( 'Aprovada', 'gstore' ),
			'rejected'  => __( 'Rejeitada', 'gstore' ),
			'cancelled' => __( 'Cancelada', 'gstore' ),
		);

		$status = sanitize_key( (string) $status );
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}

if ( ! function_exists( 'gstore_partner_account_can_show_application' ) ) {
	function gstore_partner_account_can_show_application() {
		if ( ! function_exists( 'gstore_partner_is_enabled' ) || ! gstore_partner_is_enabled() ) {
			return false;
		}

		return ! function_exists( 'gstore_partner_user_is_partner' ) || ! gstore_partner_user_is_partner( get_current_user_id() );
	}
}

if ( ! function_exists( 'gstore_partner_account_application_page_slug' ) ) {
	function gstore_partner_account_application_page_slug() {
		return sanitize_title( (string) apply_filters( 'gstore_partner_application_page_slug', 'programa-de-parceiros' ) );
	}
}

if ( ! function_exists( 'gstore_partner_account_application_page_url' ) ) {
	function gstore_partner_account_application_page_url() {
		return home_url( user_trailingslashit( gstore_partner_account_application_page_slug() ) );
	}
}

if ( ! function_exists( 'gstore_partner_account_register_application_page' ) ) {
	function gstore_partner_account_register_application_page() {
		add_rewrite_rule(
			'^' . preg_quote( gstore_partner_account_application_page_slug(), '#' ) . '/?$',
			'index.php?gstore_partner_application_page=1',
			'top'
		);
	}
}
add_action( 'init', 'gstore_partner_account_register_application_page', 6 );

if ( ! function_exists( 'gstore_partner_account_add_application_query_var' ) ) {
	function gstore_partner_account_add_application_query_var( $vars ) {
		$vars[] = 'gstore_partner_application_page';
		return $vars;
	}
}
add_filter( 'query_vars', 'gstore_partner_account_add_application_query_var' );

if ( ! function_exists( 'gstore_partner_account_maybe_flush_application_page' ) ) {
	function gstore_partner_account_maybe_flush_application_page() {
		$version = '20260601:' . gstore_partner_account_application_page_slug();
		if ( get_option( 'gstore_partner_application_page_version' ) === $version ) {
			return;
		}

		gstore_partner_account_register_application_page();
		flush_rewrite_rules( false );
		update_option( 'gstore_partner_application_page_version', $version, true );
	}
}
add_action( 'init', 'gstore_partner_account_maybe_flush_application_page', 25 );

if ( ! function_exists( 'gstore_partner_account_application_template' ) ) {
	function gstore_partner_account_application_template( $template ) {
		if ( ! get_query_var( 'gstore_partner_application_page' ) ) {
			return $template;
		}

		if ( ! function_exists( 'gstore_partner_is_enabled' ) || ! gstore_partner_is_enabled() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();

			$not_found = get_404_template();
			return $not_found ? $not_found : $template;
		}

		$partner_template = get_theme_file_path( 'templates/gstore-partner-program-page.php' );
		return file_exists( $partner_template ) ? $partner_template : $template;
	}
}
add_filter( 'template_include', 'gstore_partner_account_application_template', 20 );

if ( ! function_exists( 'gstore_partner_account_application_document_title' ) ) {
	function gstore_partner_account_application_document_title( $title ) {
		if ( get_query_var( 'gstore_partner_application_page' ) && function_exists( 'gstore_partner_is_enabled' ) && gstore_partner_is_enabled() ) {
			return sprintf(
				/* translators: %s: store name. */
				__( 'Programa de Parceiros %s', 'gstore' ),
				gstore_partner_account_store_name()
			);
		}

		return $title;
	}
}
add_filter( 'pre_get_document_title', 'gstore_partner_account_application_document_title', 20 );

if ( ! function_exists( 'gstore_partner_account_contract_is_accepted' ) ) {
	function gstore_partner_account_contract_is_accepted( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		$accepted = (bool) get_user_meta( $user_id, '_gstore_partner_contract_accepted', true );
		$version  = (string) get_user_meta( $user_id, '_gstore_partner_contract_version', true );

		return $accepted && $version === gstore_partner_account_contract_version();
	}
}

if ( ! function_exists( 'gstore_partner_account_contract_accepted_at' ) ) {
	function gstore_partner_account_contract_accepted_at( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		return $user_id > 0 ? (string) get_user_meta( $user_id, '_gstore_partner_contract_accepted_at', true ) : '';
	}
}

if ( ! function_exists( 'gstore_partner_account_handle_redemption_submission' ) ) {
	function gstore_partner_account_handle_redemption_submission() {
		if ( empty( $_POST['gstore_partner_redemption_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! is_user_logged_in() || ! gstore_partner_account_is_visible() ) {
			return;
		}

		if ( ! gstore_partner_account_contract_is_accepted( get_current_user_id() ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Aceite o contrato do Programa Revendedor antes de solicitar resgates.', 'gstore' ), 'error' );
			}

			wp_safe_redirect( gstore_partner_account_view_url( 'contrato' ) );
			exit;
		}

		$nonce = isset( $_POST['gstore_partner_redemption_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['gstore_partner_redemption_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'gstore_partner_redemption' ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Nao foi possivel validar o resgate. Atualize a pagina e tente novamente.', 'gstore' ), 'error' );
			}
			return;
		}

		$amount = isset( $_POST['gstore_partner_redemption_amount'] ) ? wp_unslash( $_POST['gstore_partner_redemption_amount'] ) : '';
		$method = isset( $_POST['gstore_partner_redemption_method'] ) ? sanitize_key( wp_unslash( $_POST['gstore_partner_redemption_method'] ) ) : 'external';
		$note   = isset( $_POST['gstore_partner_redemption_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gstore_partner_redemption_note'] ) ) : '';

		$result = gstore_partner_account_request_redemption( $amount, $method, $note );
		if ( function_exists( 'wc_add_notice' ) ) {
			if ( is_wp_error( $result ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
			} else {
				wc_add_notice( __( 'Solicitacao de resgate enviada.', 'gstore' ), 'success' );
			}
		}

		wp_safe_redirect( add_query_arg( 'view', 'creditos', wc_get_account_endpoint_url( 'revendedor' ) ) );
		exit;
	}
}
add_action( 'template_redirect', 'gstore_partner_account_handle_redemption_submission', 8 );

if ( ! function_exists( 'gstore_partner_account_handle_contract_submission' ) ) {
	function gstore_partner_account_handle_contract_submission() {
		if ( empty( $_POST['gstore_partner_contract_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! is_user_logged_in() || ! gstore_partner_account_is_visible() ) {
			return;
		}

		$nonce = isset( $_POST['gstore_partner_contract_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['gstore_partner_contract_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'gstore_partner_contract_accept' ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Nao foi possivel validar o aceite do contrato. Atualize a pagina e tente novamente.', 'gstore' ), 'error' );
			}
			return;
		}

		if ( empty( $_POST['gstore_partner_contract_accept'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Voce precisa aceitar os termos do contrato para acessar o painel de revendedor.', 'gstore' ), 'error' );
			}
			wp_safe_redirect( gstore_partner_account_view_url( 'contrato' ) );
			exit;
		}

		$user_id = get_current_user_id();
		update_user_meta( $user_id, '_gstore_partner_contract_accepted', '1' );
		update_user_meta( $user_id, '_gstore_partner_contract_version', gstore_partner_account_contract_version() );
		update_user_meta( $user_id, '_gstore_partner_contract_accepted_at', current_time( 'mysql' ) );
		update_user_meta(
			$user_id,
			'_gstore_partner_contract_acceptance',
			array(
				'version'    => gstore_partner_account_contract_version(),
				'accepted_at' => current_time( 'mysql' ),
				'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 250 ) : '',
			)
		);

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Contrato de revendedor aceito com sucesso.', 'gstore' ), 'success' );
		}

		wp_safe_redirect( gstore_partner_account_view_url( 'painel' ) );
		exit;
	}
}
add_action( 'template_redirect', 'gstore_partner_account_handle_contract_submission', 8 );

if ( ! function_exists( 'gstore_partner_account_handle_application_submission' ) ) {
	function gstore_partner_account_handle_application_submission() {
		if ( empty( $_POST['gstore_partner_application_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		}

		if ( ! gstore_partner_account_can_show_application() ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'O programa de revendedores nao esta disponivel para esta conta.', 'gstore' ), 'error' );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		$nonce = isset( $_POST['gstore_partner_application_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['gstore_partner_application_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'gstore_partner_application' ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Nao foi possivel validar a solicitacao. Atualize a pagina e tente novamente.', 'gstore' ), 'error' );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( ! function_exists( 'gstore_partner_submit_application' ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Programa de revendedores indisponivel no momento.', 'gstore' ), 'error' );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		$data = array(
			'name'         => isset( $_POST['gstore_partner_application_name'] ) ? wp_unslash( $_POST['gstore_partner_application_name'] ) : '',
			'email'        => isset( $_POST['gstore_partner_application_email'] ) ? wp_unslash( $_POST['gstore_partner_application_email'] ) : '',
			'phone'        => isset( $_POST['gstore_partner_application_phone'] ) ? wp_unslash( $_POST['gstore_partner_application_phone'] ) : '',
			'city_uf'      => isset( $_POST['gstore_partner_application_city_uf'] ) ? wp_unslash( $_POST['gstore_partner_application_city_uf'] ) : '',
			'cpf'          => isset( $_POST['gstore_partner_application_cpf'] ) ? wp_unslash( $_POST['gstore_partner_application_cpf'] ) : '',
			'cnpj'         => isset( $_POST['gstore_partner_application_cnpj'] ) ? wp_unslash( $_POST['gstore_partner_application_cnpj'] ) : '',
			'profile_type' => isset( $_POST['gstore_partner_application_profile_type'] ) ? wp_unslash( $_POST['gstore_partner_application_profile_type'] ) : '',
			'about'        => isset( $_POST['gstore_partner_application_about'] ) ? wp_unslash( $_POST['gstore_partner_application_about'] ) : '',
		);

		$result = gstore_partner_submit_application( $data, $_FILES ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_wp_error( $result ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
			}
			wp_safe_redirect( add_query_arg( 'partner_application', 'error', $redirect ) . '#gstore-partner-application' );
			exit;
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Solicitacao enviada com sucesso. Nossa equipe vai analisar seus dados.', 'gstore' ), 'success' );
		}

		wp_safe_redirect( add_query_arg( 'partner_application', 'sent', $redirect ) . '#gstore-partner-application' );
		exit;
	}
}
add_action( 'template_redirect', 'gstore_partner_account_handle_application_submission', 8 );

if ( ! function_exists( 'gstore_partner_account_view_url' ) ) {
	function gstore_partner_account_view_url( $view ) {
		return add_query_arg( 'view', sanitize_key( $view ), wc_get_account_endpoint_url( 'revendedor' ) );
	}
}

if ( ! function_exists( 'gstore_partner_account_icon' ) ) {
	function gstore_partner_account_icon( $name ) {
		$icons = array(
			'link'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l2.12-2.12a5 5 0 0 0-7.07-7.07L11 4.93"></path><path d="M14 11a5 5 0 0 0-7.07 0L4.81 13.12a5 5 0 0 0 7.07 7.07L13 19.07"></path></svg>',
			'cart'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
			'coins'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"></ellipse><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"></path><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"></path></svg>',
			'percent' => '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="19" y1="5" x2="5" y2="19"></line><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle></svg>',
			'check'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l2 2 4-4"></path><path d="M8 3h8l2 3v13a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6z"></path><path d="M8 3v4h8V3"></path></svg>',
			'headset' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13a8 8 0 0 1 16 0"></path><path d="M4 13v4a2 2 0 0 0 2 2h2v-8H6a2 2 0 0 0-2 2z"></path><path d="M20 13v4a2 2 0 0 1-2 2h-2v-8h2a2 2 0 0 1 2 2z"></path><path d="M16 19a4 4 0 0 1-4 3h-1"></path></svg>',
			'gift'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"></path></svg>',
			'info'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6"></path><path d="M10 22h4"></path><path d="M12 2a7 7 0 0 0-4 12.74V17h8v-2.26A7 7 0 0 0 12 2z"></path></svg>',
		);

		return isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['info'];
	}
}

if ( ! function_exists( 'gstore_partner_account_latest_application' ) ) {
	function gstore_partner_account_latest_application() {
		$current_user = wp_get_current_user();
		$user_id      = $current_user && $current_user->exists() ? absint( $current_user->ID ) : 0;
		$user_email   = $user_id > 0 ? (string) $current_user->user_email : '';

		if ( $user_id > 0 && function_exists( 'gstore_partner_get_latest_application' ) ) {
			return gstore_partner_get_latest_application( $user_id, $user_email );
		}

		return null;
	}
}

if ( ! function_exists( 'gstore_partner_account_render_application_cta' ) ) {
	function gstore_partner_account_render_application_cta( $context = 'account' ) {
		if ( ! gstore_partner_account_can_show_application() ) {
			return;
		}

		$latest  = gstore_partner_account_latest_application();
		$context = sanitize_key( (string) $context );
		?>
		<section class="gstore-partner-application-cta gstore-partner-application-cta--<?php echo esc_attr( 'register' === $context ? 'register' : 'account' ); ?>">
			<div>
				<strong><?php esc_html_e( 'Seja um revendedor', 'gstore' ); ?></strong>
				<p><?php esc_html_e( 'Conheca o programa, envie seus dados e acompanhe a analise da equipe comercial.', 'gstore' ); ?></p>
			</div>
			<?php if ( ! empty( $latest ) && 'pending' === $latest['status'] ) : ?>
				<span class="gstore-partner-application__badge"><?php echo esc_html( gstore_partner_account_application_status_label( $latest['status'] ) ); ?></span>
			<?php else : ?>
				<a class="button gstore-partner-application__toggle" href="<?php echo esc_url( gstore_partner_account_application_page_url() ); ?>">
					<?php esc_html_e( 'Seja um revendedor', 'gstore' ); ?>
				</a>
			<?php endif; ?>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gstore_partner_account_render_application_form' ) ) {
	function gstore_partner_account_render_application_form( $context = 'account' ) {
		if ( ! gstore_partner_account_can_show_application() ) {
			return;
		}

		$context = sanitize_key( (string) $context );
		if ( 'page' !== $context ) {
			gstore_partner_account_render_application_cta( $context );
			return;
		}

		$current_user = wp_get_current_user();
		$user_id      = $current_user && $current_user->exists() ? absint( $current_user->ID ) : 0;
		$user_name    = $user_id > 0 ? (string) $current_user->display_name : '';
		$user_email   = $user_id > 0 ? (string) $current_user->user_email : '';
		$latest       = gstore_partner_account_latest_application();

		$panel_id      = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'gstore-partner-application-panel-' ) : 'gstore-partner-application-panel-' . wp_rand( 1000, 9999 );
		$section_class = 'gstore-partner-application gstore-partner-application--page';
		?>
		<section id="gstore-partner-application" class="<?php echo esc_attr( $section_class ); ?>">
			<div class="gstore-partner-application__intro">
				<div>
					<strong><?php esc_html_e( 'Cadastro de interesse', 'gstore' ); ?></strong>
					<p><?php esc_html_e( 'Envie seus dados para avaliarmos sua participação no programa de revendedores.', 'gstore' ); ?></p>
				</div>
				<?php if ( ! empty( $latest ) && 'pending' === $latest['status'] ) : ?>
					<span class="gstore-partner-application__badge"><?php echo esc_html( gstore_partner_account_application_status_label( $latest['status'] ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $latest ) && 'pending' === $latest['status'] ) : ?>
				<p class="gstore-partner-application__status"><?php esc_html_e( 'Sua solicitacao ja foi enviada e esta em analise. Assim que for aprovada, a aba Revendedor fica disponivel nesta conta.', 'gstore' ); ?></p>
			<?php else : ?>
				<form id="<?php echo esc_attr( $panel_id ); ?>" class="gstore-partner-application__form" method="post" enctype="multipart/form-data">
					<input type="hidden" name="gstore_partner_application_action" value="submit" />
					<?php wp_nonce_field( 'gstore_partner_application', 'gstore_partner_application_nonce' ); ?>

					<div class="gstore-partner-application__grid">
						<label>
							<span><?php esc_html_e( 'Nome completo', 'gstore' ); ?></span>
							<input type="text" name="gstore_partner_application_name" value="<?php echo esc_attr( $user_name ); ?>" required autocomplete="name" />
						</label>
						<label>
							<span><?php esc_html_e( 'E-mail', 'gstore' ); ?></span>
							<input type="email" name="gstore_partner_application_email" value="<?php echo esc_attr( $user_email ); ?>" required autocomplete="email" <?php echo $user_email ? 'readonly' : ''; ?> />
						</label>
						<label>
							<span><?php esc_html_e( 'WhatsApp', 'gstore' ); ?></span>
							<input type="text" name="gstore_partner_application_phone" inputmode="tel" required autocomplete="tel" />
						</label>
						<label>
							<span><?php esc_html_e( 'Cidade / UF', 'gstore' ); ?></span>
							<input type="text" name="gstore_partner_application_city_uf" required autocomplete="address-level2" />
						</label>
						<label>
							<span><?php esc_html_e( 'CPF', 'gstore' ); ?></span>
							<input type="text" name="gstore_partner_application_cpf" inputmode="numeric" required autocomplete="off" />
						</label>
						<label>
							<span><?php esc_html_e( 'CNPJ', 'gstore' ); ?></span>
							<input type="text" name="gstore_partner_application_cnpj" inputmode="numeric" autocomplete="off" />
						</label>
						<label class="gstore-partner-application__file">
							<span><?php esc_html_e( 'Documento de identidade', 'gstore' ); ?></span>
							<input type="file" name="gstore_partner_identity_document" accept=".jpg,.jpeg,.png,.pdf" required />
						</label>
					</div>

					<fieldset class="gstore-partner-application__profile">
						<legend><?php esc_html_e( 'Voce e:', 'gstore' ); ?></legend>
						<label><input type="radio" name="gstore_partner_application_profile_type" value="store" required /> <span><?php esc_html_e( 'Loja', 'gstore' ); ?></span></label>
						<label><input type="radio" name="gstore_partner_application_profile_type" value="dispatcher" /> <span><?php esc_html_e( 'Despachante', 'gstore' ); ?></span></label>
						<label><input type="radio" name="gstore_partner_application_profile_type" value="club" /> <span><?php esc_html_e( 'Clube de tiro', 'gstore' ); ?></span></label>
						<label><input type="radio" name="gstore_partner_application_profile_type" value="instructor" /> <span><?php esc_html_e( 'Instrutor', 'gstore' ); ?></span></label>
						<label><input type="radio" name="gstore_partner_application_profile_type" value="reseller" /> <span><?php esc_html_e( 'Revendedor', 'gstore' ); ?></span></label>
						<label><input type="radio" name="gstore_partner_application_profile_type" value="other" /> <span><?php esc_html_e( 'Outro perfil comercial', 'gstore' ); ?></span></label>
					</fieldset>

					<label class="gstore-partner-application__about">
						<span><?php esc_html_e( 'Nos conte um pouco mais sobre voce', 'gstore' ); ?></span>
						<textarea name="gstore_partner_application_about" rows="5" required></textarea>
					</label>

					<button type="submit" class="button gstore-partner-primary-button"><?php esc_html_e( 'Enviar cadastro', 'gstore' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gstore_partner_account_print_application_script' ) ) {
	function gstore_partner_account_print_application_script() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		<script>
			(function(){
				if (window.gstorePartnerApplicationToggleReady) return;
				window.gstorePartnerApplicationToggleReady = true;
				document.addEventListener('click', function(event){
					var toggle = event.target.closest('[data-gstore-partner-application-toggle]');
					if (!toggle) return;
					var target = document.querySelector(toggle.getAttribute('data-target'));
					if (!target) return;
					var isHidden = target.hasAttribute('hidden');
					if (isHidden) {
						target.removeAttribute('hidden');
					} else {
						target.setAttribute('hidden', 'hidden');
					}
					toggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
				});
			})();
		</script>
		<?php
	}
}

if ( ! function_exists( 'gstore_partner_account_render_dashboard_application' ) ) {
	function gstore_partner_account_render_dashboard_application() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		gstore_partner_account_render_application_cta( 'account' );
	}
}
add_action( 'woocommerce_account_dashboard', 'gstore_partner_account_render_dashboard_application', 18 );

if ( ! function_exists( 'gstore_partner_account_render_application_page' ) ) {
	function gstore_partner_account_render_application_page() {
		if ( ! function_exists( 'gstore_partner_is_enabled' ) || ! gstore_partner_is_enabled() ) {
			return;
		}

		$store_name = gstore_partner_account_store_name();
		$hero_image = (string) apply_filters( 'gstore_partner_application_hero_image', get_theme_file_uri( 'assets/images/partners/partner-program-hero.jpg' ) );
		$profiles   = array(
			array(
				'image' => get_theme_file_uri( 'assets/images/partners/partner-program-lojistas.jpg' ),
				'title' => __( 'Lojistas', 'gstore' ),
				'text'  => __( 'Indique clientes e amplie oportunidades com apoio comercial.', 'gstore' ),
			),
			array(
				'image' => get_theme_file_uri( 'assets/images/partners/partner-program-clubes.jpg' ),
				'title' => __( 'Clubes de tiro', 'gstore' ),
				'text'  => __( 'Converta sua comunidade em vendas qualificadas e acompanhadas.', 'gstore' ),
			),
			array(
				'image' => get_theme_file_uri( 'assets/images/partners/partner-program-instrutores.jpg' ),
				'title' => __( 'Instrutores', 'gstore' ),
				'text'  => __( 'Oriente alunos e receba por indicacoes comerciais aprovadas.', 'gstore' ),
			),
			array(
				'image' => get_theme_file_uri( 'assets/images/partners/partner-program-revendedores.jpg' ),
				'title' => __( 'Revendedores', 'gstore' ),
				'text'  => __( 'Trabalhe campanhas, condicoes e atendimento com a equipe.', 'gstore' ),
			),
		);
		?>
		<main class="gstore-partner-program-page" style="<?php echo esc_attr( '--partner-hero-image: url(' . esc_url_raw( $hero_image ) . ');' ); ?>">
			<section class="gstore-partner-program-hero">
				<div class="gstore-partner-program-hero__shade"></div>
				<div class="gstore-partner-program-container gstore-partner-program-hero__content">
					<span class="gstore-partner-program-eyebrow">
						<?php
						printf(
							/* translators: %s: store name. */
							esc_html__( 'Programa comercial %s', 'gstore' ),
							esc_html( $store_name )
						);
						?>
					</span>
					<h1>
						<?php
						printf(
							/* translators: %s: store name. */
							esc_html__( 'Programa de Parceiros %s', 'gstore' ),
							esc_html( $store_name )
						);
						?>
					</h1>
					<p><?php esc_html_e( 'Indique, revenda e acompanhe recompensas em vendas aprovadas pela sua rede.', 'gstore' ); ?></p>
					<div class="gstore-partner-program-hero__actions">
						<a class="button gstore-partner-program-button" href="#gstore-partner-application"><?php esc_html_e( 'Quero ser um revendedor', 'gstore' ); ?></a>
						<a class="button gstore-partner-program-button is-ghost" href="#gstore-partner-program-how"><?php esc_html_e( 'Como funciona', 'gstore' ); ?></a>
					</div>
				</div>
			</section>

			<section class="gstore-partner-program-benefits" aria-label="<?php esc_attr_e( 'Beneficios do programa', 'gstore' ); ?>">
				<div class="gstore-partner-program-container">
					<div><span><?php echo gstore_partner_account_icon( 'percent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><strong><?php esc_html_e( 'Comissao por indicacao', 'gstore' ); ?></strong><small><?php esc_html_e( 'Ganhe em vendas aprovadas', 'gstore' ); ?></small></div>
					<div><span><?php echo gstore_partner_account_icon( 'coins' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><strong><?php esc_html_e( 'Cashback em vendas', 'gstore' ); ?></strong><small><?php esc_html_e( 'Beneficios para parceiros ativos', 'gstore' ); ?></small></div>
					<div><span><?php echo gstore_partner_account_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><strong><?php esc_html_e( 'Vendas aprovadas', 'gstore' ); ?></strong><small><?php esc_html_e( 'Processo com analise e registro', 'gstore' ); ?></small></div>
					<div><span><?php echo gstore_partner_account_icon( 'headset' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><strong><?php esc_html_e( 'Suporte comercial', 'gstore' ); ?></strong><small><?php esc_html_e( 'Equipe para orientar sua operacao', 'gstore' ); ?></small></div>
				</div>
			</section>

			<section class="gstore-partner-program-section">
				<div class="gstore-partner-program-container">
					<div class="gstore-partner-program-heading">
						<span><?php echo esc_html( sprintf( __( 'Parceiros %s', 'gstore' ), $store_name ) ); ?></span>
						<h2><?php esc_html_e( 'Quem pode ser parceiro', 'gstore' ); ?></h2>
						<p><?php esc_html_e( 'Lojistas, clubes e especialistas que ja conversam com o publico certo podem transformar indicacoes em receita.', 'gstore' ); ?></p>
					</div>
					<div class="gstore-partner-program-profile-grid">
						<?php foreach ( $profiles as $profile ) : ?>
							<article>
								<img src="<?php echo esc_url( $profile['image'] ); ?>" alt="<?php echo esc_attr( $profile['title'] ); ?>" loading="lazy" />
								<div>
									<h3><?php echo esc_html( $profile['title'] ); ?></h3>
									<p><?php echo esc_html( $profile['text'] ); ?></p>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			</section>

			<section id="gstore-partner-program-how" class="gstore-partner-program-section is-muted">
				<div class="gstore-partner-program-container gstore-partner-program-how">
					<div>
						<span class="gstore-partner-program-eyebrow is-dark"><?php esc_html_e( 'Fluxo simples', 'gstore' ); ?></span>
						<h2><?php esc_html_e( 'Como funciona o programa', 'gstore' ); ?></h2>
						<p><?php esc_html_e( 'O cadastro passa por analise da equipe. Depois da aprovacao, o parceiro recebe o caminho de indicacao e acompanha as vendas elegiveis.', 'gstore' ); ?></p>
					</div>
					<div class="gstore-partner-program-steps">
						<article><span>01</span><h3><?php esc_html_e( 'Cadastro', 'gstore' ); ?></h3><p><?php esc_html_e( 'Voce envia seus dados, documento e perfil comercial.', 'gstore' ); ?></p></article>
						<article><span>02</span><h3><?php esc_html_e( 'Analise', 'gstore' ); ?></h3><p><?php esc_html_e( 'A equipe valida se o perfil combina com o programa.', 'gstore' ); ?></p></article>
						<article><span>03</span><h3><?php esc_html_e( 'Indicacao', 'gstore' ); ?></h3><p><?php esc_html_e( 'Use seu canal, codigo ou contato comercial aprovado.', 'gstore' ); ?></p></article>
						<article><span>04</span><h3><?php esc_html_e( 'Cashback', 'gstore' ); ?></h3><p><?php esc_html_e( 'Ganhe sobre vendas aprovadas conforme regra vigente.', 'gstore' ); ?></p></article>
					</div>
				</div>
			</section>

			<section class="gstore-partner-program-section is-dark">
				<div class="gstore-partner-program-container gstore-partner-program-gains">
					<div>
						<span class="gstore-partner-program-eyebrow"><?php esc_html_e( 'Modelos de ganho', 'gstore' ); ?></span>
						<h2><?php echo esc_html( sprintf( __( 'Formas de ganhar com a %s', 'gstore' ), $store_name ) ); ?></h2>
					</div>
					<div class="gstore-partner-program-gain-grid">
						<article><h3><?php esc_html_e( 'Indicacao qualificada', 'gstore' ); ?></h3><p><?php esc_html_e( 'Receba quando sua rede compra com acompanhamento da equipe.', 'gstore' ); ?></p></article>
						<article><h3><?php esc_html_e( 'Condicoes para revenda', 'gstore' ); ?></h3><p><?php esc_html_e( 'Campanhas e oportunidades comerciais para parceiros aprovados.', 'gstore' ); ?></p></article>
						<article><h3><?php esc_html_e( 'Campanhas locais', 'gstore' ); ?></h3><p><?php esc_html_e( 'Acoes para movimentar alunos, associados e eventos.', 'gstore' ); ?></p></article>
					</div>
				</div>
			</section>

			<section class="gstore-partner-program-section">
				<div class="gstore-partner-program-container gstore-partner-program-safety">
					<h2><?php esc_html_e( 'Parceria com processo e seguranca', 'gstore' ); ?></h2>
					<ul>
						<li><?php esc_html_e( 'Vendas sujeitas a documentacao e requisitos legais aplicaveis.', 'gstore' ); ?></li>
						<li><?php esc_html_e( 'Cadastro de parceiros com analise manual da equipe.', 'gstore' ); ?></li>
						<li><?php esc_html_e( 'Cashback e comissoes vinculados a vendas aprovadas.', 'gstore' ); ?></li>
					</ul>
				</div>
			</section>

			<section class="gstore-partner-program-section is-form">
				<div class="gstore-partner-program-container gstore-partner-program-form-shell">
					<div class="gstore-partner-program-form-copy">
						<span class="gstore-partner-program-eyebrow is-dark"><?php esc_html_e( 'Cadastro de interesse', 'gstore' ); ?></span>
						<h2><?php echo esc_html( sprintf( __( 'Quero ser parceiro %s', 'gstore' ), $store_name ) ); ?></h2>
						<p><?php esc_html_e( 'Preencha seus dados para a equipe avaliar seu perfil e explicar as proximas etapas do programa.', 'gstore' ); ?></p>
					</div>
					<?php gstore_partner_account_render_application_form( 'page' ); ?>
				</div>
			</section>
		</main>
		<?php
	}
}

if ( ! function_exists( 'gstore_partner_account_render_stat' ) ) {
	function gstore_partner_account_render_stat( $icon, $title, $value, $meta = '', $status = '' ) {
		?>
		<section class="gstore-partner-stat">
			<div class="gstore-partner-icon"><?php echo gstore_partner_account_icon( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div>
				<span><?php echo esc_html( $title ); ?></span>
				<?php if ( '' !== (string) $value ) : ?>
					<strong><?php echo esc_html( $value ); ?></strong>
				<?php endif; ?>
				<?php if ( $status ) : ?>
					<small class="is-status"><?php echo esc_html( $status ); ?></small>
				<?php elseif ( $meta ) : ?>
					<small><?php echo esc_html( $meta ); ?></small>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gstore_partner_account_sale_label' ) ) {
	function gstore_partner_account_sale_label( $sale ) {
		if ( 'cancelled' === gstore_partner_account_sale_status( $sale ) ) {
			return __( 'Venda cancelada', 'gstore' );
		}

		if ( isset( $sale['type'] ) && 'refund' === $sale['type'] ) {
			return __( 'Comissao estornada', 'gstore' );
		}

		return __( 'Pagamento confirmado', 'gstore' );
	}
}

if ( ! function_exists( 'gstore_partner_account_sale_status' ) ) {
	function gstore_partner_account_sale_status( $sale ) {
		if ( isset( $sale['saleStatus'] ) && in_array( $sale['saleStatus'], array( 'cancelled', 'confirmed' ), true ) ) {
			return $sale['saleStatus'];
		}

		$type         = isset( $sale['type'] ) ? sanitize_key( $sale['type'] ) : '';
		$order_status = isset( $sale['orderStatus'] ) ? sanitize_key( $sale['orderStatus'] ) : '';

		if ( 'cancelled' === $type || in_array( $order_status, array( 'cancelled', 'failed' ), true ) ) {
			return 'cancelled';
		}

		return 'confirmed';
	}
}

if ( ! function_exists( 'gstore_partner_account_normalize_sales_filter' ) ) {
	function gstore_partner_account_normalize_sales_filter( $filter ) {
		$filter = sanitize_key( (string) $filter );
		return in_array( $filter, array( 'todos', 'cancelados', 'concluidos' ), true ) ? $filter : 'todos';
	}
}

if ( ! function_exists( 'gstore_partner_account_filter_sales' ) ) {
	function gstore_partner_account_filter_sales( $sales, $filter = 'todos' ) {
		$filter = gstore_partner_account_normalize_sales_filter( $filter );
		if ( 'todos' === $filter ) {
			return $sales;
		}

		$target = 'cancelados' === $filter ? 'cancelled' : 'confirmed';
		return array_values(
			array_filter(
				$sales,
				static function( $sale ) use ( $target ) {
					return $target === gstore_partner_account_sale_status( $sale );
				}
			)
		);
	}
}

if ( ! function_exists( 'gstore_partner_account_render_sales_filters' ) ) {
	function gstore_partner_account_render_sales_filters( $active_filter = 'todos' ) {
		$active_filter = gstore_partner_account_normalize_sales_filter( $active_filter );
		$filters       = array(
			'todos'      => __( 'Todos', 'gstore' ),
			'cancelados' => __( 'Cancelados', 'gstore' ),
			'concluidos' => __( 'Concluidos', 'gstore' ),
		);
		?>
		<div class="gstore-partner-sales-filter" role="group" aria-label="<?php esc_attr_e( 'Filtrar vendas', 'gstore' ); ?>">
			<?php foreach ( $filters as $filter => $label ) : ?>
				<a class="<?php echo esc_attr( $active_filter === $filter ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'view' => 'vendas', 'vendas_status' => $filter ), wc_get_account_endpoint_url( 'revendedor' ) ) ); ?>" <?php echo $active_filter === $filter ? 'aria-current="true"' : ''; ?>>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'gstore_partner_account_render_sales_table' ) ) {
	function gstore_partner_account_render_sales_table( $sales, $compact = false, $active_filter = 'todos' ) {
		$active_filter = gstore_partner_account_normalize_sales_filter( $active_filter );
		$visible_sales = $compact ? $sales : gstore_partner_account_filter_sales( $sales, $active_filter );
		?>
		<?php if ( ! $compact ) : ?>
			<?php gstore_partner_account_render_sales_filters( $active_filter ); ?>
		<?php endif; ?>
		<div class="gstore-partner-table-wrap">
			<table class="gstore-partner-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Pedido', 'gstore' ); ?></th>
						<th><?php esc_html_e( 'Cliente', 'gstore' ); ?></th>
						<th><?php esc_html_e( 'E-mail', 'gstore' ); ?></th>
						<th><?php esc_html_e( 'Valor da venda', 'gstore' ); ?></th>
						<th><?php esc_html_e( 'Comissao/moedas', 'gstore' ); ?></th>
						<th><?php esc_html_e( 'Status', 'gstore' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $visible_sales ) ) : ?>
					<tr><td colspan="6"><?php echo esc_html( 'todos' === $active_filter ? __( 'Nenhuma venda indicada ainda.', 'gstore' ) : __( 'Nenhuma venda encontrada para este filtro.', 'gstore' ) ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $visible_sales as $sale ) : ?>
						<?php
						$sale_status = gstore_partner_account_sale_status( $sale );
						$pill_class   = 'cancelled' === $sale_status ? 'is-cancelled' : ( isset( $sale['type'] ) && 'refund' === $sale['type'] ? 'is-warning' : 'is-success' );
						?>
						<tr>
							<td data-label="<?php esc_attr_e( 'Pedido', 'gstore' ); ?>"><?php echo esc_html( $sale['orderNumber'] ); ?></td>
							<td data-label="<?php esc_attr_e( 'Cliente', 'gstore' ); ?>"><?php echo esc_html( $sale['customerName'] ); ?></td>
							<td data-label="<?php esc_attr_e( 'E-mail', 'gstore' ); ?>"><?php echo esc_html( $sale['customerEmail'] ); ?></td>
							<td data-label="<?php esc_attr_e( 'Valor', 'gstore' ); ?>"><?php echo esc_html( $sale['orderTotalFormatted'] ); ?></td>
							<td data-label="<?php esc_attr_e( 'Comissao', 'gstore' ); ?>"><strong><?php echo esc_html( $sale['amountFormatted'] ); ?></strong></td>
							<td data-label="<?php esc_attr_e( 'Status', 'gstore' ); ?>">
								<span class="<?php echo esc_attr( 'gstore-partner-pill ' . $pill_class ); ?>">
									<?php echo esc_html( gstore_partner_account_sale_label( $sale ) ); ?>
								</span>
							</td>
						</tr>
						<?php if ( $compact && count( $visible_sales ) > 4 ) : ?>
							<?php break; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

if ( ! function_exists( 'gstore_partner_account_render_redemptions' ) ) {
	function gstore_partner_account_render_redemptions( $redemptions ) {
		$labels = array(
			'pending'   => __( 'Pendente', 'gstore' ),
			'approved'  => __( 'Aprovado', 'gstore' ),
			'rejected'  => __( 'Rejeitado', 'gstore' ),
			'cancelled' => __( 'Cancelado', 'gstore' ),
		);
		?>
		<div class="gstore-partner-redemption-list">
			<?php if ( empty( $redemptions ) ) : ?>
				<p><?php esc_html_e( 'Nenhum resgate solicitado ainda.', 'gstore' ); ?></p>
			<?php else : ?>
				<?php foreach ( $redemptions as $item ) : ?>
					<div class="gstore-partner-redemption">
						<div>
							<strong><?php echo esc_html( $item['amountFormatted'] ); ?></strong>
							<span><?php echo esc_html( 'coupon' === $item['method'] ? __( 'Cupom de loja', 'gstore' ) : __( 'Pagamento externo', 'gstore' ) ); ?></span>
						</div>
						<div>
							<span class="<?php echo esc_attr( 'gstore-partner-pill is-' . sanitize_key( $item['status'] ) ); ?>">
								<?php echo esc_html( isset( $labels[ $item['status'] ] ) ? $labels[ $item['status'] ] : $item['status'] ); ?>
							</span>
							<?php if ( ! empty( $item['coupon_code'] ) ) : ?>
								<code><?php echo esc_html( $item['coupon_code'] ); ?></code>
							<?php elseif ( ! empty( $item['external_reference'] ) ) : ?>
								<small><?php echo esc_html( $item['external_reference'] ); ?></small>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'gstore_partner_account_render_contract' ) ) {
	function gstore_partner_account_render_contract( $data, $accepted = false ) {
		$user         = wp_get_current_user();
		$partner      = isset( $data['partner'] ) && is_array( $data['partner'] ) ? $data['partner'] : array();
		$settings     = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		$accepted_at  = gstore_partner_account_contract_accepted_at( $user->ID );
		$commission   = isset( $partner['commissionPercent'] )
			? (string) $partner['commissionPercent']
			: ( isset( $settings['defaultPercent'] ) ? (string) $settings['defaultPercent'] : '5' );
		$commission   = rtrim( rtrim( $commission, '0' ), '.' );
		$commission   = '' === $commission ? '0' : $commission;
		$referral_url = isset( $partner['url'] ) ? (string) $partner['url'] : '';
		$slug         = isset( $partner['slug'] ) ? (string) $partner['slug'] : '';
		$store_name   = gstore_partner_account_store_name();
		?>
		<section class="gstore-account-contract gstore-partner-contract">
			<header class="gstore-account-contract__header">
				<div>
					<h3 class="gstore-account-contract__title"><?php esc_html_e( 'Contrato do Programa Revendedor', 'gstore' ); ?></h3>
					<p class="gstore-account-contract__status">
						<?php if ( $accepted && $accepted_at ) : ?>
							<?php
							printf(
								/* translators: %s: accepted date. */
								esc_html__( 'Aceito em %s.', 'gstore' ),
								esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $accepted_at ) )
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'Aceite obrigatorio para liberar o painel de revendedor.', 'gstore' ); ?>
						<?php endif; ?>
					</p>
				</div>
				<span class="<?php echo esc_attr( 'gstore-partner-pill ' . ( $accepted ? 'is-approved' : 'is-pending' ) ); ?>">
					<?php echo esc_html( $accepted ? __( 'Aceito', 'gstore' ) : __( 'Pendente', 'gstore' ) ); ?>
				</span>
			</header>

			<div class="gstore-account-contract__preview">
				<div class="gstore-partner-contract-document">
					<div class="gstore-partner-contract-document__header">
						<strong>
							<?php
							printf(
								/* translators: %s: store name. */
								esc_html__( 'Termos de Participacao - Programa Revendedor %s', 'gstore' ),
								esc_html( $store_name )
							);
							?>
						</strong>
						<span>
							<?php
							printf(
								/* translators: %s: contract version. */
								esc_html__( 'Versao %s', 'gstore' ),
								esc_html( gstore_partner_account_contract_version() )
							);
							?>
						</span>
					</div>

					<table>
						<tbody>
							<tr>
								<th><?php esc_html_e( 'Revendedor', 'gstore' ); ?></th>
								<td><?php echo esc_html( $user->display_name ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'E-mail', 'gstore' ); ?></th>
								<td><?php echo esc_html( $user->user_email ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Slug de indicacao', 'gstore' ); ?></th>
								<td><?php echo esc_html( $slug ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Comissao vigente', 'gstore' ); ?></th>
								<td><?php echo esc_html( $commission ); ?>%</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Link de indicacao', 'gstore' ); ?></th>
								<td><?php echo esc_html( $referral_url ); ?></td>
							</tr>
						</tbody>
					</table>

					<h4><?php esc_html_e( '1. Objeto', 'gstore' ); ?></h4>
					<p>
						<?php
						printf(
							/* translators: %s: store name. */
							esc_html__( 'O presente instrumento regula a participacao do revendedor no programa de indicacao da loja %s, por meio de link exclusivo associado ao seu cadastro.', 'gstore' ),
							esc_html( $store_name )
						);
						?>
					</p>

					<h4><?php esc_html_e( '2. Regras de comissionamento', 'gstore' ); ?></h4>
					<p><?php esc_html_e( 'A comissao e calculada sobre o subtotal pago dos produtos, apos descontos, sem incluir frete, taxas ou impostos. Pedidos cancelados, reembolsados ou invalidados podem gerar estorno de creditos.', 'gstore' ); ?></p>

					<h4><?php esc_html_e( '3. Uso do link e restricoes', 'gstore' ); ?></h4>
					<p><?php esc_html_e( 'O revendedor deve divulgar seu link de forma clara e regular. Indicacoes para compras feitas pelo proprio revendedor, ou por e-mail de cobranca equivalente ao seu cadastro, nao geram credito.', 'gstore' ); ?></p>

					<h4><?php esc_html_e( '4. Creditos e resgates', 'gstore' ); ?></h4>
					<p><?php esc_html_e( 'Os creditos sao liberados conforme aprovacao do pagamento e podem ser resgatados pelos metodos disponiveis no painel. Solicitacoes pendentes reservam saldo ate aprovacao, rejeicao ou cancelamento administrativo.', 'gstore' ); ?></p>

					<h4><?php esc_html_e( '5. Auditoria e alteracoes', 'gstore' ); ?></h4>
					<p>
						<?php
						printf(
							/* translators: %s: store name. */
							esc_html__( 'A loja %s pode auditar vendas, corrigir lancamentos e alterar regras do programa mediante atualizacao da versao destes termos. A continuidade de uso do painel depende do aceite dos termos vigentes.', 'gstore' ),
							esc_html( $store_name )
						);
						?>
					</p>

					<div class="gstore-partner-contract-document__signatures">
						<div>
							<span><?php echo esc_html( $store_name ); ?></span>
							<strong><?php esc_html_e( 'Administracao do programa', 'gstore' ); ?></strong>
						</div>
						<div>
							<span><?php esc_html_e( 'Revendedor', 'gstore' ); ?></span>
							<strong><?php echo esc_html( $user->display_name ); ?></strong>
						</div>
					</div>
				</div>
			</div>

			<?php if ( ! $accepted ) : ?>
				<form class="gstore-partner-contract-accept" method="post">
					<input type="hidden" name="gstore_partner_contract_action" value="accept" />
					<?php wp_nonce_field( 'gstore_partner_contract_accept', 'gstore_partner_contract_nonce' ); ?>
					<label>
						<input type="checkbox" name="gstore_partner_contract_accept" value="1" required />
						<span>
							<?php
							printf(
								/* translators: %s: store name. */
								esc_html__( 'Li e concordo com os termos do contrato do Programa Revendedor %s.', 'gstore' ),
								esc_html( $store_name )
							);
							?>
						</span>
					</label>
					<button type="submit" class="button gstore-partner-primary-button"><?php esc_html_e( 'Aceitar contrato e acessar painel', 'gstore' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}
}

if ( ! function_exists( 'gstore_partner_account_render_endpoint' ) ) {
	function gstore_partner_account_render_endpoint() {
		$data = gstore_partner_account_get_data();
		if ( empty( $data['isPartner'] ) || empty( $data['partner'] ) ) {
			?>
			<div class="gstore-partner-account">
				<section class="gstore-partner-panel">
					<h2><?php esc_html_e( 'Programa Revendedor', 'gstore' ); ?></h2>
					<p><?php esc_html_e( 'Sua conta ainda nao esta habilitada como revendedora.', 'gstore' ); ?></p>
				</section>
			</div>
			<?php
			return;
		}

		$user        = isset( $data['user'] ) && is_array( $data['user'] ) ? $data['user'] : array();
		$partner     = $data['partner'];
		$settings    = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		$sales       = isset( $partner['sales'] ) && is_array( $partner['sales'] ) ? $partner['sales'] : array();
		$redemptions = isset( $partner['redemptions'] ) && is_array( $partner['redemptions'] ) ? $partner['redemptions'] : array();
		$view              = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'painel';
		$view              = in_array( $view, array( 'painel', 'vendas', 'creditos', 'contrato' ), true ) ? $view : 'painel';
		$sales_filter      = isset( $_GET['vendas_status'] ) ? gstore_partner_account_normalize_sales_filter( wp_unslash( $_GET['vendas_status'] ) ) : 'todos';
		$filtered_sales    = gstore_partner_account_filter_sales( $sales, $sales_filter );
		$contract_accepted = gstore_partner_account_contract_is_accepted( get_current_user_id() );
		if ( ! $contract_accepted ) {
			$view = 'contrato';
		}

		$name               = ! empty( $user['name'] ) ? $user['name'] : wp_get_current_user()->display_name;
		$min_value          = isset( $settings['minRedeemAmount'] ) ? $settings['minRedeemAmount'] : '10.00';
		$store_name         = gstore_partner_account_store_name();
		$commission_display = rtrim( rtrim( (string) ( isset( $partner['commissionPercent'] ) ? $partner['commissionPercent'] : 0 ), '0' ), '.' );
		$commission_display = '' === $commission_display ? '0' : $commission_display;
		?>
		<div class="gstore-partner-account" data-gstore-partner-account>
			<section class="gstore-partner-hero">
				<h2>
					<?php
					printf(
						/* translators: %s: user display name. */
						esc_html__( 'Ola, %s!', 'gstore' ),
						esc_html( $name )
					);
					?>
				</h2>
				<p><?php esc_html_e( 'Gerencie sua conta revendedora, acompanhe suas vendas e acompanhe seus creditos.', 'gstore' ); ?></p>
			</section>

			<nav class="gstore-partner-tabs" aria-label="<?php esc_attr_e( 'Area revendedor', 'gstore' ); ?>">
				<a class="<?php echo 'painel' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( gstore_partner_account_view_url( 'painel' ) ); ?>"><?php esc_html_e( 'Meu Painel', 'gstore' ); ?></a>
				<a class="<?php echo 'vendas' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( gstore_partner_account_view_url( 'vendas' ) ); ?>"><?php esc_html_e( 'Minhas Vendas', 'gstore' ); ?></a>
				<a class="<?php echo 'creditos' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( gstore_partner_account_view_url( 'creditos' ) ); ?>"><?php esc_html_e( 'Meus Creditos', 'gstore' ); ?></a>
				<a class="<?php echo 'contrato' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( gstore_partner_account_view_url( 'contrato' ) ); ?>"><?php esc_html_e( 'Contrato', 'gstore' ); ?></a>
			</nav>

			<?php if ( 'painel' === $view ) : ?>
				<div class="gstore-partner-stats">
					<?php
					gstore_partner_account_render_stat( 'link', __( 'Link exclusivo', 'gstore' ), '', '', __( 'Ativo', 'gstore' ) );
					gstore_partner_account_render_stat( 'cart', __( 'Vendas indicadas', 'gstore' ), (string) absint( $partner['salesLast30'] ), __( 'Nos ultimos 30 dias', 'gstore' ) );
					gstore_partner_account_render_stat(
						'coins',
						__( 'Creditos disponiveis', 'gstore' ),
						$partner['balanceFormatted'],
						sprintf(
							/* translators: %s: store name. */
							__( 'Em moedas %s', 'gstore' ),
							$store_name
						)
					);
					gstore_partner_account_render_stat( 'percent', __( 'Comissao atual', 'gstore' ), $commission_display . '%', __( 'Sobre o valor da venda', 'gstore' ) );
					?>
				</div>

				<section class="gstore-partner-link-panel">
					<div class="gstore-partner-icon"><?php echo gstore_partner_account_icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div>
						<h3><?php esc_html_e( 'Seu link de parceiro', 'gstore' ); ?></h3>
						<p><?php esc_html_e( 'Divulgue seu link exclusivo e ganhe creditos por cada venda realizada.', 'gstore' ); ?></p>
						<input type="text" readonly value="<?php echo esc_attr( $partner['url'] ); ?>" data-gstore-partner-link />
					</div>
					<button type="button" class="button gstore-partner-copy" data-gstore-copy-partner-link><?php esc_html_e( 'Copiar link', 'gstore' ); ?></button>
					<button type="button" class="button gstore-partner-share" data-gstore-share-partner-link><?php esc_html_e( 'Compartilhar', 'gstore' ); ?></button>
				</section>

				<section class="gstore-partner-panel">
					<header class="gstore-partner-panel__header">
						<h3><?php esc_html_e( 'Minhas Vendas', 'gstore' ); ?></h3>
						<a href="<?php echo esc_url( gstore_partner_account_view_url( 'vendas' ) ); ?>"><?php esc_html_e( 'Ver todas', 'gstore' ); ?></a>
					</header>
					<?php gstore_partner_account_render_sales_table( array_slice( $sales, 0, 4 ), true ); ?>
				</section>

				<div class="gstore-partner-bottom-grid">
					<section class="gstore-partner-panel gstore-partner-balance-panel">
						<h3><?php esc_html_e( 'Saldo em moedas', 'gstore' ); ?></h3>
						<div class="gstore-partner-balance">
							<div class="gstore-partner-icon"><?php echo gstore_partner_account_icon( 'coins' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<div>
								<span><?php esc_html_e( 'Seu saldo atual', 'gstore' ); ?></span>
								<strong><?php echo esc_html( $partner['balanceFormatted'] ); ?></strong>
								<small><?php esc_html_e( '1 moeda = R$ 1,00', 'gstore' ); ?></small>
							</div>
						</div>
						<a class="button gstore-partner-primary-button" href="<?php echo esc_url( gstore_partner_account_view_url( 'creditos' ) ); ?>"><?php esc_html_e( 'Usar creditos', 'gstore' ); ?></a>
					</section>

					<section class="gstore-partner-panel gstore-partner-how">
						<h3><?php esc_html_e( 'Como funciona', 'gstore' ); ?></h3>
						<div class="gstore-partner-how__body">
							<div class="gstore-partner-icon"><?php echo gstore_partner_account_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<ul>
								<li><?php esc_html_e( 'Divulgue seu link exclusivo para amigos e clientes.', 'gstore' ); ?></li>
								<li><?php esc_html_e( 'Cada compra realizada atraves do seu link gera creditos para voce.', 'gstore' ); ?></li>
								<li><?php esc_html_e( 'Os creditos sao liberados apos o pedido ser concluido ou processado.', 'gstore' ); ?></li>
							</ul>
						</div>
					</section>
				</div>
			<?php elseif ( 'vendas' === $view ) : ?>
				<section class="gstore-partner-panel">
					<header class="gstore-partner-panel__header">
						<h3><?php esc_html_e( 'Minhas Vendas', 'gstore' ); ?></h3>
						<span><?php echo esc_html( sprintf( _n( '%d venda rastreada', '%d vendas rastreadas', count( $filtered_sales ), 'gstore' ), count( $filtered_sales ) ) ); ?></span>
					</header>
					<?php gstore_partner_account_render_sales_table( $sales, false, $sales_filter ); ?>
				</section>
			<?php elseif ( 'creditos' === $view ) : ?>
				<div class="gstore-partner-credit-grid">
					<section class="gstore-partner-panel">
						<h3><?php esc_html_e( 'Solicitar resgate', 'gstore' ); ?></h3>
						<div class="gstore-partner-balance is-credit-view">
							<div class="gstore-partner-icon"><?php echo gstore_partner_account_icon( 'coins' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<div>
								<span><?php esc_html_e( 'Saldo disponivel', 'gstore' ); ?></span>
								<strong><?php echo esc_html( $partner['balanceFormatted'] ); ?></strong>
								<small>
									<?php
									printf(
										/* translators: %s: minimum redemption amount. */
										esc_html__( 'Minimo para resgate: R$ %s', 'gstore' ),
										esc_html( number_format_i18n( (float) $min_value, 2 ) )
									);
									?>
								</small>
							</div>
						</div>
						<form class="gstore-partner-redemption-form" method="post">
							<input type="hidden" name="gstore_partner_redemption_action" value="request" />
							<?php wp_nonce_field( 'gstore_partner_redemption', 'gstore_partner_redemption_nonce' ); ?>
							<label>
								<span><?php esc_html_e( 'Valor do resgate', 'gstore' ); ?></span>
								<input type="text" name="gstore_partner_redemption_amount" placeholder="0,00" required />
							</label>
							<label>
								<span><?php esc_html_e( 'Metodo de resgate', 'gstore' ); ?></span>
								<select name="gstore_partner_redemption_method">
									<option value="external"><?php esc_html_e( 'Pagamento externo', 'gstore' ); ?></option>
									<option value="coupon"><?php esc_html_e( 'Cupom de loja', 'gstore' ); ?></option>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Observacao', 'gstore' ); ?></span>
								<textarea name="gstore_partner_redemption_note" rows="3"></textarea>
							</label>
							<button type="submit" class="button gstore-partner-primary-button"><?php esc_html_e( 'Solicitar resgate', 'gstore' ); ?></button>
						</form>
					</section>

					<section class="gstore-partner-panel">
						<h3><?php esc_html_e( 'Historico de creditos', 'gstore' ); ?></h3>
						<?php gstore_partner_account_render_redemptions( $redemptions ); ?>
					</section>
				</div>
			<?php else : ?>
				<?php gstore_partner_account_render_contract( $data, $contract_accepted ); ?>
			<?php endif; ?>
		</div>
		<script>
			(function(){
				var root = document.querySelector('[data-gstore-partner-account]');
				if (!root) return;
				var input = root.querySelector('[data-gstore-partner-link]');
				var copy = root.querySelector('[data-gstore-copy-partner-link]');
				var share = root.querySelector('[data-gstore-share-partner-link]');
				function link(){ return input ? input.value : ''; }
				if (copy) {
					copy.addEventListener('click', function(){
						var value = link();
						if (!value) return;
						if (navigator.clipboard && navigator.clipboard.writeText) {
							navigator.clipboard.writeText(value);
						} else if (input) {
							input.select();
							document.execCommand('copy');
						}
						copy.textContent = '<?php echo esc_js( __( 'Copiado', 'gstore' ) ); ?>';
						window.setTimeout(function(){ copy.textContent = '<?php echo esc_js( __( 'Copiar link', 'gstore' ) ); ?>'; }, 1600);
					});
				}
				if (share) {
					share.addEventListener('click', function(){
						var value = link();
						if (navigator.share && value) {
							navigator.share({ title: '<?php echo esc_js( $store_name ); ?>', url: value });
						} else if (copy) {
							copy.click();
						}
					});
				}
			})();
		</script>
		<?php
	}
}
add_action( 'woocommerce_account_revendedor_endpoint', 'gstore_partner_account_render_endpoint' );

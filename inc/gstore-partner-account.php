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
		if ( ! is_user_logged_in() ) {
			return array(
				'enabled'   => false,
				'isPartner' => false,
			);
		}

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

		return new WP_Error( 'partner_service_missing', __( 'Programa de revendedores indisponível.', 'gstore' ) );
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

		if ( function_exists( 'gstore_get_store_name' ) ) {
			$store_name = (string) gstore_get_store_name( 'display' );
		}

		if ( '' === trim( $store_name ) ) {
			$store_name = (string) get_option( 'blogname', '' );
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
			'pending'   => __( 'Em análise', 'gstore' ),
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
				wc_add_notice( __( 'Não foi possível validar o resgate. Atualize a página e tente novamente.', 'gstore' ), 'error' );
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
				wc_add_notice( __( 'Solicitação de resgate enviada.', 'gstore' ), 'success' );
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
				wc_add_notice( __( 'Não foi possível validar o aceite do contrato. Atualize a página e tente novamente.', 'gstore' ), 'error' );
			}
			return;
		}

		if ( empty( $_POST['gstore_partner_contract_accept'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Você precisa aceitar os termos do contrato para acessar o painel de revendedor.', 'gstore' ), 'error' );
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
				wc_add_notice( __( 'O programa de revendedores não está disponível para esta conta.', 'gstore' ), 'error' );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		$nonce = isset( $_POST['gstore_partner_application_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['gstore_partner_application_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'gstore_partner_application' ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Não foi possível validar a solicitação. Atualize a página e tente novamente.', 'gstore' ), 'error' );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( ! function_exists( 'gstore_partner_submit_application' ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Programa de revendedores indisponível no momento.', 'gstore' ), 'error' );
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
			'users'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path><circle cx="9.5" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path><path d="M8 18h3"></path><path d="M9.5 16.5V20"></path></svg>',
			'check'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l2 2 4-4"></path><path d="M8 3h8l2 3v13a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6z"></path><path d="M8 3v4h8V3"></path></svg>',
			'headset' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13a8 8 0 0 1 16 0"></path><path d="M4 13v4a2 2 0 0 0 2 2h2v-8H6a2 2 0 0 0-2 2z"></path><path d="M20 13v4a2 2 0 0 1-2 2h-2v-8h2a2 2 0 0 1 2 2z"></path><path d="M16 19a4 4 0 0 1-4 3h-1"></path></svg>',
			'store'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10h16"></path><path d="M5 10l1-5h12l1 5"></path><path d="M6 10v9h12v-9"></path><path d="M9 19v-5h6v5"></path><path d="M7 10v2a2 2 0 0 0 4 0v-2"></path><path d="M13 10v2a2 2 0 0 0 4 0v-2"></path></svg>',
			'target'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><circle cx="12" cy="12" r="3"></circle><path d="M12 2v4"></path><path d="M12 18v4"></path><path d="M2 12h4"></path><path d="M18 12h4"></path></svg>',
			'user'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="4"></circle><path d="M5 21a7 7 0 0 1 14 0"></path><path d="M17 11l2 2 4-4"></path></svg>',
			'briefcase' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M3 12h18"></path><path d="M10 12v2h4v-2"></path><path d="M8 17h8"></path></svg>',
			'handshake' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12l3-3 2 2 3-3"></path><path d="M2 12l4-4 5 5a2 2 0 0 0 3 0l4-4 4 4"></path><path d="M6 16l2 2"></path><path d="M10 18l1 1a2 2 0 0 0 3 0l4-4"></path></svg>',
			'megaphone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11v2a2 2 0 0 0 2 2h3l8 4V5l-8 4H5a2 2 0 0 0-2 2z"></path><path d="M8 15l1 5"></path><path d="M19 9a4 4 0 0 1 0 6"></path></svg>',
			'shield'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"></path><path d="M9 12l2 2 4-5"></path></svg>',
			'lock'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path><path d="M12 15v2"></path></svg>',
			'document' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M10 9H8"></path><path d="M16 13H8"></path><path d="M16 17H8"></path></svg>',
			'gift'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"></path></svg>',
			'alert'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l9 16H3z"></path><path d="M12 9v5"></path><path d="M12 17h.01"></path></svg>',
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
				<p><?php esc_html_e( 'Conheça o programa, envie seus dados e acompanhe a análise da equipe comercial.', 'gstore' ); ?></p>
			</div>
			<?php if ( $was_sent || ( ! empty( $latest ) && 'pending' === $latest['status'] ) ) : ?>
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

if ( ! function_exists( 'gstore_partner_account_render_application_feedback' ) ) {
	function gstore_partner_account_application_feedback_status() {
		if ( empty( $_GET['partner_application'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}

		$status = sanitize_key( wp_unslash( $_GET['partner_application'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $status, array( 'sent', 'error' ), true ) ? $status : '';
	}

	function gstore_partner_account_render_application_feedback() {
		$status = gstore_partner_account_application_feedback_status();
		if ( '' === $status ) {
			return;
		}

		$message = isset( $_GET['partner_application_message'] ) ? sanitize_text_field( wp_unslash( $_GET['partner_application_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $message ) {
			$message = 'sent' === $status
				? __( 'Solicitacao enviada com sucesso. Nossa equipe vai analisar seus dados.', 'gstore' )
				: __( 'Nao foi possivel enviar sua solicitacao. Revise os dados e tente novamente.', 'gstore' );
		}
		$is_success = 'sent' === $status;
		?>
		<div class="<?php echo esc_attr( 'gstore-partner-application-modal gstore-partner-application-modal--' . $status ); ?>" data-gstore-partner-application-modal role="dialog" aria-modal="true" aria-labelledby="gstore-partner-application-modal-title">
			<div class="gstore-partner-application-modal__backdrop" data-gstore-partner-application-modal-close></div>
			<div class="gstore-partner-application-modal__panel">
				<button type="button" class="gstore-partner-application-modal__close" data-gstore-partner-application-modal-close aria-label="<?php esc_attr_e( 'Fechar aviso', 'gstore' ); ?>">
					&times;
				</button>
				<span class="gstore-partner-application-modal__icon"><?php echo gstore_partner_account_icon( $is_success ? 'check' : 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<h3 id="gstore-partner-application-modal-title">
					<?php echo esc_html( $is_success ? __( 'Solicitacao enviada', 'gstore' ) : __( 'Nao foi possivel enviar', 'gstore' ) ); ?>
				</h3>
				<p><?php echo esc_html( $is_success ? __( 'Sua solicitacao foi enviada e esta em analise. Nossa equipe vai revisar seus dados e entrar em contato.', 'gstore' ) : $message ); ?></p>
				<button type="button" class="button gstore-partner-primary-button" data-gstore-partner-application-modal-close>
					<?php echo esc_html( $is_success ? __( 'Entendi', 'gstore' ) : __( 'Revisar dados', 'gstore' ) ); ?>
				</button>
			</div>
		</div>
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
		$was_sent     = 'sent' === gstore_partner_account_application_feedback_status();

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
			<?php gstore_partner_account_render_application_feedback(); ?>

			<?php if ( ! empty( $latest ) && 'pending' === $latest['status'] ) : ?>
				<p class="gstore-partner-application__status"><?php esc_html_e( 'Sua solicitação já foi enviada e está em análise. Assim que for aprovada, a aba Revendedor fica disponível nesta conta.', 'gstore' ); ?></p>
			<?php else : ?>
				<form id="<?php echo esc_attr( $panel_id ); ?>" class="gstore-partner-application__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="gstore_partner_submit_application" />
					<input type="hidden" name="gstore_partner_application_action" value="submit" />
					<input type="hidden" name="gstore_partner_application_redirect" value="<?php echo esc_url( gstore_partner_account_application_page_url() ); ?>" />
					<?php wp_nonce_field( 'gstore_partner_application', 'gstore_partner_application_nonce' ); ?>

					<div class="gstore-partner-application__grid">
						<label>
							<span><?php esc_html_e( 'Nome completo', 'gstore' ); ?></span>
							<input type="text" name="gstore_partner_application_name" value="<?php echo esc_attr( $user_name ); ?>" placeholder="<?php esc_attr_e( 'Nome completo', 'gstore' ); ?>" required autocomplete="name" />
						</label>
						<label>
							<span><?php esc_html_e( 'E-mail', 'gstore' ); ?></span>
							<input type="email" name="gstore_partner_application_email" value="<?php echo esc_attr( $user_email ); ?>" placeholder="<?php esc_attr_e( 'E-mail', 'gstore' ); ?>" required autocomplete="email" <?php echo $user_email ? 'readonly' : ''; ?> />
						</label>
						<label>
							<span><?php esc_html_e( 'WhatsApp', 'gstore' ); ?></span>
							<input type="text" name="gstore_partner_application_phone" placeholder="<?php esc_attr_e( 'WhatsApp', 'gstore' ); ?>" inputmode="tel" required autocomplete="tel" />
						</label>
						<label>
							<span><?php esc_html_e( 'Cidade / UF', 'gstore' ); ?></span>
							<input type="text" name="gstore_partner_application_city_uf" placeholder="<?php esc_attr_e( 'Cidade / UF', 'gstore' ); ?>" required autocomplete="address-level2" />
						</label>
						<label>
							<span><?php esc_html_e( 'CPF', 'gstore' ); ?></span>
							<input type="text" name="gstore_partner_application_cpf" placeholder="<?php esc_attr_e( 'CPF', 'gstore' ); ?>" inputmode="numeric" required autocomplete="off" />
						</label>
						<label>
							<span><?php esc_html_e( 'CNPJ', 'gstore' ); ?></span>
							<input type="text" name="gstore_partner_application_cnpj" placeholder="<?php esc_attr_e( 'CNPJ se aplicável', 'gstore' ); ?>" inputmode="numeric" autocomplete="off" />
						</label>
						<label class="gstore-partner-application__file">
							<span class="gstore-partner-application__file-label">
								<?php esc_html_e( 'Documento de identidade', 'gstore' ); ?>
								<small class="gstore-partner-application__file-required"><?php esc_html_e( '(campo obrigatório)', 'gstore' ); ?></small>
							</span>
							<input type="file" name="gstore_partner_identity_document" accept=".jpg,.jpeg,.png,.pdf" required data-gstore-partner-document-input />
						</label>
					</div>

					<fieldset class="gstore-partner-application__profile" data-gstore-partner-profile-group>
						<legend><?php esc_html_e( 'Você é:', 'gstore' ); ?></legend>
						<label><input type="checkbox" name="gstore_partner_application_profile_type[]" value="store" data-gstore-partner-profile-option /> <span><?php esc_html_e( 'Loja', 'gstore' ); ?></span></label>
						<label><input type="checkbox" name="gstore_partner_application_profile_type[]" value="dispatcher" data-gstore-partner-profile-option /> <span><?php esc_html_e( 'Despachante', 'gstore' ); ?></span></label>
						<label><input type="checkbox" name="gstore_partner_application_profile_type[]" value="club" data-gstore-partner-profile-option /> <span><?php esc_html_e( 'Clube de tiro', 'gstore' ); ?></span></label>
						<label><input type="checkbox" name="gstore_partner_application_profile_type[]" value="instructor" data-gstore-partner-profile-option /> <span><?php esc_html_e( 'Instrutor', 'gstore' ); ?></span></label>
						<label><input type="checkbox" name="gstore_partner_application_profile_type[]" value="reseller" data-gstore-partner-profile-option /> <span><?php esc_html_e( 'Revendedor', 'gstore' ); ?></span></label>
						<label><input type="checkbox" name="gstore_partner_application_profile_type[]" value="other" data-gstore-partner-profile-option /> <span><?php esc_html_e( 'Outro perfil comercial', 'gstore' ); ?></span></label>
					</fieldset>

					<label class="gstore-partner-application__about">
						<span><?php esc_html_e( 'Conte para nós um pouco mais sobre você', 'gstore' ); ?></span>
						<textarea name="gstore_partner_application_about" rows="5" placeholder="<?php esc_attr_e( 'Conte para nós um pouco mais sobre você', 'gstore' ); ?>" required></textarea>
					</label>

					<button type="submit" class="button gstore-partner-primary-button" data-submitting-text="<?php esc_attr_e( 'Enviando cadastro...', 'gstore' ); ?>"><?php esc_html_e( 'Enviar cadastro', 'gstore' ); ?></button>
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
				var applicationModal = document.querySelector('[data-gstore-partner-application-modal]');
				if (applicationModal) {
					document.documentElement.classList.add('gstore-partner-application-modal-open');
					var modalCloseButtons = applicationModal.querySelectorAll('[data-gstore-partner-application-modal-close]');
					var focusTarget = applicationModal.querySelector('.gstore-partner-application-modal__close');
					function closeApplicationModal() {
						applicationModal.setAttribute('hidden', 'hidden');
						document.documentElement.classList.remove('gstore-partner-application-modal-open');
						if (window.history && window.history.replaceState) {
							var nextUrl = new URL(window.location.href);
							nextUrl.searchParams.delete('partner_application');
							nextUrl.searchParams.delete('partner_application_message');
							window.history.replaceState({}, document.title, nextUrl.pathname + nextUrl.search + nextUrl.hash);
						}
					}
					modalCloseButtons.forEach(function(button){
						button.addEventListener('click', closeApplicationModal);
					});
					document.addEventListener('keydown', function(event){
						if (event.key === 'Escape' && !applicationModal.hasAttribute('hidden')) {
							closeApplicationModal();
						}
					});
					if (focusTarget) {
						focusTarget.focus();
					}
				}
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
				document.querySelectorAll('[data-gstore-partner-profile-group]').forEach(function(group){
					var options = Array.prototype.slice.call(group.querySelectorAll('[data-gstore-partner-profile-option]'));
					if (!options.length) return;
					function syncRequiredState() {
						var hasChecked = options.some(function(option){ return option.checked; });
						options.forEach(function(option){
							option.required = !hasChecked;
						});
					}
					options.forEach(function(option){
						option.addEventListener('change', syncRequiredState);
					});
					syncRequiredState();
				});
				document.querySelectorAll('.gstore-partner-application__form').forEach(function(form){
					var maxDocumentSize = 8 * 1024 * 1024;
					var documentInput = form.querySelector('[data-gstore-partner-document-input]');
					var documentField = documentInput ? documentInput.closest('.gstore-partner-application__file') : null;
					var submitButton = form.querySelector('button[type="submit"]');
					function formatFileSize(bytes) {
						return (bytes / (1024 * 1024)).toFixed(bytes >= 10 * 1024 * 1024 ? 0 : 1).replace('.', ',') + ' MB';
					}
					function getStatus() {
						var status = form.querySelector('[data-gstore-partner-form-status]');
						if (!status) {
							status = document.createElement('p');
							status.setAttribute('data-gstore-partner-form-status', '1');
							status.setAttribute('aria-live', 'polite');
							form.insertBefore(status, form.firstChild);
						}
						return status;
					}
					function setStatus(type, message) {
						var status = getStatus();
						status.className = 'gstore-partner-application__status gstore-partner-application__status--' + type;
						status.textContent = message;
					}
					function clearStatus() {
						var status = form.querySelector('[data-gstore-partner-form-status]');
						if (status) {
							status.remove();
						}
					}
					function setDocumentInvalid(message) {
						if (!documentInput) {
							return;
						}
						documentInput.setAttribute('aria-invalid', 'true');
						documentInput.classList.add('is-invalid');
						if (documentField) {
							documentField.classList.add('is-invalid');
						}
						if (typeof documentInput.setCustomValidity === 'function') {
							documentInput.setCustomValidity(message);
						}
					}
					function clearDocumentInvalid() {
						if (!documentInput) {
							return;
						}
						documentInput.removeAttribute('aria-invalid');
						documentInput.classList.remove('is-invalid');
						if (documentField) {
							documentField.classList.remove('is-invalid');
						}
						if (typeof documentInput.setCustomValidity === 'function') {
							documentInput.setCustomValidity('');
						}
					}
					function validateDocumentRequired() {
						if (!documentInput || (documentInput.files && documentInput.files.length)) {
							clearDocumentInvalid();
							return true;
						}
						var message = '<?php echo esc_js( __( 'Inclua o documento de identidade obrigatório antes de enviar. Os dados preenchidos continuam na tela.', 'gstore' ) ); ?>';
						setDocumentInvalid(message);
						setStatus('error', message);
						documentInput.focus();
						return false;
					}
					function validateDocumentSize() {
						if (!documentInput || !documentInput.files || !documentInput.files.length) {
							return true;
						}
						var file = documentInput.files[0];
						if (file.size > maxDocumentSize) {
							var message = 'O documento selecionado tem ' + formatFileSize(file.size) + '. Envie um arquivo de ate 8 MB.';
							setDocumentInvalid(message);
							setStatus('error', message);
							documentInput.focus();
							return false;
						}
						clearDocumentInvalid();
						clearStatus();
						return true;
					}
					if (documentInput) {
						documentInput.addEventListener('change', function(){
							if (validateDocumentRequired()) {
								validateDocumentSize();
							}
						});
						documentInput.addEventListener('invalid', function(event){
							if (!documentInput.files || !documentInput.files.length) {
								event.preventDefault();
								validateDocumentRequired();
							}
						});
					}
					if (submitButton) {
						submitButton.addEventListener('click', function(event){
							if (!validateDocumentRequired()) {
								event.preventDefault();
								event.stopPropagation();
							}
						});
					}
					form.addEventListener('submit', function(event){
						if (!validateDocumentRequired() || !validateDocumentSize()) {
							event.preventDefault();
							event.stopPropagation();
							return;
						}
						if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
							return;
						}
						form.setAttribute('aria-busy', 'true');
						setStatus('info', 'Enviando cadastro. Aguarde a confirmacao na tela.');
						if (submitButton) {
							submitButton.dataset.originalText = submitButton.textContent;
							submitButton.textContent = submitButton.getAttribute('data-submitting-text') || 'Enviando cadastro...';
							submitButton.disabled = true;
							submitButton.classList.add('is-submitting');
						}
					});
				});
				function setupSafetyCarousel(carousel) {
					var items = Array.prototype.slice.call(carousel.querySelectorAll('article'));
					var indicator = carousel.parentElement ? carousel.parentElement.querySelector('[data-gstore-partner-safety-indicator]') : null;
					if (!items.length || !indicator) return;
					var fill = indicator.querySelector('[data-gstore-partner-safety-fill]');
					var current = indicator.querySelector('[data-gstore-partner-safety-current]');
					var total = indicator.querySelector('[data-gstore-partner-safety-total]');
					var startX = 0;
					var startedAtEnd = false;
					if (!fill || !current || !total) return;
					total.textContent = String(items.length);
					function isMobile() {
						return window.matchMedia('(max-width: 640px)').matches;
					}
					function isAtEnd() {
						var maxScroll = carousel.scrollWidth - carousel.clientWidth;
						return maxScroll > 0 && carousel.scrollLeft >= maxScroll - 2;
					}
					function update() {
						if (!isMobile()) return;
						var maxScroll = Math.max(1, carousel.scrollWidth - carousel.clientWidth);
						var index = Math.round((carousel.scrollLeft / maxScroll) * (items.length - 1));
						index = Math.max(0, Math.min(items.length - 1, index));
						current.textContent = String(index + 1);
						fill.style.transform = 'scaleX(' + ((index + 1) / items.length) + ')';
					}
					function goToFirst() {
						if (!isMobile()) return;
						carousel.scrollTo({ left: 0, behavior: 'smooth' });
					}
					carousel.addEventListener('scroll', function(){
						update();
					}, { passive: true });
					carousel.addEventListener('touchstart', function(event){
						if (!isMobile() || !event.touches.length) return;
						startX = event.touches[0].clientX;
						startedAtEnd = isAtEnd();
					}, { passive: true });
					carousel.addEventListener('touchmove', function(event){
						if (!isMobile() || !startedAtEnd || !event.touches.length) return;
						if (startX - event.touches[0].clientX > 28) {
							startedAtEnd = false;
							goToFirst();
						}
					}, { passive: true });
					carousel.addEventListener('wheel', function(event){
						if (isAtEnd() && event.deltaX > 0) {
							goToFirst();
						}
					}, { passive: true });
					window.addEventListener('resize', update, { passive: true });
					update();
				}
				document.querySelectorAll('.gstore-partner-program-safety').forEach(setupSafetyCarousel);
			})();
		</script>
		<?php
	}
}
add_action( 'wp_footer', 'gstore_partner_account_print_application_script', 30 );

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

		$store_name     = gstore_partner_account_store_name();
		$hero_image     = (string) apply_filters( 'gstore_partner_application_hero_image', get_theme_file_uri( 'assets/images/partners/partner-program-hero.jpg' ) );
		$form_image     = (string) apply_filters( 'gstore_partner_application_form_image', get_theme_file_uri( 'assets/images/partners/partner-program-revendedores.jpg' ) );
		$process_image  = (string) apply_filters( 'gstore_partner_application_process_image', get_theme_file_uri( 'assets/images/partners/partner-program-process.jpg' ) );
		$benefits       = array(
			array(
				'icon'  => 'users',
				'title' => __( 'Comissão por indicação', 'gstore' ),
				'text'  => __( 'Ganhe com cada venda aprovada.', 'gstore' ),
			),
			array(
				'icon'  => 'coins',
				'title' => __( 'Cashback em vendas', 'gstore' ),
				'text'  => __( 'Receba cashback sobre cada venda realizada.', 'gstore' ),
			),
			array(
				'icon'  => 'shield',
				'title' => __( 'Vendas aprovadas', 'gstore' ),
				'text'  => __( 'Pagamentos vinculados a vendas aprovadas.', 'gstore' ),
			),
			array(
				'icon'  => 'headset',
				'title' => __( 'Suporte comercial', 'gstore' ),
				'text'  => __( 'Equipe dedicada para apoiar suas vendas.', 'gstore' ),
			),
		);
		$profiles    = array(
			array(
				'icon'  => 'store',
				'title' => __( 'Lojistas', 'gstore' ),
				'text'  => __( 'Lojas que desejam ampliar seu portfólio.', 'gstore' ),
			),
			array(
				'icon'  => 'target',
				'title' => __( 'Clubes de tiro', 'gstore' ),
				'text'  => __( 'Clubes que querem oferecer mais.', 'gstore' ),
			),
			array(
				'icon'  => 'user',
				'title' => __( 'Instrutores', 'gstore' ),
				'text'  => __( 'Instrutores que desejam indicar com confiança.', 'gstore' ),
			),
			array(
				'icon'  => 'briefcase',
				'title' => __( 'Revendedores', 'gstore' ),
				'text'  => __( 'Representantes que buscam qualidade.', 'gstore' ),
			),
		);
		$steps       = array(
			array(
				'icon'  => 'user',
				'title' => __( 'Cadastro', 'gstore' ),
				'text'  => __( 'Preencha o formulário e envie seus dados.', 'gstore' ),
			),
			array(
				'icon'  => 'check',
				'title' => __( 'Análise', 'gstore' ),
				'text'  => __( 'Nossa equipe avalia seu perfil.', 'gstore' ),
			),
			array(
				'icon'  => 'megaphone',
				'title' => __( 'Indicação', 'gstore' ),
				'text'  => __( 'Indique sua rede e acompanhe suas vendas.', 'gstore' ),
			),
			array(
				'icon'  => 'coins',
				'title' => __( 'Cashback', 'gstore' ),
				'text'  => __( 'Receba comissões e cashback aprovados.', 'gstore' ),
			),
		);
		$security    = array(
			array(
				'icon'  => 'shield',
				'title' => __( 'Vendas seguras', 'gstore' ),
				'text'  => __( 'Transações protegidas e confiáveis.', 'gstore' ),
			),
			array(
				'icon'  => 'document',
				'title' => __( 'Cadastro verificado', 'gstore' ),
				'text'  => __( 'Análise manual de cada cadastro.', 'gstore' ),
			),
			array(
				'icon'  => 'check',
				'title' => __( 'Cashback controlado', 'gstore' ),
				'text'  => __( 'Vinculado a vendas aprovadas.', 'gstore' ),
			),
			array(
				'icon'  => 'lock',
				'title' => __( 'Transparência total', 'gstore' ),
				'text'  => __( 'Acompanhe todo o processo no painel.', 'gstore' ),
			),
		);
		?>
		<main class="gstore-partner-program-page" style="<?php echo esc_attr( '--partner-hero-image: url(' . esc_url_raw( $hero_image ) . '); --partner-form-image: url(' . esc_url_raw( $form_image ) . ');' ); ?>">
			<section class="gstore-partner-program-hero">
				<div class="gstore-partner-program-hero__shade"></div>
				<div class="gstore-partner-program-container gstore-partner-program-hero__content">
					<span class="gstore-partner-program-eyebrow">
						<?php esc_html_e( 'Programa de parceiros', 'gstore' ); ?>
					</span>
					<h1><?php esc_html_e( 'Indique, revenda e', 'gstore' ); ?> <span><?php esc_html_e( 'ganhe', 'gstore' ); ?></span> <?php esc_html_e( 'com sua rede.', 'gstore' ); ?></h1>
					<p>
						<?php
						printf(
							/* translators: %s: store name. */
							esc_html__( 'Parceria sólida, benefícios reais e suporte comercial da %s.', 'gstore' ),
							esc_html( $store_name )
						);
						?>
					</p>
					<div class="gstore-partner-program-hero__actions">
						<a class="button gstore-partner-program-button" href="#gstore-partner-application"><?php esc_html_e( 'Quero ser parceiro', 'gstore' ); ?></a>
						<a class="button gstore-partner-program-button is-ghost" href="#gstore-partner-program-how"><?php esc_html_e( 'Como funciona', 'gstore' ); ?></a>
					</div>
				</div>
			</section>

			<section class="gstore-partner-program-benefits" aria-label="<?php esc_attr_e( 'Benefícios do programa', 'gstore' ); ?>">
				<div class="gstore-partner-program-container">
					<?php foreach ( $benefits as $benefit ) : ?>
						<article>
							<span><?php echo gstore_partner_account_icon( $benefit['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<div>
								<strong><?php echo esc_html( $benefit['title'] ); ?></strong>
								<small><?php echo esc_html( $benefit['text'] ); ?></small>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="gstore-partner-program-section">
				<div class="gstore-partner-program-container">
					<div class="gstore-partner-program-heading">
						<h2><?php esc_html_e( 'Quem pode ser parceiro', 'gstore' ); ?></h2>
					</div>
					<div class="gstore-partner-program-profile-grid">
						<?php foreach ( $profiles as $profile ) : ?>
							<article>
								<span><?php echo gstore_partner_account_icon( $profile['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
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
				<div class="gstore-partner-program-container">
					<div class="gstore-partner-program-heading">
						<h2><?php esc_html_e( 'Como funciona o programa', 'gstore' ); ?></h2>
					</div>
					<div class="gstore-partner-program-steps">
						<?php foreach ( $steps as $index => $step ) : ?>
							<article>
								<span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
								<div class="gstore-partner-program-steps__icon"><?php echo gstore_partner_account_icon( $step['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
								<h3><?php echo esc_html( $step['title'] ); ?></h3>
								<p><?php echo esc_html( $step['text'] ); ?></p>
							</article>
						<?php endforeach; ?>
					</div>
					<article class="gstore-partner-program-process-detail">
						<figure class="gstore-partner-program-process-detail__media">
							<img
								src="<?php echo esc_url( $process_image ); ?>"
								alt="<?php esc_attr_e( 'Mesa de trabalho com painel de vendas e formulário de cadastro do programa de parceiros', 'gstore' ); ?>"
								loading="lazy"
								decoding="async"
							/>
						</figure>
						<div class="gstore-partner-program-process-detail__content">
							<span class="gstore-partner-program-eyebrow is-dark"><?php esc_html_e( 'Processo detalhado', 'gstore' ); ?></span>
							<h3><?php esc_html_e( 'Da solicitação ao cashback, tudo fica registrado.', 'gstore' ); ?></h3>
							<p><?php esc_html_e( 'O primeiro passo é enviar seus dados, documento e perfil comercial. A equipe confere as informações, entende sua atuação e valida se o cadastro combina com o programa de parceiros.', 'gstore' ); ?></p>
							<p><?php esc_html_e( 'Depois da aprovação, seu link de indicação e suas condições comerciais ficam ativos. As vendas geradas pela sua indicação entram no acompanhamento do painel e só viram crédito quando o pagamento é confirmado.', 'gstore' ); ?></p>
							<ul>
								<li><strong><?php esc_html_e( 'Análise manual:', 'gstore' ); ?></strong> <?php esc_html_e( 'cada solicitação é revisada antes da liberação.', 'gstore' ); ?></li>
								<li><strong><?php esc_html_e( 'Venda vinculada:', 'gstore' ); ?></strong> <?php esc_html_e( 'o pedido precisa estar associado ao seu link de indicação.', 'gstore' ); ?></li>
								<li><strong><?php esc_html_e( 'Pagamento confirmado:', 'gstore' ); ?></strong> <?php esc_html_e( 'pedidos cancelados ou sem pagamento aprovado não geram comissão.', 'gstore' ); ?></li>
								<li><strong><?php esc_html_e( 'Painel do parceiro:', 'gstore' ); ?></strong> <?php esc_html_e( 'acompanhe vendas, status e valores disponíveis para resgate.', 'gstore' ); ?></li>
							</ul>
						</div>
					</article>
				</div>
			</section>

			<section class="gstore-partner-program-section is-security">
				<div class="gstore-partner-program-container">
					<div class="gstore-partner-program-heading">
						<h2><?php esc_html_e( 'Segurança e processo', 'gstore' ); ?></h2>
					</div>
					<div class="gstore-partner-program-safety">
						<?php foreach ( $security as $item ) : ?>
							<article>
								<span><?php echo gstore_partner_account_icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<div>
									<strong><?php echo esc_html( $item['title'] ); ?></strong>
									<small><?php echo esc_html( $item['text'] ); ?></small>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
					<div class="gstore-partner-program-safety-indicator" data-gstore-partner-safety-indicator aria-hidden="true">
						<div class="gstore-partner-program-safety-indicator__track">
							<span data-gstore-partner-safety-fill></span>
						</div>
						<span class="gstore-partner-program-safety-indicator__count"><b data-gstore-partner-safety-current>1</b>/<span data-gstore-partner-safety-total>4</span></span>
					</div>
				</div>
			</section>

			<section class="gstore-partner-program-section is-form">
				<div class="gstore-partner-program-container gstore-partner-program-form-shell">
					<div class="gstore-partner-program-form-copy">
						<h2><?php echo esc_html( sprintf( __( 'Quero ser parceiro %s', 'gstore' ), $store_name ) ); ?></h2>
						<p><?php esc_html_e( 'Preencha seus dados e nossa equipe entrará em contato.', 'gstore' ); ?></p>
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
			<div class="gstore-partner-stat__title">
				<span class="gstore-partner-stat__icon"><?php echo gstore_partner_account_icon( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span><?php echo esc_html( $title ); ?></span>
			</div>
			<?php if ( '' !== (string) $value ) : ?>
				<strong><?php echo esc_html( $value ); ?></strong>
			<?php endif; ?>
			<?php if ( $status ) : ?>
				<small class="is-status"><?php echo esc_html( $status ); ?></small>
			<?php elseif ( $meta ) : ?>
				<small><?php echo esc_html( $meta ); ?></small>
			<?php endif; ?>
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
			return __( 'Comissão estornada', 'gstore' );
		}

		return __( 'Pagamento confirmado', 'gstore' );
	}
}

if ( ! function_exists( 'gstore_partner_account_sale_item_label' ) ) {
	function gstore_partner_account_sale_item_label( $sale ) {
		$item_keys = array(
			'itemName',
			'item',
			'itemLabel',
			'productName',
			'product',
			'productTitle',
			'product_title',
			'orderItem',
			'order_item',
		);

		foreach ( $item_keys as $key ) {
			if ( ! empty( $sale[ $key ] ) && ! is_array( $sale[ $key ] ) ) {
				return (string) $sale[ $key ];
			}
		}

		$items = array();
		foreach ( array( 'items', 'products', 'lineItems', 'line_items', 'orderItems', 'order_items' ) as $items_key ) {
			if ( empty( $sale[ $items_key ] ) || ! is_array( $sale[ $items_key ] ) ) {
				continue;
			}

			foreach ( $sale[ $items_key ] as $item ) {
				if ( is_array( $item ) ) {
					foreach ( array( 'name', 'title', 'productName', 'product_name', 'itemName', 'item_name' ) as $name_key ) {
						if ( ! empty( $item[ $name_key ] ) ) {
							$items[] = (string) $item[ $name_key ];
							break;
						}
					}
				} elseif ( is_scalar( $item ) ) {
					$items[] = (string) $item;
				}
			}
		}

		if ( ! empty( $items ) ) {
			$items = array_values( array_filter( array_unique( $items ) ) );
			if ( count( $items ) > 1 ) {
				return sprintf(
					/* translators: 1: first item name, 2: additional item count. */
					__( '%1$s +%2$d itens', 'gstore' ),
					$items[0],
					count( $items ) - 1
				);
			}

			return $items[0];
		}

		if ( function_exists( 'wc_get_order' ) ) {
			$order_id = 0;
			foreach ( array( 'orderId', 'order_id', 'orderNumber', 'order_number' ) as $order_key ) {
				if ( ! empty( $sale[ $order_key ] ) ) {
					$order_id = absint( $sale[ $order_key ] );
					break;
				}
			}

			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					foreach ( $order->get_items() as $order_item ) {
						$items[] = $order_item->get_name();
					}

					$items = array_values( array_filter( array_unique( $items ) ) );
					if ( count( $items ) > 1 ) {
						return sprintf(
							/* translators: 1: first item name, 2: additional item count. */
							__( '%1$s +%2$d itens', 'gstore' ),
							$items[0],
							count( $items ) - 1
						);
					}

					if ( ! empty( $items[0] ) ) {
						return $items[0];
					}
				}
			}
		}

		return __( 'Item indisponivel', 'gstore' );
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
			'concluidos' => __( 'Concluídos', 'gstore' ),
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
						<th><?php esc_html_e( 'Item', 'gstore' ); ?></th>
						<th><?php esc_html_e( 'Valor da venda', 'gstore' ); ?></th>
						<th><?php esc_html_e( 'Comissão/moedas', 'gstore' ); ?></th>
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
							<td data-label="<?php esc_attr_e( 'Pedido', 'gstore' ); ?>"><span class="gstore-partner-table__value"><?php echo esc_html( $sale['orderNumber'] ); ?></span></td>
							<td data-label="<?php esc_attr_e( 'Cliente', 'gstore' ); ?>"><span class="gstore-partner-table__value"><?php echo esc_html( $sale['customerName'] ); ?></span></td>
							<td class="gstore-partner-table__item" data-label="<?php esc_attr_e( 'Item', 'gstore' ); ?>"><span class="gstore-partner-table__value"><?php echo esc_html( gstore_partner_account_sale_item_label( $sale ) ); ?></span></td>
							<td data-label="<?php esc_attr_e( 'Valor', 'gstore' ); ?>"><span class="gstore-partner-table__value"><?php echo esc_html( $sale['orderTotalFormatted'] ); ?></span></td>
							<td data-label="<?php esc_attr_e( 'Comissão', 'gstore' ); ?>"><span class="gstore-partner-table__value"><strong><?php echo esc_html( $sale['amountFormatted'] ); ?></strong></span></td>
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
		<div class="<?php echo esc_attr( 'gstore-partner-redemption-list' . ( empty( $redemptions ) ? ' is-empty' : '' ) ); ?>">
			<?php if ( empty( $redemptions ) ) : ?>
				<div class="gstore-partner-empty-state">
					<span class="gstore-partner-empty-state__icon"><?php echo gstore_partner_account_icon( 'coins' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<p><?php esc_html_e( 'Nenhum resgate solicitado ainda.', 'gstore' ); ?></p>
				</div>
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
					<h3 class="gstore-account-contract__title gstore-partner-section-title">
						<span class="gstore-partner-title-icon"><?php echo gstore_partner_account_icon( 'document' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span><?php esc_html_e( 'Contrato do Programa Revendedor', 'gstore' ); ?></span>
					</h3>
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
							<?php esc_html_e( 'Aceite obrigatório para liberar o painel de revendedor.', 'gstore' ); ?>
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
								esc_html__( 'Termos de Participação - Programa Revendedor %s', 'gstore' ),
								esc_html( $store_name )
							);
							?>
						</strong>
						<span>
							<?php
							printf(
								/* translators: %s: contract version. */
								esc_html__( 'Versão %s', 'gstore' ),
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
								<th><?php esc_html_e( 'Slug de indicação', 'gstore' ); ?></th>
								<td><?php echo esc_html( $slug ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Comissão vigente', 'gstore' ); ?></th>
								<td><?php echo esc_html( $commission ); ?>%</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Link de indicação', 'gstore' ); ?></th>
								<td><?php echo esc_html( $referral_url ); ?></td>
							</tr>
						</tbody>
					</table>

					<h4><?php esc_html_e( '1. Objeto', 'gstore' ); ?></h4>
					<p>
						<?php
						printf(
							/* translators: %s: store name. */
							esc_html__( 'O presente instrumento regula a participação do revendedor no programa de indicação da loja %s, por meio de link exclusivo associado ao seu cadastro.', 'gstore' ),
							esc_html( $store_name )
						);
						?>
					</p>

					<h4><?php esc_html_e( '2. Regras de comissionamento', 'gstore' ); ?></h4>
					<p><?php esc_html_e( 'A comissão é calculada sobre o subtotal pago dos produtos, após descontos, sem incluir frete, taxas ou impostos. Pedidos cancelados, reembolsados ou invalidados podem gerar estorno de créditos.', 'gstore' ); ?></p>

					<h4><?php esc_html_e( '3. Uso do link e restrições', 'gstore' ); ?></h4>
					<p><?php esc_html_e( 'O revendedor deve divulgar seu link de forma clara e regular. Indicações para compras feitas pelo próprio revendedor, ou por e-mail de cobrança equivalente ao seu cadastro, não geram crédito.', 'gstore' ); ?></p>

					<h4><?php esc_html_e( '4. Créditos e resgates', 'gstore' ); ?></h4>
					<p><?php esc_html_e( 'Os créditos são liberados conforme aprovação do pagamento e podem ser resgatados pelos métodos disponíveis no painel. Solicitações pendentes reservam saldo até aprovação, rejeição ou cancelamento administrativo.', 'gstore' ); ?></p>

					<h4><?php esc_html_e( '5. Auditoria e alterações', 'gstore' ); ?></h4>
					<p>
						<?php
						printf(
							/* translators: %s: store name. */
							esc_html__( 'A loja %s pode auditar vendas, corrigir lançamentos e alterar regras do programa mediante atualização da versão destes termos. A continuidade de uso do painel depende do aceite dos termos vigentes.', 'gstore' ),
							esc_html( $store_name )
						);
						?>
					</p>

					<div class="gstore-partner-contract-document__signatures">
						<div>
							<span><?php echo esc_html( $store_name ); ?></span>
							<strong><?php esc_html_e( 'Administração do programa', 'gstore' ); ?></strong>
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
					<p><?php esc_html_e( 'Sua conta ainda não está habilitada como revendedora.', 'gstore' ); ?></p>
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
						esc_html__( 'Olá, %s!', 'gstore' ),
						esc_html( $name )
					);
					?>
				</h2>
				<p><?php esc_html_e( 'Gerencie sua conta revendedora, acompanhe suas vendas e acompanhe seus créditos.', 'gstore' ); ?></p>
			</section>

			<nav class="gstore-partner-tabs" aria-label="<?php esc_attr_e( 'Área revendedor', 'gstore' ); ?>">
				<a class="<?php echo 'painel' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( gstore_partner_account_view_url( 'painel' ) ); ?>"><?php esc_html_e( 'Meu Painel', 'gstore' ); ?></a>
				<a class="<?php echo 'vendas' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( gstore_partner_account_view_url( 'vendas' ) ); ?>"><?php esc_html_e( 'Minhas Vendas', 'gstore' ); ?></a>
				<a class="<?php echo 'creditos' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( gstore_partner_account_view_url( 'creditos' ) ); ?>"><?php esc_html_e( 'Meus Créditos', 'gstore' ); ?></a>
				<a class="<?php echo 'contrato' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( gstore_partner_account_view_url( 'contrato' ) ); ?>"><?php esc_html_e( 'Contrato', 'gstore' ); ?></a>
			</nav>

			<?php if ( 'painel' === $view ) : ?>
				<div class="gstore-partner-stats">
					<?php
					gstore_partner_account_render_stat( 'cart', __( 'Vendas indicadas', 'gstore' ), (string) absint( $partner['salesLast30'] ), __( 'Nos últimos 30 dias', 'gstore' ) );
					gstore_partner_account_render_stat(
						'coins',
						__( 'Créditos disponíveis', 'gstore' ),
						$partner['balanceFormatted'],
						sprintf(
							/* translators: %s: store name. */
							__( 'Em moedas %s', 'gstore' ),
							$store_name
						)
					);
					gstore_partner_account_render_stat( 'percent', __( 'Comissão atual', 'gstore' ), $commission_display . '%', __( 'Sobre o valor da venda', 'gstore' ) );
					?>
				</div>

				<section class="gstore-partner-link-panel">
					<div class="gstore-partner-link-panel__content">
						<header class="gstore-partner-link-panel__header">
							<h3>
								<span class="gstore-partner-title-icon"><?php echo gstore_partner_account_icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php esc_html_e( 'Seu link de parceiro', 'gstore' ); ?>
							</h3>
							<span class="gstore-partner-status-pill is-active"><?php esc_html_e( 'Ativo', 'gstore' ); ?></span>
						</header>
						<p><?php esc_html_e( 'Divulgue seu link exclusivo e ganhe créditos por cada venda realizada.', 'gstore' ); ?></p>
						<div class="gstore-partner-link-panel__actions">
							<input type="text" readonly value="<?php echo esc_attr( $partner['url'] ); ?>" data-gstore-partner-link />
							<button type="button" class="button gstore-partner-copy" data-gstore-copy-partner-link><?php esc_html_e( 'Copiar link', 'gstore' ); ?></button>
							<button type="button" class="button gstore-partner-share" data-gstore-share-partner-link><?php esc_html_e( 'Compartilhar', 'gstore' ); ?></button>
						</div>
					</div>
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
						<h3 class="gstore-partner-section-title">
							<span class="gstore-partner-title-icon"><?php echo gstore_partner_account_icon( 'coins' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Saldo em moedas', 'gstore' ); ?>
						</h3>
						<div class="gstore-partner-balance">
							<div>
								<span><?php esc_html_e( 'Seu saldo atual', 'gstore' ); ?></span>
								<strong><?php echo esc_html( $partner['balanceFormatted'] ); ?></strong>
								<small><?php esc_html_e( '1 moeda = R$ 1,00', 'gstore' ); ?></small>
							</div>
						</div>
						<a class="button gstore-partner-primary-button" href="<?php echo esc_url( gstore_partner_account_view_url( 'creditos' ) ); ?>"><?php esc_html_e( 'Usar créditos', 'gstore' ); ?></a>
					</section>

					<section class="gstore-partner-panel gstore-partner-how">
						<h3 class="gstore-partner-section-title">
							<span class="gstore-partner-title-icon"><?php echo gstore_partner_account_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Como funciona', 'gstore' ); ?>
						</h3>
						<div class="gstore-partner-how__body gstore-partner-how__body--plain">
							<ul>
								<li><?php esc_html_e( 'Divulgue seu link exclusivo para amigos e clientes.', 'gstore' ); ?></li>
								<li><?php esc_html_e( 'Cada compra realizada através do seu link gera créditos para você.', 'gstore' ); ?></li>
								<li><?php esc_html_e( 'Os créditos são liberados após o pedido ser concluído ou processado.', 'gstore' ); ?></li>
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
					<section class="gstore-partner-panel gstore-partner-credit-request">
						<h3 class="gstore-partner-credit-title gstore-partner-section-title">
							<span class="gstore-partner-title-icon"><?php echo gstore_partner_account_icon( 'coins' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span><?php esc_html_e( 'Solicitar resgate', 'gstore' ); ?></span>
						</h3>
						<div class="gstore-partner-balance is-credit-view">
							<div class="gstore-partner-icon"><?php echo gstore_partner_account_icon( 'coins' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<div>
								<span><?php esc_html_e( 'Saldo disponível', 'gstore' ); ?></span>
								<strong><?php echo esc_html( $partner['balanceFormatted'] ); ?></strong>
								<small>
									<?php
									printf(
										/* translators: %s: minimum redemption amount. */
										esc_html__( 'Mínimo para resgate: R$ %s', 'gstore' ),
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
								<span><?php esc_html_e( 'Método de resgate', 'gstore' ); ?></span>
								<select name="gstore_partner_redemption_method">
									<option value="external"><?php esc_html_e( 'Pagamento externo', 'gstore' ); ?></option>
									<option value="coupon"><?php esc_html_e( 'Cupom de loja', 'gstore' ); ?></option>
								</select>
							</label>
							<label>
								<span><?php esc_html_e( 'Observação', 'gstore' ); ?></span>
								<textarea name="gstore_partner_redemption_note" rows="3"></textarea>
							</label>
							<button type="submit" class="button gstore-partner-primary-button"><?php esc_html_e( 'Solicitar resgate', 'gstore' ); ?></button>
						</form>
					</section>

					<section class="gstore-partner-panel gstore-partner-credit-history">
						<h3 class="gstore-partner-section-title">
							<span class="gstore-partner-title-icon"><?php echo gstore_partner_account_icon( 'coins' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span><?php esc_html_e( 'Histórico de créditos', 'gstore' ); ?></span>
						</h3>
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

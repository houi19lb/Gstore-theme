<?php
/** Shared customer account presentation. Native WooCommerce endpoints own all mutations. */
defined( 'ABSPATH' ) || exit;

function gstore_account_support_url() {
	return add_query_arg( 'gstore_account_view', 'atendimento', wc_get_account_endpoint_url( 'dashboard' ) );
}

function gstore_account_is_support() {
	return is_user_logged_in() && ! is_wc_endpoint_url() && isset( $_GET['gstore_account_view'] )
		&& 'atendimento' === sanitize_key( wp_unslash( $_GET['gstore_account_view'] ) );
}

function gstore_account_dashboard_menu( $items ) {
	unset( $items['downloads'], $items['edit-address'] );
	$base = array(
		'dashboard' => __( 'Início', 'gstore' ),
		'orders' => __( 'Pedidos', 'gstore' ),
		'edit-account' => __( 'Meus dados', 'gstore' ),
		'atendimento' => __( 'Atendimento', 'gstore' ),
	);
	if ( ! isset( $items['revendedor'] ) && function_exists( 'gstore_partner_account_can_show_application' ) && gstore_partner_account_can_show_application() ) {
		$base['seja-revendedor'] = __( 'Seja um revendedor', 'gstore' );
	}
	// Keep program/extension endpoints and the nonce-protected logout link.
	$logout = $items['customer-logout'] ?? null;
	unset( $items['customer-logout'] );
	$items = $base + $items;
	if ( null !== $logout ) {
		$items['customer-logout'] = $logout;
	}
	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'gstore_account_dashboard_menu', 100 );

function gstore_account_menu_is_current( $endpoint ) {
	if ( gstore_account_is_support() ) {
		return 'atendimento' === $endpoint;
	}
	if ( 'edit-account' === $endpoint && is_wc_endpoint_url( 'edit-address' ) ) {
		return true;
	}
	return wc_is_current_account_menu_item( $endpoint );
}

/** The same store-info fields and URL helpers used by /atendimento. */
function gstore_account_contact_channels() {
	$info = gstore_store_info();
	$primary = trim( (string) $info->get_value( 'contact.contact_primary_link', '' ) );
	$channels = array(
		array( 'kind' => 'primary', 'description' => __( 'Fale com a equipe pelo canal de atendimento da loja.', 'gstore' ), 'label' => $info->get_value( 'contact.whatsapp_label', 'WhatsApp' ), 'url' => $primary ?: gstore_get_whatsapp_link(), 'icon' => 'fa-solid fa-headset' ),
		array( 'kind' => 'email', 'description' => __( 'Envie sua dúvida por e-mail. Se for sobre um pedido, informe o número.', 'gstore' ), 'label' => __( 'E-mail', 'gstore' ), 'url' => gstore_get_store_email_link(), 'icon' => 'fa-regular fa-envelope' ),
		array( 'kind' => 'telegram', 'description' => __( 'Acesse o contato da loja no Telegram para receber orientações.', 'gstore' ), 'label' => $info->get_value( 'contact.telegram_label', 'Telegram' ), 'url' => gstore_get_telegram_link(), 'icon' => 'fa-brands fa-telegram' ),
	);
	// Only direct support channels belong here. Social profiles stay in the site footer.
	return array_values( array_filter( $channels, static function ( $channel ) {
		return '' !== trim( (string) $channel['label'] ) && '' !== esc_url( $channel['url'] );
	} ) );
}

/** Bounded, HPOS-compatible queries; never accept a customer ID from the request. */
function gstore_account_dashboard_orders() {
	$customer_id = get_current_user_id();
	if ( ! $customer_id ) {
		return array();
	}
	return wc_get_orders( array( 'customer_id' => $customer_id, 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC' ) );
}

function gstore_account_order_counts() {
	$customer_id = get_current_user_id();
	$counts = array( 'total' => 0, 'active' => 0, 'completed' => 0 );
	if ( ! $customer_id ) {
		return $counts;
	}
	$counts['total'] = wc_get_customer_order_count( $customer_id );
	foreach ( array( 'active' => array( 'pending', 'on-hold', 'processing' ), 'completed' => array( 'completed' ) ) as $key => $statuses ) {
		$result = wc_get_orders( array( 'customer_id' => $customer_id, 'status' => $statuses, 'limit' => 1, 'return' => 'ids', 'paginate' => true ) );
		$counts[ $key ] = (int) $result->total;
	}
	return $counts;
}

/** Presentation only: never infer payment or shipment from a color. */
function gstore_account_order_tone( $order ) {
	$status = $order->get_status();
	// A legacy/custom label may differ from the persisted state (e.g. paid cancellation).
	if ( function_exists( 'gstore_my_account_get_orders_tab_status_label' )
		&& gstore_my_account_get_orders_tab_status_label( $order ) !== wc_get_order_status_name( $status ) ) {
		return 'neutral';
	}
	return array( 'cancelled' => 'danger', 'failed' => 'danger', 'completed' => 'success', 'processing' => 'info', 'pending' => 'warning', 'on-hold' => 'warning' )[ $status ] ?? 'neutral';
}

function gstore_account_order_progress( $order ) {
	$stages = array(
		'processando_pagamento' => __( 'Processando pagamento', 'gstore' ),
		'pagamento_confirmado' => __( 'Pagamento confirmado', 'gstore' ),
		'aguardando_documentacao' => __( 'Aguardando documentação', 'gstore' ),
		'processando_documentacao' => __( 'Processando documentação', 'gstore' ),
		'preparando_entrega' => __( 'Preparando entrega', 'gstore' ),
		'enviado' => __( 'Enviado', 'gstore' ),
	);
	$stage = gstore_get_order_fulfillment_stage( $order );
	if ( class_exists( '\\GStore\\Services\\Fulfillment_Service' ) ) {
		$data = \GStore\Services\Fulfillment_Service::get_fulfillment_data( $order->get_id() );
		if ( ! empty( $data['stages'] ) && is_array( $data['stages'] ) ) {
			// Preserve the plugin's fulfillment branches (including pickup).
			$stages = $data['stages'];
			unset( $stages['documentacao_negada'] );
			if ( isset( $stages['processando_documentacao'] ) ) {
				$stages['processando_documentacao'] = __( 'Processando documentação', 'gstore' );
			}
		}
	}
	$rejected = 'documentacao_negada' === $stage;
	$index = array_search( $rejected ? 'processando_documentacao' : $stage, array_keys( $stages ), true );
	$inactive = in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true );
	$label = $rejected ? __( 'Documentação negada', 'gstore' ) : ( $stages[ $stage ] ?? wc_get_order_status_name( $order->get_status() ) );
	if ( $inactive ) {
		$label = function_exists( 'gstore_my_account_get_orders_tab_status_label' )
			? gstore_my_account_get_orders_tab_status_label( $order )
			: wc_get_order_status_name( $order->get_status() );
	}
	$messages = array(
		'processando_pagamento' => __( 'Acompanhe a confirmação e os detalhes do pagamento no seu pedido.', 'gstore' ),
		'pagamento_confirmado' => __( 'Pagamento confirmado. Acompanhe os próximos passos no seu pedido.', 'gstore' ),
		'aguardando_documentacao' => __( 'Abra o pedido para consultar e enviar a documentação solicitada.', 'gstore' ),
		'processando_documentacao' => __( 'Sua documentação está em análise. Consulte os arquivos e as orientações no pedido.', 'gstore' ),
		'documentacao_negada' => __( 'Sua documentação precisa de atenção. Abra o pedido para ver a revisão e entre em contato com o atendimento.', 'gstore' ),
		'preparando_entrega' => __( 'Seu pedido está sendo preparado para envio. Acompanhe os detalhes por aqui.', 'gstore' ),
		'enviado' => __( 'Seu pedido foi enviado. Consulte os detalhes e o rastreamento disponível no pedido.', 'gstore' ),
	);
	$tone = gstore_account_order_tone( $order );
	if ( ! $inactive ) {
		$tone = array( 'documentacao_negada' => 'danger', 'aguardando_documentacao' => 'warning', 'processando_pagamento' => 'warning', 'pagamento_confirmado' => 'info', 'processando_documentacao' => 'info', 'preparando_entrega' => 'info', 'enviado' => 'success', 'pronto_retirada' => 'info', 'retirado' => 'success' )[ $stage ] ?? $tone;
	}
	return array(
		'tone' => $tone,
		'stages' => $stages, 'index' => $index, 'show_timeline' => ! $inactive && false !== $index,
		'label' => $label, 'rejected' => $rejected,
		'message' => $inactive ? __( 'Consulte os detalhes e as opções disponíveis no seu pedido.', 'gstore' ) : ( $messages[ $stage ] ?? __( 'Consulte as orientações e os próximos passos no seu pedido.', 'gstore' ) ),
	);
}

/** Fill gaps in the pt-BR WooCommerce catalog without replacing installed translations. */
function gstore_account_email_verification_translation( $translation, $text ) {
	if ( $translation !== $text || 'pt_BR' !== determine_locale() ) {
		return $translation;
	}
	$messages = array(
		'Confirm your email address to check for past orders and link them to your account.' => 'Confirme seu e-mail para buscar pedidos anteriores e vinculá-los à sua conta.',
		'Confirm email address' => 'Confirmar e-mail',
		'Confirm your email address' => 'Confirme seu e-mail',
		'Confirm your email address for {site_title}' => 'Confirme seu e-mail em {site_title}',
		"Once you've confirmed that %s is your email address, we'll link any past orders to your account." => 'Após confirmar que %s é seu e-mail, vincularemos à sua conta os pedidos anteriores feitos com esse endereço, se houver.',
		"If you didn't request this email, there's nothing to worry about, and you can safely ignore it." => 'Se você não solicitou este e-mail, não se preocupe. Pode ignorar esta mensagem com segurança.',
		'Confirm your email address to check for past orders. A confirmation link was sent recently — please check your inbox.' => 'Confirme seu e-mail para buscar pedidos anteriores. Enviamos um link de confirmação recentemente. Verifique sua caixa de entrada.',
		'A confirmation link has been sent to your email address. Please check your inbox.' => 'Enviamos um link de confirmação para seu e-mail. Verifique sua caixa de entrada.',
		'A confirmation link was sent recently. Please check your inbox, or wait a moment before requesting a new one.' => 'Enviamos um link de confirmação recentemente. Verifique sua caixa de entrada ou aguarde um momento antes de solicitar outro.',
		'Your email address has been confirmed.' => 'Seu e-mail foi confirmado.',
		'This confirmation link is invalid or has expired. Please request a new one.' => 'Este link de confirmação é inválido ou expirou. Solicite um novo link.',
		'Unable to confirm this email while you are logged in to a different account. Please log out and open the link again.' => 'Não é possível confirmar este e-mail enquanto você estiver conectado a outra conta. Saia da conta e abra o link novamente.',
		'You need to be logged in to confirm your email address.' => 'Entre na sua conta para confirmar seu e-mail.',
	);
	return $messages[ $text ] ?? $translation;
}
add_filter( 'gettext_woocommerce', 'gstore_account_email_verification_translation', 20, 2 );

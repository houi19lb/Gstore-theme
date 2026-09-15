<?php
/** Loaded only by the CLI regression test with --render. Synthetic presentation, no saving. */
if ( PHP_SAPI !== 'cli' || ! defined( 'ABSPATH' ) ) { exit; }
if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
$preview_mode = true;
function wc_clean( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function has_action( $name ) { return false; }
function _x( $value, ...$args ) { return $value; }
function _n( $single, $plural, $count, ...$args ) { return 1 === $count ? $single : $plural; }
function wc_get_account_orders_actions( $order ) { return 'pending' === $order->status ? array( 'pay' => array( 'url' => 'https://example.test/pay', 'name' => 'Pagar' ), 'view' => array( 'url' => $order->get_view_order_url(), 'name' => 'Visualizar' ), 'cancel' => array( 'url' => 'https://example.test/cancel', 'name' => 'Cancelar' ) ) : array( 'view' => array( 'url' => $order->get_view_order_url(), 'name' => 'Visualizar' ), 'order-again' => array( 'url' => 'https://example.test/reorder', 'name' => 'Refazer compra' ) ); }

function gstore_get_myaccount_icon( $endpoint ) {
	$icons = array( 'dashboard' => 'fa-house', 'orders' => 'fa-box', 'edit-account' => 'fa-user', 'customer-logout' => 'fa-arrow-right-from-bracket' );
	return isset( $icons[ $endpoint ] ) ? '<i class="fa-solid ' . $icons[ $endpoint ] . '" aria-hidden="true"></i>' : '';
}
$plugin_stages = array_combine( $stage_keys, array( 'Processando pagamento', 'Pagamento confirmado', 'Aguardando documentação', 'Processando documentação', 'Preparando entrega', 'Enviado' ) );
$store = array( 'contact.contact_primary_link' => 'https://example.test/contato', 'contact.whatsapp_label' => 'Teleatendimento', 'telegram_url' => 'https://t.me/example', 'email_url' => 'mailto:ajuda@example.test' );
function wp_json_encode( $value ) { return json_encode( $value ); }
function gstore_get_order_fulfillment_documents( $order ) { return array( array( 'id' => 'fixture', 'doc_type' => 'documento_geral', 'label' => 'documento_geral', 'filename' => 'documento-de-identificacao-exemplo.pdf', 'status' => 'documentacao_negada' === $order->stage ? 'rejected' : 'pending', 'review_note' => 'Exemplo fictício: a imagem está cortada. Envie o documento completo e legível.' ) ); }
function gstore_get_order_required_documents( $order ) { return array(); }
function gstore_get_order_doc_profile( $order ) { return 'none'; }
$root = dirname( __DIR__, 2 );
$css = file_get_contents( $root . '/assets/css/tokens.css' ) . file_get_contents( $root . '/assets/css/my-account.css' ) . file_get_contents( $root . '/assets/css/account-dashboard.min.css' ) . file_get_contents( $root . '/assets/css/fulfillment-timeline.min.css' );
foreach ( array( 'css', 'webfonts' ) as $folder ) {
	if ( ! is_dir( $dir . '/fontawesome/' . $folder ) ) { mkdir( $dir . '/fontawesome/' . $folder, 0777, true ); }
	foreach ( glob( $root . '/assets/vendor/fontawesome/6.5.1/' . $folder . '/*' ) as $asset ) { if ( is_file( $asset ) ) { copy( $asset, $dir . '/fontawesome/' . $folder . '/' . basename( $asset ) ); } }
}
foreach ( array( 'inicio', 'andamento', 'concluido', 'vazio', 'atendimento', 'dados', 'enderecos', 'orders', 'pedido-negado', 'pedido-analise', 'pedido-enviado', 'pedido-preparando', 'pedido-pagamento', 'pedido-cancelado', 'pedido-retirada' ) as $page ) {
	$_GET = 'atendimento' === $page ? array( 'gstore_account_view' => 'atendimento' ) : array();
	$endpoint = array( 'dados' => 'edit-account', 'enderecos' => 'edit-address', 'orders' => 'orders' )[ $page ] ?? '';
	$order = new WC_Order(); $order->status = 'andamento' === $page ? 'processing' : 'cancelled';
	$orders = array( $order, clone $order, clone $order, clone $order, clone $order );
	$orders[1]->id = 41; $orders[1]->status = 'processing'; $orders[1]->stage = 'documentacao_negada';
	$orders[2]->id = 40; $orders[2]->status = 'completed'; $orders[2]->stage = 'enviado';
	$orders[3]->id = 43; $orders[3]->status = 'pending'; $orders[3]->stage = 'processando_pagamento';
	$orders[4]->id = 44; $orders[4]->status = 'processing'; $orders[4]->stage = 'processando_documentacao';
	if ( 'concluido' === $page ) { $order->status = 'completed'; $order->stage = 'enviado'; }
	if ( 'vazio' === $page ) { $orders = array(); }
	$preview_form = '';
	if ( 'dados' === $page ) {
		$preview_form = '<form class="woocommerce-EditAccountForm edit-account" onsubmit="return false">';
		foreach ( array( 'first_name' => array( 'Nome', 'Cliente', 'first' ), 'last_name' => array( 'Sobrenome', 'Exemplo', 'last' ), 'display_name' => array( 'Nome de exibição', 'Cliente Exemplo', 'wide' ), 'email' => array( 'Endereço de e-mail', 'cliente@example.test', 'wide' ) ) as $key => $field ) {
			$preview_form .= '<p class="woocommerce-form-row form-row form-row-' . $field[2] . '"><label for="account_' . $key . '">' . $field[0] . '</label><input class="input-text" id="account_' . $key . '" type="' . ( 'email' === $key ? 'email' : 'text' ) . '" value="' . $field[1] . '"></p>';
		}
		$preview_form .= '<fieldset><legend>Alteração de senha</legend>';
		foreach ( array( 'current' => 'Senha atual', 'new' => 'Nova senha', 'confirm' => 'Confirmar nova senha' ) as $key => $label ) {
			$preview_form .= '<p class="form-row form-row-wide"><label for="password_' . $key . '">' . $label . '</label><span class="password-input"><input class="input-text" id="password_' . $key . '" type="password" autocomplete="off"></span></p>';
		}
		$preview_form .= '</fieldset></form>';
	}
	if ( 'enderecos' === $page ) {
		$preview_form = '<div class="woocommerce-Addresses">';
		foreach ( array( 'Cobrança', 'Entrega' ) as $label ) { $preview_form .= '<section class="woocommerce-Address"><header class="woocommerce-Address-title title"><h2>Endereço de ' . $label . '</h2><a class="edit" href="https://example.test/edit-address">Editar</a></header><address>Cliente Exemplo<br>Endereço fictício para revisão visual.</address></section>'; }
		$preview_form .= '</div>';
	}
	if ( 'orders' === $page ) {
		ob_start(); wc_get_template( 'myaccount/orders.php', array( 'has_orders' => true, 'customer_orders' => (object) array( 'orders' => $orders, 'max_num_pages' => 1 ), 'current_page' => 1 ) ); $preview_form = ob_get_clean();
	}
	$is_detail = str_starts_with( $page, 'pedido-' );
	if ( $is_detail ) {
		$endpoint = 'view-order';
		$order->stage = array( 'pedido-negado' => 'documentacao_negada', 'pedido-analise' => 'processando_documentacao', 'pedido-enviado' => 'enviado', 'pedido-retirada' => 'pronto_retirada', 'pedido-preparando' => 'preparando_entrega', 'pedido-pagamento' => 'processando_pagamento', 'pedido-cancelado' => '' )[ $page ];
		$order->status = 'pedido-cancelado' === $page ? 'cancelled' : ( 'pedido-enviado' === $page ? 'completed' : 'processing' );
		if ( 'pedido-retirada' === $page ) { $plugin_stages = array( 'processando_pagamento' => 'Processando pagamento', 'pagamento_confirmado' => 'Pagamento confirmado', 'pronto_retirada' => 'Pronto para retirada na loja', 'retirado' => 'Retirado' ); }
		$preview_order_details = '<section class="woocommerce-order-details"><h2 class="woocommerce-order-details__title">Detalhes do pedido</h2><table class="woocommerce-table woocommerce-table--order-details"><thead><tr><th>Produto</th><th>Total</th></tr></thead><tbody><tr><td>Produto de exemplo × 1</td><td>R$ 250,00</td></tr></tbody><tfoot><tr><th>Total</th><td>R$ 250,00</td></tr></tfoot></table><p>Dados fictícios. No site, esta seção mantém os detalhes e as ações do WooCommerce.</p></section>';
		ob_start(); wc_get_template( 'myaccount/view-order.php', array( 'order' => $order ) ); $preview_form = ob_get_clean();
	}
	ob_start(); wc_get_template( 'myaccount/my-account.php' ); $content = ob_get_clean();
	// This preview has no account backend; unsupported actions point to its visible scope note.
	$content = preg_replace( '#href="(?:/customer-logout\.html|https://example\.test[^" ]*|https://t\.me/example|mailto:ajuda@example\.test)"#', 'href="#preview-scope"', $content );
	$html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Minha conta — prévia da revisão</title><link rel="stylesheet" href="/fontawesome/css/all.min.css"><style>body{margin:0;font-family:Arial,sans-serif;--gstore-color-accent-contrast:#111}*{box-sizing:border-box}.entry-content>.woocommerce{max-width:1000px;margin:auto}.preview-note{margin:0;padding:12px 24px;background:#f4f5f6;font-size:12px;line-height:1.8}.preview-note a{color:inherit;margin-right:14px}.woocommerce form .form-row::before,.woocommerce form .form-row::after,.woocommerce-Address-title::before,.woocommerce-Address-title::after{content:" ";display:table}.woocommerce table.shop_table td{border-top:3px solid #222}form label{display:block;margin-bottom:8px}input{min-height:44px;width:100%}</style><style>' . $css . '</style></head><body class="woocommerce-account logged-in"><p id="preview-scope" class="preview-note">Prévia visual · dados fictícios · ações de pedidos e salvamento continuam no WooCommerce<br><a href="/inicio.html">Pedido cancelado</a><a href="/andamento.html">Em andamento</a><a href="/concluido.html">Concluído</a><a href="/vazio.html">Sem pedidos</a><a href="/atendimento.html">Atendimento</a><a href="/dados.html">Meus dados</a><a href="/enderecos.html">Endereços</a><a href="/orders.html">Histórico de pedidos</a><a href="/pedido-negado.html">Pedido: documentação negada</a><a href="/pedido-analise.html">Em análise</a><a href="/pedido-enviado.html">Enviado</a><a href="/pedido-retirada.html">Retirada</a></p><main class="wp-block-group is-layout-constrained"><div class="entry-content"><div class="woocommerce">' . $content . '</div></div></main></body></html>';
	if ( $is_detail ) {
		// Render the real upload UI, but prevent all document operations in this static fixture.
		$script = '<script>window.gstoreFulfillment={orderId:42};</script><script>' . file_get_contents( $root . '/assets/js/fulfillment-timeline.min.js' ) . '</script><script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".gstore-view-order input,.gstore-view-order button").forEach(function(el){el.disabled=true;el.title="Prévia visual: ação indisponível";});});</script>';
		$html = str_replace( '</head>', '<meta http-equiv="Content-Security-Policy" content="connect-src &apos;none&apos;; form-action &apos;none&apos;"></head>', $html );
		$html = str_replace( '</body>', $script . '</body>', $html );
	}
	// Local-only interaction layer. Native network operations remain blocked by CSP.
	$html = str_replace( '</head>', '<meta http-equiv="Content-Security-Policy" content="connect-src &apos;none&apos;; form-action &apos;none&apos;"></head>', $html );
	$html = str_replace( '</body>', '<script>' . file_get_contents( __DIR__ . '/account-preview-interactions.js' ) . '</script></body>', $html );
	file_put_contents( $dir . '/' . $page . '.html', $html );
}

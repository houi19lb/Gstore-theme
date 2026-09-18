<?php
/** php tests/account-dashboard.php [--render=directory] — synthetic data only. */
define( 'ABSPATH', __DIR__ );
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function __( $s, ...$args ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return preg_match( '/^(https?:|mailto:|tel:|\/)/', (string) $s ) ? esc_attr( $s ) : ''; }
function esc_html__( $s, ...$args ) { return esc_html( $s ); }
function esc_html_e( $s, ...$args ) { echo esc_html( $s ); }
function esc_attr_e( $s, ...$args ) { echo esc_attr( $s ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_kses_post( $s ) { return $s; }
function add_filter( ...$args ) {}
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $s ) ); }
function wp_unslash( $s ) { return $s; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function get_current_user_id() { return $GLOBALS['customer_id'] ?? 7; }
function is_wc_endpoint_url( $endpoint = '' ) { return $endpoint ? $endpoint === ( $GLOBALS['endpoint'] ?? '' ) : ! empty( $GLOBALS['endpoint'] ); }
function wc_is_current_account_menu_item( $endpoint ) { return $endpoint === ( $GLOBALS['endpoint'] ?: 'dashboard' ); }
function wc_get_account_endpoint_url( $endpoint ) { if ( ! empty( $GLOBALS['preview_mode'] ) ) { return '/' . ( array( 'dashboard' => 'inicio', 'edit-account' => 'dados', 'edit-address' => 'enderecos' )[ $endpoint ] ?? $endpoint ) . '.html'; } return 'https://example.test/account/' . ( 'dashboard' === $endpoint ? '' : $endpoint . '/' ); }
function add_query_arg( $key, $value = null, $url = "" ) { if ( is_array( $key ) ) { return "/orders.html?" . http_build_query( $key ); } if ( ! empty( $GLOBALS['preview_mode'] ) && 'gstore_account_view' === $key ) { return '/atendimento.html'; } return $url . '?' . $key . '=' . $value; }
function home_url( $path ) { return 'https://example.test' . $path; }
function gstore_get_catalog_url() { return home_url( '/catalogo/' ); }
function wp_get_current_user() { return (object) array( 'ID' => 7, 'first_name' => 'Cliente', 'display_name' => 'Cliente Exemplo', 'user_email' => 'cliente@example.test' ); }
function wc_get_customer_order_count( $id ) { return count( $GLOBALS['orders'] ); }
function wc_get_orders( $args ) { $GLOBALS['queries'][] = $args; return ! empty( $args['paginate'] ) ? (object) array( 'total' => count( array_filter( $GLOBALS['orders'], static fn( $order ) => in_array( $order->status, $args['status'], true ) ) ) ) : array_slice( $GLOBALS['orders'], 0, $args['limit'] ?? null ); }
function wc_format_datetime( $date ) { return $date->format( 'd/m/Y' ); }
function wc_get_order_status_name( $s ) { return array( 'pending' => 'Aguardando pagamento', 'processing' => 'Processando', 'completed' => 'Concluído', 'on-hold' => 'Em espera', 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado', 'failed' => 'Falhou' )[ $s ] ?? $s; }
function gstore_my_account_get_orders_tab_status_label( $order ) { return 'cancelled' === $order->status && $order->paid ? 'Pago/Confirmado' : wc_get_order_status_name( $order->status ); }
function gstore_get_order_fulfillment_stage( $order ) { return $order->stage; }
function gstore_store_info() { return new class { function get_value( $key, $fallback = '' ) { return $GLOBALS['store'][ $key ] ?? $fallback; } }; }
function gstore_get_whatsapp_link() { return $GLOBALS['store']['whatsapp_url'] ?? ''; }
function gstore_get_store_email_link() { return $GLOBALS['store']['email_url'] ?? ''; }
function gstore_get_telegram_link() { return $GLOBALS['store']['telegram_url'] ?? ''; }
function gstore_get_social_link( $network ) { return $GLOBALS['store'][ $network . '_url' ] ?? ''; }
function gstore_get_phone( $format ) { return $GLOBALS['store']['phone'] ?? ''; }
function wc_get_template( $name, $args = array() ) { extract( $args ); include dirname( __DIR__ ) . '/woocommerce/' . $name; }
function wc_get_account_menu_items() { return gstore_account_dashboard_menu( array( 'dashboard' => 'Painel', 'orders' => 'Pedidos', 'edit-account' => 'Dados', 'customer-logout' => 'Sair' ) ); }
function do_action( $name, ...$args ) { if ( 'woocommerce_view_order' === $name && ! empty( $GLOBALS['preview_mode'] ) ) { echo $GLOBALS['preview_order_details'] ?? ''; } if ( 'woocommerce_account_navigation' === $name ) { wc_get_template( 'myaccount/navigation.php' ); } elseif ( 'woocommerce_account_content' === $name ) { if ( ! empty( $GLOBALS['preview_form'] ) ) { echo $GLOBALS['preview_form']; } else { wc_get_template( 'myaccount/dashboard.php' ); } } }
class AccountDate extends DateTime { function date( $format ) { return $this->format( $format ); } }
class WC_Order {
	public $stage = 'preparando_entrega'; public $status = 'processing'; public $paid = false; public $id = 42;
	function get_id() { return $this->id; }
	function get_item_count() { return 1; }
	function get_item_count_refunded() { return 0; }
	function get_meta( $key, $single = true ) { return '_gstore_fulfillment_stage' === $key ? $this->stage : ''; }
	function get_order_number() { return (string) ( 10440 + $this->id ); }
	function get_status() { return $this->status; }
	function get_date_created() { return new AccountDate( '2026-09-11' ); }
	function get_formatted_order_total() { return 'R$ 250,00'; }
	function get_view_order_url() {
		if ( empty( $GLOBALS['preview_mode'] ) ) { return 'https://example.test/account/view-order/42/'; }
		$page = array( 'documentacao_negada' => 'negado', 'processando_documentacao' => 'analise', 'enviado' => 'enviado', 'pronto_retirada' => 'retirada', 'processando_pagamento' => 'pagamento' )[ $this->stage ] ?? 'preparando';
		if ( 'cancelled' === $this->status ) { $page = 'cancelado'; }
		return '/pedido-' . $page . '.html?pedido=' . $this->get_order_number();
	}
}
require dirname( __DIR__ ) . '/inc/gstore-account-dashboard.php';
$endpoint = '';
$order = new WC_Order(); $orders = array( $order );
$menu = gstore_account_dashboard_menu( array( 'downloads' => 'Arquivos', 'edit-address' => 'Endereços', 'vip' => 'VIP', 'revendedor' => 'Parceiros', 'customer-logout' => 'Sair' ) );
check( array_slice( array_keys( $menu ), 0, 4 ) === array( 'dashboard', 'orders', 'edit-account', 'atendimento' ), 'Base menu order' );
check( isset( $menu['vip'], $menu['revendedor'], $menu['customer-logout'] ) && ! isset( $menu['downloads'], $menu['edit-address'] ), 'Extension endpoints preserved' );
$_GET['gstore_account_view'] = 'atendimento';
check( gstore_account_is_support() && ! gstore_account_menu_is_current( 'dashboard' ), 'Support active item' );
$endpoint = 'view-order'; check( ! gstore_account_is_support(), 'Support cannot hijack order endpoint' );
$endpoint = 'edit-address'; check( gstore_account_menu_is_current( 'edit-account' ), 'Address grouped under data' );
$endpoint = ''; $_GET = array( 'customer_id' => 999 );
gstore_account_dashboard_orders(); check( end( $queries )['customer_id'] === 7 && end( $queries )['limit'] === 4, 'Scoped bounded query' );
$counts = gstore_account_order_counts(); check( array( 'total' => 1, 'active' => 1, 'completed' => 0 ) === $counts && end( $queries )['customer_id'] === 7 && end( $queries )['limit'] === 1, 'Counts use customer-scoped HPOS queries' );
$customer_id = 0; $count = count( $queries ); check( array() === gstore_account_dashboard_orders() && count( $queries ) === $count, 'Guest cannot query orders' ); $customer_id = 7;
$stage_keys = array( 'processando_pagamento', 'pagamento_confirmado', 'aguardando_documentacao', 'processando_documentacao', 'preparando_entrega', 'enviado' );
foreach ( $stage_keys as $i => $stage ) {
	$order->stage = $stage;
	$p = gstore_account_order_progress( $order );
	check( $p['show_timeline'] && $i === $p['index'] && array_keys( $p['stages'] ) === $stage_keys, 'Six ordered stages: ' . $stage );
}
$order->stage = 'documentacao_negada'; $p = gstore_account_order_progress( $order );
check( 3 === $p['index'] && $p['rejected'] && 'Documentação negada' === $p['label'], 'Rejected document needs attention at review step' );
foreach ( array( 'cancelled', 'refunded', 'failed' ) as $status ) { $order->status = $status; check( ! gstore_account_order_progress( $order )['show_timeline'], 'Stopped orders must not suggest progress' ); }
$order->status = 'cancelled'; $order->paid = true; $p = gstore_account_order_progress( $order );
check( 'Pago/Confirmado' === $p['label'] && ! $p['show_timeline'] && 'cancelled' === $order->status, 'Honor existing order-list label without changing order status or inventing fulfillment progress' ); $order->paid = false;
$order->status = 'processing'; $order->stage = 'unknown'; check( ! gstore_account_order_progress( $order )['show_timeline'], 'Unknown stage must not pretend payment is current' );
$store = array( 'contact.contact_primary_link' => 'https://example.test/contato', 'contact.whatsapp_label' => 'Teleatendimento', 'contact.telegram_label' => 'Comunidade', 'telegram_url' => 'https://t.me/example', 'email_url' => 'mailto:ajuda@example.test' );
$channels = gstore_account_contact_channels(); check( 3 === count( $channels ) && 'Teleatendimento' === $channels[0]['label'] && $channels[0]['url'] === $store['contact.contact_primary_link'] && 'Comunidade' === $channels[2]['label'], 'Configured channels and labels' );
$store += array( 'facebook_url' => 'https://example.test/facebook', 'instagram_url' => 'https://example.test/instagram', 'youtube_url' => 'https://example.test/youtube', 'twitter_url' => 'https://example.test/twitter', 'tiktok_url' => 'https://example.test/tiktok', 'phone' => '551100000000' );
check( $channels === gstore_account_contact_channels(), 'Public social profiles and separate phone must never become account support cards' );
$store = array( 'contact.contact_primary_link' => 'javascript:alert(1)' ); check( array() === gstore_account_contact_channels(), 'Unsafe or absent links hidden' );
$order->stage = 'preparando_entrega';
ob_start(); wc_get_template( 'myaccount/dashboard.php' ); $html = ob_get_clean();
check( str_contains( $html, $order->get_view_order_url() ) && ! str_contains( $html, 'data-docs' ), 'Dashboard links to native detail without document payloads' );
$orders = array(); ob_start(); wc_get_template( 'myaccount/dashboard.php' ); $empty = ob_get_clean(); check( str_contains( $empty, 'Seus pedidos vão aparecer aqui' ), 'Empty state' ); $orders = array( $order );
// A plugin may return a pickup branch instead of the shipping branch.
class AccountPluginFixture { static function get_fulfillment_data( $id ) { return array( 'stages' => $GLOBALS['plugin_stages'] ?? array( 'processando_pagamento' => 'Processando pagamento', 'pronto_retirada' => 'Pronto para retirada', 'retirado' => 'Retirado' ), 'documents' => array( 'storage_path' => 'SECRET' ) ); } }
class_alias( AccountPluginFixture::class, 'GStore\\Services\\Fulfillment_Service' );
$order->stage = 'pronto_retirada'; $p = gstore_account_order_progress( $order ); check( 1 === $p['index'] && 'Pronto para retirada' === $p['label'] && ! isset( $p['documents'] ), 'Plugin branches preserved; document data excluded' );
// Guard semantic presentation against misleading success/error colors.
foreach ( array( 'cancelled' => 'danger', 'completed' => 'success', 'processing' => 'info', 'pending' => 'warning', 'on-hold' => 'warning', 'refunded' => 'neutral', 'failed' => 'danger', 'custom' => 'neutral' ) as $status => $tone ) {
	$order->status = $status; $order->paid = false;
	check( $tone === gstore_account_order_tone( $order ), 'Semantic order tone: ' . $status );
}
$order->status = 'cancelled'; $order->paid = true;
check( 'neutral' === gstore_account_order_tone( $order ) && 'neutral' === gstore_account_order_progress( $order )['tone'], 'Legacy paid label must not look cancelled or fulfilled' );
$order->paid = false; $order->status = 'processing'; $order->stage = 'documentacao_negada';
check( 'danger' === gstore_account_order_progress( $order )['tone'], 'Document correction is an attention state' );
$order->stage = 'enviado';
check( 'success' === gstore_account_order_progress( $order )['tone'], 'Sent state is visually complete without claiming delivery' );
$order->stage = 'preparando_entrega';
echo "PASS: account routing, menus, customer isolation, states, channels and native detail links.\n";

// Optional synthetic screenshot fixtures; generated output stays local.
foreach ( $argv as $arg ) {
	if ( str_starts_with( $arg, '--render=' ) ) {
		$dir = substr( $arg, 9 );
		require __DIR__ . '/fixtures/account-dashboard-preview.php';
	}
}

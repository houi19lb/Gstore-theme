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
function wp_kses_post( $s ) { return $s; }
function add_filter( ...$args ) {}
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $s ) ); }
function wp_unslash( $s ) { return $s; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function get_current_user_id() { return $GLOBALS['customer_id'] ?? 7; }
function is_wc_endpoint_url( $endpoint = '' ) { return $endpoint ? $endpoint === ( $GLOBALS['endpoint'] ?? '' ) : ! empty( $GLOBALS['endpoint'] ); }
function wc_is_current_account_menu_item( $endpoint ) { return $endpoint === ( $GLOBALS['endpoint'] ?: 'dashboard' ); }
function wc_get_account_endpoint_url( $endpoint ) { return 'https://example.test/account/' . ( 'dashboard' === $endpoint ? '' : $endpoint . '/' ); }
function add_query_arg( $key, $value, $url ) { return $url . '?' . $key . '=' . $value; }
function home_url( $path ) { return 'https://example.test' . $path; }
function gstore_get_catalog_url() { return home_url( '/catalogo/' ); }
function wp_get_current_user() { return (object) array( 'ID' => 7, 'first_name' => 'Cliente', 'display_name' => 'Cliente Exemplo', 'user_email' => 'cliente@example.test' ); }
function wc_get_customer_order_count( $id ) { return count( $GLOBALS['orders'] ); }
function wc_get_orders( $args ) { $GLOBALS['queries'][] = $args; return ! empty( $args['paginate'] ) ? (object) array( 'total' => count( array_filter( $GLOBALS['orders'], static fn( $order ) => in_array( $order->status, $args['status'], true ) ) ) ) : $GLOBALS['orders']; }
function wc_format_datetime( $date ) { return $date->format( 'd/m/Y' ); }
function wc_get_order_status_name( $s ) { return array( 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado', 'failed' => 'Falhou' )[ $s ] ?? $s; }
function gstore_get_order_fulfillment_stage( $order ) { return $order->stage; }
function gstore_store_info() { return new class { function get_value( $key, $fallback = '' ) { return $GLOBALS['store'][ $key ] ?? $fallback; } }; }
function gstore_get_whatsapp_link() { return $GLOBALS['store']['whatsapp_url'] ?? ''; }
function gstore_get_store_email_link() { return $GLOBALS['store']['email_url'] ?? ''; }
function gstore_get_telegram_link() { return $GLOBALS['store']['telegram_url'] ?? ''; }
function gstore_get_social_link( $network ) { return $GLOBALS['store'][ $network . '_url' ] ?? ''; }
function gstore_get_phone( $format ) { return $GLOBALS['store']['phone'] ?? ''; }
function wc_get_template( $name, $args = array() ) { extract( $args ); include dirname( __DIR__ ) . '/woocommerce/' . $name; }
function wc_get_account_menu_items() { return gstore_account_dashboard_menu( array( 'dashboard' => 'Painel', 'orders' => 'Pedidos', 'edit-account' => 'Dados', 'customer-logout' => 'Sair' ) ); }
function do_action( $name, ...$args ) { if ( 'woocommerce_account_navigation' === $name ) { wc_get_template( 'myaccount/navigation.php' ); } elseif ( 'woocommerce_account_content' === $name ) { wc_get_template( 'myaccount/dashboard.php' ); } }
class AccountDate extends DateTime { function date( $format ) { return $this->format( $format ); } }
class WC_Order {
	public $stage = 'preparando_entrega'; public $status = 'processing';
	function get_id() { return 42; }
	function get_order_number() { return '10482'; }
	function get_status() { return $this->status; }
	function get_date_created() { return new AccountDate( '2026-09-11' ); }
	function get_formatted_order_total() { return 'R$ 250,00'; }
	function get_view_order_url() { return 'https://example.test/account/view-order/42/'; }
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
gstore_account_dashboard_orders(); check( end( $queries )['customer_id'] === 7 && end( $queries )['limit'] === 3, 'Scoped bounded query' );
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
$order->status = 'processing'; $order->stage = 'unknown'; check( ! gstore_account_order_progress( $order )['show_timeline'], 'Unknown stage must not pretend payment is current' );
$store = array( 'contact.contact_primary_link' => 'https://example.test/contato', 'contact.whatsapp_label' => 'Teleatendimento', 'contact.telegram_label' => 'Comunidade', 'telegram_url' => 'https://t.me/example', 'email_url' => 'mailto:ajuda@example.test' );
$channels = gstore_account_contact_channels(); check( 3 === count( $channels ) && 'Teleatendimento' === $channels[0]['label'] && $channels[0]['url'] === $store['contact.contact_primary_link'] && 'Comunidade' === $channels[2]['label'], 'Configured channels and labels' );
$store = array( 'contact.contact_primary_link' => 'javascript:alert(1)' ); check( array() === gstore_account_contact_channels(), 'Unsafe or absent links hidden' );
$order->stage = 'preparando_entrega';
ob_start(); wc_get_template( 'myaccount/dashboard.php' ); $html = ob_get_clean();
check( str_contains( $html, $order->get_view_order_url() ) && ! str_contains( $html, 'data-docs' ), 'Dashboard links to native detail without document payloads' );
$orders = array(); ob_start(); wc_get_template( 'myaccount/dashboard.php' ); $empty = ob_get_clean(); check( str_contains( $empty, 'Seus pedidos vão aparecer aqui' ), 'Empty state' ); $orders = array( $order );
// A plugin may return a pickup branch instead of the shipping branch.
class AccountPluginFixture { static function get_fulfillment_data( $id ) { return array( 'stages' => $GLOBALS['plugin_stages'] ?? array( 'processando_pagamento' => 'Processando pagamento', 'pronto_retirada' => 'Pronto para retirada', 'retirado' => 'Retirado' ), 'documents' => array( 'storage_path' => 'SECRET' ) ); } }
class_alias( AccountPluginFixture::class, 'GStore\\Services\\Fulfillment_Service' );
$order->stage = 'pronto_retirada'; $p = gstore_account_order_progress( $order ); check( 1 === $p['index'] && 'Pronto para retirada' === $p['label'] && ! isset( $p['documents'] ), 'Plugin branches preserved; document data excluded' );
echo "PASS: account routing, menus, customer isolation, states, channels and native detail links.\n";

// Optional synthetic screenshot fixtures; generated output stays local.
foreach ( $argv as $arg ) {
	if ( ! str_starts_with( $arg, '--render=' ) ) { continue; }
	$dir = substr( $arg, 9 ); if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
	$plugin_stages = array_combine( $stage_keys, array( 'Processando pagamento', 'Pagamento confirmado', 'Aguardando documentação', 'Processando documentação', 'Preparando entrega', 'Enviado' ) );
	$order->stage = 'preparando_entrega';
	$store = array( 'contact.contact_primary_link' => 'https://example.test/contato', 'contact.whatsapp_label' => 'Teleatendimento', 'telegram_url' => 'https://t.me/example', 'email_url' => 'mailto:ajuda@example.test' );
	foreach ( array( 'inicio', 'atendimento' ) as $page ) {
		$_GET = 'atendimento' === $page ? array( 'gstore_account_view' => 'atendimento' ) : array();
		ob_start(); wc_get_template( 'myaccount/my-account.php' ); $content = ob_get_clean();
		$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/my-account.css' ) . file_get_contents( dirname( __DIR__ ) . '/assets/css/account-dashboard.css' );
		file_put_contents( $dir . '/' . $page . '.html', '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fixture sintética — Minha conta</title><style>body{margin:0;padding:40px;font-family:Arial,sans-serif;background:#f5f5f2}*{box-sizing:border-box}@media(max-width:600px){body{padding:20px}}</style><style>' . $css . '</style><body>' . $content . '</body></html>' );
	}
}

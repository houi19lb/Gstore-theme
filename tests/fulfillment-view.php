<?php
/** Render the actual customer template with synthetic documents. */
define('ABSPATH', __DIR__);
class WC_Order { function get_id() { return 42; } }
function gstore_get_order_fulfillment_stage($order) { return $GLOBALS['stage']; }
function gstore_get_order_fulfillment_documents($order) { return [['id'=>'a', 'doc_type'=>'documento_geral', 'filename'=>'fixture.pdf', 'label'=>'Fixture', 'status'=>$GLOBALS['doc_status'], 'private_document_id'=>123, 'storage_path'=>'SECRET_PATH']]; }
function gstore_get_order_required_documents($order) { return []; }
function gstore_get_order_doc_profile($order) { return 'none'; }
function esc_attr($value) { return htmlspecialchars((string)$value, ENT_QUOTES); }
function esc_html($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function home_url($path) { return 'https://example.test' . $path; }
function wp_json_encode($value) { return json_encode($value); }
function do_action(...$args) {}
foreach (['aguardando_documentacao'=>'pending', 'processando_documentacao'=>'pending', 'documentacao_negada'=>'rejected', 'preparando_entrega'=>'approved'] as $stage => $doc_status) {
    $order = new WC_Order();
    ob_start();
    require dirname(__DIR__) . '/woocommerce/myaccount/view-order.php';
    $html = ob_get_clean();
    if (str_contains($html, 'SECRET_PATH') || str_contains($html, 'private_document_id')) throw new RuntimeException('Private fields exposed');
    if (substr_count($html, 'class="gstore-fulfillment-timeline__step ') !== 6) throw new RuntimeException('Rejection became an extra mandatory step');
    if ($stage === 'documentacao_negada' && (!str_contains($html, 'is-current is-rejected') || !str_contains($html, 'Entre em contato com o atendente'))) throw new RuntimeException('Missing rejection message');
    if ($stage === 'processando_documentacao' && (!str_contains($html, 'Verificando documentação') || !str_contains($html, 'id="gstore-fulfillment-upload"'))) throw new RuntimeException('Missing verification upload');
    if ($stage === 'preparando_entrega' && (str_contains($html, 'id="gstore-fulfillment-upload"') || !str_contains($html, 'Documentação aprovada'))) throw new RuntimeException('Preparation display incorrect');
}
echo "PASS: customer template renders waiting, verification, rejection and approval without private vault fields.\n";

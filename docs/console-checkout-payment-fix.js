/**
 * Script para colar no Console do navegador (F12 → Console)
 * no checkout, para:
 * 1. Corrigir a linha "Pagamento" em "Ver detalhes" (mostrar Pix/Cartão conforme seleção)
 * 2. Mostrar informações úteis no console
 *
 * Uso: copie todo o conteúdo e cole no Console, depois Enter.
 */
(function () {
  if (typeof jQuery === 'undefined') {
    console.warn('[Gstore] jQuery não encontrado. Este script precisa do jQuery.');
    return;
  }
  var $ = jQuery;

  function getPaymentLabel() {
    var method = ($('input[name="payment_method"]:checked').val() || '').trim();
    return method === 'blu_pix' ? 'Pix' : method === 'blu_checkout' ? 'Cartão' : '';
  }

  function syncPaymentRow() {
    var label = getPaymentLabel();
    if (!label) return;
    $('.Gstore-checkout-summary-top__totals .Gstore-summary-row').each(function () {
      if ($(this).find('span').first().text().trim() === 'Pagamento') {
        $(this).find('span').last().text(label);
      }
    });
  }

  function logInfo() {
    var method = ($('input[name="payment_method"]:checked').val() || '').trim();
    var label = getPaymentLabel();
    console.log('[Gstore Checkout]', {
      metodo_selecionado: method || '(nenhum)',
      label_exibicao: label || '(vazio)',
      total_radios: $('input[name="payment_method"]').length,
      checked_val: $('input[name="payment_method"]:checked').val() || null
    });
  }

  // Corrige ao clicar em "Ver detalhes"
  $(document).on('click', '.Gstore-checkout-summary-top__toggle', function () {
    var $t = $(this);
    setTimeout(function () {
      if ($t.hasClass('is-open')) {
        syncPaymentRow();
        console.log('[Gstore] Linha Pagamento atualizada ao abrir Ver detalhes.');
      }
    }, 50);
  });

  // Corrige agora (se o painel já estiver aberto)
  syncPaymentRow();

  // Mostra informações no console
  logInfo();
  console.log('[Gstore] Script ativo. Selecione PIX ou Cartão e clique em "Ver detalhes" para corrigir a linha Pagamento.');
})();

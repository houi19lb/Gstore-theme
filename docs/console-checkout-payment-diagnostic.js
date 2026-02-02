/**
 * Script de DIAGNÓSTICO para colar no Console (F12 → Console) na página do checkout.
 * Analisa por que "Ver detalhes" mostra Cartão mesmo com PIX selecionado.
 *
 * O que faz:
 * 1. Intercepta as chamadas AJAX do resumo do carrinho (gstore_get_cart_summary)
 * 2. Observa quando a linha "Pagamento" é escrita no DOM e com qual valor
 * 3. Observa mudanças na seleção PIX/Cartão
 * 4. Mostra no console: o que foi enviado, o que o backend retornou, e quando o DOM foi atualizado
 *
 * Uso: copie todo o conteúdo, cole no Console e pressione Enter.
 * Depois: selecione PIX, clique em "Ver detalhes" e veja os logs.
 */
(function () {
  if (typeof jQuery === 'undefined') {
    console.warn('[Gstore Diagnóstico] jQuery não encontrado.');
    return;
  }
  var $ = jQuery;

  var log = [];
  function add(step, data) {
    var entry = { passo: step, hora: new Date().toISOString().slice(11, 23), dados: data };
    log.push(entry);
    console.log('[Gstore Diag]', step, data);
  }

  // ----- 1. Interceptar AJAX do resumo do carrinho -----
  var ajax = $.ajax;
  $.ajax = function (opt) {
    var url = (opt && opt.url) || '';
    var data = opt && opt.data;
    var isCartSummary = (typeof data === 'object' && data && data.action === 'gstore_get_cart_summary') ||
      (typeof data === 'string' && data.indexOf('gstore_get_cart_summary') !== -1);

    if (isCartSummary) {
      var paymentSent = null;
      if (typeof data === 'object' && data.payment_method !== undefined) {
        paymentSent = data.payment_method;
      } else if (typeof data === 'string') {
        var match = data.match(/payment_method=([^&]*)/);
        paymentSent = match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : '(não encontrado na string)';
      }
      add('AJAX ENVIADO (resumo carrinho)', {
        payment_method_enviado: paymentSent,
        momento_dom: $('input[name="payment_method"]:checked').val() || '(nenhum)'
      });

      var origSuccess = opt.success;
      opt.success = function (response) {
        var back = response && response.data ? {
          payment_method: response.data.payment_method,
          payment_method_title: response.data.payment_method_title
        } : { erro: 'sem response.data' };
        add('AJAX RESPOSTA (backend)', back);
        if (origSuccess) return origSuccess.apply(this, arguments);
      };
    }

    return ajax.apply(this, arguments);
  };

  // ----- 2. Observar quando a linha "Pagamento" é escrita no DOM -----
  function lerLinhaPagamento(container) {
    var $c = container ? $(container) : $('.Gstore-checkout-summary-top__totals');
    var valor = null;
    $c.find('.Gstore-summary-row').each(function () {
      if ($(this).find('span').first().text().trim() === 'Pagamento') {
        valor = $(this).find('span').last().text().trim();
        return false;
      }
    });
    return valor;
  }

  var lastLoggedPaymentValue = null;
  var totalsEl = document.querySelector('.Gstore-checkout-summary-top__totals');
  if (totalsEl) {
    var observer = new MutationObserver(function () {
      var valor = lerLinhaPagamento(totalsEl);
      if (valor === null || valor === lastLoggedPaymentValue) return;
      lastLoggedPaymentValue = valor;
      add('DOM ATUALIZADO (linha Pagamento)', {
        valor_exibido: valor,
        momento_radio: $('input[name="payment_method"]:checked').val() || '(nenhum)'
      });
    });
    observer.observe(totalsEl, { childList: true, subtree: true, characterData: true, characterDataOldValue: true });
  } else {
    add('AVISO', { msg: 'Elemento .Gstore-checkout-summary-top__totals não encontrado. Abra a página do checkout.' });
  }

  // ----- 3. Observar mudança de seleção PIX/Cartão -----
  $(document).on('change', 'input[name="payment_method"]', function () {
    add('USUÁRIO TROCOU MÉTODO', {
      novo_valor: $(this).val(),
      label: $(this).val() === 'blu_pix' ? 'Pix' : $(this).val() === 'blu_checkout' ? 'Cartão' : $(this).val()
    });
  });

  // ----- 4. Resumo ao final -----
  window.gstoreDiagnosticoResumo = function () {
    console.log('--- RESUMO DO DIAGNÓSTICO (ordem cronológica) ---');
    log.forEach(function (e, i) {
      console.log(i + 1 + '. ' + e.passo, e.dados);
    });
    var ultimoEnvio = log.filter(function (e) { return e.passo.indexOf('AJAX ENVIADO') !== -1; }).pop();
    var ultimaResposta = log.filter(function (e) { return e.passo.indexOf('AJAX RESPOSTA') !== -1; }).pop();
    var ultimoDom = log.filter(function (e) { return e.passo.indexOf('DOM ATUALIZADO') !== -1; }).pop();
    console.log('--- CAUSA PROVÁVEL ---');
    if (ultimaResposta && ultimoDom && ultimaResposta.dados.payment_method_title === ultimoDom.dados.valor_exibido) {
      console.log('O valor exibido em "Ver detalhes" veio da RESPOSTA do backend (payment_method_title).');
      console.log('Backend retornou:', ultimaResposta.dados);
      if (ultimoEnvio) {
        console.log('No momento do request foi enviado payment_method:', ultimoEnvio.dados.payment_method_enviado);
        if (ultimoEnvio.dados.payment_method_enviado !== 'blu_pix') {
          console.log('→ CAUSA: o front enviou "' + ultimoEnvio.dados.payment_method_enviado + '" em vez de blu_pix (ex.: DOM já tinha Cartão por fragmento do WooCommerce, ou loadCartSummary rodou antes do clique em PIX).');
        }
      }
    } else {
      console.log('Use os logs acima para ver: 1) o que foi enviado no AJAX, 2) o que o backend devolveu, 3) quando o DOM foi preenchido e com qual valor.');
    }
    return log;
  };

  add('DIAGNÓSTICO INICIADO', {
    instrucoes: 'Selecione PIX, clique em "Ver detalhes". Depois digite gstoreDiagnosticoResumo() e Enter para ver o resumo.'
  });
})();

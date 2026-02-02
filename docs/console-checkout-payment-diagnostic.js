/**
 * Script de DIAGNÓSTICO para colar no Console (F12 → Console) na página do checkout.
 * Analisa por que "Ver detalhes" mostra Cartão mesmo com PIX selecionado.
 *
 * Uso: copie todo o conteúdo, cole no Console e pressione Enter.
 * Depois: selecione PIX, clique em "Ver detalhes" e digite gstoreDiagnosticoResumo()
 */
(function () {
  window.__gstoreDiagLog = window.__gstoreDiagLog || [];

  window.gstoreDiagnosticoResumo = function () {
    var log = window.__gstoreDiagLog;
    console.log('%c========== RESUMO DO DIAGNÓSTICO ==========', 'font-weight:bold; font-size:12px');
    console.log('Ordem cronológica dos eventos:\n');
    log.forEach(function (e, i) {
      console.log('%c' + (i + 1) + '. ' + e.passo + ' (' + e.hora + ')', 'color:#06c');
      console.log('   ', e.dados);
    });

    var envios = log.filter(function (e) { return e.passo.indexOf('AJAX ENVIADO') !== -1; });
    var respostas = log.filter(function (e) { return e.passo.indexOf('AJAX RESPOSTA') !== -1; });
    var domUpdates = log.filter(function (e) { return e.passo.indexOf('DOM ATUALIZADO') !== -1; });
    var ultimoEnvio = envios[envios.length - 1];
    var ultimaResposta = respostas[respostas.length - 1];
    var ultimoDom = domUpdates[domUpdates.length - 1];

    console.log('\n%c--- O QUE ESTÁ ACONTECENDO (em português claro) ---', 'font-weight:bold');
    if (ultimoEnvio) {
      var enviado = ultimoEnvio.dados.payment_method_enviado;
      var esperado = 'blu_pix (quando você escolheu PIX)';
      console.log('1. No momento do request do resumo do carrinho:');
      console.log('   • Valor ENVIADO no request:', enviado);
      console.log('   • Valor ESPERADO (se você clicou em PIX):', esperado);
      if (ultimoEnvio.dados.__gstorePaymentMethod !== undefined) {
        console.log('   • Variável do fix (__gstorePaymentMethod):', ultimoEnvio.dados.__gstorePaymentMethod || '(vazio)');
      }
      console.log('   • Radio marcado no DOM na hora do request:', ultimoEnvio.dados.momento_dom);
    }
    if (ultimaResposta) {
      console.log('2. O que o BACKEND devolveu:');
      console.log('   • payment_method:', ultimaResposta.dados.payment_method);
      console.log('   • payment_method_title (texto que aparece em "Ver detalhes"):', ultimaResposta.dados.payment_method_title);
    }
    if (ultimoDom) {
      console.log('3. O que foi ESCRITO na tela (linha Pagamento em "Ver detalhes"):');
      console.log('   • valor_exibido:', ultimoDom.dados.valor_exibido);
      console.log('   • radio marcado nesse momento:', ultimoDom.dados.momento_radio);
    }

    console.log('\n%c--- POR QUE DEU ERRADO ---', 'font-weight:bold');
    if (ultimoEnvio && ultimoEnvio.dados.payment_method_enviado !== 'blu_pix' && ultimaResposta && ultimaResposta.dados.payment_method_title === 'Cartão') {
      console.log('• O front enviou "' + ultimoEnvio.dados.payment_method_enviado + '" em vez de "blu_pix".');
      console.log('• O backend só repete o que recebe: devolveu "Cartão" porque recebeu blu_checkout.');
      console.log('• Possíveis causas:');
      console.log('  - O script do tema (checkout-steps.js) está em cache e não envia o método correto.');
      console.log('  - O fragmento do WooCommerce substitui o bloco de pagamento e o radio volta para Cartão antes do request.');
      console.log('  - O fix inline (functions.php) não está rodando ou __gstorePaymentMethod não foi definido no clique em PIX.');
    } else if (ultimaResposta && ultimaResposta.dados.payment_method_title === 'Pix') {
      console.log('• Tudo certo: o backend devolveu Pix e "Ver detalhes" deve mostrar Pix.');
    } else {
      console.log('• Use os eventos acima para ver em que ordem o request foi enviado, o que o backend retornou e o que foi escrito no DOM.');
    }

    console.log('\n%c--- CHECKLIST RÁPIDO ---', 'font-weight:bold');
    console.log('| Esperado (PIX selecionado) | Seu caso (último request) |');
    console.log('| payment_method enviado: blu_pix |', (ultimoEnvio && ultimoEnvio.dados.payment_method_enviado) || '?', '|');
    console.log('| payment_method_title: Pix |', (ultimaResposta && ultimaResposta.dados.payment_method_title) || '?', '|');
    console.log('| valor exibido em "Ver detalhes": Pix |', (ultimoDom && ultimoDom.dados.valor_exibido) || '?', '|');

    return log;
  };

  if (typeof jQuery === 'undefined') {
    console.warn('[Gstore Diagnóstico] jQuery não encontrado. Cole o script na página do checkout.');
    return;
  }
  var $ = jQuery;
  var log = window.__gstoreDiagLog;

  function add(step, data) {
    var entry = { passo: step, hora: new Date().toISOString().slice(11, 23), dados: data };
    log.push(entry);
    console.log('[Gstore Diag]', step, data);
  }

  // ----- 1. Interceptar AJAX do resumo do carrinho -----
  var ajax = $.ajax;
  $.ajax = function (opt) {
    var data = opt && opt.data;
    var isCartSummary = (typeof data === 'object' && data && data.action === 'gstore_get_cart_summary') ||
      (typeof data === 'string' && data.indexOf('gstore_get_cart_summary') !== -1);

    if (isCartSummary) {
      var paymentSent = null;
      if (typeof data === 'object' && data.payment_method !== undefined) {
        paymentSent = data.payment_method;
      } else if (typeof data === 'string') {
        var match = data.match(/payment_method=([^&]*)/);
        paymentSent = match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : '(não encontrado)';
      }
      add('AJAX ENVIADO (resumo carrinho)', {
        payment_method_enviado: paymentSent,
        momento_dom: $('input[name="payment_method"]:checked').val() || '(nenhum)',
        __gstorePaymentMethod: window.__gstorePaymentMethod !== undefined ? window.__gstorePaymentMethod : '(não definido)'
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
    add('USUÁRIO TROCOU MÉTODO (evento change)', {
      novo_valor: $(this).val(),
      label: $(this).val() === 'blu_pix' ? 'Pix' : $(this).val() === 'blu_checkout' ? 'Cartão' : $(this).val()
    });
  });

  add('DIAGNÓSTICO INICIADO', {
    instrucoes: 'Selecione PIX, clique em "Ver detalhes". Depois digite gstoreDiagnosticoResumo() e Enter.'
  });
})();

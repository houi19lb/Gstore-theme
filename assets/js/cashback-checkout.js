(function ($) {
  'use strict';

  const config = window.gstoreCashback;
  if (!config || !config.quote) return;

  let quote = config.quote;
  let updateTimer = null;
  let refreshTimer = null;
  let saving = false;

  function formatCoins(value) {
    return new Intl.NumberFormat('pt-BR').format(Math.max(0, Number(value) || 0));
  }

  function formatMoney(cents) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((Number(cents) || 0) / 100);
  }

  function ensureCard() {
    const $slot = $('[data-step="payment"] .Gstore-checkout-step__payment-container').first();
    if (!$slot.length) return null;
    let $card = $slot.find('.gstore-cashback-checkout').first();
    if (!$card.length) {
      $card = $('<section class="gstore-cashback-checkout" aria-labelledby="gstore-cashback-checkout-title">' +
        '<div class="gstore-cashback-checkout__heading"><span class="gstore-cashback-checkout__icon" aria-hidden="true">✦</span><div><h3 id="gstore-cashback-checkout-title">Usar minhas moedas</h3><p>Use seu saldo para reduzir o valor desta compra.</p></div></div>' +
        '<div class="gstore-cashback-checkout__body"></div>' +
        '<p class="gstore-cashback-checkout__message" role="status" aria-live="polite"></p>' +
        '</section>');
      $slot.find('.Gstore-checkout-step__order-review-slot').before($card);
    }
    const $form = $('form.checkout').first();
    if ($form.length && !$form.find('[name="gstore_cashback_coins"]').length) {
      $form.append($('<input>', { type: 'hidden', name: 'gstore_cashback_coins', value: 0 }));
    }
    return $card;
  }

  function render() {
    const $card = ensureCard();
    if (!$card) return;
    const $body = $card.find('.gstore-cashback-checkout__body');
    if (!quote.loggedIn) {
      $body.empty().append($('<p>').text('Entre na sua conta para usar moedas nesta compra.'))
        .append($('<a>', { href: config.loginUrl, class: 'gstore-cashback-checkout__link', text: 'Entrar na minha conta' }));
      return;
    }
    const available = Math.max(0, Number(quote.availableCoins) || 0);
    const maximum = Math.max(0, Number(quote.maximumCoins) || 0);
    const applied = Math.max(0, Number(quote.appliedCoins) || 0);
    $('form.checkout [name="gstore_cashback_coins"]').val(applied);
    if (!available) {
      $body.empty().append($('<p>').text('Você ainda não tem moedas disponíveis. As próximas compras elegíveis poderão gerar saldo.'));
      return;
    }
    if (!maximum) {
      $body.empty().append($('<p>').text('Suas moedas não podem ser usadas nos produtos desta compra.'));
      return;
    }
    $body.html('<div class="gstore-cashback-checkout__balance"></div>' +
      '<label class="gstore-cashback-checkout__toggle"><input type="checkbox" data-cashback-toggle><span>Usar moedas nesta compra</span></label>' +
      '<div class="gstore-cashback-checkout__control"><label for="gstore-cashback-coins">Quantidade de moedas</label><input id="gstore-cashback-coins" type="number" inputmode="numeric" min="1" step="1" data-cashback-amount><small data-cashback-limit></small></div>' +
      '<p class="gstore-cashback-checkout__discount"></p>');
    $body.find('.gstore-cashback-checkout__balance').text('Seu saldo: ' + formatCoins(available) + ' moedas');
    $body.find('[data-cashback-toggle]').prop('checked', applied > 0).prop('disabled', saving);
    $body.find('[data-cashback-amount]').attr('max', maximum).val(applied || maximum).prop('disabled', !applied || saving);
    $body.find('[data-cashback-limit]').text('Até ' + formatCoins(maximum) + ' moedas nesta compra.');
    $body.find('.gstore-cashback-checkout__discount').text(applied ? 'Desconto aplicado: ' + formatMoney(quote.discountCents) : 'Nenhuma moeda aplicada.');
  }

  function setMessage(message) {
    const $card = ensureCard();
    if ($card) $card.find('.gstore-cashback-checkout__message').text(message || '');
  }

  function send(action, coins) {
    return $.ajax({
      url: config.ajaxUrl,
      type: 'POST',
      dataType: 'json',
      data: { action: action, nonce: config.nonce, coins: coins }
    });
  }

  function setCoins(coins) {
    if (saving) return;
    saving = true;
    setMessage('Atualizando moedas...');
    send('gstore_cashback_set_coins', coins).done(function (response) {
      if (!response || !response.success) {
        setMessage('Não foi possível aplicar as moedas. Confira seu saldo.');
        return;
      }
      quote = response.data;
      render();
      setMessage('Moedas atualizadas no pedido.');
      $(document.body).trigger('update_checkout');
    }).fail(function (xhr) {
      const message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
      setMessage(message || 'Não foi possível aplicar as moedas. Tente novamente.');
    }).always(function () {
      saving = false;
      render();
      refreshQuote();
    });
  }

  function refreshQuote() {
    if (saving || !quote.loggedIn) return;
    send('gstore_cashback_quote', 0).done(function (response) {
      if (response && response.success) {
        quote = response.data;
        render();
      }
    });
  }

  $(document).on('change', '[data-cashback-toggle]', function () {
    setCoins(this.checked ? Number(quote.maximumCoins) || 0 : 0);
  });
  $(document).on('input', '[data-cashback-amount]', function () {
    const coins = Math.max(0, Math.min(Number(this.value) || 0, Number(quote.maximumCoins) || 0));
    clearTimeout(updateTimer);
    updateTimer = setTimeout(function () { setCoins(coins); }, 350);
  });
  $(document.body).on('updated_checkout gstore_checkout_step_changed', function () {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(function () { render(); refreshQuote(); }, 150);
  });
  $(function () { setTimeout(function () { render(); refreshQuote(); }, 250); });
})(jQuery);

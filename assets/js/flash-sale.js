(function () {
  function getDismissStorageKey(card) {
    var campaignKey = card.getAttribute('data-gstore-flash-sale-key');
    return campaignKey ? 'gstore_flash_sale_dismissed:' + campaignKey : '';
  }

  function restoreFloatingCards() {
    document.querySelectorAll('.gstore-flash-sale-floating').forEach(function (card) {
      var storageKey = getDismissStorageKey(card);
      try {
        if (storageKey && window.sessionStorage.getItem(storageKey) === '1') {
          card.remove();
          return;
        }
      } catch (error) {
        // O card continua utilizável quando o navegador bloqueia o armazenamento.
      }
      card.hidden = false;
    });
  }

  function parseDate(value) {
    if (!value) return NaN;
    return new Date(value.replace(' ', 'T')).getTime();
  }

  function updateMobileTimerUnits(element, values) {
    var clock = element.closest('[data-gstore-flash-sale-clock], .gstore-flash-sale-clock');
    if (!clock) return;

    clock.classList.toggle('gstore-flash-sale-clock--has-days', values.days > 0);
    Object.keys(values).forEach(function (unit) {
      var unitElement = clock.querySelector('[data-gstore-flash-sale-mobile-countdown="' + unit + '"]');
      if (unitElement) unitElement.textContent = pad(values[unit]);
    });
  }

  function updateTimers() {
    document.querySelectorAll('[data-gstore-flash-sale-end]').forEach(function (element) {
      var end = parseDate(element.getAttribute('data-gstore-flash-sale-end'));
      var seconds = Math.max(0, Math.floor((end - Date.now()) / 1000));
      var days = Math.floor(seconds / 86400);
      var hours = Math.floor((seconds % 86400) / 3600);
      var minutes = Math.floor((seconds % 3600) / 60);
      var remainingSeconds = seconds % 60;

      updateMobileTimerUnits(element, {
        days: days,
        hours: hours,
        minutes: minutes,
        seconds: remainingSeconds
      });

      if (days > 0) {
        element.textContent = [pad(days) + 'd', pad(hours) + 'h', pad(minutes) + 'm'].join(' ');
        return;
      }

      element.textContent = [hours, minutes, remainingSeconds].map(function (part) {
        return String(part).padStart(2, '0');
      }).join(':');
    });
  }

  function fitProductNames() {
    document.querySelectorAll('[data-gstore-flash-sale-name]').forEach(function (element) {
      var fullName = element.getAttribute('data-gstore-flash-sale-name') || '';
      var words = fullName.trim().split(/\s+/);

      element.textContent = fullName;
      while (words.length && element.scrollWidth > element.clientWidth) {
        words.pop();
        element.textContent = words.join(' ');
      }
    });
  }

  function pad(value) {
    return String(Math.max(0, value)).padStart(2, '0');
  }

  function updateUpcomingFlashSales() {
    document.querySelectorAll('[data-gstore-flash-sale-upcoming]').forEach(function (banner) {
      var start = parseDate(banner.getAttribute('data-gstore-flash-sale-start'));
      var announced = parseDate(banner.getAttribute('data-gstore-flash-sale-announced'));
      var remaining = Math.max(0, Math.floor((start - Date.now()) / 1000));

      if (!Number.isFinite(start)) return;

      if (remaining <= 0) {
        window.location.reload();
        return;
      }

      var values = {
        days: Math.floor(remaining / 86400),
        hours: Math.floor((remaining % 86400) / 3600),
        minutes: Math.floor((remaining % 3600) / 60),
        seconds: remaining % 60
      };

      Object.keys(values).forEach(function (unit) {
        var element = banner.querySelector('[data-gstore-flash-sale-countdown="' + unit + '"]');
        if (element) element.textContent = pad(values[unit]);
      });

      var percentage = 0;
      if (Number.isFinite(announced) && announced < start) {
        percentage = Math.min(100, Math.max(0, ((Date.now() - announced) / (start - announced)) * 100));
      }

      var progress = banner.querySelector('.gstore-flash-sale-upcoming__progress');
      if (progress) {
        progress.setAttribute('aria-valuenow', String(Math.round(percentage)));
        progress.style.setProperty('--gstore-flash-sale-progress', percentage + '%');
      }
    });
  }

  document.addEventListener('click', function (event) {
    var close = event.target.closest('[data-gstore-flash-sale-close]');
    if (!close) return;
    var card = close.closest('.gstore-flash-sale-floating');
    if (!card) return;
    var storageKey = getDismissStorageKey(card);
    try {
      if (storageKey) window.sessionStorage.setItem(storageKey, '1');
    } catch (error) {
      // Mesmo sem armazenamento, o botão deve fechar a oferta nesta página.
    }
    card.remove();
  });

  // O wp_footer imprime o cartão depois dos scripts; aguarde o HTML completo.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      restoreFloatingCards();
      updateTimers();
      fitProductNames();
    }, { once: true });
  } else {
    restoreFloatingCards();
  }
  window.addEventListener('pageshow', restoreFloatingCards);

  updateTimers();
  updateUpcomingFlashSales();
  fitProductNames();
  window.setInterval(function () {
    updateTimers();
    updateUpcomingFlashSales();
  }, 1000);
  window.addEventListener('resize', fitProductNames);
}());

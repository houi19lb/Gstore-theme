(function () {
  function updateTimers() {
    document.querySelectorAll('[data-gstore-flash-sale-end]').forEach(function (element) {
      var end = new Date(element.getAttribute('data-gstore-flash-sale-end').replace(' ', 'T')).getTime();
      var seconds = Math.max(0, Math.floor((end - Date.now()) / 1000));
      var hours = Math.floor(seconds / 3600);
      var minutes = Math.floor((seconds % 3600) / 60);
      var remainingSeconds = seconds % 60;
      element.textContent = [hours, minutes, remainingSeconds].map(function (part) {
        return String(part).padStart(2, '0');
      }).join(':');
    });
  }

  document.addEventListener('click', function (event) {
    var close = event.target.closest('[data-gstore-flash-sale-close]');
    if (!close) return;
    var card = close.closest('.gstore-flash-sale-floating');
    if (card) card.remove();
  });

  updateTimers();
  window.setInterval(updateTimers, 1000);
}());

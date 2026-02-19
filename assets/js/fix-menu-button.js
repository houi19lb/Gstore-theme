/**
 * Script de correção IMEDIATA do botão MENU do header.
 * Cole no Console do navegador (F12 > Console) e pressione Enter.
 *
 * Corrige o botão hamburger E a animação para X quando o menu lateral abre.
 */
(function() {
  'use strict';

  var log = function(msg, type) {
    var styles = {
      info:  'color:#2196F3;font-weight:bold',
      ok:    'color:#4CAF50;font-weight:bold',
      warn:  'color:#FF9800;font-weight:bold',
      error: 'color:#f44336;font-weight:bold'
    };
    console.log('%c[GStore Fix] ' + msg, styles[type] || styles.info);
  };

  log('Aplicando correção do botão MENU + animação X...', 'info');

  var btn = document.querySelector('.Gstore-header__menu-toggle');
  if (!btn) {
    log('Botão .Gstore-header__menu-toggle não encontrado.', 'error');
    return;
  }

  var fixCSS = document.createElement('style');
  fixCSS.id = 'gstore-menu-fix';
  fixCSS.textContent = [
    '/* === Estado normal (hamburger) === */',

    '.Gstore-header__menu-toggle {',
    '  display: flex !important;',
    '  flex-direction: row !important;',
    '  align-items: center !important;',
    '  justify-content: center !important;',
    '  gap: 6px !important;',
    '  height: 34px;',
    '  min-width: 72px;',
    '  padding: 0 8px;',
    '}',

    '.Gstore-header__menu-toggle .Gstore-header__menu-icon {',
    '  display: inline-flex !important;',
    '  flex-direction: column !important;',
    '  justify-content: center !important;',
    '  gap: 3px !important;',
    '  width: auto !important;',
    '  height: auto !important;',
    '  background-color: transparent !important;',
    '}',

    '.Gstore-header__menu-toggle .Gstore-header__menu-line {',
    '  display: block !important;',
    '  width: 14px !important;',
    '  height: 3px !important;',
    '  border-radius: 2px;',
    '  background-color: var(--gstore-color-accent, #ff5c00) !important;',
    '  opacity: 0.85;',
    '  transition: transform 0.3s ease, opacity 0.3s ease;',
    '  transform-origin: center;',
    '}',

    '.Gstore-header__menu-toggle .Gstore-header__menu-text {',
    '  font-size: 0.9375rem !important;',
    '  font-weight: 700 !important;',
    '  letter-spacing: 0.05em;',
    '  line-height: 1 !important;',
    '  text-transform: uppercase;',
    '  white-space: nowrap;',
    '  color: var(--gstore-color-text-light, #fff) !important;',
    '  width: auto !important;',
    '  height: auto !important;',
    '  background-color: transparent !important;',
    '}',

    '',
    '/* === Estado ativo (X) === */',

    '.Gstore-header__menu-toggle.is-active .Gstore-header__menu-icon {',
    '  display: inline-flex !important;',
    '  flex-direction: column !important;',
    '  gap: 3px !important;',
    '  width: auto !important;',
    '  height: auto !important;',
    '  background-color: transparent !important;',
    '}',

    '.Gstore-header__menu-toggle.is-active .Gstore-header__menu-line {',
    '  width: 14px !important;',
    '  height: 3px !important;',
    '  background-color: var(--gstore-color-accent, #ff5c00) !important;',
    '}',

    '.Gstore-header__menu-toggle.is-active .Gstore-header__menu-line:nth-child(1) {',
    '  transform: translateY(5px) rotate(45deg) !important;',
    '}',

    '.Gstore-header__menu-toggle.is-active .Gstore-header__menu-line:nth-child(2) {',
    '  opacity: 0 !important;',
    '}',

    '.Gstore-header__menu-toggle.is-active .Gstore-header__menu-line:nth-child(3) {',
    '  transform: translateY(-5px) rotate(-45deg) !important;',
    '}',

    '.Gstore-header__menu-toggle.is-active .Gstore-header__menu-text {',
    '  width: auto !important;',
    '  height: auto !important;',
    '  background-color: transparent !important;',
    '  color: var(--gstore-color-text-light, #fff) !important;',
    '}'
  ].join('\n');

  var old = document.getElementById('gstore-menu-fix');
  if (old) old.remove();
  document.head.appendChild(fixCSS);

  log('CSS injetado com sucesso.', 'ok');

  // Verifica estado atual
  var cs = window.getComputedStyle(btn);
  if (cs.flexDirection === 'row') {
    log('flex-direction: row - OK', 'ok');
  } else {
    log('flex-direction: ' + cs.flexDirection + ' - PROBLEMA', 'error');
  }

  var iconEl = btn.querySelector('.Gstore-header__menu-icon');
  if (iconEl) {
    var ics = window.getComputedStyle(iconEl);
    if (parseInt(ics.height) > 5) {
      log('menu-icon height: ' + ics.height + ' - OK', 'ok');
    } else {
      log('menu-icon height: ' + ics.height + ' - PROBLEMA (deveria ser ~15px)', 'error');
    }
  }

  var lineEls = btn.querySelectorAll('.Gstore-header__menu-line');
  if (lineEls.length === 3) {
    var lcs = window.getComputedStyle(lineEls[0]);
    log('menu-line: ' + lcs.width + ' x ' + lcs.height + ', bg: ' + lcs.backgroundColor, 'ok');
  }

  log('Correção aplicada! Teste abrir/fechar o menu lateral.', 'ok');
  log('Para correção permanente: atualize o tema e limpe o cache do servidor.', 'info');
})();

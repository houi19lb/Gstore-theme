/**
 * Script de correção IMEDIATA do botão MENU do header.
 * Cole no Console do navegador (F12 > Console) e pressione Enter.
 *
 * Corrige:
 * 1. Estado normal (hamburger) - flex-direction, dimensões, cores
 * 2. Estado ativo (X) - anula transforms e opacity herdados de CSS
 *    cacheado antigo que usa "span:nth-child(N)" nos filhos diretos
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

  log('Aplicando correção completa...', 'info');

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

    '.Gstore-header__menu-toggle span.Gstore-header__menu-icon {',
    '  display: inline-flex !important;',
    '  flex-direction: column !important;',
    '  justify-content: center !important;',
    '  gap: 3px !important;',
    '  width: auto !important;',
    '  height: auto !important;',
    '  background-color: transparent !important;',
    '  transform: none !important;',
    '  opacity: 1 !important;',
    '}',

    '.Gstore-header__menu-toggle span.Gstore-header__menu-line {',
    '  display: block !important;',
    '  width: 14px !important;',
    '  height: 3px !important;',
    '  border-radius: 2px;',
    '  background-color: var(--gstore-color-accent, #b5a642) !important;',
    '  opacity: 0.85;',
    '  transition: transform 0.3s ease, opacity 0.3s ease;',
    '  transform-origin: center;',
    '  transform: none;',
    '}',

    '.Gstore-header__menu-toggle span.Gstore-header__menu-text {',
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
    '  transform: none !important;',
    '  opacity: 1 !important;',
    '}',

    '',
    '/* === Estado ativo (X) === */',

    '.Gstore-header__menu-toggle.is-active span.Gstore-header__menu-icon {',
    '  display: inline-flex !important;',
    '  flex-direction: column !important;',
    '  gap: 3px !important;',
    '  width: auto !important;',
    '  height: auto !important;',
    '  background-color: transparent !important;',
    '  transform: none !important;',
    '  opacity: 1 !important;',
    '}',

    '.Gstore-header__menu-toggle.is-active span.Gstore-header__menu-line {',
    '  width: 14px !important;',
    '  height: 3px !important;',
    '  background-color: var(--gstore-color-accent, #b5a642) !important;',
    '}',

    '.Gstore-header__menu-toggle.is-active span.Gstore-header__menu-line:nth-child(1) {',
    '  transform: translateY(5px) rotate(45deg) !important;',
    '  opacity: 0.85 !important;',
    '}',

    '.Gstore-header__menu-toggle.is-active span.Gstore-header__menu-line:nth-child(2) {',
    '  opacity: 0 !important;',
    '  transform: none !important;',
    '}',

    '.Gstore-header__menu-toggle.is-active span.Gstore-header__menu-line:nth-child(3) {',
    '  transform: translateY(-5px) rotate(-45deg) !important;',
    '  opacity: 0.85 !important;',
    '}',

    '.Gstore-header__menu-toggle.is-active span.Gstore-header__menu-text {',
    '  width: auto !important;',
    '  height: auto !important;',
    '  background-color: transparent !important;',
    '  color: var(--gstore-color-text-light, #fff) !important;',
    '  transform: none !important;',
    '  opacity: 1 !important;',
    '}'
  ].join('\n');

  var old = document.getElementById('gstore-menu-fix');
  if (old) old.remove();
  document.head.appendChild(fixCSS);

  log('Correção aplicada! Teste abrir/fechar o menu lateral.', 'ok');
  log('Para correção permanente: atualize o tema e limpe o cache do servidor.', 'info');
})();

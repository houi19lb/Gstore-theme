/**
 * Script de correção IMEDIATA do botão MENU do header.
 * Cole no Console do navegador (F12 > Console) e pressione Enter.
 *
 * Problema: O style.css?ver=1.3 cacheado contém regras legadas com o seletor
 * genérico ".Gstore-header__menu-toggle span" (especificidade 0,1,1) que
 * sobrescreve os seletores de classe corretos (0,1,0).
 *
 * Este script injeta CSS com especificidade elevada para anular as regras
 * cacheadas e restaurar o visual correto do botão.
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

  log('Aplicando correção do botão MENU...', 'info');

  var btn = document.querySelector('.Gstore-header__menu-toggle');
  if (!btn) {
    log('Botão .Gstore-header__menu-toggle não encontrado.', 'error');
    return;
  }

  // Injeta CSS com especificidade alta para vencer ".Gstore-header__menu-toggle span" (0,1,1)
  // Novos seletores: ".Gstore-header__menu-toggle .Gstore-header__menu-*" (0,2,0)
  var fixCSS = document.createElement('style');
  fixCSS.id = 'gstore-menu-fix';
  fixCSS.textContent = [
    '/* Fix: anula regras legadas cacheadas de .Gstore-header__menu-toggle span */',

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
    '}'
  ].join('\n');

  // Remove fix anterior se existir
  var old = document.getElementById('gstore-menu-fix');
  if (old) old.remove();

  document.head.appendChild(fixCSS);

  // Verifica resultado
  var cs = window.getComputedStyle(btn);
  var iconEl = btn.querySelector('.Gstore-header__menu-icon');
  var textEl = btn.querySelector('.Gstore-header__menu-text');
  var linesEl = btn.querySelectorAll('.Gstore-header__menu-line');

  var ok = true;

  if (cs.flexDirection !== 'row') {
    log('AVISO: flex-direction ainda é ' + cs.flexDirection, 'error');
    ok = false;
  }

  if (iconEl) {
    var iconCs = window.getComputedStyle(iconEl);
    if (iconCs.display !== 'inline-flex' && iconCs.display !== 'flex') {
      log('AVISO: .menu-icon display = ' + iconCs.display + ' (esperado: inline-flex)', 'error');
      ok = false;
    }
    if (parseInt(iconCs.height) < 5) {
      log('AVISO: .menu-icon height = ' + iconCs.height + ' (esperado: ~15px)', 'error');
      ok = false;
    }
  }

  if (textEl) {
    var textCs = window.getComputedStyle(textEl);
    if (parseInt(textCs.height) < 5) {
      log('AVISO: .menu-text height = ' + textCs.height + ' (esperado: ~15px)', 'error');
      ok = false;
    }
  }

  for (var i = 0; i < linesEl.length; i++) {
    var lineCs = window.getComputedStyle(linesEl[i]);
    if (parseInt(lineCs.width) > 20) {
      log('AVISO: .menu-line[' + (i+1) + '] width = ' + lineCs.width + ' (esperado: 14px)', 'error');
      ok = false;
    }
  }

  if (ok) {
    log('Correção aplicada com sucesso! O botão MENU está correto agora.', 'ok');
    log('Para correção permanente: atualize o tema no servidor e limpe o cache.', 'info');
  } else {
    log('Correção parcial. Alguns estilos ainda estão sendo sobrescritos.', 'warn');
    log('Tente limpar o cache do servidor/CDN e recarregar a página.', 'warn');
  }
})();

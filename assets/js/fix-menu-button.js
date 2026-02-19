/**
 * Script de diagnóstico PROFUNDO do botão MENU - Estado NORMAL e ATIVO.
 * Cole no Console do navegador (F12 > Console) e pressione Enter.
 *
 * 1. Captura estilos no estado normal (hamburger fechado)
 * 2. Simula clique para abrir o menu (estado is-active)
 * 3. Captura estilos no estado ativo (X aberto)
 * 4. Mostra tudo lado a lado para comparação entre sites
 */
(function() {
  'use strict';

  var log = function(msg, type) {
    var styles = {
      info:  'color:#2196F3;font-weight:bold',
      ok:    'color:#4CAF50;font-weight:bold',
      warn:  'color:#FF9800;font-weight:bold',
      error: 'color:#f44336;font-weight:bold',
      data:  'color:#9C27B0'
    };
    console.log('%c[Diag] ' + msg, styles[type] || styles.info);
  };

  function captureAll(label) {
    log('========== ' + label + ' ==========', 'info');

    var btn = document.querySelector('.Gstore-header__menu-toggle');
    if (!btn) { log('Botão não encontrado!', 'error'); return; }

    var icon = btn.querySelector('.Gstore-header__menu-icon');
    var lines = btn.querySelectorAll('.Gstore-header__menu-line');
    var textSpan = btn.querySelector('.Gstore-header__menu-text');

    function dump(el, name) {
      if (!el) { log(name + ': NÃO EXISTE', 'error'); return; }
      var cs = window.getComputedStyle(el);
      var r = el.getBoundingClientRect();
      log(name, 'warn');
      log('  classes: ' + el.className, 'data');
      log('  display: ' + cs.display + ' | visibility: ' + cs.visibility + ' | opacity: ' + cs.opacity, 'data');
      log('  flexDir: ' + cs.flexDirection + ' | alignItems: ' + cs.alignItems + ' | justifyContent: ' + cs.justifyContent, 'data');
      log('  width: ' + cs.width + ' | height: ' + cs.height + ' | gap: ' + cs.gap, 'data');
      log('  padding: ' + cs.padding + ' | margin: ' + cs.margin, 'data');
      log('  bgColor: ' + cs.backgroundColor + ' | color: ' + cs.color, 'data');
      log('  transform: ' + cs.transform, 'data');
      log('  rect: ' + Math.round(r.left) + ',' + Math.round(r.top) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height), 'data');
    }

    dump(btn, 'BUTTON');
    dump(icon, 'ICON (.Gstore-header__menu-icon)');
    for (var i = 0; i < lines.length; i++) {
      dump(lines[i], 'LINE[' + (i+1) + '] (.Gstore-header__menu-line)');
    }
    dump(textSpan, 'TEXT (.Gstore-header__menu-text)');

    // Capturar regras CSS que afetam o botão no estado atual
    log('--- CSS Rules com "is-active" ou "menu-toggle" ---', 'info');
    try {
      var sheets = document.styleSheets;
      for (var s = 0; s < sheets.length; s++) {
        try {
          var rules = sheets[s].cssRules || sheets[s].rules;
          if (!rules) continue;
          function scanRules(ruleList, mediaCtx) {
            for (var r = 0; r < ruleList.length; r++) {
              var rule = ruleList[r];
              if (rule.type === CSSRule.MEDIA_RULE) {
                var mq = rule.conditionText || rule.media.mediaText;
                if (window.matchMedia(mq).matches) {
                  scanRules(rule.cssRules, '@media(' + mq + ')');
                }
                continue;
              }
              if (!rule.selectorText) continue;
              var sel = rule.selectorText;
              var isRelevant = (sel.indexOf('is-active') !== -1 && sel.indexOf('menu') !== -1) ||
                               (sel.indexOf('menu-toggle') !== -1 && sel.indexOf('span') !== -1);
              if (isRelevant) {
                var src = sheets[s].href ? sheets[s].href.split('/').pop().split('?')[0] : 'inline';
                log('  [' + src + ']' + (mediaCtx ? ' ' + mediaCtx : ''), 'data');
                log('    ' + rule.cssText.substring(0, 250), 'data');
              }
            }
          }
          scanRules(rules, null);
        } catch(e) {}
      }
    } catch(e) {
      log('Erro ao ler stylesheets: ' + e.message, 'error');
    }

    // Verificar inline styles
    log('--- Inline styles ---', 'info');
    log('  btn.style: "' + btn.style.cssText + '"', 'data');
    if (icon) log('  icon.style: "' + icon.style.cssText + '"', 'data');
    for (var j = 0; j < lines.length; j++) {
      log('  line[' + (j+1) + '].style: "' + lines[j].style.cssText + '"', 'data');
    }
    if (textSpan) log('  text.style: "' + textSpan.style.cssText + '"', 'data');
  }

  // Captura no estado atual
  var btn = document.querySelector('.Gstore-header__menu-toggle');
  if (!btn) { log('Botão não encontrado!', 'error'); return; }

  var isOpen = btn.classList.contains('is-active');

  if (isOpen) {
    captureAll('ESTADO ATIVO (menu aberto)');
    log('O menu já está aberto. Feche e rode de novo para capturar ambos os estados.', 'info');
  } else {
    captureAll('ESTADO NORMAL (menu fechado)');

    log('Abrindo menu para capturar estado ativo...', 'info');
    btn.click();

    setTimeout(function() {
      captureAll('ESTADO ATIVO (menu aberto)');

      log('Fechando menu...', 'info');
      btn.click();

      log('======================================', 'info');
      log('PRONTO! Cole os resultados para comparação.', 'ok');
    }, 500);
  }
})();

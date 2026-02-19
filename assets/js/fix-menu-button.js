/**
 * Script de diagnóstico PROFUNDO do botão MENU do header.
 * Cole no Console do navegador (F12 > Console) e pressione Enter.
 *
 * Compara computed styles entre o site "bom" e o "ruim" para
 * encontrar a diferença visual exata.
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
    console.log('%c[GStore Fix] ' + msg, styles[type] || styles.info);
  };

  log('=== DIAGNÓSTICO PROFUNDO DO BOTÃO MENU ===', 'info');

  var btn = document.querySelector('.Gstore-header__menu-toggle');
  if (!btn) { log('Botão NÃO encontrado.', 'error'); return; }

  var icon = btn.querySelector('.Gstore-header__menu-icon');
  var lines = btn.querySelectorAll('.Gstore-header__menu-line');
  var textSpan = btn.querySelector('.Gstore-header__menu-text');

  function getStyles(el, label) {
    if (!el) { log(label + ': ELEMENTO AUSENTE', 'error'); return null; }
    var cs = window.getComputedStyle(el);
    var rect = el.getBoundingClientRect();
    return {
      display: cs.display,
      visibility: cs.visibility,
      opacity: cs.opacity,
      width: cs.width,
      height: cs.height,
      color: cs.color,
      backgroundColor: cs.backgroundColor,
      fontSize: cs.fontSize,
      fontWeight: cs.fontWeight,
      gap: cs.gap,
      flexDirection: cs.flexDirection,
      alignItems: cs.alignItems,
      justifyContent: cs.justifyContent,
      position: cs.position,
      overflow: cs.overflow,
      zIndex: cs.zIndex,
      transform: cs.transform,
      clipPath: cs.clipPath,
      borderRadius: cs.borderRadius,
      padding: cs.padding,
      margin: cs.margin,
      rectTop: Math.round(rect.top),
      rectLeft: Math.round(rect.left),
      rectWidth: Math.round(rect.width),
      rectHeight: Math.round(rect.height)
    };
  }

  function printStyles(styles, label) {
    if (!styles) return;
    log('--- ' + label + ' ---', 'info');
    log('  display: ' + styles.display + ' | visibility: ' + styles.visibility + ' | opacity: ' + styles.opacity, 'data');
    log('  width: ' + styles.width + ' | height: ' + styles.height, 'data');
    log('  backgroundColor: ' + styles.backgroundColor + ' | color: ' + styles.color, 'data');
    log('  position: ' + styles.position + ' | zIndex: ' + styles.zIndex + ' | overflow: ' + styles.overflow, 'data');
    log('  transform: ' + styles.transform + ' | clipPath: ' + styles.clipPath, 'data');
    log('  gap: ' + styles.gap + ' | flexDirection: ' + styles.flexDirection, 'data');
    log('  padding: ' + styles.padding + ' | margin: ' + styles.margin, 'data');
    log('  BoundingRect: top=' + styles.rectTop + ' left=' + styles.rectLeft +
        ' width=' + styles.rectWidth + ' height=' + styles.rectHeight, 'data');
  }

  // Botão
  printStyles(getStyles(btn, 'BUTTON'), 'BUTTON .Gstore-header__menu-toggle');

  // Ícone container
  printStyles(getStyles(icon, 'ICON'), 'SPAN .Gstore-header__menu-icon');

  // Cada linha
  for (var i = 0; i < lines.length; i++) {
    var ls = getStyles(lines[i], 'LINE ' + (i+1));
    printStyles(ls, 'SPAN .Gstore-header__menu-line[' + (i+1) + ']');
  }

  // Texto
  printStyles(getStyles(textSpan, 'TEXT'), 'SPAN .Gstore-header__menu-text');

  // Header containers
  log('=== CONTAINERS DO HEADER ===', 'info');

  var headerShell = document.querySelector('.Gstore-header-shell');
  var headerMain = document.querySelector('.Gstore-header');
  var headerInner = document.querySelector('.Gstore-header__inner');
  var headerContent = document.querySelector('.Gstore-header__content');

  if (headerShell) printStyles(getStyles(headerShell), 'header.Gstore-header-shell');
  if (headerMain) printStyles(getStyles(headerMain), 'div.Gstore-header');
  if (headerInner) printStyles(getStyles(headerInner), 'div.Gstore-header__inner');
  if (headerContent) printStyles(getStyles(headerContent), 'div.Gstore-header__content');

  // Verificar todas as stylesheets que afetam o botão
  log('=== CSS RULES QUE AFETAM O BOTÃO ===', 'info');
  try {
    var sheets = document.styleSheets;
    var matchingRules = [];
    for (var s = 0; s < sheets.length; s++) {
      try {
        var rules = sheets[s].cssRules || sheets[s].rules;
        if (!rules) continue;
        for (var r = 0; r < rules.length; r++) {
          var rule = rules[r];
          if (rule.selectorText && rule.selectorText.indexOf('menu-toggle') !== -1) {
            matchingRules.push({
              selector: rule.selectorText,
              cssText: rule.cssText.substring(0, 200),
              sheet: sheets[s].href || 'inline'
            });
          }
          if (rule.selectorText && rule.selectorText.indexOf('menu-line') !== -1) {
            matchingRules.push({
              selector: rule.selectorText,
              cssText: rule.cssText.substring(0, 200),
              sheet: sheets[s].href || 'inline'
            });
          }
          if (rule.selectorText && rule.selectorText.indexOf('menu-icon') !== -1) {
            matchingRules.push({
              selector: rule.selectorText,
              cssText: rule.cssText.substring(0, 200),
              sheet: sheets[s].href || 'inline'
            });
          }
          if (rule.selectorText && rule.selectorText.indexOf('menu-text') !== -1) {
            matchingRules.push({
              selector: rule.selectorText,
              cssText: rule.cssText.substring(0, 200),
              sheet: sheets[s].href || 'inline'
            });
          }
          // Checa media queries
          if (rule.type === CSSRule.MEDIA_RULE) {
            var mediaRules = rule.cssRules;
            for (var mr = 0; mr < mediaRules.length; mr++) {
              var mRule = mediaRules[mr];
              if (mRule.selectorText &&
                  (mRule.selectorText.indexOf('menu-toggle') !== -1 ||
                   mRule.selectorText.indexOf('menu-line') !== -1 ||
                   mRule.selectorText.indexOf('menu-icon') !== -1 ||
                   mRule.selectorText.indexOf('menu-text') !== -1)) {
                matchingRules.push({
                  selector: mRule.selectorText,
                  cssText: mRule.cssText.substring(0, 200),
                  media: rule.conditionText,
                  sheet: sheets[s].href || 'inline'
                });
              }
            }
          }
        }
      } catch(e) {
        log('  (não foi possível ler: ' + (sheets[s].href || 'inline') + ')', 'warn');
      }
    }
    log('Total de regras CSS encontradas para o menu: ' + matchingRules.length, 'info');
    for (var m = 0; m < matchingRules.length; m++) {
      var mr2 = matchingRules[m];
      var sheetName = mr2.sheet ? mr2.sheet.split('/').pop() : 'inline';
      log('  [' + sheetName + ']' + (mr2.media ? ' @media(' + mr2.media + ')' : ''), 'data');
      log('    ' + mr2.cssText, 'data');
    }
  } catch(e) {
    log('Erro ao inspecionar stylesheets: ' + e.message, 'error');
  }

  // Verificar CSS custom properties (variáveis)
  log('=== CSS VARIABLES ===', 'info');
  var root = document.documentElement;
  var rootStyles = getComputedStyle(root);
  var vars = ['--gstore-color-accent', '--gstore-color-text-light', '--gstore-transition-fast',
              '--gstore-color-bg-dark', '--gstore-header-bg', '--gstore-color-primary'];
  for (var v = 0; v < vars.length; v++) {
    var val = rootStyles.getPropertyValue(vars[v]).trim();
    log('  ' + vars[v] + ': ' + (val || '(não definida)'), val ? 'data' : 'warn');
  }

  // Verificar se header.css está carregado
  log('=== STYLESHEETS CARREGADAS (header) ===', 'info');
  var allLinks = document.querySelectorAll('link[rel="stylesheet"]');
  var headerCSSFound = false;
  for (var l = 0; l < allLinks.length; l++) {
    var href = allLinks[l].href || '';
    if (href.indexOf('header') !== -1 || href.indexOf('Gstore') !== -1 || href.indexOf('gstore') !== -1) {
      log('  ' + href.split('/').slice(-3).join('/'), 'data');
      if (href.indexOf('header') !== -1) headerCSSFound = true;
    }
  }
  if (!headerCSSFound) {
    log('  AVISO: header.css NÃO encontrado nas stylesheets!', 'error');
  }

  log('=== DIAGNÓSTICO CONCLUÍDO ===', 'info');
  log('Cole os resultados acima para comparação entre site bom e ruim.', 'info');
})();

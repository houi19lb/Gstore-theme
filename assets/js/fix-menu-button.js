/**
 * Script de diagnóstico e correção do botão MENU do header.
 * Cole este código inteiro no Console do navegador (F12 > Console) e pressione Enter.
 *
 * O que ele faz:
 *  1. Encontra o botão .Gstore-header__menu-toggle
 *  2. Verifica se a estrutura interna (ícone hamburger) está presente
 *  3. Reconstrói o innerHTML se os spans estiverem faltando
 *  4. Detecta elementos <img> soltos/mal posicionados no header
 *  5. Garante que os estilos inline de fallback estejam aplicados
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

  log('Iniciando diagnóstico do botão MENU...', 'info');

  // --- 1. Localizar o botão ---
  var btn = document.querySelector('.Gstore-header__menu-toggle');
  if (!btn) {
    log('Botão .Gstore-header__menu-toggle NÃO encontrado na página.', 'error');
    return;
  }
  log('Botão encontrado: ' + btn.outerHTML.substring(0, 120) + '...', 'ok');

  // --- 2. Verificar estrutura interna ---
  var icon = btn.querySelector('.Gstore-header__menu-icon');
  var lines = btn.querySelectorAll('.Gstore-header__menu-line');
  var textSpan = btn.querySelector('.Gstore-header__menu-text');

  var needsFix = false;

  if (!icon) {
    log('PROBLEMA: Span .Gstore-header__menu-icon está AUSENTE.', 'error');
    needsFix = true;
  } else {
    log('Span .Gstore-header__menu-icon presente.', 'ok');
  }

  if (lines.length < 3) {
    log('PROBLEMA: Encontradas ' + lines.length + ' linhas do hamburger (esperado: 3).', 'error');
    needsFix = true;
  } else {
    log('3 linhas do hamburger presentes.', 'ok');
  }

  if (!textSpan) {
    log('PROBLEMA: Span .Gstore-header__menu-text está AUSENTE.', 'error');
    needsFix = true;
  } else {
    log('Span .Gstore-header__menu-text presente: "' + textSpan.textContent + '"', 'ok');
  }

  // --- 3. Reconstruir o innerHTML se necessário ---
  if (needsFix) {
    log('Reconstruindo estrutura interna do botão...', 'warn');

    var wasActive = btn.classList.contains('is-active');
    var wasExpanded = btn.getAttribute('aria-expanded');

    btn.innerHTML =
      '<span class="Gstore-header__menu-icon" aria-hidden="true">' +
        '<span class="Gstore-header__menu-line"></span>' +
        '<span class="Gstore-header__menu-line"></span>' +
        '<span class="Gstore-header__menu-line"></span>' +
      '</span>' +
      '<span class="Gstore-header__menu-text">MENU</span>';

    if (wasActive) btn.classList.add('is-active');
    if (wasExpanded) btn.setAttribute('aria-expanded', wasExpanded);

    // Aplica estilos inline de fallback caso o CSS não carregue
    var iconEl = btn.querySelector('.Gstore-header__menu-icon');
    iconEl.style.display = 'inline-flex';
    iconEl.style.flexDirection = 'column';
    iconEl.style.justifyContent = 'center';
    iconEl.style.gap = '3px';

    var lineEls = btn.querySelectorAll('.Gstore-header__menu-line');
    for (var i = 0; i < lineEls.length; i++) {
      lineEls[i].style.display = 'block';
      lineEls[i].style.width = '14px';
      lineEls[i].style.height = '3px';
      lineEls[i].style.borderRadius = '2px';
      lineEls[i].style.backgroundColor = 'var(--gstore-color-accent, #ff5c00)';
      lineEls[i].style.opacity = '0.85';
    }

    var textEl = btn.querySelector('.Gstore-header__menu-text');
    textEl.style.fontSize = '0.9375rem';
    textEl.style.fontWeight = '700';
    textEl.style.letterSpacing = '0.05em';
    textEl.style.textTransform = 'uppercase';
    textEl.style.whiteSpace = 'nowrap';
    textEl.style.color = 'var(--gstore-color-text-light, #fff)';

    log('Estrutura do botão reconstruída com sucesso!', 'ok');
  } else {
    log('Estrutura do botão está correta, nenhuma correção necessária.', 'ok');
  }

  // --- 4. Detectar <img> soltos/mal posicionados no header ---
  log('Verificando elementos <img> no header...', 'info');

  var headerShell = document.querySelector('.Gstore-header-shell') ||
                    document.querySelector('.Gstore-header');
  if (headerShell) {
    var imgs = headerShell.querySelectorAll('img');
    var issues = [];

    for (var j = 0; j < imgs.length; j++) {
      var img = imgs[j];
      var rect = img.getBoundingClientRect();
      var parentLink = img.closest('a');
      var parentLogo = img.closest('.Gstore-header__logo, .wp-block-site-logo');
      var src = img.getAttribute('src') || '';
      var isVisible = rect.width > 0 && rect.height > 0;

      // Verifica se a imagem está fora do container visível do header
      var headerRect = headerShell.getBoundingClientRect();
      var isOutside = rect.bottom < headerRect.top || rect.top > headerRect.bottom ||
                      rect.right < headerRect.left || rect.left > headerRect.right;

      if (!src || src === '') {
        issues.push({ img: img, reason: 'src vazio' });
      } else if (!parentLogo && !parentLink) {
        issues.push({ img: img, reason: 'img solta (sem container logo/link)' });
      } else if (isOutside && isVisible) {
        issues.push({ img: img, reason: 'img visível fora dos limites do header' });
      } else if (!isVisible && !img.hasAttribute('loading')) {
        issues.push({ img: img, reason: 'img invisível sem loading lazy/eager' });
      }
    }

    if (issues.length > 0) {
      log('Encontrados ' + issues.length + ' problema(s) com imagens no header:', 'warn');
      for (var k = 0; k < issues.length; k++) {
        var issue = issues[k];
        log('  → ' + issue.reason + ': ' + (issue.img.src || '(sem src)').substring(0, 80), 'warn');
        log('    Posição: top=' + Math.round(issue.img.getBoundingClientRect().top) +
            'px, left=' + Math.round(issue.img.getBoundingClientRect().left) + 'px', 'warn');

        // Se a imagem está solta (sem container de logo), esconde ela
        if (issue.reason === 'img solta (sem container logo/link)' ||
            issue.reason === 'src vazio') {
          issue.img.style.display = 'none';
          log('    ✗ Imagem ocultada (display:none).', 'warn');
        }
      }
    } else {
      log('Nenhum problema com imagens no header.', 'ok');
    }

    log('Total de imagens no header: ' + imgs.length, 'info');
  } else {
    log('Container do header não encontrado para verificação de imagens.', 'warn');
  }

  // --- 5. Verificar se o drawer mobile existe ---
  var drawer = document.querySelector('.Gstore-mobile-drawer');
  if (!drawer) {
    log('AVISO: Drawer mobile (.Gstore-mobile-drawer) não encontrado.', 'warn');
  } else {
    log('Drawer mobile encontrado.', 'ok');
  }

  log('Diagnóstico concluído.', 'info');
})();

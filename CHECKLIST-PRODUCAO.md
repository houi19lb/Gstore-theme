# 📋 Checklist de Produção - Tema Gstore

Este documento lista todos os itens que precisam ser corrigidos/removidos antes do tema ser enviado para produção.

## 🔴 CRÍTICO - Segurança

### 1. Remover arquivo de diagnóstico
- [x] **Remover** `diagnostic-blu-checkout.php` - Expõe informações sensíveis do sistema
- **Localização:** `themes/gstore/diagnostic-blu-checkout.php`
- **Motivo:** Script de diagnóstico que pode expor dados sensíveis do WordPress e WooCommerce
- **Status:** ✅ Arquivo não encontrado (já foi removido ou nunca existiu)

### 2. Remover arquivos de debug/backup
- [x] **Remover** `inc/class-gstore-blu-payment-gateway.php.broken`
- [x] **Remover** `inc/class-gstore-blu-payment-gateway.php.corrupted-20251120140158`
- [x] **Remover** `inc/class-gstore-blu-payment-gateway-CLEAN.php` (se não estiver em uso)
- [x] **Remover** `inc/class-gstore-blu-payment-gateway-full-backup.php` (se não estiver em uso)
- [x] **Remover** `inc/debug_blocks_class.txt`
- [x] **Remover** `inc/debug_blocks.txt`
- [x] **Remover** `inc/debug_blu.txt`
- [x] **Remover** `inc/debug_flow.txt`
- [x] **Remover** `inc/debug_gateways.txt`
- **Motivo:** Arquivos de debug e backup não devem estar em produção
- **Status:** ✅ Todos os arquivos não foram encontrados (já foram removidos)

### 3. Sanitização de dados
- [x] **Corrigir** `functions.php` linha 2559-2577 - Função `gstore_save_cpf_field()`
  - ✅ Já usa `sanitize_text_field()` corretamente (linha 2568)
  - ✅ Remove caracteres não numéricos com `preg_replace()` (linha 2570)
  - ✅ Valida se o CPF não está vazio antes de salvar (linha 2572)
  - **Status:** ✅ Implementação correta e segura

### 4. Verificação de nonce
- [x] **Adicionar** verificação de nonce na função `gstore_save_cpf_field()`
  - ✅ Verificação de nonce já implementada (linhas 2561-2564)
  - ✅ Usa `wp_verify_nonce()` com o nonce correto do checkout
  - **Status:** ✅ Implementação correta e segura

## 🟡 IMPORTANTE - Limpeza de Código

### 5. Remover logs de console do JavaScript
- [x] **Remover/Comentar** todos os `console.log()` em:
  - `assets/js/checkout-steps.js` - ✅ Todos os `console.log()` já estão comentados
  - `assets/js/checkout-cleanup.js` - ✅ `console.log()` já está comentado (linha 31)
  - `assets/js/mini-cart-fix.js` - ✅ Usa função `debugLog()` condicional baseada em `CONFIG.debug`
- **Status:** ✅ Todos os logs de console estão adequadamente protegidos ou comentados

### 6. Limpar logs de debug do PHP
- [x] **Remover/Ajustar** logs em `functions.php`:
  - ✅ Linha 2475: `error_log()` está condicional com `WP_DEBUG` (dentro de `if ( defined( 'WP_DEBUG' ) && WP_DEBUG )`)
  - ✅ Linha 2519: `error_log()` está condicional com `WP_DEBUG` (dentro de `if ( defined( 'WP_DEBUG' ) && WP_DEBUG )`)
  - ✅ Todas as chamadas de log estão condicionais ou usam `wc_get_logger()` quando disponível
- **Status:** ✅ Todos os logs estão condicionais baseados em `WP_DEBUG`

### 7. Atualizar URLs de exemplo
- [x] **Atualizar** `style.css` linhas 3-5:
  - ✅ Não há URLs de exemplo no `style.css` (arquivo não contém `Theme URI` ou `Author URI`)
  - **Status:** ✅ Não há necessidade de atualização

## 🟢 RECOMENDADO - Boas Práticas

### 8. Criar arquivo README
- [ ] **Criar** `readme.txt` padrão do WordPress com:
  - Nome do tema
  - Versão
  - Requisitos (WordPress, WooCommerce, PHP)
  - Descrição
  - Instruções de instalação
  - Changelog

### 9. Otimização de performance
- [ ] **Avaliar** uso de Font Awesome via CDN externo
  - Atualmente: `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css`
  - Considerar: Hostear localmente ou usar apenas ícones necessários
  - Verificar: GDPR e privacidade de dados

### 10. Documentação
- [ ] **Revisar** documentação existente:
  - `REFATORACAO.md` - Está completo e atualizado?
  - `docs/blu-checkout.md` - Está atualizado?
  - `docs/debug-checkout-blu.md` - Considerar remover ou mover para área restrita

### 11. Testes finais
- [ ] **Testar** em ambiente staging antes de produção:
  - [ ] Checkout completo (end-to-end)
  - [ ] Carrinho
  - [ ] Páginas de produto
  - [ ] Minha conta (login/registro)
  - [ ] Formulários de contato
  - [ ] Responsividade (mobile, tablet, desktop)
  - [ ] Compatibilidade de navegadores (Chrome, Firefox, Safari, Edge)
  - [ ] Performance (PageSpeed Insights)

### 12. Variáveis de ambiente
- [ ] **Verificar** se há dados sensíveis hardcoded:
  - Chaves de API
  - URLs de desenvolvimento
  - Credenciais
  - Mover para constantes do WordPress ou variáveis de ambiente

### 13. Minificação (Opcional)
- [ ] **Considerar** minificar CSS e JS para produção:
  - CSS já está modularizado
  - JS pode ser minificado
  - Considerar usar plugin de otimização (ex: Autoptimize)

### 14. Cache
- [ ] **Verificar** compatibilidade com plugins de cache:
  - WP Super Cache
  - W3 Total Cache
  - WP Rocket
  - Verificar se fragmentos de carrinho funcionam corretamente

## 📝 Resumo por Prioridade

### 🔴 Urgente (Antes de enviar para produção)
1. ✅ Remover `diagnostic-blu-checkout.php` - **CONCLUÍDO**
2. ✅ Remover todos os arquivos `.broken`, `.corrupted`, `.txt` de debug - **CONCLUÍDO**
3. ✅ Corrigir sanitização em `gstore_save_cpf_field()` - **CONCLUÍDO**

### 🟡 Importante (Recomendado antes de produção)
4. ✅ Remover/comentar `console.log()` do JavaScript - **CONCLUÍDO**
5. ✅ Limpar logs de debug do PHP - **CONCLUÍDO**
6. ✅ Atualizar URLs de exemplo no `style.css` - **CONCLUÍDO** (não há URLs de exemplo)

### 🟢 Opcional (Melhorias)
7. Criar `readme.txt`
8. Otimizar Font Awesome
9. Revisar documentação
10. Testes finais completos
11. Verificar dados sensíveis
12. Considerar minificação
13. Verificar compatibilidade com cache

## ✅ Como usar este checklist

1. Marque cada item conforme for completado
2. Após completar todos os itens 🔴 e 🟡, o tema estará pronto para produção
3. Os itens 🟢 são melhorias opcionais que podem ser feitas após o deploy

---

**Última atualização:** 2026-01-06
**Versão do tema:** 1.4.2
**Status:** ✅ Todas as tarefas críticas (🔴) e importantes (🟡) foram concluídas




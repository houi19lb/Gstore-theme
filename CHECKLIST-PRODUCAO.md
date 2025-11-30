# 📋 Checklist de Produção - Tema Gstore

Este documento lista todos os itens que precisam ser corrigidos/removidos antes do tema ser enviado para produção.

## 🔴 CRÍTICO - Segurança

### 1. Remover arquivo de diagnóstico
- [ ] **Remover** `diagnostic-blu-checkout.php` - Expõe informações sensíveis do sistema
- **Localização:** `themes/gstore/diagnostic-blu-checkout.php`
- **Motivo:** Script de diagnóstico que pode expor dados sensíveis do WordPress e WooCommerce

### 2. Remover arquivos de debug/backup
- [ ] **Remover** `inc/class-gstore-blu-payment-gateway.php.broken`
- [ ] **Remover** `inc/class-gstore-blu-payment-gateway.php.corrupted-20251120140158`
- [ ] **Remover** `inc/class-gstore-blu-payment-gateway-CLEAN.php` (se não estiver em uso)
- [ ] **Remover** `inc/class-gstore-blu-payment-gateway-full-backup.php` (se não estiver em uso)
- [ ] **Remover** `inc/debug_blocks_class.txt`
- [ ] **Remover** `inc/debug_blocks.txt`
- [ ] **Remover** `inc/debug_blu.txt`
- [ ] **Remover** `inc/debug_flow.txt`
- [ ] **Remover** `inc/debug_gateways.txt`
- **Motivo:** Arquivos de debug e backup não devem estar em produção

### 3. Sanitização de dados
- [ ] **Corrigir** `functions.php` linha 1050-1051 - Função `gstore_save_cpf_field()`
  - Atualmente usa `$_POST['billing_cpf']` sem sanitização adequada
  - Deve usar `sanitize_text_field()` ou `sanitize_textarea_field()`
  - Exemplo:
  ```php
  if ( ! empty( $_POST['billing_cpf'] ) ) {
      $cpf = sanitize_text_field( $_POST['billing_cpf'] );
      $cpf = preg_replace( '/[^0-9]/', '', $cpf );
      update_post_meta( $order_id, 'billing_cpf', $cpf );
      update_post_meta( $order_id, '_billing_cpf', $cpf );
  }
  ```

### 4. Verificação de nonce
- [ ] **Adicionar** verificação de nonce na função `gstore_save_cpf_field()`
  - O WooCommerce já verifica nonce no checkout, mas é boa prática verificar novamente
  - Adicionar: `check_ajax_referer()` ou verificação manual de nonce do checkout

## 🟡 IMPORTANTE - Limpeza de Código

### 5. Remover logs de console do JavaScript
- [ ] **Remover/Comentar** todos os `console.log()` em:
  - `assets/js/checkout-steps.js` - **Múltiplos console.log** (linhas 69, 83, 291, 325, 472, 829, 846, 850, 857, 958, 966, 976, 985-992, 1073, 1085, 1095, 1105-1109, 1157-1165, 1173, 1179, 1203, 1250-1253, 1258, 1262, 1286, 1293, 1297, 1301, 1305)
  - `assets/js/checkout-cleanup.js` - linha 31
- **Solução:** Remover completamente ou criar função wrapper que só executa se `WP_DEBUG` estiver ativo

### 6. Limpar logs de debug do PHP
- [ ] **Remover/Ajustar** logs em `functions.php`:
  - Linha 968: `error_log()` incondicional
  - Linha 1010: `error_log()` incondicional
  - Linhas 992-997: Já está condicional com `WP_DEBUG`, mas revisar se está correto
- **Solução:** Manter apenas logs condicionais baseados em `WP_DEBUG`

### 7. Atualizar URLs de exemplo
- [ ] **Atualizar** `style.css` linhas 3-5:
  - `Theme URI: https://cacarmas.example` → URL real
  - `Author URI: https://cacarmas.example` → URL real

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
1. Remover `diagnostic-blu-checkout.php`
2. Remover todos os arquivos `.broken`, `.corrupted`, `.txt` de debug
3. Corrigir sanitização em `gstore_save_cpf_field()`

### 🟡 Importante (Recomendado antes de produção)
4. Remover/comentar `console.log()` do JavaScript
5. Limpar logs de debug do PHP
6. Atualizar URLs de exemplo no `style.css`

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

**Última atualização:** 2025-01-27
**Versão do tema:** 1.0.0




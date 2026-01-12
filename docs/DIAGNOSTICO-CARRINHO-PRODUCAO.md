# Diagnóstico: Bugs do Carrinho de Bandeja em Produção (Hostinger)

## Problemas Identificados

### 1. **Cache/CDN Interferindo em Requisições AJAX**
**Causa**: A Hostinger pode ter cache de CDN ou servidor que está cacheando respostas AJAX do carrinho, causando dessincronia.

**Sintomas**:
- Produto removido ainda aparece no mini-cart
- Contador não atualiza após remoção
- Fragmentos retornam dados antigos

**Solução Implementada**: 
- Headers anti-cache já estão implementados em `gstore_prevent_cart_ajax_cache()`
- **Verificar**: Se os headers estão sendo aplicados corretamente em produção

### 2. **Problemas com Fragmentos na Remoção**
**Causa**: O evento `removed_from_cart` pode não estar retornando fragmentos corretos ou o timing pode estar errado.

**Sintomas**:
- Mini-cart não atualiza após remover produto
- Contador fica desatualizado
- Produto removido ainda visível na bandeja

**Código Atual**:
- `gstore_enhance_cart_fragments()` adiciona fragmentos para `.wc-block-mini-cart__badge` e `.Gstore-cart-count`
- `handleRemovedFromCart()` em `mini-cart-fix.js` tenta atualizar após remoção

**Possível Problema**: O filtro `woocommerce_add_to_cart_fragments` pode não estar sendo chamado corretamente na remoção em produção.

### 3. **Problemas de Sessão/Cookie**
**Causa**: Diferenças de configuração de sessão entre ambiente local e produção podem causar perda de sessão durante requisições AJAX.

**Sintomas**:
- Carrinho "esquece" produtos removidos
- Sessão não persiste entre requisições
- Nonces inválidos

**Verificar**:
- Configuração de cookies em produção
- Domínio dos cookies (www vs não-www)
- HTTPS vs HTTP

### 4. **Timing de Atualização do Store**
**Causa**: O código JavaScript pode estar tentando atualizar o mini-cart antes do servidor processar completamente a remoção.

**Sintomas**:
- Atualização parcial (contador atualiza mas produto ainda aparece)
- Race conditions entre múltiplas requisições

**Código Atual**:
- `handleRemovedFromCart()` usa timeout de 100ms antes de atualizar
- Pode ser insuficiente em produção com latência maior

### 5. **Problemas com Store API REST**
**Causa**: A API REST do WooCommerce (`/wp-json/wc/store/v1/cart`) pode não estar funcionando corretamente em produção.

**Sintomas**:
- Fallback para API REST falha
- Nonce da Store API inválido ou expirado
- CORS ou permissões incorretas

**Verificar**:
- Se `window.wc.storeApiNonce` está disponível em produção
- Se a rota REST está acessível
- Se há plugins bloqueando requisições REST

### 6. **Problemas com Eventos WooCommerce**
**Causa**: O evento `removed_from_cart` pode não estar sendo disparado corretamente em produção.

**Sintomas**:
- `handleRemovedFromCart()` nunca é chamado
- Fragmentos não são retornados
- Carrinho não atualiza

**Verificar**:
- Se o WooCommerce está disparando eventos corretamente
- Se há plugins interferindo nos eventos
- Se o AJAX está habilitado corretamente

## Soluções Implementadas

### ✅ Solução 1: Melhorar Verificação de Fragmentos na Remoção
**Implementado**: 
- Função `gstore_ensure_removal_fragments()` melhorada com filtro de alta prioridade (999)
- Hook específico `gstore_force_fragments_on_removal()` para `wc_ajax_remove_from_cart`
- Garantia de que fragmentos sempre existam mesmo se WooCommerce não retornar

**Arquivos modificados**:
- `functions.php`: Linhas 961-1000

### ✅ Solução 2: Aumentar Timeouts e Adicionar Retry Logic
**Implementado**:
- Timeouts aumentados no CONFIG:
  - `maxRetries`: 3 → 5
  - `initialRetryDelay`: 300ms → 500ms
  - `maxRetryDelay`: 2000ms → 3000ms
  - `storeCheckTimeout`: 5000ms → 10000ms
- Múltiplas tentativas com delays crescentes (300ms, 800ms, 1500ms, 3000ms)
- Retry logic melhorado em `handleRemovedFromCart()`

**Arquivos modificados**:
- `assets/js/mini-cart-fix.js`: Linhas 17-24, 441-520

### ✅ Solução 3: Melhorar Headers Anti-Cache
**Implementado**:
- Headers HTTP melhorados em `gstore_prevent_cart_ajax_cache()`:
  - `Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private`
  - `X-Accel-Buffering: no` (para Nginx)
  - `Vary: Cookie` (garante cache por sessão)
  - `X-Cache-Control: no-cache` (para proxies/CDN)
- Verificação melhorada de requisições AJAX (inclui `wc-ajax`)

**Arquivos modificados**:
- `functions.php`: Linhas 1032-1060

### ✅ Solução 4: Melhorar Verificação de Sucesso
**Implementado**:
- Função `verifyRefreshSuccess()` melhorada com múltiplas estratégias:
  - Verificação via Store API
  - Verificação via DOM (fallback)
  - Múltiplas tentativas com delays (500ms, 1000ms, 2000ms)
- Logging detalhado para diagnóstico

**Arquivos modificados**:
- `assets/js/mini-cart-fix.js`: Linhas 349-420

### ✅ Solução 5: Melhorar Tratamento de Erros e Fallbacks
**Implementado**:
- Múltiplas tentativas automáticas em caso de falha
- Fallback adicional após 3 segundos
- Logging melhorado (debug e error)
- Verificação de fragmentos mais robusta (múltiplas estratégias de extração)

**Arquivos modificados**:
- `assets/js/mini-cart-fix.js`: Linhas 441-520, 526-550

### ✅ Solução 6: Corrigir Imagens Duplicadas (Cart Block)
**Causa**: O atributo `hidden` do HTML5 estava sendo ignorado devido a estilos base do tema que forçavam `display: block` em todas as imagens, e o tema remove os estilos padrão do WooCommerce Blocks que resolveriam isso.
**Implementado**: Adicionada regra global `[hidden] { display: none !important; }` para garantir que elementos ocultos via atributo HTML (usados pela Interactivity API do WooCommerce) sejam respeitados.

**Arquivos modificados**:
- `assets/css/base.css`
- `assets/css/utilities.css`

## Como Testar em Produção

### 1. Habilitar Debug Temporariamente
No arquivo `assets/js/mini-cart-fix.js`, linha 23, altere:
```javascript
debug: true // Temporariamente para diagnóstico
```

Isso habilitará logs detalhados no console do navegador.

### 2. Testar Remoção de Produtos
1. Adicione produtos ao carrinho
2. Abra o console do navegador (F12)
3. Remova um produto do carrinho
4. Verifique os logs:
   - `removed_from_cart event received`
   - `Mini-cart refreshed successfully`
   - `Cart count verified`

### 3. Verificar Problemas Comuns

**Se fragmentos não estão sendo retornados**:
- Verifique se `gstore_force_fragments_on_removal()` está sendo executado
- Verifique se headers anti-cache estão sendo aplicados
- Verifique logs do servidor para erros PHP

**Se mini-cart não atualiza**:
- Verifique se `window.wp.data` está disponível
- Verifique se `window.wc.storeApiNonce` está disponível
- Verifique se há erros JavaScript no console

**Se contador não atualiza**:
- Verifique se elementos `.wc-block-mini-cart__badge` e `.Gstore-cart-count` existem
- Verifique se fragmentos estão sendo aplicados ao DOM
- Verifique se há conflitos com outros scripts

## Configurações da Hostinger a Verificar

1. **Cache/CDN**: Desabilitar cache para requisições AJAX do carrinho
2. **Sessões**: Verificar configuração de cookies e sessões PHP
3. **PHP**: Verificar se `session.use_cookies` está habilitado
4. **Nginx/Apache**: Verificar se headers estão sendo respeitados
5. **Plugins**: Verificar se plugins de cache não estão interferindo

## Próximos Passos

1. ✅ Implementar melhorias no código para produção
2. ⏳ Testar em ambiente de staging/produção
3. ⏳ Habilitar debug temporariamente para diagnóstico
4. ⏳ Verificar configurações específicas da Hostinger (cache, CDN, sessões)
5. ⏳ Monitorar logs e ajustar conforme necessário


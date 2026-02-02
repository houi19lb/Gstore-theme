# Troubleshooting: Mini Cart Fix - Guia de Diagnóstico

## Contexto rápido (LLMs/novos devs)
- Guia de diagnóstico do mini‑cart fix.
- Usa checagens de nonces, fragments e REST do WooCommerce.

**Versão do Fix**: v1.2.0  
**Última Atualização**: Implementação de força estratégia híbrida quando nonce global ausente  
**Data**: 2024

---

## 📋 Checklist de Diagnóstico Rápido

Use este checklist para identificar rapidamente o problema:

- [ ] Console do navegador mostra erros JavaScript?
- [ ] `window.wc.storeApiNonce` está disponível?
- [ ] `window.gstoreMiniCart.storeApiNonce` está disponível?
- [ ] Logs `[MiniCartFix]` aparecem no console?
- [ ] Requisições AJAX retornam status 200?
- [ ] Fragmentos estão sendo retornados nas respostas AJAX?
- [ ] Store do WordPress (`wp.data`) está disponível?
- [ ] API REST `/wp-json/wc/store/v1/cart` está acessível?

---

## 🔍 Passo 1: Verificar Logs do Console

### 1.1 Abrir Console do Navegador
1. Pressione `F12` ou `Ctrl+Shift+I` (Windows/Linux) / `Cmd+Option+I` (Mac)
2. Vá para a aba **Console**
3. Limpe o console (`Ctrl+L` ou ícone de limpar)
4. Reproduza o problema (adicionar/remover produto)

### 1.2 Logs Esperados

**Ao adicionar produto:**
```
[MiniCartFix] added_to_cart event received {fragments: true, cart_hash: '...', button: 1}
[MiniCartFix] Expected count extracted from .wc-block-mini-cart__badge: X
[MiniCartFix] handleAddedToCart: Iniciando refresh com expectedCount: X
[MiniCartFix] Starting mini-cart refresh... {useHybridFirst: true/false, expectedCount: X, force: false}
[MiniCartFix] Store invalidated successfully
[MiniCartFix] API refresh successful: {items: Array(X), ...}
[MiniCartFix] Mini-cart refresh completed successfully
```

**Ao remover produto:**
```
[MiniCartFix] removed_from_cart event received {fragments: true, cart_hash: '...', button: 1}
[MiniCartFix] handleRemovedFromCart: Iniciando refresh com expectedCount: X
[MiniCartFix] Starting mini-cart refresh... {useHybridFirst: true/false, expectedCount: X, force: true}
[MiniCartFix] Mini-cart refreshed successfully after removal
```

### 1.3 Logs de Diagnóstico Inicial

Ao carregar a página, você deve ver:
```
[MiniCartFix] ==================================================
[MiniCartFix] DIAGNÓSTICO DO MINI-CART FIX v1.2.0
[MiniCartFix] ==================================================
[MiniCartFix]   jQuery disponível: ✓
[MiniCartFix]   wp.data disponível: ✓
[MiniCartFix]   window.wc disponível: ✓
[MiniCartFix]   window.wc.storeApiNonce: ✓ ou ✗
[MiniCartFix]   gstoreMiniCart disponível: ✓
[MiniCartFix]   gstoreMiniCart.storeApiNonce: ✓
[MiniCartFix]   Nonce obtido: ✓
[MiniCartFix]   Store disponível: ✓
[MiniCartFix]   API disponível: ✓
[MiniCartFix]   Cart API URL: https://...
[MiniCartFix]   Mini-cart badges encontrados: X
[MiniCartFix]   Valor atual do badge: X
```

### 1.4 Problemas Comuns nos Logs

**❌ `window.wc.storeApiNonce: ✗`**
- **Causa**: WooCommerce Blocks não está injetando o nonce global
- **Solução**: O fix deve detectar isso e forçar estratégia híbrida automaticamente
- **Verificar**: Se aparece `Forcing Hybrid strategy due to missing global nonce` nos logs

**❌ `Store not available`**
- **Causa**: WordPress data store não está carregado
- **Solução**: Verificar se WooCommerce Blocks está ativo e carregado corretamente

**❌ `API not available`**
- **Causa**: Nenhum nonce disponível (nem global nem fallback)
- **Solução**: Verificar se `gstoreMiniCart` está sendo injetado via PHP

**❌ `Store update verification failed`**
- **Causa**: Store não está atualizando após `receiveCart()`
- **Solução**: Pode ser timing - o fix já aumenta delay para 300ms

---

## 🔍 Passo 2: Verificar Requisições de Rede

### 2.1 Abrir Network Tab
1. No DevTools, vá para a aba **Network**
2. Filtre por **XHR** ou **Fetch**
3. Limpe a lista
4. Reproduza o problema

### 2.2 Requisições Esperadas

**Ao adicionar produto:**
- `wc-ajax=add_to_cart` - Status 200, retorna JSON com `fragments`
- `/wp-json/wc/store/v1/cart` - Status 200, retorna dados do carrinho

**Ao remover produto:**
- `wc-ajax=remove_from_cart` - Status 200, retorna JSON com `fragments`
- `/wp-json/wc/store/v1/cart` - Status 200, retorna dados atualizados

### 2.3 Verificar Headers Anti-Cache

Na aba **Network**, clique em uma requisição AJAX do carrinho e verifique os **Response Headers**:

**Headers esperados:**
```
Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private
X-Accel-Buffering: no
Vary: Cookie
X-Cache-Control: no-cache
```

**❌ Se headers não estão presentes:**
- Verificar se `gstore_prevent_cart_ajax_cache()` está sendo executado
- Verificar se há plugins de cache interferindo

### 2.4 Verificar Fragmentos na Resposta

Na requisição `wc-ajax=add_to_cart` ou `remove_from_cart`, verifique o **Response**:

**Deve conter:**
```json
{
  "fragments": {
    ".wc-block-mini-cart__badge": "<span class=\"wc-block-mini-cart__badge\">3</span>",
    ".Gstore-cart-count": "<span class=\"Gstore-cart-count\">3</span>",
    ...
  },
  "cart_hash": "..."
}
```

**❌ Se fragmentos não estão presentes:**
- Verificar se `gstore_enhance_cart_fragments()` está sendo executado
- Verificar se `gstore_force_fragments_on_removal()` está sendo executado (para remoção)

---

## 🔍 Passo 3: Verificar Estado do Store

### 3.1 Inspecionar Store no Console

Cole no console do navegador:

```javascript
// Verificar se store está disponível
console.log('Store disponível:', !!(window.wp && window.wp.data && window.wp.data.select('wc/store/cart')));

// Obter dados atuais do carrinho
const cartData = window.wp?.data?.select('wc/store/cart')?.getCartData();
console.log('Dados do carrinho:', cartData);
console.log('Items count:', cartData?.items_count);
console.log('Items:', cartData?.items);

// Verificar nonces disponíveis
console.log('window.wc.storeApiNonce:', window.wc?.storeApiNonce);
console.log('gstoreMiniCart.storeApiNonce:', window.gstoreMiniCart?.storeApiNonce);
```

### 3.2 Problemas Comuns

**❌ `cartData` é `null` ou `undefined`**
- Store não está inicializado
- Verificar se WooCommerce Blocks está carregado

**❌ `items_count` não corresponde ao número real de itens**
- Store está desatualizado
- O fix deve atualizar via API, verificar logs

**❌ `items` contém produtos antigos/removidos**
- Store não está sendo atualizado após remoção
- Verificar se `receiveCart()` está sendo chamado

---

## 🔍 Passo 4: Verificar DOM e Elementos

### 4.1 Inspecionar Elementos do Mini-Cart

No console:

```javascript
// Verificar badges
const badges = document.querySelectorAll('.wc-block-mini-cart__badge');
console.log('Badges encontrados:', badges.length);
badges.forEach((badge, i) => {
  console.log(`Badge ${i}:`, badge.textContent.trim());
});

// Verificar contadores customizados
const customCounters = document.querySelectorAll('.Gstore-cart-count');
console.log('Contadores customizados:', customCounters.length);
customCounters.forEach((counter, i) => {
  console.log(`Contador ${i}:`, counter.textContent.trim());
});

// Verificar drawer do mini-cart
const drawer = document.querySelector('.wc-block-mini-cart__drawer');
console.log('Drawer encontrado:', !!drawer);
console.log('Drawer aberto:', drawer?.classList.contains('is-open'));

// Verificar itens no drawer
const drawerItems = document.querySelectorAll('.wc-block-mini-cart__items .wc-block-mini-cart-item');
console.log('Itens no drawer:', drawerItems.length);
```

### 4.2 Problemas Comuns

**❌ Badge mostra número incorreto**
- Fragmentos não estão sendo aplicados ao DOM
- Verificar se `update_wc_div()` está sendo chamado
- Verificar se há conflitos com outros scripts

**❌ Drawer mostra produtos antigos**
- Store não está atualizado
- Verificar se `receiveCart()` está sendo chamado com dados corretos

---

## 🔍 Passo 5: Testar API REST Diretamente

### 5.1 Testar no Console

```javascript
// Obter nonce
const nonce = window.gstoreMiniCart?.storeApiNonce || window.wc?.storeApiNonce;
console.log('Nonce:', nonce);

// Fazer requisição manual
fetch('/wp-json/wc/store/v1/cart', {
  method: 'GET',
  headers: {
    'Content-Type': 'application/json',
    'X-WC-Store-API-Nonce': nonce
  },
  credentials: 'same-origin'
})
.then(response => {
  console.log('Status:', response.status);
  return response.json();
})
.then(data => {
  console.log('Dados da API:', data);
  console.log('Items count:', data.items_count);
  console.log('Items:', data.items);
})
.catch(error => {
  console.error('Erro na API:', error);
});
```

### 5.2 Problemas Comuns

**❌ Status 401 (Unauthorized)**
- Nonce inválido ou expirado
- Verificar se nonce está sendo renovado corretamente

**❌ Status 403 (Forbidden)**
- Permissões incorretas
- Verificar se usuário tem permissão para acessar API

**❌ Status 404 (Not Found)**
- Rota não existe
- Verificar se WooCommerce Blocks está ativo
- Verificar se permalinks estão configurados corretamente

**❌ CORS Error**
- Problema de configuração do servidor
- Verificar headers CORS

---

## 🔍 Passo 6: Verificar Eventos WooCommerce

### 6.1 Monitorar Eventos no Console

```javascript
// Monitorar evento added_to_cart
jQuery(document.body).on('added_to_cart', function(event, fragments, cart_hash, button) {
  console.log('EVENTO added_to_cart:', {
    fragments: fragments,
    cart_hash: cart_hash,
    button: button
  });
});

// Monitorar evento removed_from_cart
jQuery(document.body).on('removed_from_cart', function(event, fragments, cart_hash, button) {
  console.log('EVENTO removed_from_cart:', {
    fragments: fragments,
    cart_hash: cart_hash,
    button: button
  });
});

// Monitorar outros eventos
jQuery(document.body).on('wc_fragments_refreshed', function() {
  console.log('EVENTO wc_fragments_refreshed');
});

jQuery(document.body).on('wc_cart_button_updated', function() {
  console.log('EVENTO wc_cart_button_updated');
});
```

### 6.2 Problemas Comuns

**❌ Evento `removed_from_cart` não é disparado**
- WooCommerce não está disparando evento
- Verificar se AJAX está habilitado
- Verificar se há plugins interferindo

**❌ Evento disparado mas sem `fragments`**
- WooCommerce não está retornando fragmentos
- Verificar se `gstore_force_fragments_on_removal()` está sendo executado

---

## 🔧 Soluções Alternativas

### Solução A: Forçar Refresh Manual

Se o mini-cart não atualiza automaticamente, você pode forçar um refresh manual:

```javascript
// No console do navegador
window.gstoreRefreshMiniCart(true);
```

### Solução B: Sincronizar DOM Manualmente

Se o store está correto mas o DOM não:

```javascript
// Obter count da API
fetch('/wp-json/wc/store/v1/cart', {
  headers: {
    'X-WC-Store-API-Nonce': window.gstoreMiniCart.storeApiNonce
  }
})
.then(r => r.json())
.then(data => {
  // Sincronizar elementos
  window.gstoreSyncMiniCart(data.items_count);
});
```

### Solução C: Recarregar Store Manualmente

```javascript
// Invalidar e recarregar store
const cartStore = window.wp.data.dispatch('wc/store/cart');
cartStore.invalidateResolutionForStoreSelector('getCartData');

// Aguardar e atualizar via API
setTimeout(() => {
  fetch('/wp-json/wc/store/v1/cart', {
    headers: {
      'X-WC-Store-API-Nonce': window.gstoreMiniCart.storeApiNonce
    }
  })
  .then(r => r.json())
  .then(data => {
    cartStore.receiveCart(data);
  });
}, 300);
```

---

## 🐛 Problemas Conhecidos e Soluções

### Problema 1: Produto Errado Aparece no Mini-Cart

**Sintomas:**
- Mini-cart mostra produto "Teste" mas carrinho real tem outros produtos
- Ao remover o produto errado, o mini-cart corrige

**Causa Identificada:**
- `window.wc.storeApiNonce` ausente faz Strategy 1 usar fetch interno quebrado
- Eventos secundários (`wc_cart_button_updated`) disparam Strategy 1 que sobrescreve dados corretos

**Solução Implementada:**
- Fix detecta ausência de `window.wc.storeApiNonce` e força estratégia híbrida
- Todos os refreshes usam API REST com nonce fallback quando global ausente

**Como Verificar:**
- Verificar logs: deve aparecer `Forcing Hybrid strategy due to missing global nonce`
- Verificar se `useHybridFirst: true` aparece nos logs de refresh

**Se Ainda Não Funcionar:**
1. Verificar se `gstoreMiniCart.storeApiNonce` está disponível
2. Verificar se API REST está retornando dados corretos
3. Verificar se `receiveCart()` está sendo chamado com dados corretos

### Problema 2: Mini-Cart Não Atualiza Após Remover Produto

**Sintomas:**
- Produto removido ainda aparece no mini-cart
- Contador não atualiza

**Causas Possíveis:**
1. Evento `removed_from_cart` não está sendo disparado
2. Fragmentos não estão sendo retornados
3. Store não está sendo atualizado

**Soluções:**
1. Verificar logs do evento `removed_from_cart`
2. Verificar fragmentos na resposta AJAX
3. Verificar se `receiveCart()` está sendo chamado

### Problema 3: Contador Correto Mas Produtos Errados

**Sintomas:**
- Badge mostra número correto
- Mas drawer mostra produtos antigos/errados

**Causa:**
- Store está parcialmente atualizado
- `items_count` atualizado mas `items` não

**Solução:**
- Verificar se `receiveCart()` está recebendo dados completos da API
- Verificar se API está retornando `items` corretos

---

## 📝 Informações Técnicas

### Estratégias de Refresh Implementadas

1. **Strategy 1: Store Invalidation**
   - Invalida cache do store e força busca de dados
   - **Problema**: Requer `window.wc.storeApiNonce` para funcionar corretamente
   - **Solução**: Fix força estratégia híbrida quando nonce ausente

2. **Strategy 1.5: Hybrid Refresh**
   - Combina invalidação + API REST + `receiveCart()`
   - **Vantagem**: Usa nonce fallback quando global ausente
   - **Uso**: Forçado quando `window.wc.storeApiNonce` ausente

3. **Strategy 2: API REST Direct**
   - Busca dados diretamente da API e atualiza store via `receiveCart()`
   - **Vantagem**: Mais confiável, não depende de fetch interno

4. **Strategy 3: Component Reload**
   - Força reload do componente React
   - **Limitação**: Não atualiza store, apenas DOM

### Configurações Atuais

```javascript
CONFIG = {
  maxRetries: 5,
  initialRetryDelay: 500,
  maxRetryDelay: 3000,
  debounceDelay: 500,
  storeCheckTimeout: 10000,
  debug: true // Habilitar para diagnóstico
}
```

### Funções Globais Expostas

- `window.gstoreRefreshMiniCart(force, useHybridFirst, expectedCount)` - Força refresh manual
- `window.gstoreMiniCartDiagnostics()` - Executa diagnóstico completo
- `window.gstoreSyncMiniCart(count)` - Sincroniza elementos DOM com count

---

## 🚨 Escalação: Quando Nada Funciona

Se nenhuma das soluções acima funcionar:

1. **Coletar Informações:**
   - Screenshot do console com todos os logs
   - Screenshot da aba Network com requisições AJAX
   - Resultado de `window.gstoreMiniCartDiagnostics()`
   - Versão do WordPress e WooCommerce
   - Lista de plugins ativos

2. **Verificar Configurações do Servidor:**
   - Cache/CDN configurado corretamente?
   - Headers anti-cache sendo respeitados?
   - Sessões PHP funcionando corretamente?
   - Permalinks configurados?

3. **Verificar Conflitos:**
   - Desabilitar outros plugins temporariamente
   - Testar com tema padrão do WordPress
   - Verificar se há JavaScript errors não relacionados

4. **Último Recurso:**
   - Considerar aumentar delays no CONFIG
   - Considerar adicionar mais retries
   - Considerar implementar polling como fallback

---

## 📞 Contato e Suporte

**Arquivos Relacionados:**
- `assets/js/mini-cart-fix.js` - Script principal do fix
- `functions.php` - Funções PHP relacionadas (fragments, headers, etc.)
- `DIAGNOSTICO-CARRINHO-PRODUCAO.md` - Documentação anterior

**Última Alteração Significativa:**
- Forçar estratégia híbrida quando `window.wc.storeApiNonce` ausente
- Aumentar delay de verificação em `refreshViaAPI` de 100ms para 300ms
- Adicionar verificação em `executeRefreshStrategies` para garantir consistência

---

**Nota**: Este documento deve ser atualizado sempre que novas alterações forem feitas no código do mini-cart fix.













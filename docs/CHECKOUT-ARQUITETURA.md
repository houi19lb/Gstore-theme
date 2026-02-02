# Arquitetura do Checkout - Gstore

## Contexto rápido (LLMs/novos devs)
- Documento de arquitetura do checkout (tema + plugin).
- **Tema** = UI/fluxo visual; **Plugin** = regras de negócio e integrações.

## Visão Geral

O checkout do Gstore é um sistema híbrido que combina **componentes do tema** (UI/UX) com **componentes do plugin** (lógica de negócio). Este documento explica como ambos trabalham juntos para fornecer uma experiência de checkout completa.

---

## Arquitetura: Tema vs Plugin

### Regra de Ouro

- **Tema (`gstore`)**: Responsável pela **apresentação** (UI/UX), templates, CSS e JavaScript do frontend
- **Plugin (`gstore-core`)**: Responsável pela **lógica de negócio**, APIs, cálculos, webhooks e integrações

### Separação de Responsabilidades

| Componente | Localização | Responsabilidade |
|------------|------------|------------------|
| **UI do Checkout** | Tema (`assets/js/checkout-steps.js`) | Interface em 3 etapas, navegação, validação visual |
| **Cálculo de Frete** | Plugin (`includes/shipping/`) | Lógica de cálculo, persistência na sessão |
| **Resumo do Carrinho** | Plugin (`includes/ajax/class-gstore-core-ajax-handlers.php`) | Endpoint AJAX `gstore_get_cart_summary` |
| **Gateways de Pagamento** | Plugin (`includes/blu/`) | Blu Checkout, Blu Pix, webhooks |
| **Taxa de Parcelamento** | Plugin (`includes/blu/class-gstore-blu-checkout-handler.php`) | Cálculo de juros, fees |
| **Templates HTML** | Tema (`templates/page-checkout.html`) | Estrutura visual do checkout |
| **Estilos CSS** | Tema (`assets/css/checkout.css`, `checkout-steps.css`) | Aparência visual |

---

## Fluxo do Checkout em 3 Etapas

### Etapa 1: Escolha do Método de Pagamento

**Arquivo:** `assets/js/checkout-steps.js` (linhas 16-24)

```javascript
{
  id: 'payment-method',
  name: 'Pagamento',
  icon: 'fa-credit-card',
  title: 'Escolha o Método de Pagamento',
  description: 'Selecione como deseja pagar seu pedido.',
  fields: []
}
```

**O que acontece:**
1. Usuário escolhe entre **Blu Checkout** (cartão) ou **Blu Pix**
2. JavaScript do tema (`checkout-steps.js`) detecta a seleção
3. Dispara `update_checkout` para atualizar o backend
4. Plugin processa a seleção e atualiza a sessão do WooCommerce

**Componentes envolvidos:**
- **Tema:** `checkout-steps.js` - Handler de seleção de pagamento
- **Plugin:** `class-gstore-blu-payment-gateway.php` - Processamento do gateway

---

### Etapa 2: Dados Básicos

**Arquivo:** `assets/js/checkout-steps.js` (linhas 25-36)

```javascript
{
  id: 'contact',
  name: 'Dados Básicos',
  icon: 'fa-envelope',
  title: 'Seus Dados',
  description: 'Informe seu email, telefone e CEP para calcular o frete.',
  fields: [
    'billing_email',
    'billing_phone',
    'billing_postcode'
  ]
}
```

**O que acontece:**
1. Usuário preenche email, telefone e CEP
2. Quando o CEP é digitado, o JavaScript dispara cálculo de frete
3. **Plugin** calcula frete via endpoint AJAX `gstore_calculate_shipping`
4. Frete é persistido na sessão do WooCommerce
5. Resumo do carrinho é atualizado com novos valores

**Fluxo detalhado:**

```
Usuário digita CEP
    ↓
checkout-steps.js detecta mudança
    ↓
Chama endpoint AJAX: gstore_calculate_shipping (Plugin)
    ↓
Plugin calcula frete e salva na sessão
    ↓
Plugin retorna rates (terrestre/aéreo)
    ↓
Tema atualiza UI com opções de frete
    ↓
Usuário seleciona modo (terrestre/aéreo)
    ↓
Tema atualiza campos hidden no formulário
    ↓
Dispara update_checkout
    ↓
Plugin recalcula taxas de parcelamento (base muda com frete)
```

**Componentes envolvidos:**
- **Tema:** `checkout-steps.js` - Cálculo de frete, renderização de opções
- **Plugin:** `class-gstore-core-shipping-hooks.php` - Cálculo e persistência
- **Plugin:** `class-gstore-core-ajax-handlers.php` - Endpoint AJAX

---

### Etapa 3: Finalizar Pedido

**Arquivo:** `assets/js/checkout-steps.js` (linhas 37-44)

```javascript
{
  id: 'payment',
  name: 'Finalizar',
  icon: 'fa-check',
  title: 'Finalizar Pedido',
  description: 'Clique no botão abaixo para finalizar seu pedido.',
  fields: []
}
```

**O que acontece:**
1. Usuário visualiza resumo final (produtos, frete, parcelas, total)
2. Clica em "Finalizar pedido"
3. **Tema** serializa formulário e envia via AJAX
4. **Plugin** processa o pedido via gateway selecionado
5. Redireciona para checkout da Blu (se Blu Checkout) ou exibe QR Code (se Pix)

**Componentes envolvidos:**
- **Tema:** `checkout-steps.js` - Serialização e envio do formulário
- **Plugin:** `class-gstore-blu-payment-gateway.php` - Processamento do pagamento
- **Plugin:** `class-gstore-blu-pix-gateway.php` - Processamento do Pix

---

## Componentes do Tema

### 1. JavaScript: `checkout-steps.js`

**Localização:** `assets/js/checkout-steps.js`

**Responsabilidades:**
- Gerenciar navegação entre as 3 etapas
- Validar campos em cada etapa
- Calcular frete quando CEP é digitado
- Atualizar resumo do carrinho
- Sincronizar dados com o backend via AJAX
- Gerenciar campos hidden do formulário

**Funções principais:**

#### `init()`
Inicializa o checkout, organiza campos em etapas, configura event listeners.

#### `loadCartSummary()`
Carrega resumo do carrinho via endpoint AJAX do plugin:
```javascript
$.ajax({
  url: wc_checkout_params.ajax_url,
  type: 'POST',
  data: {
    action: 'gstore_get_cart_summary',
    payment_method: paymentMethod,
    gstore_blu_installments: installmentsValue
  },
  success: function(response) {
    renderSummary(response.data);
  }
});
```

#### `updateCheckoutShippingHiddenFields()`
Atualiza campos hidden no formulário com dados de frete:
```javascript
// Adiciona campos para cada item do carrinho
items.forEach((item) => {
  const cartItemKey = item.key;
  const selectedMode = checkoutSelectedShippingByItem[cartItemKey] || 'land';
  const rates = checkoutShippingRatesByItem[cartItemKey] || [];
  
  if (rates.length > 0) {
    // Campo hidden com modo selecionado
    $checkoutForm.append(
      $('<input>', {
        type: 'hidden',
        name: `gstore_shipping_mode[${cartItemKey}]`,
        value: selectedMode
      })
    );
    
    // Campo hidden com rates (JSON)
    $checkoutForm.append(
      $('<input>', {
        type: 'hidden',
        name: `gstore_shipping_rates[${cartItemKey}]`,
        value: JSON.stringify(rates)
      })
    );
  }
});
```

#### `calculateShipping(cep)`
Calcula frete via endpoint do plugin:
```javascript
$.ajax({
  url: wc_checkout_params.ajax_url,
  type: 'POST',
  data: {
    action: 'gstore_calculate_shipping',
    postcode: cep,
    // ... outros dados
  },
  success: function(response) {
    // Atualiza UI com rates retornados
    renderShippingOptions(response.data);
  }
});
```

#### Recálculo Automático de Taxas
Quando o modo de frete muda, o sistema recalcula automaticamente as taxas de parcelamento:

```javascript
$(document.body).one('updated_checkout', function() {
  setTimeout(function() {
    // Recarrega resumo com novos totais
    loadCartSummary();
    setTimeout(function() {
      // Atualiza preview de parcelas
      if (lastCartSummaryData) {
        updateInstallmentsPreview(lastCartSummaryData);
      }
      maybeFetchInstallmentQuotes();
    }, 200);
  }, 300);
});
```

---

### 2. CSS: Estilos do Checkout

**Arquivos:**
- `assets/css/checkout.css` - Estilos base do checkout
- `assets/css/checkout-steps.css` - Estilos específicos das 3 etapas
- `assets/css/checkout-pix.css` - Estilos do QR Code Pix

**Responsabilidades:**
- Layout das 3 etapas
- Estilização dos campos de formulário
- Animações de transição entre etapas
- Responsividade mobile
- Estilos do resumo do carrinho

---

### 3. Templates HTML

**Arquivo:** `templates/page-checkout.html`

**Responsabilidades:**
- Estrutura HTML do checkout
- Blocos Gutenberg para checkout
- Integração com templates do WooCommerce

---

## Componentes do Plugin

### 1. AJAX Handlers

**Arquivo:** `includes/ajax/class-gstore-core-ajax-handlers.php`

#### Endpoint: `gstore_get_cart_summary`

**Ação:** `wp_ajax_gstore_get_cart_summary` / `wp_ajax_nopriv_gstore_get_cart_summary`

**Responsabilidade:** Retorna resumo completo do carrinho para o frontend

**Resposta JSON:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "key": "cart_item_key",
        "product_id": 123,
        "name": "Nome do Produto",
        "quantity": 1,
        "subtotal": "R$ 100,00",
        "image": "url_da_imagem"
      }
    ],
    "totals": {
      "subtotal": "R$ 100,00",
      "shipping": "R$ 30,00",
      "shipping_raw": 30,
      "total": "R$ 130,00",
      "total_raw": 130
    },
    "shipping": {
      "chosen_method": "gstore_custom_shipping:ground",
      "rates": [
        {
          "id": "gstore_custom_shipping:ground",
          "label": "Frete Terrestre",
          "cost": 30,
          "cost_formatted": "R$ 30,00",
          "mode": "land"
        },
        {
          "id": "gstore_custom_shipping:air",
          "label": "Frete Aéreo",
          "cost": 70,
          "cost_formatted": "R$ 70,00",
          "mode": "air"
        }
      ],
      "value": 30,
      "formatted": "R$ 30,00"
    },
    "installments": {
      "selected": 1,
      "per_installment": "R$ 130,00",
      "total": "R$ 130,00"
    },
    "payment_method": "blu_checkout"
  }
}
```

#### Endpoint: `gstore_calculate_shipping`

**Ação:** `wp_ajax_gstore_calculate_shipping` / `wp_ajax_nopriv_gstore_calculate_shipping`

**Responsabilidade:** Calcula frete baseado no CEP e produtos do carrinho

**Fluxo:**
1. Recebe CEP e dados do carrinho
2. Calcula frete para cada item (terrestre/aéreo)
3. Persiste na sessão do WooCommerce
4. Retorna rates disponíveis

**Persistência na Sessão:**
```php
// Salva CEP na sessão
WC()->customer->set_shipping_postcode($postcode);
WC()->customer->save();

// Salva frete na sessão
WC()->session->set('gstore_shipping_fee_value', $total_shipping);
WC()->session->set('gstore_shipping_fee_method', $selected_method);
```

---

### 2. Shipping Hooks

**Arquivo:** `includes/shipping/class-gstore-core-shipping-hooks.php`

#### Hook: `woocommerce_cart_calculate_fees`

**Função:** `add_shipping_fee_from_session()`

**Responsabilidade:** Adiciona frete como fee no carrinho quando calculado

```php
add_action('woocommerce_cart_calculate_fees', 'add_shipping_fee_from_session', 20);

function add_shipping_fee_from_session($cart) {
    $shipping_value = WC()->session->get('gstore_shipping_fee_value', 0);
    if ($shipping_value > 0) {
        $cart->add_fee(__('Frete', 'gstore'), $shipping_value, true);
    }
}
```

#### Hook: `woocommerce_checkout_update_order_review`

**Função:** `sync_shipping_modes_from_post()`

**Responsabilidade:** Sincroniza modos de frete selecionados do POST para o carrinho

```php
add_action('woocommerce_checkout_update_order_review', 'sync_shipping_modes_from_post', 10, 1);

function sync_shipping_modes_from_post($post_data) {
    parse_str($post_data, $parsed);
    
    if (isset($parsed['gstore_shipping_mode'])) {
        foreach ($parsed['gstore_shipping_mode'] as $cart_item_key => $mode) {
            WC()->cart->cart_contents[$cart_item_key]['gstore_shipping_mode'] = $mode;
        }
        WC()->cart->set_session();
    }
}
```

---

### 3. Blu Checkout Handler

**Arquivo:** `includes/blu/class-gstore-blu-checkout-handler.php`

#### Hook: `woocommerce_cart_calculate_fees`

**Função:** `add_installment_fee()`

**Responsabilidade:** Calcula e adiciona taxa de parcelamento ao total

**Cálculo da Taxa:**
```php
// Base: produtos + frete - descontos
$base = $cart_contents_total + $shipping_total - $discount_total;

// Taxa baseada em percentual ou tabela progressiva
if ($strategy === 'table') {
    $fee_value = calcular_taxa_tabela($base, $installments, $config);
} else {
    $fee_value = $base * ($percent / 100) * $installments;
}

// Adiciona como fee
$cart->add_fee(__('Taxa de Parcelamento', 'gstore'), $fee_value, true);
```

**Recálculo Automático:**
Quando o frete muda, a base de cálculo muda, então a taxa é recalculada automaticamente pelo WooCommerce ao disparar `update_checkout`.

---

### 4. Gateways de Pagamento

#### Blu Checkout (Cartão)

**Arquivo:** `includes/blu/class-gstore-blu-payment-gateway.php`

**Processamento:**
1. Recebe pedido do WooCommerce
2. Cria link de pagamento na API da Blu
3. Armazena metadados do link no pedido
4. Redireciona cliente para checkout da Blu
5. Aguarda confirmação via webhook

**Webhook:**
- Endpoint: `/wp-json/gstore-blu/v1/webhook`
- Valida assinatura (se configurado)
- Atualiza status do pedido conforme status da Blu

#### Blu Pix

**Arquivo:** `includes/blu/class-gstore-blu-pix-gateway.php`

**Processamento:**
1. Recebe pedido do WooCommerce
2. Cria pagamento Pix na API da Blu
3. Gera QR Code e código Pix
4. Exibe instruções na página "Obrigado"
5. Consulta status periodicamente ou via webhook

---

## Integração Tema ↔ Plugin

### Comunicação via AJAX

O tema e o plugin se comunicam exclusivamente via endpoints AJAX do WordPress:

```
Tema (JavaScript)
    ↓
Endpoint AJAX (Plugin)
    ↓
Processamento (Plugin)
    ↓
Resposta JSON (Plugin)
    ↓
Atualização UI (Tema)
```

### Endpoints Utilizados

| Endpoint | Ação | Responsabilidade |
|----------|------|------------------|
| `gstore_get_cart_summary` | Plugin | Retorna resumo completo do carrinho |
| `gstore_calculate_shipping` | Plugin | Calcula e persiste frete |
| `gstore_blu_get_product_installment_quotes` | Tema/Plugin | Retorna cotações de parcelamento |
| `update_order_review` | WooCommerce | Atualiza resumo do pedido |

### Sincronização de Dados

#### 1. Campos Hidden no Formulário

O tema adiciona campos hidden no formulário de checkout para enviar dados ao plugin:

```javascript
// Modo de frete selecionado
<input type="hidden" name="gstore_shipping_mode[cart_item_key]" value="land" />

// Rates disponíveis (JSON)
<input type="hidden" name="gstore_shipping_rates[cart_item_key]" value='[{"id":"...","cost":30}]' />
```

O plugin lê esses campos via `woocommerce_checkout_update_order_review` e sincroniza com o carrinho.

#### 2. Sessão do WooCommerce

O plugin persiste dados na sessão do WooCommerce:

```php
// Frete
WC()->session->set('gstore_shipping_fee_value', $value);
WC()->session->set('gstore_shipping_fee_method', $method);

// Parcelas
WC()->session->set('gstore_blu_installments', $installments);
WC()->session->set('chosen_payment_method', $method);
```

O tema lê esses dados quando necessário via `loadCartSummary()`.

---

## Sistema de Frete

### Fluxo Completo

```
1. Usuário digita CEP
   ↓
2. Tema: checkout-steps.js detecta mudança
   ↓
3. Tema: Chama gstore_calculate_shipping (AJAX)
   ↓
4. Plugin: Calcula frete para cada item
   ↓
5. Plugin: Persiste na sessão WC
   ↓
6. Plugin: Retorna rates (terrestre/aéreo)
   ↓
7. Tema: Renderiza opções de frete
   ↓
8. Usuário seleciona modo (terrestre/aéreo)
   ↓
9. Tema: Atualiza campos hidden
   ↓
10. Tema: Dispara update_checkout
    ↓
11. Plugin: Sincroniza modo selecionado com carrinho
    ↓
12. Plugin: Recalcula fees (frete + parcelamento)
    ↓
13. Tema: Atualiza resumo com novos valores
```

### Cálculo de Frete

**Arquivo do Plugin:** `includes/shipping/class-gstore-core-shipping-method.php`

**Fatores considerados:**
- CEP de destino
- Região (Sul, Rio de Janeiro, Resto do Brasil)
- Peso tático do produto
- Modo selecionado (terrestre ou aéreo)

**Rates por Item:**
Cada item do carrinho pode ter seu próprio frete calculado, permitindo diferentes modos por item.

---

## Sistema de Parcelamento

### Cálculo da Taxa

**Arquivo do Plugin:** `includes/blu/class-gstore-blu-checkout-handler.php`

**Base de Cálculo:**
```
Base = Produtos + Frete - Descontos
```

**Estratégias:**
1. **Percentual Fixo:** Taxa = Base × (Percentual / 100) × Parcelas
2. **Tabela Progressiva:** Taxa varia conforme número de parcelas (tabela configurável)

### Recálculo Automático

Quando o frete muda, a base de cálculo muda, então:

1. Plugin recalcula taxa automaticamente via hook `woocommerce_cart_calculate_fees`
2. Tema detecta mudança no resumo do carrinho
3. Tema atualiza preview de parcelas automaticamente

**Código no Tema:**
```javascript
$(document.body).one('updated_checkout', function() {
  setTimeout(function() {
    loadCartSummary(); // Recarrega com novos totais
    setTimeout(function() {
      updateInstallmentsPreview(lastCartSummaryData); // Atualiza UI
      maybeFetchInstallmentQuotes(); // Busca novas cotações
    }, 200);
  }, 300);
});
```

---

## Fluxo de Finalização do Pedido

### 1. Serialização do Formulário

**Tema:** `checkout-steps.js` (linha ~2650)

```javascript
// Coleta todos os campos do formulário
const formDataObj = {};
$checkoutForm.find('input, select, textarea').each(function() {
  const $input = $(this);
  const name = $input.attr('name');
  // ... processa cada campo
});

// Inclui campos hidden de frete
// gstore_shipping_mode[cart_item_key]
// gstore_shipping_rates[cart_item_key]
```

### 2. Envio via AJAX

```javascript
$.ajax({
  type: 'POST',
  url: wc_checkout_params.checkout_url,
  data: formData,
  dataType: 'json',
  success: function(response) {
    if (response.result === 'success') {
      // Redireciona para checkout da Blu ou exibe Pix
    }
  }
});
```

### 3. Processamento no Plugin

**Plugin:** `class-gstore-blu-payment-gateway.php`

```php
public function process_payment($order_id) {
    $order = wc_get_order($order_id);
    
    // Cria link na API da Blu
    $response = $this->create_payment_link($order);
    
    // Armazena metadados
    $this->store_link_metadata($order, $response);
    
    // Redireciona
    return array(
        'result' => 'success',
        'redirect' => $response['smart_checkout_url']
    );
}
```

---

## Pontos de Integração

### 1. Hooks do WooCommerce

| Hook | Tema | Plugin | Descrição |
|------|------|--------|-----------|
| `woocommerce_checkout_fields` | ✅ | ❌ | Customiza campos do formulário |
| `woocommerce_cart_calculate_fees` | ❌ | ✅ | Adiciona frete e taxa de parcelamento |
| `woocommerce_checkout_update_order_review` | ✅ | ✅ | Sincroniza dados do POST |
| `woocommerce_review_order_before_payment` | ✅ | ❌ | Renderiza seletor de parcelas |

### 2. Endpoints AJAX

| Endpoint | Definido em | Consumido por |
|----------|-------------|---------------|
| `gstore_get_cart_summary` | Plugin | Tema |
| `gstore_calculate_shipping` | Plugin | Tema |
| `gstore_blu_get_product_installment_quotes` | Tema | Tema |

### 3. Sessão do WooCommerce

| Chave | Definida por | Lida por |
|-------|--------------|----------|
| `gstore_shipping_fee_value` | Plugin | Plugin |
| `gstore_shipping_fee_method` | Plugin | Plugin |
| `gstore_blu_installments` | Tema/Plugin | Tema/Plugin |
| `chosen_payment_method` | Tema | Tema/Plugin |

---

## Debugging e Logs

### Logs do Plugin

**Localização:** `wp-content/debug.log`

**Formato:**
```
[26-Jan-2026 03:01:23 UTC] GSTORE_DEBUG {"sessionId":"...","location":"...","message":"...","data":{...}}
```

### Logs do Tema

**Localização:** `wp-content/uploads/gstore-debug-logs/debug.log`

**Formato:** NDJSON (uma linha JSON por entrada)

**Acesso via Admin:**
- WordPress Admin → Gstore Debug Logs

---

## Considerações Importantes

### 1. Dependência entre Tema e Plugin

- O tema **depende** do plugin para funcionar corretamente
- O plugin pode funcionar sem o tema (mas sem UI customizada)
- Endpoints AJAX do plugin são essenciais para o tema

### 2. Sincronização de Estado

- Estado é mantido na **sessão do WooCommerce** (plugin)
- Tema sincroniza via **campos hidden** no formulário
- Ambos devem estar alinhados para evitar inconsistências

### 3. Performance

- Cálculos pesados ficam no **plugin** (backend)
- Tema apenas **renderiza** e **sincroniza** dados
- Cache de rates de frete no frontend para evitar requisições desnecessárias

---

## Referências

- [Hooks do Checkout](./hooks-checkout.md)
- [Blu Checkout](./blu-checkout.md)
- [Blu Pix](./blu-pix.md)
- [Organização Tema e Plugins](./organizacao-tema-e-plugins.md)
- [Comprar Agora](./compraragora.md)

---

**Última atualização:** Janeiro 2026  
**Versão:** 1.0

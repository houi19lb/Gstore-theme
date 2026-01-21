Correção: Frete não atualiza no "Comprar Agora"
Problema Original
Quando o cliente usa "Comprar Agora" (pula o carrinho e vai direto para checkout), o CEP digitado na Etapa 2 não atualiza o "ver detalhes" do frete.

Diagnóstico via Debug Mode
Através de instrumentação com logs, identificamos múltiplos problemas:

Problema 1: product_id faltando (H1 - CONFIRMADO)
O endpoint gstore_get_cart_summary não retornava o product_id dos itens.

Evidência do log:

{"location":"get_cart_summary:items_built","data":{"items_ids":[12579]}}
✅ Corrigido - product_id agora é retornado.

Problema 2: Frete não persistido na sessão (H6/H7 - CONFIRMADO)
O endpoint ajax_calculate_shipping calculava o frete corretamente, mas não persistia na sessão do WooCommerce.

Evidência do log:

{"location":"ajax_calculate_shipping:success","data":{"rates":[{"id":"gstore_custom_shipping:ground","cost":330}]}}
{"location":"get_cart_summary:totals","data":{"shipping_total":0,"shipping_fee":null}}
O frete era calculado (330), mas shipping_total continuava 0.

✅ Corrigido - Agora o endpoint:

Atualiza o CEP na sessão do cliente
Salva o valor do frete na sessão (gstore_shipping_fee_value)
Adiciona o frete como fee no carrinho via hook woocommerce_cart_calculate_fees
Problema 3: Frontend sem dados de shipping (H8 - CONFIRMADO)
A resposta do get_cart_summary não incluía informações suficientes sobre frete para o JavaScript.

✅ Corrigido - Resposta agora inclui:

{
  "shipping": {
    "chosen_method": "gstore_custom_shipping:ground",
    "rates": [
      {"id": "gstore_custom_shipping:ground", "label": "Terrestre", "cost": 330},
      {"id": "gstore_custom_shipping:air", "label": "Aéreo", "cost": 770}
    ],
    "value": 330,
    "formatted": "R$ 330,00"
  },
  "totals": {
    "shipping": "R$ 330,00",
    "shipping_raw": 330
  }
}
Arquivos Modificados
1. includes/ajax/class-gstore-core-ajax-handlers.php
Alterações:

Adicionado product_id ao array de itens
Adicionado shipping_raw nos totais
Nova seção shipping na resposta com rates, valor e método escolhido
Método write_debug_log atualizado para gravar em wp-content
2. includes/shipping/class-gstore-core-shipping-hooks.php
Alterações:

Novo hook woocommerce_cart_calculate_fees → add_shipping_fee_from_session()
Endpoint ajax_calculate_shipping agora persiste o frete na sessão:
Atualiza CEP do cliente
Salva gstore_shipping_fee_value e gstore_shipping_fee_method na sessão
Força recálculo do carrinho
Status Atual
| Componente | Status |

|------------|--------|

| Backend - Cálculo de frete | ✅ Funcionando |

| Backend - Persistência na sessão | ✅ Funcionando |

| Backend - Resposta com dados de shipping | ✅ Funcionando |

| Frontend - Exibir no "ver detalhes" | ⏳ Pendente (JavaScript do tema) |

| Frontend - Selecionar método de frete | ⏳ Pendente (JavaScript do tema) |

Próximos Passos
O backend está completo. O JavaScript do tema (checkout-steps.js) precisa ser atualizado para:

Ler os dados de shipping da resposta do gstore_get_cart_summary:
const shippingData = response.data.shipping;
const shippingValue = shippingData.value; // 330
const shippingRates = shippingData.rates; // [{id, label, cost}, ...]
Exibir as opções de frete para o usuário selecionar entre terrestre/aéreo
Atualizar o "ver detalhes" com o valor do frete selecionado
Fluxo Corrigido
WC Session
Plugin Ajax Handler
Tema checkout-steps.js
Cliente
WC Session
Plugin Ajax Handler
Tema checkout-steps.js
Cliente
Clica Comprar Agora
loadCartSummary
items COM product_id
Digita CEP na Etapa 2
gstore_calculate_shipping
Salva gstore_shipping_fee_value
rates (ground: 330, air: 770)
loadCartSummary
Lê fee e adiciona ao cart
shipping.value=330, shipping.rates=[...]
Atualiza "ver detalhes" ✓
Logs de Debug
Para remover a instrumentação após confirmar que está funcionando, remover os blocos // #region agent log ... // #endregion dos arquivos:

includes/ajax/class-gstore-core-ajax-handlers.php
includes/shipping/class-gstore-core-shipping-hooks.php
includes/blu/class-gstore-blu-checkout-handler.php
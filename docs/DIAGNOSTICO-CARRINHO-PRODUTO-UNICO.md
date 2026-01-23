# Diagnóstico: Carrinho esvaziando no produto único

## Contexto do problema
Ao clicar em **"Adicionar ao carrinho"** na página de produto único, o item aparecia no mini‑cart e logo era removido. No catálogo isso não ocorria.

## Causa raiz confirmada
O carrinho era esvaziado por um **AJAX** chamado logo após o add‑to‑cart:

- **Ação AJAX:** `gstore_blu_get_product_installment_quotes`
- **Endpoint:** `wp-admin/admin-ajax.php`
- **Comportamento:** a função executada pelo plugin chamava `WC()->cart->empty_cart()`
- **Stack confirmado em runtime:** `Gstore_Core_Blu_Checkout_Handler->ajax_product_installment_quotes`

Isso limpava os cookies do carrinho (`woocommerce_cart_hash` e `woocommerce_items_in_cart`) e deixava o carrinho vazio quando o usuário abria `/carrinho/`.

## O que foi feito (mudanças permanentes)
### 1) Forçar o handler do tema para o AJAX de parcelas
Para evitar que o handler do plugin continue esvaziando o carrinho, a ação AJAX foi **sobrescrita** no tema:

- **Arquivo:** `functions.php`
- **Função:** `gstore_override_blu_installment_ajax_handler()`
- **Resultado:** os callbacks do plugin para `gstore_blu_get_product_installment_quotes` são removidos e o handler do tema é registrado novamente.

Com isso, o AJAX de parcelas passa a executar `gstore_ajax_get_product_installment_quotes()` (tema), que **não** chama `empty_cart()`.

## O erro que estava sendo contornado no AJAX
O AJAX de parcelas serve apenas para **calcular preços parcelados** na página de produto. Ele **não deveria** alterar o carrinho.  
Porém, o handler do plugin (`Gstore_Core_Blu_Checkout_Handler->ajax_product_installment_quotes`) chamava `WC()->cart->empty_cart()` durante essa requisição, o que:

- removia o item recém‑adicionado;
- limpava cookies do carrinho;
- fazia o carrinho real ficar vazio ao abrir `/carrinho/`.

## Como o problema se manifestava
1. Usuário clicava em **Adicionar ao carrinho** no produto único.
2. Logo após o add‑to‑cart, disparava a ação AJAX `gstore_blu_get_product_installment_quotes`.
3. O plugin esvaziava o carrinho e o mini‑cart “zerava”.

## Arquivos e mudanças específicas
- **`functions.php`**
  - `gstore_override_blu_installment_ajax_handler()`:
    - remove callbacks do plugin para `gstore_blu_get_product_installment_quotes`;
    - registra novamente o handler do tema.
  - Handler que permanece ativo:
    - `gstore_ajax_get_product_installment_quotes()` (tema) — apenas calcula parcelas, **não altera o carrinho**.

## Observação sobre o mini‑cart
O mini‑cart atualiza com fragments e Store API. Quando o carrinho era esvaziado pelo AJAX do plugin, os cookies eram limpos e a Store API passava a devolver carrinho vazio.

## Instrumentações temporárias
Foram adicionados logs e backtraces para identificar a ação responsável e confirmar a origem do `empty_cart()`.  
Todos os logs foram removidos após a confirmação da causa.

## Resultado esperado
- Adicionar ao carrinho no produto único mantém o item no carrinho real.
- O mini‑cart e a página `/carrinho/` ficam consistentes.

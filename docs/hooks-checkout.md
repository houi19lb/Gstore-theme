# Documentação de Hooks: Checkout Gstore (Finalizar Compra)

Este documento detalha os hooks (ações e filtros) utilizados na página de checkout do tema Gstore, incluindo as customizações específicas do tema e os pontos de ancoragem padrão do WooCommerce.

---

## 1. Customizações Específicas do Tema Gstore

Estas funções estão localizadas principalmente no arquivo `functions.php` e gerenciam a lógica exclusiva da loja.

### Interface e Layout
| Hook | Função | Descrição |
| :--- | :--- | :--- |
| `the_content` | `gstore_ensure_checkout_block` | Garante a exibição do shortcode de checkout se a página estiver vazia. |
| `the_content` | `gstore_force_classic_checkout` | Força o Checkout Clássico para compatibilidade com campos como CPF. |
| `render_block` | `gstore_wrap_checkout_summary` | Envolve o resumo do pedido no card estilizado com ícones de confiança. |
| `wp_enqueue_scripts` | `gstore_enqueue_checkout_assets` | Carrega CSS/JS de etapas, PIX, Auto-fill de CEP e Calculadora. |
| `init` | `gstore_move_privacy_policy_text` | Move o texto de privacidade para baixo do botão de finalização. |

### Campos e Validação
| Hook | Função | Descrição |
| :--- | :--- | :--- |
| `woocommerce_checkout_fields` | `gstore_customize_checkout_fields` | Remove país, move o CEP para o topo e ajusta obrigatoriedade (Blu). |
| `woocommerce_billing_fields` | `gstore_add_cpf_field` | Adiciona o campo de CPF ao formulário de cobrança. |
| `woocommerce_validate_postcode` | `gstore_validate_postcode_optional` | Torna o CEP opcional no pré-checkout quando usando Blu Checkout. |

### Pagamento e Parcelamento (Blu)
| Hook | Função | Descrição |
| :--- | :--- | :--- |
| `woocommerce_available_payment_gateways` | `gstore_blu_only_gateway` | Filtra para exibir apenas métodos Blu (Cartão e PIX). |
| `woocommerce_review_order_before_payment` | `gstore_blu_render_installments` | Renderiza o seletor de parcelas customizado na UI. |
| `woocommerce_cart_calculate_fees` | `gstore_blu_add_installment_fee` | Calcula e adiciona a taxa de juros do parcelamento ao total. |
| `woocommerce_checkout_update_order_meta` | `gstore_blu_save_installments_meta` | Salva os dados de parcelamento e CPF nos metadados do pedido. |

---

## 2. Hooks de Exibição (Ações Padrão)

Hooks utilizados para inserir conteúdo visual em posições estratégicas do formulário.

### Blocos de Revisão do Pedido
*   `woocommerce_checkout_before_order_review`: Executa antes do início do bloco de revisão (subtotal/total).
*   `woocommerce_checkout_order_review`: Renderiza a revisão inteira (ponto principal do checkout).
*   `woocommerce_checkout_before_order_review_heading`: Antes do título "Seu pedido".
*   `woocommerce_checkout_after_order_review_heading`: Depois do título "Seu pedido".
*   `woocommerce_checkout_after_order_review`: Após o conteúdo completo do bloco de revisão.

### Internos da Tabela de Totais
*   `woocommerce_review_order_before_cart_contents`: Antes da lista de produtos.
*   `woocommerce_review_order_after_cart_contents`: Depois da lista de produtos.
*   `woocommerce_review_order_before_shipping`: Antes das opções de frete.
*   `woocommerce_review_order_after_shipping`: Depois das opções de frete.
*   `woocommerce_review_order_before_order_total`: Antes da linha do valor total (Total do Pedido).
*   `woocommerce_review_order_after_order_total`: Depois da linha do valor total.
*   `woocommerce_review_order_before_payment`: Antes da lista de métodos de pagamento (usado pela Gstore para parcelas).
*   `woocommerce_review_order_after_payment`: Depois dos métodos de pagamento e botão finalizar.

---

## 3. Hooks de Valores e Formatação (Filtros)

Utilizados para modificar textos, cálculos ou o HTML de valores específicos.

### Valores de Carrinho/Checkout
*   `woocommerce_cart_subtotal`: Altera o valor do subtotal.
*   `woocommerce_cart_total`: Altera o valor total final.
*   `woocommerce_get_formatted_order_total`: Altera o formato do total (ex: incluir "em até 12x").

### HTML de Totais
*   `woocommerce_cart_totals_order_total_html`: Altera o HTML da linha de total.
*   `woocommerce_cart_totals_subtotal_html`: Altera o HTML da linha de subtotal.
*   `woocommerce_cart_totals_shipping_html`: Altera o HTML da exibição do frete.
*   `woocommerce_cart_totals_coupon_html`: Altera o HTML de exibição de cupons.
*   `woocommerce_cart_totals_fee_html`: Altera o HTML das taxas (como a taxa de parcelamento da Blu).

---

## 4. Hooks de Gateways e Métodos de Pagamento

Específicos para a interação com os meios de pagamento.

*   `woocommerce_gateway_title`: Filtro para alterar o nome do gateway na lista (ex: de "Blu" para "Cartão de Crédito").
*   `woocommerce_gateway_description`: Filtro para alterar a descrição que aparece abaixo do título do método.
*   `woocommerce_payment_successful_result`: Filtro para alterar o redirecionamento após a compra ser processada.

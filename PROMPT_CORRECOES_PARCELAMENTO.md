# Prompt: Correções do Sistema de Parcelamento

## Contexto

O sistema de parcelamento usa dropdown interativo no produto, com cálculo **sem juros** no tema e aplicação de taxa **somente no checkout** (regra `fee_only`). A função `initProductInstallmentQuotes()` em `assets/js/single-product.js` gerencia as requisições AJAX e popula o dropdown.

## Problemas Identificados

### Problema 1: Dropdown não funciona quando produto está indisponível

**Situação:** Quando um produto está sem estoque (out of stock), o dropdown de parcelas não é populado ou não funciona corretamente.

**Localização:** 
- `assets/js/single-product.js` - função `initProductInstallmentQuotes()`
- `woocommerce/content-single-product.php` - template do buybox

**Causa provável:** 
- A função pode estar verificando disponibilidade do produto antes de fazer a requisição AJAX
- O endpoint PHP pode estar retornando erro quando produto está indisponível
- O wrapper `[data-gstore-installment-wrapper]` pode não estar sendo encontrado em produtos indisponíveis

**Ação necessária:**
1. Verificar se `initProductInstallmentQuotes()` está sendo chamada mesmo quando produto está indisponível
2. Verificar se o endpoint `Gstore_Core_Blu_Checkout_Handler->ajax_product_installment_quotes()` (plugin) trata produtos indisponíveis corretamente
3. Garantir que o dropdown funcione mesmo quando `buybox` tem classe `is-out-of-stock`
4. Testar com produto sem estoque e verificar se as quotes são calculadas corretamente (mesmo sem estoque, o preço e parcelamento devem ser calculáveis)

### Problema 2: Cards de produtos não usam função sincronizada com variáveis

**Situação:** Os cards de produtos (loops/catálogo) ainda não estão usando o sistema AJAX de parcelamento que sincroniza com variações de produto.

**Localização:**
- `woocommerce/content-product.php` - template dos cards
- Possivelmente arquivos CSS relacionados: `assets/css/components/product-card.css`

**Causa provável:**
- Os cards estão usando cálculo estático de parcelamento (linha ~25 em `content-product.php`)
- Não há atributos `data-gstore-installment-target="1"` nos cards
- Não há chamada para `initProductInstallmentQuotes()` ou função similar para cards
- Cards não têm wrapper `[data-gstore-installment-wrapper]` e select dropdown

**Ação necessária:**
1. Adicionar atributos `data-gstore-installment-target="1"` nos elementos de parcelamento dos cards
2. Adicionar wrapper `[data-gstore-installment-wrapper]` e select `[data-gstore-installment-select]` nos cards (similar ao que foi feito em `content-single-product.php`)
3. Criar função similar a `initProductInstallmentQuotes()` mas para cards, ou adaptar a existente para funcionar em ambos os contextos
4. Garantir que a função funcione com produtos variáveis nos cards (usar preço mínimo ou preço da variação selecionada)
5. Considerar performance: evitar muitas requisições AJAX simultâneas quando há muitos cards na página

## Estrutura Atual

### Arquivos relevantes:

1. **`assets/js/single-product.js`** (linha ~141-507)
   - Função `initProductInstallmentQuotes()` - gerencia parcelamento na página single
   - Função `populateInstallmentSelect()` - popula dropdown
   - Função `setupInstallmentSelectListeners()` - gerencia seleção
   - Cache `quotesCache` - armazena todas as quotes

2. **`woocommerce/content-single-product.php`** (linha ~717-727)
   - Template com wrapper e select dropdown
   - Atributos `data-gstore-installment-target="1"`, `data-product-id`, etc.

3. **`includes/blu/class-gstore-blu-checkout-handler.php`**
   - Endpoint AJAX `ajax_product_installment_quotes()`
   - Calcula quotes de parcelamento via plugin

4. **`woocommerce/content-product.php`** (linha ~24-28)
   - Cálculo estático de parcelamento nos cards
   - **NECESSITA ATUALIZAÇÃO**

## Instruções de Implementação

### Para Problema 1 (Produto Indisponível):

1. **Verificar endpoint PHP (plugin):**
   ```php
   // Em includes/blu/class-gstore-blu-checkout-handler.php, método ajax_product_installment_quotes()
   // Garantir que funciona mesmo quando:
   // - $product->is_in_stock() === false
   // - $product->get_stock_status() === 'outofstock'
   ```

2. **Verificar JavaScript:**
   ```javascript
   // Em assets/js/single-product.js
   // Verificar se initProductInstallmentQuotes() é chamada
   // mesmo quando buybox tem classe 'is-out-of-stock'
   ```

3. **Testar cenários:**
   - Produto simples sem estoque
   - Produto variável com todas variações sem estoque
   - Produto variável com algumas variações sem estoque

### Para Problema 2 (Cards de Produtos):

1. **Modificar template de cards:**
   - Adicionar estrutura similar ao single product:
     ```php
     <div class="price-sub-wrapper" data-gstore-installment-wrapper>
         <div class="price-sub" data-gstore-installment-target="1" 
              data-product-id="<?php echo $product->get_id(); ?>"
              data-max-installments="21">
             <!-- Texto inicial -->
         </div>
         <select class="price-sub-installments-select" 
                 data-gstore-installment-select 
                 style="display: none;">
             <option value="">Carregando...</option>
         </select>
     </div>
     ```

2. **Criar/adaptar função JavaScript:**
   - Opção A: Adaptar `initProductInstallmentQuotes()` para funcionar em cards também
   - Opção B: Criar `initProductCardInstallmentQuotes()` específica para cards
   - Considerar debounce/throttle para evitar muitas requisições simultâneas

3. **Tratar produtos variáveis nos cards:**
   - Usar preço mínimo (`get_variation_price('min')`) para cálculo inicial
   - Quando usuário interagir com card, pode fazer requisição específica

4. **Performance:**
   - Considerar lazy loading: só fazer requisição quando card entra em viewport
   - Ou fazer requisição apenas quando usuário interage com o card
   - Cache compartilhado entre single e cards

## Critérios de Sucesso

### Problema 1 resolvido quando:
- [ ] Dropdown funciona em produtos sem estoque
- [ ] Quotes são calculadas corretamente mesmo sem estoque
- [ ] Não há erros no console quando produto está indisponível

### Problema 2 resolvido quando:
- [ ] Cards de produtos mostram dropdown de parcelas
- [ ] Dropdown nos cards é populado via AJAX (não cálculo estático)
- [ ] Funciona com produtos variáveis (usa preço correto)
- [ ] Performance é aceitável (não trava página com muitos cards)
- [ ] Cache é compartilhado entre single e cards quando possível

## Notas Adicionais

- Manter compatibilidade com sistema existente
- Testar em diferentes cenários: produtos simples, variáveis, com/sem estoque
- Verificar responsividade do dropdown em mobile

## Referências

- Endpoint AJAX: `includes/blu/class-gstore-blu-checkout-handler.php`
- Função JavaScript: `assets/js/single-product.js` linha ~141
- Template Single: `woocommerce/content-single-product.php` linha ~717
- Template Cards: `woocommerce/content-product.php` linha ~24

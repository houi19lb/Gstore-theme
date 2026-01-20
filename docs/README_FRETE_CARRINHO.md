# Cálculo de frete no carrinho e persistência do radio

Este documento descreve os ajustes feitos para o cálculo de frete no carrinho, persistência do modo selecionado e estabilidade da UI durante atualizações AJAX.

## Objetivo

- Fazer o cálculo de frete no carrinho funcionar via AJAX.
- Manter o radio de envio selecionado (ex.: Aéreo) mesmo após atualização do carrinho.
- Evitar recarregamentos visuais (piscadas) e regressões no botão de finalizar.
- Garantir que o frete só apareça após confirmação do CEP.
- Manter subtotal de item e total do carrinho sincronizados após alterar quantidade.

## O que foi implementado

### 1) Cálculo de frete no carrinho (AJAX)

- O calculador de frete do carrinho usa o endpoint `gstore_calculate_shipping`, que retorna apenas JSON com as tarifas.
- O carrinho renderiza as opções de frete a partir desse JSON no front-end.
- O fluxo evita render HTML do servidor para esse passo, então a UI é montada no `assets/js/cart.js`.
 - Após o cálculo, o botão de finalizar é liberado automaticamente (sem exigir nova seleção manual do radio).

### 2) Persistência do radio selecionado

Como o AJAX de frete retorna apenas JSON, o `checked` não vem do servidor. A persistência foi resolvida no front-end:

- O modo selecionado é salvo no `localStorage` por item do carrinho.
- Quando o carrinho é re-renderizado (AJAX), o modo salvo é reaplicado.
- Se o modo salvo não existir mais, o primeiro rate disponível vira o default.

### 3) Confirmação de frete por sessão

- O CEP pode continuar salvo (localStorage) para facilitar a digitação.
- A confirmação do cálculo de frete é controlada por sessão (sessionStorage).
- Assim, ao recarregar a página, o frete não reaparece automaticamente sem o usuário clicar em **Calcular frete**.

### 4) Atualização do carrinho sem “piscar”

- Ao alterar quantidade, o carrinho é atualizado via AJAX.
- Em vez de substituir todo o HTML, foi implementada uma atualização cirúrgica:
  - Atualiza preços e subtotais dos itens.
  - Atualiza quantidades e limites (`max`) retornados pelo servidor.
  - Mantém o bloco de totais visível (evita sumiço).

### 5) Totais e subtotal por item

- O subtotal do item no card é atualizado ao mudar quantidade.
- O subtotal e total do carrinho continuam sendo recalculados no resumo.

## Arquivo alterado

- `assets/js/cart.js`

## Principais ajustes

- **Persistência do modo selecionado**:
  - Salvamento por item do carrinho em `localStorage`.
  - Recuperação do modo salvo ao re-renderizar as opções.
- **Fallback seguro**:
  - Caso o modo salvo não exista mais, seleciona o primeiro rate disponível.
- **Controle de confirmação por sessão**:
  - O frete só aparece após o usuário clicar em **Calcular frete**.
- **Atualização de UI sem substituições pesadas**:
  - Atualiza elementos específicos (itens, subtotais e totais).
  - Evita piscadas e desaparecimento do resumo.

## Como testar

1. No carrinho, clique em **Calcular frete**.
2. Selecione **Frete Aéreo**.
3. Aguarde o update AJAX do carrinho.
4. O radio **Aéreo** deve permanecer selecionado.
5. Altere a quantidade e verifique:
   - Subtotal do item atualizado.
   - Total do carrinho atualizado.
   - Resumo não some e não “pisca”.
6. Recarregue a página e verifique:
   - CEP permanece digitado.
   - Opções de frete não aparecem até clicar em **Calcular frete**.

## Observações

- O cálculo do frete no carrinho é feito por item e atualizado via AJAX.
- A persistência é 100% front-end, pois o endpoint de cálculo retorna apenas JSON.

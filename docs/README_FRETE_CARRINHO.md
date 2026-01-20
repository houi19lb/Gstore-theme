# Cálculo de frete no carrinho e persistência do radio

Este documento descreve o que foi ajustado para o cálculo de frete no carrinho e para manter o modo de envio selecionado após atualizações AJAX.

## Objetivo

- Fazer o cálculo de frete no carrinho funcionar via AJAX.
- Manter o radio de envio selecionado (ex.: Aéreo) mesmo após atualização do carrinho.

## O que foi implementado

### 1) Cálculo de frete no carrinho (AJAX)

- O calculador de frete do carrinho usa o endpoint `gstore_calculate_shipping`, que retorna apenas JSON com as tarifas.
- O carrinho renderiza as opções de frete a partir desse JSON no front-end.
- O fluxo evita render HTML do servidor para esse passo, então a UI é montada no `assets/js/cart.js`.

### 2) Persistência do radio selecionado

Como o AJAX de frete retorna apenas JSON, o `checked` não vem do servidor. A persistência foi resolvida no front-end:

- O modo selecionado é salvo no `localStorage` por item do carrinho.
- Quando o carrinho é re-renderizado (AJAX), o modo salvo é reaplicado.
- Se o modo salvo não existir mais, o primeiro rate disponível vira o default.

## Arquivo alterado

- `assets/js/cart.js`

## Principais ajustes

- **Persistência do modo selecionado**:
  - Salvamento por item do carrinho em `localStorage`.
  - Recuperação do modo salvo ao re-renderizar as opções.
- **Fallback seguro**:
  - Caso o modo salvo não exista mais, seleciona o primeiro rate disponível.

## Como testar

1. No carrinho, clique em **Calcular frete**.
2. Selecione **Frete Aéreo**.
3. Aguarde o update AJAX do carrinho.
4. O radio **Aéreo** deve permanecer selecionado.

## Observações

- O cálculo do frete no carrinho é feito por item e atualizado via AJAX.
- A persistência é 100% front-end, pois o endpoint de cálculo retorna apenas JSON.

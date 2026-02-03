## Handoff: Checkout Pix/Cartao (persistencia)

### Contexto
O checkout em 3 etapas do tema `gstore` usa `checkout-steps.js` para UI e o plugin para regras de negocio. O resumo do pedido e taxas dependem do `payment_method` enviado no `update_checkout` e no AJAX `gstore_get_cart_summary`.

### Problema atual (ainda ocorre)
Ao selecionar **Pix** na etapa 1 e avancar para a etapa 2, os detalhes volta a exibir **Cartao**. O usuario reporta que o problema persiste mesmo apos ajustes.

Exemplo de trecho fornecido:
- DOM path: `main#wp--skip-link--target` (shell do checkout com resumo)
- Resumo mostra "Pagamento Cartao" após avancar etapas.

### O que foi feito (mudancas no codigo)
1. Persistencia do metodo de pagamento:
   - Adicionado `persistSelectedPaymentMethod()` para manter `payment_method` via hidden input e radios.
   - Garantia de persistencia ao:
     - mudar metodo (`change` em `input[name="payment_method"]`)
     - avancar etapa (`nextStep`)
     - trocar etapa (`setActiveStep`)
   - Fallback hidden recebe id fixo para evitar erro de selector do WooCommerce:
     - `id="gstore_payment_method_fallback"` e `data-gstore-fallback="1"`.

2. Seletor seguro:
   - Evitado selector dinamico com valor no query (`[value="..."]`) para não gerar erro de jQuery
     quando o valor contem caracteres inesperados. Passou a filtrar por `.val()`.

Arquivos alterados:
- `wp-content/themes/gstore/assets/js/checkout-steps.js`
- `wp-content/plugins/Plugin GStore White Label/tests/e2e/checkout-pix.spec.js` (novo teste E2E)

### Testes automatizados executados
Comandos:
```
WP_BASE_URL=http://localhost:10005 npx playwright test tests/e2e/checkout-pix.spec.js
```

Resultados:
- 2 testes passaram:
  - Pix persiste apos mudanca de frete
  - Cartao persiste apos mudanca de frete

### O que ainda precisamos melhorar
1. Garantir que **Pix continue selecionado ao entrar na etapa 2**:
   - O resumo continua mudando para Cartao ao trocar etapa.
   - Possivel causa: algum `update_checkout` do WooCommerce apos mover etapas,
     sobrescrevendo a selecao ou ignorando o hidden.

2. Atualizar testes E2E para cobrir o bug real:
   - Adicionar passo: selecionar Pix -> clicar em "Continuar" -> validar resumo na etapa 2.
   - Confirmar que o label "Pagamento" permanece "Pix" apos a troca de etapa.

### Proximos passos sugeridos para o novo agente
- Instrumentar `checkout-steps.js` com logs temporarios para:
  - valor de `lastSelectedPaymentMethod`
  - valor atual de `resolveSelectedPaymentMethod()`
  - payload enviado no `update_checkout`
  - resposta do AJAX `gstore_get_cart_summary`
- Verificar se existe outro JS (WooCommerce `checkout.min.js`) trocando a selecao apos o
  `update_checkout` ou ao mover o DOM para a etapa 2.
- Expandir o teste Playwright para reproduzir o bug da etapa 2 (passo a passo).

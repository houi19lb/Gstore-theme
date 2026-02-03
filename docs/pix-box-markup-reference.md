# Pix Box – markup atual (plugin)

## Contexto rápido
- A saída do Pix é gerada pelo plugin (backend).
- O tema cuida do estilo e do countdown/cópia via JS.

**CSS:** `assets/css/checkout-pix.css`  
**JS:** `assets/js/checkout-pix.js`

---

## Estrutura HTML (referência)

```html
<div class="pix-box pix-box--pending"
     data-expires-at="1738166400"
     data-order-status="pending">
  <div class="pix-box__header">
    <div>
      <h2 class="pix-box__title">Pagamento via Pix</h2>
      <p class="pix-box__subtitle">Escaneie o QR Code ou copie o código abaixo.</p>
      <div class="pix-box__countdown" data-role="pix-countdown"></div>
      <div class="pix-box__expired-message" data-role="pix-expired-message">
        Este PIX expirou. Por favor, realize um novo pedido.
      </div>
    </div>
    <span class="pix-box__status">Aguardando pagamento</span>
  </div>

  <div class="pix-box__content">
    <div class="pix-box__qr">
      <img src="data:image/png;base64,..." alt="QR Code para pagamento Pix" />
      <p class="pix-box__expires">Válido até: <strong>01/01/2026</strong></p>
    </div>

    <div class="pix-box__details">
      <div class="pix-box__amount">
        <span>Total do pedido</span>
        <strong>R$ 129,90</strong>
      </div>

      <label class="pix-box__label" for="pixCode-123">Código Pix (copiar e colar)</label>
      <div class="pix-box__code-group">
        <textarea id="pixCode-123" class="pix-box__code" readonly>000201...</textarea>
        <button type="button" class="pix-box__copy" data-copy-target="#pixCode-123">
          Copiar código
        </button>
      </div>

      <p class="pix-box__meta">Token: ...</p>
      <p class="pix-box__meta pix-box__meta--muted">Status: ...</p>
    </div>
  </div>
</div>
```

---

## Atributos importantes

| Atributo | Obrigatório | Descrição |
|----------|-------------|-----------|
| `data-expires-at` | Sim | Timestamp Unix (segundos) do vencimento. |
| `data-order-status` | Não | Status do pedido (para bloquear countdown). |

O `checkout-pix.js`:
- Atualiza o texto do countdown em `[data-role="pix-countdown"]`.
- Adiciona `pix-box--client-expired` quando expira.
- Oculta QR/código e exibe `[data-role="pix-expired-message"]`.

---

## Arquivos relacionados

| Arquivo | Uso |
|--------|-----|
| `assets/css/checkout-pix.css` | Estilos do Pix Box e countdown. |
| `assets/js/checkout-pix.js` | Countdown + copiar código (Pix Box). |

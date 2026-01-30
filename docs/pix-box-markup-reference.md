# Pix Box v2 – markup de referência (countdown + barra com curva)

Design: card com cabeçalho, countdown “Seu Pix expira em”, barra de progresso com curva (queda rápida no início) e código copia e cola.

**CSS:** `assets/css/components/pix-box.css`  
**Countdown/barra:** `assets/js/pix-box-countdown.js` (use `data-expires-at` e `data-total-seconds` no `.pix-urgency`)

O checkout atual continua usando `.pix-box` antigo e `checkout-pix.css` até você trocar a saída do plugin por esta estrutura.

---

## Estrutura HTML

```html
<section class="pix-box-card" aria-label="Pagamento Pix">
  <div class="pix-box-card__head">
    <div class="pix-box-card__label">
      <strong>Countdown + progresso (curva)</strong>
      <span>Boa pra expiração curta (ex.: 15 min). Tempo fica “visível”.</span>
    </div>
    <span class="pix-box-pill">Pendente</span>
  </div>

  <div class="pix-box">
    <div class="pix-box__content">
      <div class="pix-qr">
        <img alt="QR Code Pix" src="data:image/png;base64,..." />
        <p class="pix-box-fineprint">Abra o app do banco e pague pelo QR Code.</p>
      </div>

      <div class="pix-details">
        <div class="pix-amount">
          <span>Total</span>
          <strong>R$ 129,90</strong>
        </div>

        <div class="pix-urgency" data-expires-at="1738166400" data-total-seconds="900" role="status" aria-live="polite">
          <div class="pix-timer pix-timer--pulse">
            <div class="pix-timer__left">
              <strong>Seu Pix expira em</strong>
              <span>Finalize agora para garantir a reserva.</span>
            </div>
            <div class="pix-timer__right">15:00</div>
          </div>
          <div class="pix-bar" aria-hidden="true"><i></i></div>
        </div>

        <div class="pix-meta">
          <small><b>Código Pix (copia e cola)</b></small>
          <div class="pix-code-row">
            <textarea id="pixCode-123" readonly>00020126360014BR.GOV.BCB.PIX...</textarea>
            <button type="button" class="btn" data-copy-target="#pixCode-123">Copiar</button>
          </div>
          <small>Confirmação costuma cair em poucos segundos após o pagamento.</small>
        </div>
      </div>
    </div>
  </div>
</section>
```

---

## Atributos do countdown

No container `.pix-urgency`:

| Atributo | Obrigatório | Descrição |
|----------|-------------|-----------|
| `data-expires-at` | Sim | Timestamp Unix (segundos) em que o Pix expira. |
| `data-total-seconds` | Não | Duração total em segundos (padrão: 900 = 15 min). Usado para o cálculo da barra. |

O script `pix-box-countdown.js` procura todos os `.pix-urgency[data-expires-at]`, preenche `.pix-timer__right` com `MM:SS` e anima o `<i>` dentro de `.pix-bar` com a curva (GAMMA = 2,4). Ao chegar em zero dispara o evento `gstore_pix_countdown_finished`.

---

## Classes de status (pill)

- **`pix-box-pill`** – pendente (laranja)
- **`pix-box-pill pix-box-pill--processed`** – pago (verde)
- **`pix-box-pill pix-box-pill--expired`** – expirado (vermelho)

---

## Opcional: wrap e topbar (preview)

Para uma página de preview com título e hint:

```html
<div class="pix-box-wrap">
  <div class="pix-box-topbar">
    <h1>Pix Box v2 — Preview</h1>
    <p class="pix-box-hint">Barra com curva: cai mais rápido no começo e desacelera no fim.</p>
  </div>
  <section class="pix-box-card">...</section>
</div>
```

---

## Como usar no tema

1. **CSS** (onde for exibir o bloco):
   ```php
   wp_enqueue_style(
     'gstore-pix-box-v2',
     get_theme_file_uri( 'assets/css/components/pix-box.css' ),
     array(),
     wp_get_theme()->get( 'Version' )
   );
   ```

2. **Countdown + barra**:
   ```php
   wp_enqueue_script(
     'gstore-pix-box-countdown',
     get_theme_file_uri( 'assets/js/pix-box-countdown.js' ),
     array(),
     wp_get_theme()->get( 'Version' ),
     true
   );
   ```

3. **Botão copiar**: use `data-copy-target="#id-do-textarea"` no `.btn`. Para funcionar, o handler de copiar precisa tratar também `.pix-box .btn[data-copy-target]` (por exemplo em `checkout-pix.js` com seletor `.pix-box__copy, .pix-box .btn[data-copy-target]`).

4. **HTML**: gerar a estrutura acima com os dados do pedido (QR em base64, EMV, valor, `data-expires-at` com o timestamp que o plugin já grava em meta).

---

## Arquivos

| Arquivo | Uso |
|--------|-----|
| `assets/css/components/pix-box.css` | Estilos do Pix Box v2 (card, timer, barra, código). |
| `assets/js/pix-box-countdown.js` | Countdown em MM:SS + barra com curva. |
| `assets/css/checkout-pix.css` | Estilos do checkout atual (`.pix-box` antigo). |

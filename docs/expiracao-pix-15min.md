# Onde e como a expiração do Pix (15 min) é configurada e renderizada

**Repositório:** Este documento está no diretório do **tema** gstore (`wp-content/themes/gstore/`). A **seção 1** refere-se ao código do tema (este workspace); a **seção 2** ao plugin GStore White Label (outro diretório, ex.: `wp-content/plugins/gstore-core/`). Assim fica claro ao abrir só o tema ou só o plugin qual parte da doc se aplica a cada um.

Validação da documentação sobre onde e como a expiração do Pix (15 min) é configurada e renderizada, confrontando com o código do tema gstore e esclarecendo a divisão plugin vs tema.

---

## 1. O que existe no tema gstore (este workspace)

### Gateway Pix no tema não é carregado

- O tema possui [inc/class-gstore-blu-pix-gateway.php](../inc/class-gstore-blu-pix-gateway.php), mas **não há** `require`/`include` desse arquivo em [functions.php](../functions.php). O gateway Pix ativo em produção vem do **plugin** (gstore-core).
- O tema só referencia o ID `blu_pix` (ex.: functions.php ~3265, ~3296, ~4805) para regras de checkout e labels.

### Versão do gateway no tema (não ativa)

- **Expiração:** `expiration_days` (campo "Dias de expiração", default 1), não `expiration_minutes`.
- **Meta de expiração:** `META_EXPIRES_AT` = `_gstore_blu_pix_expires_at` (data YYYY-MM-DD), não timestamp Unix.
- **Frontend:** Em [inc/class-gstore-blu-pix-gateway.php](../inc/class-gstore-blu-pix-gateway.php) (~552–738), `output_pix_instructions()` renderiza:
  - `.pix-box` com "Válido até: [data]" em `.pix-box__expires`.
  - **Não** há `data-expires-at`, `[data-role="pix-countdown"]`, `[data-role="pix-expired-message"]` nem script de countdown "Expira em: X:XX".

Ou seja: no tema, a expiração é só por dia e sem countdown em minutos.

### O que o tema realmente usa (estilos e JS)

- **CSS:** [assets/css/checkout-pix.css](../assets/css/checkout-pix.css) — estiliza `.pix-box`, `.pix-box__expires`, `.pix-box__copy`, etc. Aplicado no checkout e em order-received/view-order (functions.php ~3082–3145, ~3118–3144).
- **JS:** [assets/js/checkout-pix.js](../assets/js/checkout-pix.js) — handler do botão `.pix-box__copy` (copiar código).
- **Script inline em functions.php:** ~3824–3845 — corrige o status exibido na `.pix-box` (só "paid" como aprovado); não trata expiração nem countdown.

Conclusão no tema: **quem renderiza a caixa Pix (e, no plugin, o countdown de 15 min) é o plugin**. O tema só estiliza e complementa (copy + ajuste de status).

---

## 2. O que fica no plugin (fora deste workspace)

O **plugin** (gstore-core) implementa:

- **Configuração:** WooCommerce → Pix Blu → "Expiração do Pix (minutos)" → `expiration_minutes` (default 15); opcionalmente `expiration_days` quando minutos = 0.
- **Backend:** Handler de cancelamento automático (Action Scheduler), meta `_gstore_blu_pix_expires_at_ts` (timestamp).
- **Frontend:** Em `output_pix_instructions()` do plugin: `data-expires-at`, countdown "Expira em: X:XX", classes de aviso (≤3 min, ≤1 min), mensagem "PIX expirado" e script que atualiza a cada 1 s.

Arquivos do plugin (não estão no diretório do tema): `includes/blu/class-gstore-blu-pix-gateway.php`, `class-gstore-blu-pix-expiration-handler.php`, loader.

---

## 3. Resumo: plugin vs tema

| Aspecto | Plugin (gstore-core) | Tema (gstore) |
|--------|----------------------|----------------|
| Gateway Pix ativo | Sim (loader carrega o gateway) | Não (classe existe mas não é carregada) |
| Expiração | `expiration_minutes` (15 min) + opcional dias | Apenas na classe não usada: `expiration_days` |
| Cancelamento automático | Handler + Action Scheduler + meta `_gstore_blu_pix_expires_at_ts` | Não participa |
| Countdown "Expira em: X:XX" | Sim, em `output_pix_instructions()` | Não (classe do tema não tem countdown) |
| "Válido até" / mensagem expirado | Sim | Só na classe não carregada (sem countdown) |
| Estilos `.pix-box` | Inline no gateway | [assets/css/checkout-pix.css](../assets/css/checkout-pix.css) |
| JS (ex.: copiar código) | Inline no gateway | [assets/js/checkout-pix.js](../assets/js/checkout-pix.js) + script de status em [functions.php](../functions.php) |

---

## 4. Se quiser mudar texto ou visual

- **Alterar textos/estrutura do countdown e expiração em 15 min:** no **plugin**, no método `output_pix_instructions()` de `includes/blu/class-gstore-blu-pix-gateway.php` (HTML, labels, script do countdown).
- **Alterar apenas estilos da caixa Pix:** no **tema**, em [assets/css/checkout-pix.css](../assets/css/checkout-pix.css).
- **Override do bloco (thank you / view order):** no tema, sobrescrever o template que exibe a área de pagamento; para manter o countdown do plugin, preservar `.pix-box`, `data-expires-at` e `[data-role="pix-countdown"]` (e a estrutura que o script do plugin espera). Se o tema substituir o bloco por outro sem essa estrutura, o countdown deixa de funcionar a menos que o tema implemente um equivalente.

---

## 5. Arquivos principais (resumo)

**No tema (este workspace):**

- [inc/class-gstore-blu-pix-gateway.php](../inc/class-gstore-blu-pix-gateway.php) — gateway **não carregado**; expiração em dias, sem countdown.
- [assets/css/checkout-pix.css](../assets/css/checkout-pix.css), [assets/js/checkout-pix.js](../assets/js/checkout-pix.js), trechos em [functions.php](../functions.php) (enqueue + script de status) — estilos e complementos ao bloco gerado pelo plugin.

**No plugin (fora do workspace):**

- Gateway com `expiration_minutes`, `output_pix_instructions()` (countdown + "Válido até" + "PIX expirado").
- Handler de cancelamento automático e meta `_gstore_blu_pix_expires_at_ts`.

---

## 6. O que precisa mudar para funcionar

### Já corrigido no plugin

- **Handler de expiração no bootstrap:** O handler do Action Scheduler (`gstore_blu_pix_check_expiration`) só era registrado quando um pedido Pix era feito. No request do cron, o hook disparava sem callback e o cancelamento automático nunca rodava. **Correção:** o loader do plugin passa a registrar o handler ao carregar (em `includes/class-gstore-core-loader.php`), para o hook existir em todo request, inclusive quando o cron executa.

### Exigências para tudo funcionar

1. **WooCommerce** ativo.
2. **Plugin** (GStore White Label) ativo; o **tema gstore** pode ou não estar ativo — o Pix é do plugin.
3. **Action Scheduler** (vem com WooCommerce) ou **WP-Cron** funcional, para o cancelamento automático rodar na hora da expiração.
4. **Pix Blu** ativado em WooCommerce → Pagamentos, com "Expiração do Pix (minutos)" configurado (ex.: 15).

### Opcional (melhorias)

- **Tema:** Se o tema fizer override dos templates de thank-you / view-order e remover ou alterar o bloco do Pix, o countdown do plugin pode quebrar. Para o "Expira em" seguir funcionando, manter no HTML a estrutura que o plugin espera: `.pix-box`, `data-expires-at` e `[data-role="pix-countdown"]`. Se o tema substituir o bloco pela nova estrutura (Pix Box v2 com `.pix-urgency`, etc.), é preciso que o plugin passe a gerar esse HTML ou que o tema injete o bloco com `data-expires-at` e use [assets/js/pix-box-countdown.js](../assets/js/pix-box-countdown.js).
- **Plugin:** `current_time( 'timestamp' )` está deprecado desde o WordPress 5.3+. Nos arquivos do Pix (gateway e handler) ainda é usado. Trocar por `time()` ou `current_datetime()->getTimestamp()` conforme o uso (UTC vs fuso do site).

## Objetivo deste documento

Este documento descreve **como o tema `gstore` está organizado hoje** e recomenda **o que deve permanecer no tema** vs **o que deve virar plugin**, com justificativas e um plano de migração seguro.

### Regra de ouro

- **Tema**: tudo que é **apresentação** (UI/UX) e depende fortemente de markup/CSS do tema.
- **Plugin**: tudo que é **regra de negócio**, **integrações**, **APIs**, **webhooks**, **cron**, **AJAX**, **cálculo de frete**, **gateway de pagamento**, **campos de checkout**, **alterações de fluxo WooCommerce**.

Se amanhã trocarmos o tema (ou criarmos um tema novo), o site deve continuar vendendo com: **pagamento**, **frete**, **checkout**, **regras do carrinho**, **webhooks** e **configurações** intactas. Isso só acontece se esse “core” estiver em plugin.

---

## Organização atual do repositório (visão prática)

### Pastas/arquivos de UI (ficam no tema)

- **`templates/` e `parts/`**: templates HTML do tema (Gutenberg/Block Theme “clássico” do projeto).
- **`woocommerce/`**: overrides/ajustes de templates do WooCommerce (quando usados).
- **`style.css`**: CSS do tema.
- **`assets/css/`, `assets/js/`**: scripts e estilos do frontend (UI).
- **`theme.json`**: configurações do tema (tipografia, cores, presets).

### Pastas/arquivos de “core” (hoje estão no tema, mas deveriam ser plugin)

O tema carrega funcionalidades tipicamente de plugin via `functions.php` e `inc/`, por exemplo:

- **Gateways Blu**
  - `inc/class-gstore-blu-payment-gateway.php` (checkout via link, webhooks, cron fallback)
  - `inc/class-gstore-blu-pix-gateway.php` (Pix, webhooks, render de instruções, consulta)
  - `inc/class-gstore-blu-payment-gateway-blocks.php` (integração com Woo Blocks)
  - `inc/blu-filter.php` (força gateways Blu no checkout)
- **Frete customizado**
  - `inc/class-gstore-shipping-method.php` (cálculo por “peso tático”, região)
  - `inc/class-gstore-shipping-admin.php` (tela admin e export/import do JSON)
  - `assets/json/shipping-rates.json` (dados de regra de frete)
- **Config/infra de loja**
  - `inc/class-gstore-store-info.php` + `store-info.json` (dados da loja em JSON, export/import)
- **Ferramentas admin**
  - `inc/class-gstore-admin.php` (regeneração de thumbnails)

### “Plugins dentro do tema” (anti-padrão)

Existem diretórios de plugin dentro do tema:

- `Plugin GStore/` (ex.: `gstore-optimizer.php`)
- `Plugin GStore White Label/` (ex.: `gstore-white-label.php`)

Isso funciona só porque o código está no filesystem, mas **não é o lugar correto** para plugins no WordPress.

- O local correto é **`wp-content/plugins/`** (ou **`wp-content/mu-plugins/`** se precisar sempre ativo).
- Se o tema for trocado ou atualizado por ZIP, esses “plugins” podem desaparecer.

---

## O que deve ficar no tema (e por quê)

### Deve ficar no tema

- **Templates / markup / layout**
  - `templates/`, `parts/`, `woocommerce/` (quando o objetivo é “desenhar” páginas)
- **Estilos e scripts de UI**
  - `style.css`, `assets/css/`, `assets/js/`
- **Ajustes de renderização puramente visuais**
  - filtros que só alteram HTML/CSS de blocos e componentes do tema
  - CSS crítico inline para above-the-fold (se for 100% acoplado ao layout atual)

### Motivo

Esses itens:
- mudam com frequência conforme a identidade visual
- são específicos do markup/classes do tema
- não precisam sobreviver à troca de tema (porque serão reescritos junto com a UI)

---

## O que deve virar plugin (e por quê)

### 1) Pagamentos Blu (Checkout por link + Pix)

**Mover para plugin** (ex.: `gstore-blu` ou dentro de um `gstore-core`):

- `inc/class-gstore-blu-payment-gateway.php`
- `inc/class-gstore-blu-pix-gateway.php`
- `inc/class-gstore-blu-payment-gateway-blocks.php`
- `inc/blu-filter.php`
- trechos do `functions.php` relacionados a:
  - captura/validação de parcelas e fee
  - endpoints AJAX (ex.: cotação de parcelas)
  - qualquer lógica de checkout que não seja “visual”

**Por quê**

- É **core de vendas**: se o tema cair/trocar, pagamento não pode parar.
- Contém **webhooks e cron**: isso é infra, não UI.
- Precisamos versionar e atualizar com segurança (hotfix), independente do tema.

### 2) Frete customizado “peso tático” + admin de frete

**Mover para plugin** (ex.: `gstore-shipping` ou `gstore-core`):

- `inc/class-gstore-shipping-method.php`
- `inc/class-gstore-shipping-admin.php`
- trechos do `functions.php` relacionados a:
  - injeção/seleção de método de envio
  - AJAX de cálculo de frete/CEP
  - regras auxiliares de região

**E principalmente: mover o dado para fora do tema**

Hoje o dado vive em `assets/json/shipping-rates.json` dentro do tema.
Recomendação: persistir em um destes lugares (na ordem mais segura):

- **`wp_options`** (com export/import no admin)  
ou
- **`wp-content/uploads/gstore/`** (JSON sob uploads, que sobrevive a troca de tema)

**Por quê**

- Regras de frete são **negócio**, não UI.
- Se trocar o tema, não podemos perder os valores.

### 3) “Store Info” (dados da loja) e sua gestão

**Mover para plugin** (ex.: `gstore-store-info` ou `gstore-core`):

- `inc/class-gstore-store-info.php`
- `store-info.json` (idealmente migrar para `wp_options` ou uploads)

**Por quê**

- Isso é **conteúdo/config** da loja, não deveria depender do tema.
- O tema só consome (renderiza) essas informações.

### 4) Ferramentas administrativas

**Mover para plugin**:

- `inc/class-gstore-admin.php` (regeneração de thumbnails)
- páginas de configuração “operacionais” (manutenção), que não têm relação com UI pública

**Por quê**

- Ferramentas de manutenção devem sobreviver a troca de tema.
- Facilita permissionamento, auditoria e compatibilidade.

### 5) Otimizações e white-label

Esses itens **já são plugins**, só estão no lugar errado:

- `Plugin GStore/gstore-optimizer.php`
- `Plugin GStore White Label/gstore-white-label.php`

**Ação recomendada**

- mover para `wp-content/plugins/` (ou `mu-plugins/` se necessário)
- ativar via painel do WP como plugins normais

---

## Proposta de arquitetura (simples e escalável)

### Tema (`gstore`)

Responsável por:

- `templates/`, `parts/`, `woocommerce/` (UI)
- `style.css`, `assets/` (UI)
- `functions.php` reduzido a:
  - `add_theme_support`
  - `enqueue` de CSS/JS do tema
  - filtros de renderização **puramente visuais**

### Plugins

Opção A (mais simples): **um plugin “core”**

- **`gstore-core`**
  - Blu Checkout + Blu Pix + Blocks integration
  - Frete customizado + admin de frete
  - Store Info + export/import
  - endpoints AJAX/REST necessários

Opção B (mais modular): **plugins por domínio**

- **`gstore-blu`** (checkout link + pix + webhooks)
- **`gstore-shipping`** (método de envio + admin + dados)
- **`gstore-store-info`** (dados da loja)
- **`gstore-optimizer`** (já existe)
- **`gstore-white-label`** (já existe)

---

## Plano de migração (sem quebrar produção)

### Fase 0 — Preparação (sem mudar comportamento)

- Mover `Plugin GStore/` e `Plugin GStore White Label/` para **`wp-content/plugins/`**.
- Confirmar que ativação e paths funcionam (URLs/`plugin_dir_path`, `plugin_dir_url`).

### Fase 1 — Criar plugin “core” e duplicar hooks (com fallback)

- Criar `wp-content/plugins/gstore-core/gstore-core.php`.
- Registrar as mesmas actions/filters que hoje estão no tema.
- No tema, manter wrappers temporários (se necessário) para não quebrar nada.

### Fase 2 — Migrar classes do `inc/` para o plugin

- Mover arquivos:
  - Blu gateways/blocks/filter
  - shipping method/admin
  - store info
  - admin tools
- Trocar chamadas de `get_theme_file_path()`/`get_theme_file_uri()` por caminhos do plugin quando forem “core”.

### Fase 3 — Migrar dados para fora do tema

- Frete: migrar `assets/json/shipping-rates.json` para `wp_options` ou `uploads`.
- Store Info: migrar `store-info.json` para `wp_options` ou `uploads`.
- Criar rotina de migração “se existir JSON no tema e não existir dado no destino, importar”.

### Fase 4 — Enxugar `functions.php`

Remover do tema:

- endpoints AJAX/REST de negócio
- regras de pagamento/frete
- cron/webhooks

Deixar no tema só UI.

---

## Critérios rápidos (para decidir em 30s)

- **Se tem `wp_ajax_` / `register_rest_route` / `wp_schedule_event` / webhooks** → plugin.
- **Se mexe em checkout fields, fees, gateways, shipping rates** → plugin.
- **Se é “admin tool”** (config/manutenção) → plugin.
- **Se é CSS/HTML/layout/componente visual** → tema.
- **Se depende do markup do tema** (classes CSS do tema) → tema.
- **Se precisa sobreviver à troca de tema** → plugin.

---

## Observações específicas do `gstore`

- O `functions.php` está grande e mistura:
  - UI/performance (tema)
  - carrinho/checkout/mini-cart (misto)
  - pagamentos (plugin)
  - frete (plugin)
  - admin tools (plugin)

A recomendação é reduzir o `functions.php` para um “bootstrap” do tema e mover o restante para plugins, começando pelo que é **mais crítico para faturamento**: pagamentos e frete.


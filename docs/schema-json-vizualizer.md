## Schema JSON — Vizualizer (Grafo)

Este documento define o **formato padrão** dos arquivos JSON consumidos pelo **Vizualizer** (visualização em mind map/grafo).

A ideia é representar um sistema como um **grafo normalizado**:

- **Nós**: `entities` (arquivos, hooks, endpoints, componentes, classes, etc.)
- **Arestas**: `connections` (chamadas, registros, handoffs entre camadas, etc.)

---

## Objetivos do schema

- **Separar camadas** (ex.: `php`, `wordpress`, `react`) para facilitar leitura e filtros.
- **Representar entidades** como nós únicos e reutilizáveis (sem duplicação).
- **Representar conexões** como arestas normalizadas (com tipo e notas).
- **Permitir visualização em mind map** (agrupamento, filtros por `layer`/`kind`/`tags`).
- **Manter extensibilidade**: `details` (por entidade) e `metadata/meta` (por documento) permitem evoluir sem quebrar o core.

---

## Estrutura (alto nível)

- **`project`**: metadados do projeto (plugin/tema/app).
- **`entities`**: lista de entidades normalizadas (**nós** do grafo).
- **`connections`**: lista de conexões normalizadas (**arestas** do grafo).
- **`metadata`** *(opcional)*: metadados do documento (geração, escopo, notas).
- **`meta`** e **`rules`** *(opcionais)*: envelope de geração/LLM (usado em alguns pipelines).

> Recomendação: o “core” do Vizualizer deve depender apenas de `project/entities/connections`.  
> `metadata/meta/rules` são úteis para auditoria, geração e contexto, mas não são obrigatórios para desenhar o grafo.

---

## Tipos principais

### project

- **`name`** *(string, obrigatório)*: nome do projeto.
- **`type`** *(string, obrigatório)*: tipo do projeto (ex.: `plugin`, `theme`, `app`, `service`).
- **`version`** *(string, opcional)*: versão do projeto.
- **`description`** *(string, opcional)*: descrição curta.

### entity

Cada item em `entities[]` vira um **nó** no mind map.

- **`id`** *(string, obrigatório)*: identificador único e estável.
- **`layer`** *(string, obrigatório)*: camada da entidade (ex.: `php`, `wordpress`, `react`).
- **`kind`** *(string, obrigatório)*: tipo “curto” (ex.: `file`, `class`, `hook`, `endpoint`, `component`, `app`).
- **`title`** *(string, obrigatório)*: nome/título humano.
- **`path`** *(string, opcional)*: caminho do arquivo (quando aplicável).
- **`summary`** *(string, opcional)*: resumo curto (1–2 linhas).
- **`details`** *(objeto livre, opcional)*: detalhes estruturados (screenId, build, data injected, etc.).
- **`tags`** *(string[], opcional)*: rótulos para filtros (ex.: `admin`, `checkout`, `shipping`, `legacy`).

### connection

Cada item em `connections[]` vira uma **aresta** no mind map.

- **`id`** *(string, obrigatório)*: identificador único.
- **`from`** *(string, obrigatório)*: `entity.id` de origem.
- **`to`** *(string, obrigatório)*: `entity.id` de destino.
- **`type`** *(string, obrigatório)*: tipo de conexão (ex.: `calls`, `registers`, `hooks_into`, `enqueues`, `rest_route`, `mounts`, `fetches`, `initializes`).
- **`handoff`** *(boolean, opcional)*: `true` quando há **transição de responsabilidade/camada** (ex.: PHP registra endpoint; React consome endpoint).
- **`notes`** *(string, opcional)*: observações para tooltip.

### metadata (opcional)

Metadados do documento (não do projeto).

- **`generatedAt`** *(string, opcional)*: data/hora de geração (idealmente ISO-8601).
- **`scope`** *(string, opcional)*: escopo (ex.: `admin`, `frontend`, `checkout`).
- **`schema`** *(string, opcional)*: nome/versão do schema (ex.: `VizualizerDocumentation (draft-07)`).
- **`notes`** *(string[], opcional)*: lista de notas importantes.

### meta e rules (opcionais; envelope de geração)

Alguns pipelines (ex.: geração via LLM) usam um envelope adicional:

- **`meta`** *(objeto, opcional)*: informações do gerador/limites (ex.: `generatedAt`, `modelTarget`, `limits`).
- **`rules`** *(string[], opcional)*: regras/constraints usadas para gerar o documento.

Isso é útil quando você quer guardar “como foi gerado”, sem misturar com `project`.

---

## Exemplo mínimo

```json
{
  "project": {
    "name": "Meu Sistema",
    "type": "app",
    "version": "0.1.0"
  },
  "entities": [
    {
      "id": "php:file:bootstrap.php",
      "layer": "php",
      "kind": "file",
      "title": "Bootstrap",
      "path": "bootstrap.php"
    },
    {
      "id": "wp:endpoint:meu_namespace_v1_ping",
      "layer": "wordpress",
      "kind": "endpoint",
      "title": "GET /wp-json/meu-namespace/v1/ping",
      "summary": "Healthcheck do backend."
    }
  ],
  "connections": [
    {
      "id": "c:bootstrap_registers_ping",
      "from": "php:file:bootstrap.php",
      "to": "wp:endpoint:meu_namespace_v1_ping",
      "type": "registers",
      "handoff": true
    }
  ]
}
```

---

## Exemplo mais completo (com envelope de geração + metadata)

```json
{
  "meta": {
    "generatedAt": "2026-01-08T22:04:53.940Z",
    "generator": "vizualizer-export",
    "limits": { "maxEntities": 120, "maxConnections": 200 }
  },
  "rules": [
    "Não inventar endpoints/hooks que não existam.",
    "Marcar handoff quando houver transição de camada."
  ],
  "project": {
    "name": "Exemplo WP + React",
    "type": "plugin",
    "version": "1.0.0",
    "description": "Exemplo de plugin que registra endpoint REST e monta uma tela React no admin."
  },
  "entities": [
    {
      "id": "php:file:meu-plugin.php",
      "layer": "php",
      "kind": "file",
      "title": "Bootstrap do plugin",
      "path": "meu-plugin.php",
      "summary": "Inicializa o plugin e registra hooks principais.",
      "tags": ["bootstrap"]
    },
    {
      "id": "wp:hook:rest_api_init",
      "layer": "wordpress",
      "kind": "hook",
      "title": "rest_api_init",
      "tags": ["wp-core"]
    },
    {
      "id": "wp:endpoint:meu_namespace_v1_settings",
      "layer": "wordpress",
      "kind": "endpoint",
      "title": "GET/POST /wp-json/meu-namespace/v1/settings",
      "summary": "Lê e salva configurações do plugin.",
      "tags": ["rest"]
    },
    {
      "id": "php:hook:admin_enqueue_scripts",
      "layer": "wordpress",
      "kind": "hook",
      "title": "admin_enqueue_scripts",
      "tags": ["admin"]
    },
    {
      "id": "react:app:settings",
      "layer": "react",
      "kind": "app",
      "title": "Settings (React)",
      "path": "src/settings/index.jsx",
      "summary": "UI de configurações no admin.",
      "details": {
        "mountRootId": "my-react-settings",
        "build": { "js": "build/settings.js", "css": "build/settings.css" }
      },
      "tags": ["admin", "ui"]
    }
  ],
  "connections": [
    {
      "id": "c:bootstrap_hooks_rest_api_init",
      "from": "php:file:meu-plugin.php",
      "to": "wp:hook:rest_api_init",
      "type": "hooks_into",
      "handoff": true
    },
    {
      "id": "c:rest_api_init_registers_settings_endpoint",
      "from": "wp:hook:rest_api_init",
      "to": "wp:endpoint:meu_namespace_v1_settings",
      "type": "registers",
      "handoff": true
    },
    {
      "id": "c:bootstrap_hooks_admin_enqueue",
      "from": "php:file:meu-plugin.php",
      "to": "php:hook:admin_enqueue_scripts",
      "type": "hooks_into",
      "handoff": true
    },
    {
      "id": "c:enqueue_mounts_settings_app",
      "from": "php:hook:admin_enqueue_scripts",
      "to": "react:app:settings",
      "type": "mounts",
      "handoff": true,
      "notes": "Enfileira o build e renderiza o container #my-react-settings."
    },
    {
      "id": "c:react_fetches_settings_endpoint",
      "from": "react:app:settings",
      "to": "wp:endpoint:meu_namespace_v1_settings",
      "type": "fetches",
      "handoff": true
    }
  ],
  "metadata": {
    "generatedAt": "2026-01-08",
    "scope": "admin",
    "schema": "VizualizerDocumentation (draft-07)",
    "notes": ["Exemplo compacto cobrindo details/tags/handoff/meta/rules."]
  }
}
```

---

## Convenções recomendadas

- **IDs (estáveis e únicos)**:
  - prefira IDs determinísticos e fáceis de ler.
  - use prefixos por camada para evitar colisões: `php:`, `wp:`, `react:`.
  - padronize o “subtipo” dentro do id: `php:file:...`, `wp:hook:...`, `wp:endpoint:...`, `react:app:...`.
- **handoff**:
  - use `true` quando houver troca de camada/responsabilidade (ex.: `php` → `wordpress`, `wordpress` → `react`).
  - mantenha `false`/omitido para chamadas internas na mesma camada.
- **kind/type (curtos e consistentes)**:
  - isso impacta diretamente filtros/legendas e melhora muito a leitura do grafo.
- **summary vs details**:
  - `summary`: 1–2 linhas (tooltip).
  - `details`: dados estruturados (IDs de tela, build assets, variáveis globais injetadas, etc.).
- **tags**:
  - use para recortes úteis do sistema: `admin`, `frontend`, `checkout`, `shipping`, `legacy`, `woocommerce`, `wp-cli`, `cron`.

---

## Validação (opcional)

Se você quiser **validar automaticamente** o formato do JSON, use o schema em:

- `docs/vizualizer-document.schema.json`

> Observação: o schema é “opinionated” (inclui enums para camadas) e pode ser ajustado conforme seu projeto.


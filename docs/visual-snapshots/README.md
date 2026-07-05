# Visual snapshots

Esta pasta guarda os prints de referencia do Gstore Theme.

Estrutura esperada:

```text
latest/<site-id>/<page-id>/<state-id>/<viewport-id>.png
latest/<site-id>/<page-id>/<state-id>/capture-meta.json
archive/<theme-version>/<site-id>/<page-id>/<state-id>/<viewport-id>.png
```

Use `latest` para a versao mais nova do tema. O manifesto que define lojas, paginas, estados e tamanhos fica em `docs/visual-snapshots.manifest.json`.

As evidencias oficiais devem vir do Google Chrome real do usuario para manter login, cookies e estados reais das lojas `armastore` e `cacarmas`.

Arquivos auxiliares em `latest/`:

- `_capture-blockers.json`: estados obrigatorios que nao foram capturados e o motivo de seguranca/sessao.
- `_session-state-audit.json`: resumo do que ja existia no Chrome real, acoes reversiveis executadas e estados dependentes de dados reais.

Estados sensiveis de conta, checkout e pedidos podem existir em `latest/` como PNGs redigidos. Nesses casos, o `capture-meta.json` deve declarar `redacted: true`, e URLs/identificadores reais devem estar sanitizados.

Estados negativos que exigiriam mutacao destrutiva da sessao real podem existir como fixtures declaradas. Hoje isso vale para `cart/empty`, `favorites/empty` e `my-account/logged-out`; nesses casos, o `capture-meta.json` deve declarar `fixture: true` e `fixtureKind`.

Comandos principais:

```bash
npm run visual:capture -- --base-url=http://armastore.local/ --site-id=local
npm run visual:capture -- --routes-file=docs/visual-snapshots.routes.local.json
npm run visual:fixtures
npm run visual:audit
npm run visual:audit:blocked
```

Use `visual:audit` para exigir todos os PNGs obrigatorios, incluindo fixtures. Use `visual:fixtures` apenas para regenerar os estados negativos declarados. Use `visual:audit:blocked` quando a matriz estiver completa exceto por estados documentados em `_capture-blockers.json`.

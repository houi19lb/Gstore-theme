# Evidencias visuais das paginas

## Contexto rapido

- Este documento define a regra para manter prints de exemplo das paginas do Gstore Theme.
- A matriz estruturada fica em `docs/visual-snapshots.manifest.json`.
- As rotas reais usadas nas lojas de referencia ficam em `docs/visual-snapshots.reference-routes.json`.
- Os arquivos de imagem da versao atual devem ficar em `docs/visual-snapshots/latest/`, separados por loja de referencia.
- Estados bloqueados ou dependentes de sessao ficam documentados em `docs/visual-snapshots/latest/_capture-blockers.json` e `docs/visual-snapshots/latest/_session-state-audit.json`.
- Cada tela obrigatoria deve ter, no minimo, 3 tamanhos: desktop grande, desktop pequeno e celular.
- As lojas de referencia atuais sao Arma Store (`armastore`) e CAC Armas (`cacarmas`), sempre capturadas pelo Google Chrome real do usuario para aproveitar login, cookies, carrinho e estado real.

## Tamanhos padrao

| ID | Uso | Viewport |
| --- | --- | --- |
| `desktop-lg` | Desktop grande | 1440 x 1100 |
| `desktop-sm` | Desktop pequeno | 1024 x 900 |
| `mobile` | Celular | 390 x 844 |

O breakpoint de celular precisa ficar abaixo dos ajustes mobile do tema. Se um bug so aparece em outro aparelho, adicione um estado extra no manifesto em vez de substituir estes tres tamanhos.

## Onde salvar

Use sempre este padrao:

```text
docs/visual-snapshots/latest/<site-id>/<page-id>/<state-id>/<viewport-id>.png
```

Exemplos:

```text
docs/visual-snapshots/latest/armastore/home/default/desktop-lg.png
docs/visual-snapshots/latest/cacarmas/single-product/buybox-open/mobile.png
docs/visual-snapshots/latest/armastore/checkout/pix-selected/desktop-sm.png
```

`latest` representa a versao mais nova do tema. Se for importante guardar historico visual de uma release antiga, mova uma copia para `docs/visual-snapshots/archive/<theme-version>/`, mas a revisao diaria deve olhar para `latest`.

## Regra de atualizacao

Atualize as evidencias visuais sempre que um PR, hotfix ou release tocar em qualquer uma destas areas:

- `templates/`, `parts/` ou `woocommerce/`.
- `style.css`, `theme.json`, `assets/css/` ou `assets/js/`.
- Renderizacao de frontend em `functions.php` ou `inc/`.
- Campos configuraveis por loja que alteram header, footer, home, catalogo, produto, carrinho ou checkout.
- Versao do tema no cabecalho de `style.css`.

Fluxo recomendado:

1. Confirme a versao atual em `style.css` (`Version:`).
2. Gere novamente os prints das paginas afetadas nos tres viewports padrao.
3. Salve os arquivos seguindo o padrao de caminho acima.
4. Atualize `themeVersion`, `updatedAt` e, quando necessario, os estados em `docs/visual-snapshots.manifest.json`.
5. Rode `npm run visual:audit` antes de entregar.

Se uma mudanca for global, como tokens, header, footer, grid de produto ou breakpoints, recapture todas as paginas obrigatorias. Se a mudanca for localizada, recapture a pagina alterada e qualquer pagina que reutilize o mesmo componente.

## Como capturar com Chrome real

O fluxo oficial para as evidencias de referencia usa o Google Chrome real do usuario, nao uma sessao isolada do Playwright. Isso e importante porque Arma Store e CAC Armas podem ter login, carrinho, favoritos, pedidos, gateways e modais dependentes da sessao real.

Regras de seguranca durante a captura:

- Nao finalizar compra.
- Nao alterar configuracoes administrativas.
- Nao salvar alteracoes permanentes em conta, endereco, pedido, tema ou produtos.
- Pode navegar, abrir menus, trocar viewport, abrir modais, ver paginas logadas e capturar screenshots.
- Antes de criar estado reversivel, como adicionar item ao carrinho ou favorito, confirme se ja existe estado pronto no Chrome. Se nao existir, registre o bloqueio ou avise antes de alterar.
- Estados que exibem nome, e-mail, endereco, pedido, CPF, telefone ou totais reais podem ser versionados apenas como PNG redigido. O screenshot bruto deve ficar temporario e nao deve entrar no repositorio; `capture-meta.json` tambem precisa sanitizar URL, H1 e qualquer identificador real.
- Estados negativos que exigiriam destruir estado real, como carrinho vazio, favoritos vazios ou conta deslogada quando a sessao real esta logada, podem ser cobertos por fixture declarada em vez de alterar a conta.

Fluxo recomendado:

1. Abra ou localize no Chrome real as abas de `https://armastore.com.br/` e `https://cacarmas.com.br/`.
2. Capture primeiro paginas publicas: home, catalogo, categoria, produto, ofertas, blog/post, atendimento, sobre, politica e informativo.
3. Depois capture estados interativos: menu mobile aberto, filtros abertos, busca, mini cart/carrinho se disponivel, modal informativo e accordions/abas do produto.
4. Por ultimo capture estados logados: minha conta, pedidos, favoritos e checkout, somente se ja estiverem seguros no Chrome e sem mutacao critica.
5. Salve cada PNG no caminho com `site-id` e salve `capture-meta.json` ao lado quando houver diferenca entre viewport alvo e viewport medido.

O Chrome em janela normal pode ter limites de altura/largura por causa do monitor, da barra do navegador e do `devicePixelRatio`. Quando isso acontecer:

- Use a largura CSS medida (`window.innerWidth`) como referencia principal de breakpoint.
- Use `fullPage` ou recorte para produzir o PNG final no tamanho alvo.
- Registre em `capture-meta.json` o viewport medido, o alvo e se houve normalizacao de pixels.
- Para `mobile` 390px, use uma tecnica reversivel no Chrome real: janela com largura externa aproximada de 796px e zoom do site em 200% (`Ctrl+0`, depois cinco `Ctrl++`). Isso deixa `window.innerWidth` em 390px em lojas testadas sem trocar de perfil, sem perder cookies e sem usar sessao isolada.
- Depois de capturar mobile, restaure o zoom do site com `Ctrl+0`.

## Fixtures de estados negativos

Use fixtures somente para estados obrigatorios cuja captura real exigiria logout, remocao de itens do carrinho ou limpeza de favoritos na sessao real do Chrome.

Estados fixture aceitos hoje:

- `cart/empty`
- `favorites/empty`
- `my-account/logged-out`

Gere novamente esses PNGs com:

```bash
npm run visual:fixtures
```

Regras para fixtures:

- O `capture-meta.json` precisa declarar `fixture: true`, `fixtureKind` e o motivo da excecao.
- A fixture nao substitui evidencia real quando o estado puder ser capturado com seguranca no Chrome.
- Cada excecao precisa aparecer em `_session-state-audit.json`; `_capture-blockers.json` deve ficar reservado para ausencias reais que ainda faltam.
- Se algum dia houver uma conta/teste segura para esses estados, prefira substituir a fixture por captura real do Chrome.

## Captura local auxiliar

Instale as dependencias do projeto e o navegador usado pelo Playwright:

```bash
npm install
npx playwright install chromium
```

Depois capture a partir de uma loja WordPress rodando com o tema ativo:

```bash
npm run visual:capture -- --base-url=http://armastore.local/
```

Este caminho local e auxiliar. Ele pode validar layout do tema em uma instalacao WordPress local, mas nao substitui as evidencias oficiais dos sites reais em `armastore` e `cacarmas`.

Para capturar so algumas paginas:

```bash
npm run visual:capture -- --base-url=http://armastore.local/ --site-id=local --only=home,catalog,single-product
```

Paginas com URL dependente de conteudo real precisam de rota de exemplo da loja. Use `--route=<page-id>=<url-ou-caminho>`:

```bash
npm run visual:capture -- --base-url=http://armastore.local/ --route=single-product=/produto/produto-exemplo/
npm run visual:capture -- --base-url=http://armastore.local/ --route=product-category=/categoria-produto/armas/
npm run visual:capture -- --base-url=http://armastore.local/ --route=single-post=/post-exemplo/
```

Para nao repetir rotas no terminal, copie o arquivo de exemplo e ajuste para a loja ativa:

```bash
cp docs/visual-snapshots.routes.example.json docs/visual-snapshots.routes.local.json
npm run visual:capture -- --routes-file=docs/visual-snapshots.routes.local.json
```

`docs/visual-snapshots.routes.local.json` fica ignorado pelo Git porque os slugs e produtos de exemplo dependem de cada loja.

Se voce tiver acesso ao WordPress local da loja, o script abaixo sugere rotas reais a partir do conteudo publicado:

```bash
php scripts/inspect-wordpress-routes.php "C:/caminho/para/wordpress" http://127.0.0.1:19005/
```

Use a saida como base para `docs/visual-snapshots.routes.local.json`.

Para conferir o plano antes de gravar PNGs:

```bash
npm run visual:capture -- --base-url=http://armastore.local/ --dry-run
```

O capturador usa o manifesto como fonte de verdade: viewports, estados obrigatorios, acoes simples de UI e caminho final dos arquivos. Estados que dependem de login, carrinho, pedido, produto especifico ou dados de checkout precisam estar preparados na loja antes da captura.

## O que pode mudar por loja

Estas variaveis nao devem ser tratadas como regressao visual so por terem conteudo diferente entre lojas:

- Dados de `store-info.json`: nome da loja, contato, endereco, redes sociais, textos institucionais, horario, SEO e branding.
- Opcoes `gstore_*`: logo, cor de destaque, slides desktop/mobile da home, banner do YouTube e dados da vitrine migrados para plugin.
- Conteudo WordPress: menus, paginas, posts, categorias, tags, politica de privacidade e navegacao.
- Dados WooCommerce: produtos, imagens, precos, parcelamento, estoque, variacoes, cupons, frete, gateways e status de sessao do cliente.

Estas coisas devem continuar consistentes mesmo com conteudo diferente:

- Hierarquia dos blocos, espacamento, alinhamento, quebras responsivas e ordem visual.
- Estados de interacao: menu mobile, filtros, busca, mini cart, favoritos, calculo de frete, cupom e pagamento.
- Leitura em mobile: texto sem sobrepor, botoes acessiveis e componentes sem corte horizontal.

## Como revisar

Ao comparar os prints, classifique a diferenca antes de corrigir:

- `tema`: layout, CSS, JS, template ou componente mudou.
- `loja`: dado configuravel, produto, menu, midia ou texto local mudou.
- `sessao`: carrinho, login, favorito, cupom, frete ou gateway depende do usuario/teste.
- `ambiente`: plugin, cache, WooCommerce, WordPress ou seed de dados diferente.
- `redigido`: evidencia capturada em sessao real, mas com dados pessoais/operacionais cobertos por tarjas e metadados sanitizados.
- `fixture`: imagem gerada para estado negativo que nao podia ser produzido no Chrome real sem logout ou limpeza de dados de sessao.

So abra correcao no tema quando a diferenca for `tema` ou quando uma variavel de loja quebrar um limite que o tema deveria suportar.

## Auditoria

Use a auditoria estrita para garantir que a matriz esta 100% coberta por PNGs obrigatorios:

```bash
npm run visual:audit
```

Quando existirem ausencias justificadas por seguranca, sessao real ou dados pessoais, documente cada estado em `docs/visual-snapshots/latest/_capture-blockers.json` e rode:

```bash
npm run visual:audit:blocked
```

Esse modo so passa quando todas as ausencias estao bloqueadas de forma explicita. Se aparecer uma falta nova sem bloqueio, ele falha.

Fixtures nao contam como ausencia: elas devem existir como PNGs, passar na auditoria estrita e estar declaradas no `capture-meta.json`.

Durante a criacao inicial da biblioteca, `--allow-missing` permite ver quantos prints ainda faltam sem aceitar isso como estado final:

```bash
npm run visual:audit -- --allow-missing
```

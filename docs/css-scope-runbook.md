# CSS Scope Runbook

Este documento define onde novas regras CSS devem entrar no tema Gstore.
O objetivo e manter `style.css` e `gstore-main.css` pequenos, evitando que
CSS de home, catalogo, produto, blog ou suporte seja entregue em todas as
paginas.

## Regra principal

- `style.css`: somente cabecalho do tema, fallbacks globais pequenos e hotfix
  emergencial documentado.
- `assets/css/gstore-main.css`: tokens, base, utilities e componentes realmente
  globais.
- CSS de pagina ou componente pesado: arquivo proprio em `assets/css/`, com
  enqueue condicional em `functions.php`.
- Admin/plugin: persistem configuracoes e dados; nao devem escrever CSS
  versionado do tema em producao.

## Matriz de destino

| Escopo | Arquivo correto | Quando carrega |
| --- | --- | --- |
| Tokens base | `assets/css/tokens.css` | Global via `gstore-main.css` |
| Reset/base/utilities | `assets/css/base.css`, `utilities.css`, `responsive.css` | Global via `gstore-main.css` |
| Header/drawer/nav | `assets/css/layouts/header.css` | Global |
| Header legado migrado | `assets/css/layouts/header-legacy.css` | Global, antes do header atual |
| Mini-cart | `assets/css/components/mini-cart.css` | Global com header |
| Footer | `assets/css/layouts/footer.css` | Global |
| Home | `assets/css/layouts/home.css` | Home/front page |
| Home legado migrado | `assets/css/layouts/home-legacy.css` | Home/front page |
| Card de produto | `assets/css/components/product-card.css` | Home, catalogo, produto, carrinho e busca |
| Card de produto legado | `assets/css/components/product-card-legacy.css` | Mesmo contexto do card |
| Produto unico | `assets/css/single-product.css` | `is_product()` |
| Catalogo/taxonomias | `assets/css/catalog.css` e `assets/css/category-filter.css` | Catalogo, ofertas, favoritos, shop, categoria e tag |
| Blog/suporte | `assets/css/layouts/support-blog.css` | Blog, atendimento e arquivos de posts |
| Post unico | `assets/css/layouts/blog-single.css` e `blog-single-legacy.css` | `is_singular('post')` |
| Polimento institucional | `assets/css/layouts/institutional-polish.css` | Blog, atendimento e post unico |
| Carrinho | `assets/css/cart.css` | `is_cart()` |
| Checkout | `assets/css/checkout.css`, `checkout-steps.css`, `checkout-pix.css` | `is_checkout()` e paginas Pix |
| Pedido recebido | `assets/css/order-received.css` | Carrinho/checkout/order received |
| Minha conta | `assets/css/my-account.css` | `is_account_page()` |

## Contrato tema/plugin/admin

- O plugin/admin pode salvar opcoes, escolher menus, fornecer dados e expor
  configuracoes.
- O tema decide markup, classes, CSS e JS de UI.
- A cor de accent salva no admin deve ir para `wp_options` e ser emitida por
  `wp_add_inline_style( 'gstore-main', ... )`.
- O admin nao deve editar `style.css`, `tokens.css`, `header.css`,
  `my-account.css`, `checkout-steps.css` ou qualquer arquivo versionado em
  producao.

## Pre-deploy

Rode sempre antes de commit/deploy quando mexer em CSS:

```powershell
npm run build:assets
npm run check:assets
npm run check:css-scope
php -l functions.php
```

O check de escopo falha se:

- `style.css` voltar a crescer acima do limite.
- `style.css` receber seletores de home, catalogo, produto, blog, suporte ou
  card de produto.
- `gstore-main.css` voltar a importar CSS de pagina/componente pesado.
- Algum arquivo de escopo obrigatorio sumir.

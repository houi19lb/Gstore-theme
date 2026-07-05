# Auditoria visual de alinhamento do Gstore Theme

## Resumo

Esta auditoria revisa os snapshots atuais em `docs/visual-snapshots/latest`, com prioridade para o fluxo de compra/produto: produto, carrinho, checkout, header/mini cart e estados com frete/Pix.

O objetivo desta etapa e documentar inconsistencias visuais e riscos de layout. Nenhuma correcao de CSS, JS ou PHP foi aplicada nesta rodada.

## Status da matriz visual

Comando executado:

```bash
npm run visual:audit
```

Resultado registrado:

- Lojas verificadas: 2 (`armastore`, `cacarmas`)
- Paginas verificadas: 19
- Snapshots obrigatorios: 174
- Snapshots capturados: 174
- Snapshots ausentes: 0
- Ausencias bloqueadas documentadas: 0
- Ausencias sem bloqueio: 0
- Dimensoes divergentes: 0
- Viewports medidos divergentes: 0
- Fontes ausentes: 0

Viewports revisados:

- `desktop-lg`: 1440 x 1100
- `desktop-sm`: 1024 x 900
- `mobile`: 390 x 844

Regra de classificacao: diferencas de produto, preco, logo, cor de acento, imagem, menu, login, carrinho, pedidos e conteudo da loja foram tratadas como contexto. Os achados abaixo entram como tema/layout quando o mesmo padrao aparece em mais de uma loja ou quando a tela evidencia corte, sobreposicao ou bloqueio de interacao.

## Achados priorizados

### P0 - Header fixo atravessa o produto durante estado com frete

Evidencia:

- `docs/visual-snapshots/latest/cacarmas/single-product/shipping-quote/desktop-lg.png`

O snapshot mostra o header completo fixado no meio da area do produto, cobrindo a galeria, parte da imagem e a coluna de compra. Isso bloqueia leitura e pode impedir clique em elementos do produto.

Subsistema provavel:

- `assets/css/layouts/header.css`
- `assets/js/header.js`
- `woocommerce/content-single-product.php`
- `style.css`
- `assets/js/shipping-calculator.js`

Hipotese a validar: conflito entre comportamento sticky/fixed do header e scroll/ancora acionado pelo calculo de frete no produto.

### P1 - Buybox e acoes do produto cortam no desktop pequeno

Evidencia:

- `docs/visual-snapshots/latest/armastore/single-product/default/desktop-sm.png`
- `docs/visual-snapshots/latest/armastore/single-product/default/desktop-lg.png`
- `docs/visual-snapshots/latest/cacarmas/single-product/default/desktop-lg.png`

Em desktop pequeno, a coluna da direita fica muito justa e o botao `Adicionar ao carrinho` aparece cortado na lateral. Em desktop grande, a area tambem trabalha perto do limite quando ha aviso legal, quantidade, compra agora e cards de ajuda.

Subsistema provavel:

- `woocommerce/content-single-product.php`
- `style.css` (`.Gstore-single-product__main`, `.Gstore-single-product__summary`, `.Gstore-single-product__add-to-cart`)
- `assets/js/single-product.js`

Recomendacao: revisar grid do produto, largura minima da buybox e comportamento das acoes em 1024px antes de qualquer ajuste estetico mais amplo.

### P1 - Botao flutuante de chat sobrepoe conteudo critico em mobile e carrinho

Evidencia:

- `docs/visual-snapshots/latest/armastore/single-product/default/mobile.png`
- `docs/visual-snapshots/latest/cacarmas/single-product/default/mobile.png`
- `docs/visual-snapshots/latest/armastore/cart/with-item/mobile.png`
- `docs/visual-snapshots/latest/cacarmas/cart/with-item/mobile.png`
- `docs/visual-snapshots/latest/cacarmas/catalog/filters-open/mobile.png`
- `docs/visual-snapshots/latest/armastore/support/default/mobile.png`
- `docs/visual-snapshots/latest/armastore/about/default/mobile.png`

O chat aparece sobre textos, filtros, produto, area de frete e CTAs. Em carrinho desktop, tambem fica em cima do card lateral de atendimento:

- `docs/visual-snapshots/latest/armastore/cart/with-item/desktop-lg.png`
- `docs/visual-snapshots/latest/cacarmas/cart/with-item/desktop-lg.png`

Subsistema provavel:

- `assets/css/components/telegram-floating.css`
- `assets/js/telegram-floating.js`
- `assets/css/cart.css`
- `assets/css/category-filter.css`
- `assets/css/sobre-nos.css`
- `assets/css/informativo.css`

Recomendacao: definir zonas de exclusao por contexto ou offsets responsivos para produto, carrinho, catalogo com filtro aberto e paginas institucionais.

### P1 - Barra/header mobile aparece sobre conteudo no estado de produto aberto

Evidencia:

- `docs/visual-snapshots/latest/armastore/single-product/buybox-open/mobile.png`

O snapshot mostra uma barra de navegacao fixa cobrindo a transicao entre galeria e preco. Isso reduz a area util exatamente no fluxo de compra.

Subsistema provavel:

- `assets/css/layouts/header.css`
- `assets/js/header.js`
- `woocommerce/content-single-product.php`
- `style.css`

Recomendacao: validar estado de scroll usado na captura e garantir que barras fixas nao cubram preco, quantidade, frete ou botoes.

### P1 - Galeria do produto pode capturar area vazia ou carregamento incompleto

Evidencia:

- `docs/visual-snapshots/latest/cacarmas/single-product/default/desktop-lg.png`
- `docs/visual-snapshots/latest/cacarmas/single-product/default/mobile.png`
- `docs/visual-snapshots/latest/cacarmas/single-product/shipping-quote/desktop-lg.png`

Em alguns snapshots da CAC Armas, a area principal da galeria aparece vazia ou parcialmente carregada, enquanto thumbs e layout existem. Como o produto e loja podem ter midia diferente, este item deve ser investigado antes de ser marcado como bug definitivo.

Subsistema provavel:

- `woocommerce/content-single-product.php`
- `style.css` (`.Gstore-single-product__gallery`, `.flex-viewport`, `.flex-slides`)
- `assets/js/single-product.js`

Recomendacao: validar se o capturador espera a imagem principal estabilizar e se a galeria WooCommerce/Flexslider tem altura minima ou lazy loading conflitando com screenshot.

### P2 - Header desktop tem risco de overflow nas acoes da direita

Evidencia:

- `docs/visual-snapshots/latest/armastore/home/default/desktop-lg.png`
- `docs/visual-snapshots/latest/armastore/catalog/default/desktop-lg.png`
- `docs/visual-snapshots/latest/armastore/blog/default/desktop-lg.png`
- `docs/visual-snapshots/latest/cacarmas/home/default/desktop-lg.png`
- `docs/visual-snapshots/latest/cacarmas/catalog/default/desktop-lg.png`

As acoes de conta/favoritos/carrinho ficam muito proximas da borda direita, e em alguns snapshots o texto de favoritos aparece truncado visualmente. O padrao sugere que o header esta dependente de uma combinacao fragil de largura de logo, busca, menu e acoes.

Subsistema provavel:

- `parts/header.html`
- `templates/parts/header.html`
- `assets/css/layouts/header.css`

Recomendacao: revisar distribuicao do header desktop com `min-width: 0`, largura maxima de busca, quebra de acoes e regras para ocultar/compactar labels em larguras intermediarias.

### P2 - Checkout funcional, mas visualmente isolado do restante da loja

Evidencia:

- `docs/visual-snapshots/latest/armastore/checkout/default/desktop-lg.png`
- `docs/visual-snapshots/latest/armastore/checkout/default/mobile.png`
- `docs/visual-snapshots/latest/cacarmas/checkout/default/desktop-lg.png`
- `docs/visual-snapshots/latest/cacarmas/checkout/default/mobile.png`
- `docs/visual-snapshots/latest/armastore/checkout/pix-selected/desktop-lg.png`
- `docs/visual-snapshots/latest/cacarmas/checkout/address-filled/mobile.png`

O checkout parece consistente internamente, mas usa uma linguagem mais isolada: header simplificado, cards amplos, muito espaco em branco e componentes de pagamento com acento azul do gateway. Isso nao bloqueia compra, mas cria ruptura perceptivel em relacao a produto, carrinho e catalogo.

Subsistema provavel:

- `templates/page-checkout.html`
- `assets/css/checkout-steps.css`
- `assets/css/checkout.css`
- `assets/js/checkout-steps.js`

Recomendacao: depois dos P0/P1, alinhar densidade, raio, bordas, tom de acento e hierarquia do checkout sem comprometer clareza e seguranca da compra.

### P2 - Institucionais usam molduras/tabs mais pesadas que o e-commerce

Evidencia:

- `docs/visual-snapshots/latest/armastore/about/default/desktop-lg.png`
- `docs/visual-snapshots/latest/cacarmas/about/default/desktop-lg.png`
- `docs/visual-snapshots/latest/armastore/about/default/mobile.png`
- `docs/visual-snapshots/latest/cacarmas/support/default/desktop-lg.png`
- `docs/visual-snapshots/latest/armastore/blog/default/desktop-lg.png`

As paginas sobre, atendimento e blog usam grandes cards/molduras e tabs com bordas fortes. Elas estao legiveis, mas a linguagem fica mais editorial/institucional e menos alinhada ao catalogo/produto/carrinho.

Subsistema provavel:

- `assets/css/sobre-nos.css`
- `assets/css/informativo.css`
- `assets/css/privacy-policy.css`
- `assets/css/layouts/blog-single.css`
- templates institucionais em `templates/`

Recomendacao: criar uma segunda rodada de polimento para aproximar esses layouts dos tokens do e-commerce, preservando legibilidade juridica e institucional.

### P2 - Carrosseis horizontais aparecem cortados em mobile

Evidencia:

- `docs/visual-snapshots/latest/armastore/home/default/mobile.png`
- `docs/visual-snapshots/latest/cacarmas/home/default/mobile.png`
- `docs/visual-snapshots/latest/cacarmas/informativo/default/mobile.png`

Os cards/carrosseis mostram o proximo item cortado lateralmente. Isso pode ser intencional como indicio de scroll, mas em alguns pontos parece competindo com texto e com o chat flutuante.

Subsistema provavel:

- `assets/css/layouts/home.css`
- `assets/js/home-products-carousel.js`
- `assets/css/informativo.css`
- `assets/js/informativo.js`

Recomendacao: decidir padrao unico para carrossel mobile: peek intencional com espacamento controlado, ou card unico full-width sem corte visivel.

## Backlog recomendado

1. Corrigir ou neutralizar o header fixo atravessando `single-product/shipping-quote`.
2. Ajustar grid/buybox do produto em `desktop-sm` e validar acoes de compra.
3. Definir offsets/zonas de exclusao para o botao flutuante de chat.
4. Revisar estado mobile `single-product/buybox-open` para impedir barra fixa sobre preco e CTAs.
5. Investigar galeria de produto com area vazia/parcial antes de classificar como bug de tema.
6. Compactar header desktop e reduzir risco de overflow em acoes.
7. Harmonizar checkout com produto/carrinho sem reduzir clareza.
8. Polir institucionais e padronizar carrosseis mobile.

## Criterios de aceite para a proxima rodada

- `npm run visual:audit` continua com 174/174 snapshots capturados.
- `single-product/default`, `single-product/buybox-open` e `single-product/shipping-quote` passam sem header/barra fixa cobrindo preco, galeria ou botoes.
- `cart/with-item` e `cart/shipping-calculated` passam sem chat sobre CTA, resumo ou atendimento.
- `checkout/default`, `checkout/pix-selected` e `checkout/address-filled` mantem fluxo claro e ficam visualmente mais proximos de produto/carrinho.
- `catalog/filters-open` mobile passa sem chat ou drawer bloqueando filtros essenciais.
- Qualquer diferenca de conteudo entre Arma Store e CAC Armas continua documentada como variavel de loja, nao regressao.


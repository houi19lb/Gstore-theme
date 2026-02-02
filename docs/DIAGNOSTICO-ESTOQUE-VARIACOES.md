# Diagnóstico: estoque em produtos variáveis (WooCommerce) exibindo botões mesmo sem estoque

## Contexto rápido (LLMs/novos devs)
- Documento de diagnóstico de **estoque em variações**.
- A correção envolve o payload de variações no backend.

## Contexto / Sintoma

Na página de produto variável (ex.: [`/produto/teste/`](https://cacarmas.kivodigital.com.br/produto/teste/)), as variações **branco** e **preto** estavam com **estoque 0** no painel, mas no front-end:

- Os **botões de compra** (“Adicionar ao carrinho” / “Comprar agora”) continuavam aparecendo.
- Ao tentar comprar, o WooCommerce **bloqueava** e exibia um aviso/modal de falta de estoque (ou seja: a validação final funcionava, mas a UI não refletia corretamente o estado).

## O que estava acontecendo (causa raiz)

O front-end do WooCommerce para produtos variáveis usa um JSON de variações (`product_variations`) para habilitar/desabilitar botões e exibir disponibilidade. Nesse JSON, o WooCommerce fornece flags como:

- `is_in_stock`
- `is_purchasable`

O tema (`assets/js/single-product.js`) usa essas flags (e também uma flag custom do tema) para decidir se deve:

- Mostrar o card “Produto indisponível no momento”
- Adicionar/remover a classe `.buybox.is-out-of-stock` (que via CSS esconde os CTAs)

O problema era que **as variações com quantidade 0 estavam chegando no front-end como `is_in_stock: true`**.

Isso acontece quando há **inconsistência entre**:

- A **quantidade** (ex.: `stock_quantity = 0`)
- O **status** de estoque e/ou a forma como o WooCommerce calcula `is_in_stock` (que pode depender de `_stock_status`, além de configurações como “Gerenciar estoque”)

Na prática: o admin mostrava “0”, mas o JSON de variações ainda marcava como “em estoque”, então a UI deixava os botões visíveis (e o bloqueio só ocorria na tentativa de compra).

## Como foi diagnosticado

1. Foi adicionado log temporário no console do navegador para inspecionar o payload retornado no evento `found_variation`.
2. Ao selecionar variações “branco”/“preto”, o console indicava `is_in_stock: true` e `gstore_is_in_stock: true` mesmo com estoque 0 no painel.

Isso confirmou que o problema não era “CSS não escondendo botões”, e sim os **dados de estoque que o front-end recebia**.

## O que foi feito para corrigir

### 1) Ajuste no payload das variações (back-end)

No tema, em `functions.php`, no filtro:

- `woocommerce_available_variation`

foi alterado para calcular o estoque de forma mais robusta, verificando:

- Se a variação **gerencia estoque** (`$variation->managing_stock()`)
- A **quantidade real** (`$variation->get_stock_quantity()`)
  - Se `<= 0`, força `is_in_stock = false`
- E como fallback, respeitar `_stock_status` quando vier como `outofstock`

Além disso, o payload passou a sobrescrever `is_in_stock` no array de variação, para garantir que o front-end receba o valor correto:

- `$data['is_in_stock'] = $is_in_stock;`
- `$data['gstore_is_in_stock'] = $is_in_stock;`

Resultado: ao selecionar “branco”/“preto”, o JSON passa a retornar `is_in_stock: false`, então a UI consegue esconder os botões e mostrar “Produto indisponível”.

### 2) Ajuste da UI para casos de variação inválida/oculta (front-end)

No tema, em `assets/js/single-product.js`, foi ajustado o tratamento do evento `hide_variation`:

- O WooCommerce dispara `hide_variation` quando a combinação selecionada não deve exibir a área de compra (por exemplo, variação não comprável).
- Antes, o código voltava ao “estado inicial”, o que podia reexibir botões indevidamente.

Agora, no `hide_variation`, o script tenta identificar a variação correspondente usando `product_variations` e os atributos selecionados; se a variação existir e estiver sem estoque, mantém o estado de indisponível.

### 3) (Opcional / correção de markup) atributo `hidden` do card

No template `woocommerce/content-single-product.php`, foi ajustada a forma de imprimir o atributo `hidden` do card “Produto indisponível”, evitando uso desnecessário de `esc_attr()` em uma string já controlada (`' hidden'`).

## Como validar a correção

1. Abrir o produto variável.
2. Selecionar a variação **azul** (com estoque): botões aparecem normalmente.
3. Selecionar **branco** ou **preto** (estoque 0): botões devem sumir e o card “Produto indisponível no momento” deve aparecer.
4. Confirmar que não é mais possível tentar comprar uma variação sem estoque via UI (não apenas via bloqueio no checkout).

## Observação importante

Esse tipo de problema costuma acontecer quando algum fluxo de atualização de variações (plugin, integração, importação, etc.) altera `stock_quantity` mas não mantém sincronizado o conjunto completo de informações que o WooCommerce usa para derivar “em estoque/fora de estoque”.

Nesta correção, a estratégia foi **tornar o payload de variações consistente com a quantidade real**, garantindo que o front-end receba `is_in_stock` correto e a experiência do usuário fique coerente com a validação do carrinho/checkout.


# Design QA — avisos nos cards e na barra inferior da galeria

## Findings

- Nenhum P0, P1 ou P2 permaneceu na comparação final.
- Os dois avisos do card ocupam uma única linha horizontal chapada na faixa inferior da imagem.
- Os badges não têm sombra nem arredondamento e usam somente 2 px de separação.
- Com dois badges, o conjunto é centralizado e deixa margens laterais visualmente idênticas; com um badge, há 12 px de respiro à direita.
- Em desktop e mobile, os dois badges ficaram inteiramente contidos no card, sem quebra de linha, aumento de altura ou rolagem horizontal.
- Na página do produto, os avisos agora compartilham a faixa cinza inferior com o botão Zoom, sem cobrir a imagem nem alterar a proporção quadrada da galeria.
- O console do preview terminou sem erros ou avisos.

## Comparison history

1. [P2] A primeira implementação empilhava os avisos verticalmente no canto inferior direito.
   - Correção: o container do card passou a usar `flex-flow: row nowrap`, ocupar a faixa entre as duas margens laterais e alinhar os badges pela direita.
2. Verificação pós-correção:
   - Desktop: ambos os badges com o mesmo eixo vertical e contidos na área de 258,4 × 220 px do card.
   - Mobile: ambos os badges com o mesmo eixo vertical e contidos na área de 278,4 × 200 px do card.
   - Resultado: a divergência P2 foi eliminada.
3. [P2] O acabamento ainda tinha sombra, cantos arredondados e respiro inferior maior que o desejado.
   - Correção: `box-shadow: none`, `border-radius: 0`, gap reduzido para 2 px e faixa movida para 4 px da borda inferior.
   - Evidência pós-correção: desktop e mobile reportaram `borderRadius: 0px`, `boxShadow: none`, `gap: 2px` e os dois badges permaneceram contidos na imagem.
4. [P2] A dupla estava alinhada à direita, deixando uma margem lateral maior à esquerda.
	- Correção: o container mantém 12 px nas laterais e usa `justify-content: center` somente quando detecta um segundo badge; com um badge, preserva `flex-end`.
	- Evidência pós-correção: desktop registrou 14,19 px à esquerda e 14,20 px à direita; mobile registrou 24,19 px e 24,20 px. Um badge isolado manteve 12 px à direita nos dois breakpoints.
5. [P2] Na loja teste, a dupla continuava alinhada à direita apesar do CSS atualizado.
   - Causa observada no primeiro card real: o WordPress inseriu elementos `<br>` entre os badges, então o antigo seletor baseado no segundo filho não reconhecia a dupla.
   - Correção: o HTML agora recebe a classe explícita `Gstore-product-image-badges--count-2`, e somente o contexto `card` usa essa classe para centralizar o conjunto. A página individual continua sem essa regra.
   - Evidência pós-correção: o preview registrou `justify-content: center`, 14,19 px à esquerda e 14,20 px à direita, diferença de 0,01 px. Console sem erros ou avisos.
6. [P2] Na galeria individual, os avisos permaneciam sobre a área branca da imagem e separados verticalmente do Zoom.
   - Correção: badges, preview de variação e Zoom passaram a compartilhar uma barra de ações absoluta dentro da faixa cinza reservada, preservando a área quadrada da galeria.
   - Primeira verificação mobile: os elementos permaneceram na mesma linha, mas os textos dos dois badges truncaram cedo demais.
   - Ajuste mobile: fonte de 10,5 px, padding de 5 px e gap interno de 4 px. Em 390 px, “12x sem juros” e “Últimas unidades” ficaram completos, o Zoom continuou visível e não houve overflow horizontal.

## Required fidelity surfaces

- Fonts and typography: cards preservam 10 px; a galeria usa 12 px no desktop e 10,5 px no mobile, mantendo peso, caixa e legibilidade dos dois rótulos.
- Spacing and layout rhythm: cards preservam a linha a 4 px da borda inferior; na galeria, badges e Zoom compartilham o mesmo eixo na faixa cinza, com 12 px de margem no desktop e 8 px no mobile.
- Colors and visual tokens: frete grátis mantém `--gstore-color-accent`; parcelamento mantém `#b54708`; contraste inalterado.
- Image quality and asset fidelity: imagem real de produto e ícones Font Awesome preservados, sem substituições desenhadas em CSS.
- Copy and content: rótulos dos cards preservados; na galeria, “12x sem juros” e “Últimas unidades” permanecem completos no desktop e no mobile validado.

## Comparison evidence

- Source visual truth: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-109224da-e914-43f0-8aec-d0c13ea10799.png` — 630 × 106 px; estado anterior da galeria com os badges na área branca e o Zoom isolado na faixa cinza.
- Implementation URL: `http://127.0.0.1:8765/docs/visual-snapshots/manual/product-image-badges-preview.html`.
- Full-view combined comparison: `C:/Users/mathe/Documents/Gstore-theme-codex-product-image-badges/docs/visual-snapshots/manual/product-image-badges-single-toolbar-desktop.png` — 1350 × 759 px; viewport CSS 1366 × 768; DPR 1. A própria página de QA reúne a referência e a implementação na mesma captura.
- Focused mobile evidence: `C:/Users/mathe/Documents/Gstore-theme-codex-product-image-badges/docs/visual-snapshots/manual/product-image-badges-single-toolbar-mobile.png` — 375 × 811 px; viewport solicitado 390 × 844; DPR 1.
- State: cards preservados; galeria individual com dois avisos à esquerda e Zoom à direita na mesma faixa cinza; rótulos completos no mobile.
- Primary interactions tested: não aplicável aos badges, que são informativos e usam `pointer-events: none`.
- Console errors checked: nenhum erro ou aviso.

## Implementation checklist

1. [x] Linha horizontal única no card.
2. [x] Dois badges simultâneos sem wrap.
3. [x] Respiro inferior e lateral preservado.
4. [x] Card mantém 220 px no desktop e 200 px no mobile.
5. [x] Texto longo pode encolher e truncar sem estourar a imagem.
6. [x] Assets minificados regenerados e verificados.
7. [x] Sem sombra, sem arredondamento, gap de 2 px e distância inferior de 4 px.
8. [x] Dupla com margens laterais iguais e badge isolado com 12 px à direita.
9. [x] Galeria individual com badges e Zoom na mesma barra inferior cinza.
10. [x] Mobile em 390 px sem wrap, overflow ou truncamento dos rótulos usados na validação.

final result: passed

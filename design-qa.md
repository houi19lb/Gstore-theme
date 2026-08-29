# Design QA — margens equilibradas na faixa de avisos do card

## Findings

- Nenhum P0, P1 ou P2 permaneceu na comparação final.
- Os dois avisos do card ocupam uma única linha horizontal chapada na faixa inferior da imagem.
- Os badges não têm sombra nem arredondamento e usam somente 2 px de separação.
- Com dois badges, o conjunto é centralizado e deixa margens laterais visualmente idênticas; com um badge, há 12 px de respiro à direita.
- Em desktop e mobile, os dois badges ficaram inteiramente contidos no card, sem quebra de linha, aumento de altura ou rolagem horizontal.
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

## Required fidelity surfaces

- Fonts and typography: fonte, peso, caixa alta e tamanho de 10 px preservados; textos longos continuam com truncamento seguro.
- Spacing and layout rhythm: linha posicionada a 4 px da borda inferior, com 2 px entre badges; dupla centralizada com margens iguais e badge isolado a 12 px da direita.
- Colors and visual tokens: frete grátis mantém `--gstore-color-accent`; parcelamento mantém `#b54708`; contraste inalterado.
- Image quality and asset fidelity: imagem real de produto e ícones Font Awesome preservados, sem substituições desenhadas em CSS.
- Copy and content: rótulos “Frete grátis” e “21x sem juros” preservados na comparação.

## Comparison evidence

- Source visual truth: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-b3c70a23-b1af-4b49-94d0-fbc7688dea4a.png` — 343 × 595 px; estado real do primeiro card da loja teste com dois badges ainda alinhados à direita.
- Implementation URL: `http://127.0.0.1:8765/docs/visual-snapshots/manual/product-image-badges-preview.html`.
- Full-view combined comparison: `C:/Users/mathe/Documents/Gstore-theme-codex-product-image-badges/docs/visual-snapshots/manual/product-image-badges-count-class-comparison.png` — 1064 × 639 px; viewport CSS 1536 × 639; DPR 1,25. A própria página de QA reúne a referência e a implementação na mesma captura.
- Focused mobile evidence: `C:/Users/mathe/Documents/Gstore-theme-codex-product-image-badges/docs/visual-snapshots/manual/product-image-badges-balanced-mobile.png` — 375 × 811 px; viewport solicitado 390 × 844; DPR 1.
- State: comparação de um e dois avisos no card; dupla centralizada; badge isolado alinhado à direita; galeria individual preservada.
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

final result: passed

# Design QA — faixa horizontal chapada de avisos no card

## Findings

- Nenhum P0, P1 ou P2 permaneceu na comparação final.
- Os dois avisos do card ocupam uma única linha horizontal chapada na faixa inferior da imagem.
- Os badges não têm sombra nem arredondamento e usam somente 2 px de separação.
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

## Required fidelity surfaces

- Fonts and typography: fonte, peso, caixa alta e tamanho de 10 px preservados; textos longos continuam com truncamento seguro.
- Spacing and layout rhythm: linha posicionada a 4 px da borda inferior em desktop e mobile, com 2 px entre badges, sem participar do fluxo do card.
- Colors and visual tokens: frete grátis mantém `--gstore-color-accent`; parcelamento mantém `#b54708`; contraste inalterado.
- Image quality and asset fidelity: imagem real de produto e ícones Font Awesome preservados, sem substituições desenhadas em CSS.
- Copy and content: rótulos “Frete grátis” e “21x sem juros” preservados na comparação.

## Comparison evidence

- Source visual truth: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-c26508bc-7179-410a-825d-8c0d501dc38d.png` — 1600 × 1121 px.
- Implementation URL: `http://127.0.0.1:8765/docs/visual-snapshots/manual/product-image-badges-preview.html`.
- Full-view combined comparison: `C:/Users/mathe/Documents/Gstore-theme-codex-product-image-badges/docs/visual-snapshots/manual/product-image-badges-flat-comparison.png` — 862 × 697 px; viewport CSS 862 × 698; DPR 1,25; captura normalizada para pixels CSS.
- Focused mobile evidence: `C:/Users/mathe/Documents/Gstore-theme-codex-product-image-badges/docs/visual-snapshots/manual/product-image-badges-flat-mobile.png` — 375 × 811 px; viewport solicitado 390 × 844; DPR 1.
- State: dois avisos simultâneos no card; faixa horizontal inferior chapada; galeria individual preservada com o mesmo acabamento sem sombra e sem arredondamento.
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

final result: passed

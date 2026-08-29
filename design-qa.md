# Design QA — avisos sobre a imagem do produto

## Findings

- Nenhum bloqueio visual encontrado na comparação final.
- Os dois avisos permanecem sobre a imagem e fora do fluxo: a área do card preservou os 220 px do desktop e os 200 px do breakpoint mobile.
- Em 390 px de largura, os avisos do card e da galeria ficaram inteiramente contidos nas respectivas imagens, sem rolagem horizontal.
- O console do preview terminou sem erros ou avisos.

## Implementation checklist

1. [x] Até dois avisos ativos por produto.
2. [x] Frete grátis usa a cor de destaque do tema.
3. [x] 12x e 21x sem juros usam a cor fixa de atenção.
4. [x] Texto personalizado limitado a 32 caracteres e renderizado em um badge compacto.
5. [x] Card do catálogo usa pilha no canto inferior direito, sem alterar suas dimensões.
6. [x] Galeria do produto usa uma fileira compacta no canto inferior esquerdo, acima dos controles.
7. [x] Layout validado em desktop (1440 × 1000) e mobile (390 × 844).

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-c26508bc-7179-410a-825d-8c0d501dc38d.png`.
- Combined source and implementation preview: `http://127.0.0.1:8765/docs/visual-snapshots/manual/product-image-badges-preview.html`.
- Desktop screenshot: `C:/Users/mathe/Documents/Gstore-theme-codex-product-image-badges/docs/visual-snapshots/manual/product-image-badges-implementation-desktop.png`.
- Mobile screenshot: `C:/Users/mathe/Documents/Gstore-theme-codex-product-image-badges/docs/visual-snapshots/manual/product-image-badges-mobile-card.png`.
- State: dois avisos simultâneos no card e dois na galeria; presets e texto personalizado representados.
- Colors and visual tokens: frete grátis usa `--gstore-color-accent` e `--gstore-color-accent-contrast`; parcelamento usa `#b54708`; personalizado usa `#27272a`.
- Spacing and layout rhythm: 12 px de respiro no card e 14 px na galeria; badges compactos com fonte de 10–11 px.
- Copy and content: rótulos preservados como “Frete grátis”, “12x sem juros”, “21x sem juros” e texto personalizado.

final result: passed

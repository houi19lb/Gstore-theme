# Design QA — alinhamento lateral do breadcrumb

**Findings**

- [P2] O ambiente de teste ainda posiciona o breadcrumb 37 px além da borda direita do terceiro card.
  Location: `/ofertas-relampago/`, `.Gstore-catalog-header` e `.Gstore-breadcrumb`.
  Evidence: no CSS publicado, o cabeçalho mede 1280 px e termina em `x=1400,4`; o terceiro card termina em `x=1363,4`. A implementação local limita o cabeçalho a 1243 px, exatamente a largura ocupada por filtro, gap e três cards.
  Impact: a faixa branca perde o mesmo eixo lateral usado pela área principal da página.
  Fix: aplicado no commit `3a9d759`; falta o ambiente de teste sincronizar a nova versão do asset.

**Open Questions**

- Nenhuma sobre o alinhamento. A captura final depende apenas da sincronização do ambiente de teste com a branch `alpha`.

**Implementation Checklist**

1. Sincronizar o commit `3a9d759` no ambiente de teste.
2. Confirmar que o breadcrumb termina em `x=1363,4`, no mesmo eixo do terceiro card.
3. Confirmar que o título continua começando em `x=120,4`, alinhado ao filtro.
4. Revalidar o cabeçalho responsivo abaixo de 1024 px.

**Follow-up Polish**

- Nenhum refinamento adicional foi identificado nesta correção isolada.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-b2410d11-c13c-41ad-baba-ff4506d206db.png` (1616 × 235 px) e requisito textual de alinhar o breadcrumb à borda dos cards.
- Implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/gstore-breadcrumb-before-3a9d759-1521x235.png` (1521 × 235 px).
- Combined comparison path: `C:/Users/mathe/AppData/Local/Temp/gstore-breadcrumb-comparison-3a9d759.png`.
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: 1536 px de largura; área útil de 1521 px; recorte comparado de 1521 × 235 CSS px.
- Density normalization: `devicePixelRatio=1.25`; a captura do navegador foi retornada em 1 px por CSS px para o recorte. A comparação geométrica usou medidas DOM em CSS px.
- State: campanha ativa, faixa branca, cabeçalho da campanha e início dos cards visíveis.
- Full-view comparison evidence: título da faixa branca alinhado ao filtro; breadcrumb ainda alinhado ao fim do contêiner de 1280 px no asset publicado.
- Focused region comparison evidence: breadcrumb publicado termina em `x=1400,4`; terceiro card termina em `x=1363,4`; diferença objetiva de 37 px.
- Fonts and typography: nenhuma propriedade tipográfica foi alterada.
- Spacing and layout rhythm: somente o limite máximo do cabeçalho mudou de 1280 px para 1243 px; cards, filtro, gaps e paddings foram preservados.
- Colors and visual tokens: nenhuma cor ou token foi alterado.
- Image quality and asset fidelity: nenhuma imagem, textura ou ícone foi alterado.
- Copy and content: nenhum texto foi alterado.
- Primary interactions tested: carregamento da página e medição dos limites laterais; nenhuma interação funcional foi afetada.
- Console errors checked: nenhum erro ou aviso relacionado ao componente foi observado.

## Comparison history

1. Antes do ajuste: título em `x=120,4`, filtro em `x=120,4`, breadcrumb terminando em `x=1400,4` e terceiro card em `x=1363,4`.
2. Correção `3a9d759`: `.Gstore-catalog-header` limitado a 1243 px, mantendo a origem em `x=120,4` e trazendo o limite direito esperado para `x=1363,4`.
3. Pós-fix: validações locais passaram, mas o ambiente de teste ainda serve `flash-sale.min.css?ver=1786902736`, com `max-width: none` no cabeçalho; não há captura renderizada da versão final.

final result: blocked

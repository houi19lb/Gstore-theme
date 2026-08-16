# Design QA — cards de ofertas relâmpago

**Findings**

- [P1] O ambiente de homologação ainda exibe o card achatado da versão anterior.
  Location: `/ofertas-relampago/`, grade de produtos.
  Evidence: na homepage, o card de oferta mede 309 × 440 px; na página dedicada publicada, mede 313 × 311 px. A imagem mede 173 px de altura na homepage e 106 px na página; o botão mede 45 px contra 34 px.
  Impact: a página dedicada perde a proporção, a hierarquia e o ritmo vertical do componente aprovado na homepage.
  Fix: aplicado no commit `9360a76`: removidos os overrides exclusivos que achatavam o card e configurada a grade desktop com cards de 309 px e gap de 12 px, mantendo três colunas.

**Open Questions**

- Nenhuma sobre o design. A validação final depende somente de o ambiente de homologação receber o commit `9360a76` da branch `alpha`.

**Implementation Checklist**

1. Publicar o commit `9360a76` no ambiente de homologação.
2. Recarregar `/ofertas-relampago/` e confirmar card de 309 × 440 px no viewport de 1536 × 639.
3. Confirmar imagem de 173 px, título de 16 px, botão de 45 px e gap de 12 px.

**Follow-up Polish**

- Depois da publicação, conferir também os breakpoints abaixo de 1025 px.

## Comparison evidence

- Source visual truth: card de ofertas relâmpago da homepage.
- Source screenshot path: `C:/Users/mathe/AppData/Local/Temp/gstore-home-flash-card-1536x639.png`.
- Additional source reference: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-9b687444-f2b1-43ce-8dd8-ab08c53647fd.png` (430 × 693 px).
- Implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/gstore-detail-flash-card-before-9360a76-1536x639.png`.
- Additional implementation reference: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-1c7b5306-6dba-4091-bb67-eb79e8f33096.png` (393 × 389 px).
- Viewport: 1536 × 639 CSS px, densidade 1×.
- State: campanha ativa, primeira grade de produtos e cronômetro visíveis.
- Density normalization: as capturas completas foram feitas no mesmo viewport e densidade; os recortes fornecidos pelo usuário foram usados apenas como evidência focada.
- Full-view comparison evidence: homepage e página dedicada foram capturadas no mesmo navegador e viewport. A homepage usa cards 309 × 440 px; a página publicada usa 313 × 311 px.
- Focused region comparison evidence: imagem 173 px versus 106 px; área superior 205 px versus 126 px; título 16 px versus 13,12 px; botão 45 px versus 34 px.
- Fonts and typography: a versão publicada reduz indevidamente o título e o botão; o código foi ajustado para herdar a tipografia da homepage.
- Spacing and layout rhythm: a versão publicada usa paddings menores e gap de 24 px; o código foi ajustado para os paddings herdados da homepage e gap de 12 px.
- Colors and visual tokens: preto, branco e amarelo permanecem consistentes entre as duas vitrines.
- Image quality and asset fidelity: os mesmos assets de produto são usados; a diferença observada é de escala e área disponível.
- Copy and content: os mesmos produtos e textos aparecem nos dois estados comparados.
- Primary interactions tested: carregamento da homepage e da página dedicada; cards e botões renderizados. Nenhuma interação funcional foi alterada.
- Console errors checked: não foram observados erros que expliquem a diferença de layout; a divergência vem das regras CSS publicadas.

## Comparison history

1. Antes do ajuste: página dedicada em 313 × 311 px, com imagem, texto e botão comprimidos.
2. Correção aplicada: removidos 67 linhas de overrides dimensionais da página; grid alinhado à homepage em 309 px e 12 px de gap.
3. Pós-fix: o commit `9360a76` está em `origin/alpha`, mas `https://lojateste.kivodigital.com.br/ofertas-relampago/` ainda entrega o CSS anterior e mantém 313 × 311 px.

final result: blocked

# Design QA — tamanho do selo “Ao vivo”

**Findings**

- [P2] O selo publicado ainda apresenta fonte e padding excessivos.
  Location: `/ofertas-relampago/`, `.gstore-flash-sale-live`.
  Evidence: no viewport de 1536 × 639 CSS px, o selo mede `130,6 × 48,4px`, usa fonte de `15,2px`, padding de `12 × 20px` e gap de `8px`.
  Impact: o selo compete visualmente com o título e ocupa espaço desnecessário acima do cronômetro.
  Fix: aplicado no commit `9a86769`; no desktop, o selo passa a usar fonte de `11,5px`, padding de `8 × 12px`, gap de `6px` e raio de `8px`.

**Open Questions**

- Nenhuma sobre o ajuste. A captura final depende de o ambiente de teste sincronizar o commit `9a86769` da branch `alpha`.

**Implementation Checklist**

1. Sincronizar o commit `9a86769` no ambiente de teste.
2. Confirmar selo próximo de `94 × 32px` no desktop.
3. Confirmar que o alinhamento à direita e a distância do cronômetro permanecem iguais.
4. Confirmar que o tamanho mobile anterior foi preservado.

**Follow-up Polish**

- Nenhum refinamento adicional foi identificado nesta alteração isolada.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-9f3b4762-7f6a-421c-86e9-2b1fa304d944.png` (241 × 99 px).
- Implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/gstore-flash-live-before-9a86769-1536x639.png` (1521 × 633 px).
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: 1536 × 639 CSS px; captura retornada pelo navegador em 1521 × 633 px.
- State: campanha ativa, selo “Ao vivo” e cronômetro visíveis.
- Density normalization: a referência é um recorte focado do selo; as dimensões objetivas foram medidas no DOM em CSS px.
- Full-view comparison evidence: o selo publicado domina visualmente a área superior direita por causa da combinação de fonte de 15,2 px e padding horizontal de 20 px.
- Focused region comparison evidence: a própria referência é o recorte focado do componente; não foi necessário outro recorte.
- Fonts and typography: somente o tamanho do texto do selo foi reduzido; família, peso e caixa alta permanecem iguais.
- Spacing and layout rhythm: padding e gap internos foram reduzidos; posição externa e espaçamento em relação ao cronômetro permanecem inalterados.
- Colors and visual tokens: vermelho, branco e fundo escuro permanecem inalterados.
- Image quality and asset fidelity: nenhuma imagem ou textura foi modificada.
- Copy and content: texto “Ao vivo” permanece inalterado.
- Primary interactions tested: carregamento da página; o selo não possui interação.
- Console errors checked: nenhum erro relacionado ao componente foi observado.

## Comparison history

1. Antes do ajuste: `130,6 × 48,4px`, fonte `15,2px`, padding `12 × 20px`, gap `8px` e raio `10px`.
2. Correção `9a86769`: fonte `.72rem`, padding `8 × 12px`, gap `6px` e raio `8px`, limitada ao desktop; validações de assets, escopo CSS e testes passaram.
3. Pós-fix: o ambiente de teste ainda serve o CSS anterior (`flash-sale.min.css?ver=1786902142`), portanto ainda não existe captura renderizada do tamanho final.

final result: blocked

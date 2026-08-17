# Design QA — divisórias do timer na página de produto

## Findings

- [P2] As divisórias internas usavam um dourado fixo enquanto a borda e o ícone herdavam a cor de destaque ativa do tema.
  - Evidência: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-8073d653-2aa8-402a-8ba3-f50e2436c1b0.png`.
  - Impacto: no tema vermelho, a caixa ficava com borda vermelha e separadores marrons.
  - Correção: as divisórias agora usam `--gstore-color-accent`, o mesmo token da borda e do ícone.

## Implementation Checklist

1. Confirmar borda, ícone e divisórias na mesma família de cor.
2. Confirmar que espessura, altura e espaçamento das divisórias não mudaram.
3. Confirmar a correção em desktop e mobile depois da sincronização da `alpha`.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-8073d653-2aa8-402a-8ba3-f50e2436c1b0.png`.
- Post-implementation screenshot: indisponível antes da publicação da branch `alpha`.
- Implementation URL: página de produto participante da campanha ativa na loja teste.
- State: timer de oferta relâmpago ativo.
- Colors and visual tokens: borda, ícone e divisórias compartilham `--gstore-color-accent`.
- Spacing and layout rhythm: inalterados.
- Copy and content: inalterados.

final result: blocked

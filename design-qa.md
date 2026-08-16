# Design QA — alinhamento do cabeçalho de ofertas relâmpago

**Findings**

- [P1] O cabeçalho publicado ultrapassa os limites laterais dos três cards.
  Location: `/ofertas-relampago/`, `.gstore-flash-sale-heading`.
  Evidence: no viewport de 1536 × 695, os cards ocupam `x=438` até `x=1389` (951 px), enquanto título, status ao vivo e cronômetro ocupam `x=364` até `x=1463` (1099 px).
  Impact: título e cronômetro parecem desconectados da grade e criam duas margens laterais diferentes na mesma seção.
  Fix: aplicado no commit `ce2f4bb`: cabeçalho limitado a 951 px, centralizado e mantido responsivo com largura de 100%.

**Open Questions**

- Nenhuma sobre o design. A validação final depende de o ambiente de homologação receber o commit `ce2f4bb` da branch `alpha`.

**Implementation Checklist**

1. Publicar o commit `ce2f4bb` no ambiente de homologação.
2. Confirmar cabeçalho em `x=438`, largura de 951 px e término em `x=1389` no viewport de 1536 px.
3. Confirmar que título, subtítulo, “Ao vivo” e cronômetro permanecem dentro dessa caixa.
4. Conferir que tablet e celular continuam usando 100% da largura disponível.

**Follow-up Polish**

- Nenhum ajuste adicional foi identificado para tipografia, cores, imagens ou conteúdo nesta rodada.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-7f4f65c9-808c-451e-830d-ba5a8a4f3c6e.png` (1495 × 561 px).
- Implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/gstore-flash-heading-before-ce2f4bb-1536x695.png` (1536 × 695 px).
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: 1536 × 695 CSS px, densidade 1×.
- State: campanha ativa, cabeçalho, cronômetro e primeira linha de três cards visíveis.
- Density normalization: a referência do usuário foi usada como evidência focada; as medidas objetivas vieram do DOM renderizado em 1536 × 695.
- Full-view comparison evidence: o cabeçalho publicado ocupa 1099 px, mas a área visual formada pelos três cards ocupa 951 px.
- Focused region comparison evidence: borda esquerda do cabeçalho em 364 contra primeiro card em 438; borda direita do cabeçalho em 1463 contra terceiro card em 1389.
- Fonts and typography: tamanhos, pesos, quebras e hierarquia permanecem inalterados; somente a caixa de alinhamento foi ajustada.
- Spacing and layout rhythm: correção de `max-width: 951px` e `margin: 0 auto 12px` aplicada ao cabeçalho.
- Colors and visual tokens: preto, branco, amarelo e vermelho permanecem inalterados.
- Image quality and asset fidelity: cards e imagens de produtos não foram modificados.
- Copy and content: título, subtítulo, status e unidades do cronômetro permanecem inalterados.
- Primary interactions tested: carregamento da página e atualização do cronômetro; nenhum comportamento interativo foi alterado.
- Console errors checked: nenhum erro relacionado ao layout foi observado.

## Comparison history

1. Rodada anterior: cards da página mediam 313 × 311 px; correção `9360a76` aplicada e já observada na homologação em 309 × 440 px.
2. Rodada atual, antes do ajuste: cabeçalho em `x=364..1463`; cards em `x=438..1389`.
3. Correção aplicada: cabeçalho limitado à mesma largura total de 951 px dos três cards no commit `ce2f4bb`.
4. Pós-fix: o commit está em `origin/alpha`, mas ainda não foi renderizado na homologação para a captura final.

final result: blocked

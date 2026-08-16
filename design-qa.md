# Design QA — respiro do ícone do relógio responsivo

**Findings**

- [P2] O ícone do relógio encosta no texto “TERMINA EM” na faixa intermediária.
  Location: cabeçalho da página dedicada entre 769 px e 1100 px.
  Evidence: a captura mostra o ícone ocupando uma coluna de 12 px embora seu tamanho renderizado seja próximo de 14 px, deixando o texto visualmente colado.
  Impact: o início do contador perde legibilidade e parece sobreposto.
  Fix: a coluna exclusiva do ícone passou de 12 px para 14 px e o espaço até o rótulo passou de 2 px para 3 px, sem aumentar o padding geral do contador.

**Open Questions**

- A captura pós-fix depende da sincronização da branch `alpha` na loja teste.

**Implementation Checklist**

1. Sincronizar a branch `alpha`.
2. Repetir a largura intermediária da captura.
3. Confirmar o respiro entre o ícone e “TERMINA EM”.
4. Confirmar que a borda do contador continua livre do subtítulo.

**Follow-up Polish**

- Nenhuma mudança foi aplicada aos números, cards, filtros ou demais breakpoints.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-fccde2b7-7fc7-4210-9c7f-ab8f5bc42ef9.png` (309 × 159 px), com o ícone próximo demais do rótulo.
- Post-fix implementation screenshot path: indisponível antes da sincronização do ambiente.
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: recorte da faixa responsiva intermediária; a captura não exibe o valor exato da largura CSS.
- Density normalization: não aplicada; a avaliação usa a relação visual entre ícone, rótulo e borda.
- State: campanha ativa, contador visível e ordenação logo abaixo.
- Full-view comparison evidence: não necessária para esta correção pontual; nenhuma estrutura externa foi alterada.
- Focused region comparison evidence: o recorte mostra o ícone praticamente encostado em “TERMINA EM”.
- Fonts and typography: preservadas.
- Spacing and layout rhythm: somente a coluna do ícone e o gap adjacente foram aumentados.
- Colors and visual tokens: não alterados.
- Image quality and asset fidelity: o ícone existente foi preservado; nenhum asset novo foi criado.
- Copy and content: não alterados.
- Primary interactions tested: timer e JavaScript não foram modificados.
- Console errors checked: indisponível antes da publicação no ambiente autenticado.

## Comparison history

1. Estado anterior: coluna do ícone com 12 px e gap de 2 px.
2. Correção atual: coluna do ícone com 14 px e gap de 3 px.
3. Pós-fix: falta captura após sincronização para encerrar a comparação visual.

final result: blocked

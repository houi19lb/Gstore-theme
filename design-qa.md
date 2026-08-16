# Design QA — segunda compactação do relógio responsivo

**Findings**

- [P2] O relógio ainda cobre o subtítulo na menor largura intermediária.
  Location: cabeçalho da página dedicada entre 769 px e 1100 px.
  Evidence: na primeira captura existe um pequeno respiro; na segunda, mais estreita, a borda e o rótulo do relógio voltam a atravessar o final do subtítulo.
  Impact: a frase fica parcialmente ilegível antes de o layout mobile assumir.
  Fix: a coluna do relógio foi reduzida novamente, de 280–300 px para 240–260 px, com padding de 3 × 4 px, gaps de 2 px e unidades com 1 px de padding lateral.

**Open Questions**

- A captura pós-fix depende da sincronização da branch `alpha` na loja teste.

**Implementation Checklist**

1. Sincronizar a branch `alpha`.
2. Repetir as duas larguras intermediárias das capturas.
3. Confirmar respiro entre o subtítulo e o relógio em ambas.
4. Confirmar que desktop amplo e mobile não mudaram.

**Follow-up Polish**

- Nenhuma mudança foi aplicada aos cards, filtros ou textos.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-d7a86a29-73e6-49e9-b144-7b875d96b190.png` (683 × 774 px), largura intermediária sem colisão relevante.
- Pre-fix implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-39d60cea-40ed-480f-90b1-a1fd99bcc00c.png` (672 × 811 px), largura menor com colisão.
- Post-fix implementation screenshot path: indisponível antes da sincronização do ambiente.
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: modo responsivo intermediário; as capturas não exibem o valor exato da largura CSS.
- Density normalization: não aplicada; comparação feita pela posição relativa do título, subtítulo e relógio.
- State: campanha ativa, filtros visíveis e grid com duas colunas.
- Full-view comparison evidence: ao estreitar a página, o relógio mantém largura suficiente para avançar sobre a coluna textual.
- Focused region comparison evidence: na segunda captura, “Termina em” e a borda esquerda aparecem sobre o final de “tempo limitado”.
- Fonts and typography: tamanhos e pesos foram preservados.
- Spacing and layout rhythm: coluna limitada a 260 px, gap externo de 8 px, altura mínima de 38 px e padding interno de 3 × 4 px.
- Colors and visual tokens: não alterados.
- Image quality and asset fidelity: não há novos assets; ícones existentes foram preservados.
- Copy and content: não alterados.
- Primary interactions tested: timer e JavaScript não foram modificados.
- Console errors checked: não disponível antes da publicação no ambiente autenticado.

## Comparison history

1. Ajuste anterior: relógio reduzido para 280–300 px; resolveu a largura maior, mas não a menor.
2. Correção atual: relógio reduzido para 240–260 px e espaçamentos internos compactados novamente.
3. Pós-fix: assets minificados, escopo CSS e testes automatizados passaram; falta captura após sincronização.

final result: blocked

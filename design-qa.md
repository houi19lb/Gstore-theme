# Design QA — relógio responsivo sem colisão

**Findings**

- [P2] O relógio invade o subtítulo em larguras próximas de 1039 px.
  Location: cabeçalho da página dedicada entre 769 px e 1100 px.
  Evidence: a captura mostra a borda e o rótulo “Termina em” sobre “Produtos selecionados com preços especiais por tempo limitado.”.
  Impact: prejudica a leitura do subtítulo e deixa o cabeçalho visualmente congestionado.
  Fix: a coluna do relógio passa de 330–360 px para 280–300 px nessa faixa; gaps, altura mínima e padding interno também foram reduzidos.

**Open Questions**

- A captura pós-fix depende da sincronização da branch `alpha` na loja teste.

**Implementation Checklist**

1. Sincronizar a branch `alpha`.
2. Conferir o cabeçalho em 1039 px e nas larguras próximas.
3. Confirmar que o relógio não cobre o subtítulo.
4. Confirmar que desktop amplo e mobile mantêm os respectivos layouts.

**Follow-up Polish**

- Nenhuma mudança foi aplicada aos cards ou ao conteúdo do timer.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-dd9dfef1-2566-432e-8311-1a858d7c3cb4.png` (232 × 46 px), recorte do relógio.
- Pre-fix implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-cfb16985-173d-4f58-a688-1ebfe9ea56da.png` (723 × 850 px).
- Post-fix implementation screenshot path: indisponível antes da sincronização do ambiente.
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: 1039 × 1274 CSS px, conforme o painel responsivo da captura; screenshot exibida a 50%.
- Density normalization: não aplicada; a colisão é avaliada pela posição relativa entre subtítulo e relógio.
- State: campanha ativa, filtro visível e grid com duas colunas.
- Full-view comparison evidence: o relógio começa antes do término visual do subtítulo.
- Focused region comparison evidence: a borda esquerda e “Termina em” cruzam a última parte da frase abaixo do título.
- Fonts and typography: tamanhos e pesos não foram alterados.
- Spacing and layout rhythm: coluna do relógio limitada a 300 px, gap externo reduzido para 12 px, padding para 5 × 7 px e padding das unidades para 2 px.
- Colors and visual tokens: não alterados.
- Image quality and asset fidelity: não há novos assets; ícones existentes foram preservados.
- Copy and content: não alterados.
- Primary interactions tested: atualização do timer e JavaScript não foram modificados.
- Console errors checked: não disponível antes da publicação no ambiente autenticado.

## Comparison history

1. Antes do ajuste: relógio de pelo menos 330 px ocupa parte do espaço necessário ao subtítulo em 1039 px.
2. Correção: relógio e espaçamentos internos são compactados somente entre 769 px e 1100 px.
3. Pós-fix: assets minificados, escopo CSS e testes automatizados passaram; falta captura após sincronização.

final result: blocked

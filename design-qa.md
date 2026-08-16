# Design QA — selo “AO VIVO” no mobile estreito

**Findings**

- [P2] O selo “AO VIVO” encosta no título em 360 px.
  Location: cabeçalho da página dedicada em celulares estreitos.
  Evidence: na captura do Samsung Galaxy S8+, a borda esquerda do selo avança sobre o final de “OFERTAS RELÂMPAGO”.
  Impact: título e status perdem separação e parecem sobrepostos.
  Fix: abaixo de 400 px, o selo passa a usar fonte de .58 rem, gap de 5 px e padding de 7 × 8 px, preservando o tamanho atual nas larguras maiores.

**Open Questions**

- A captura pós-fix depende da sincronização da branch `alpha` na loja teste.

**Implementation Checklist**

1. Sincronizar a branch `alpha`.
2. Repetir o viewport de 360 × 740 px.
3. Confirmar espaço visível entre o título e o selo.
4. Confirmar que o selo mantém legibilidade e não altera o timer.

**Follow-up Polish**

- Nenhuma mudança foi aplicada ao título, timer, cards, filtros ou desktop.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-a6c3dae9-af4d-44ed-9145-3ed1342f28f4.png` (557 × 781 px), captura do DevTools configurado para 360 × 740 px a 75%.
- Post-fix implementation screenshot path: indisponível antes da sincronização do ambiente.
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: 360 × 740 CSS px, Samsung Galaxy S8+ no modo responsivo.
- Density normalization: não aplicada; a captura inclui a interface do DevTools e escala visual de 75%.
- State: campanha ativa, cabeçalho mobile, timer e primeiro card visíveis.
- Full-view comparison evidence: a página mantém uma coluna e o problema está restrito ao encontro entre título e selo.
- Focused region comparison evidence: a borda do selo cruza visualmente o final do título na primeira linha do cabeçalho.
- Fonts and typography: somente a fonte do selo reduz abaixo de 400 px; título e subtítulo permanecem iguais.
- Spacing and layout rhythm: gap e padding internos do selo diminuem progressivamente no breakpoint estreito.
- Colors and visual tokens: não alterados.
- Image quality and asset fidelity: ícone e ponto vermelho existentes foram preservados; nenhum asset novo foi criado.
- Copy and content: não alterados.
- Primary interactions tested: timer e JavaScript não foram modificados.
- Console errors checked: indisponível antes da publicação no ambiente autenticado.

## Comparison history

1. Estado anterior: selo com fonte de .66 rem e padding de 8 × 10 px em 360 px, encostando no título.
2. Correção atual: breakpoint em 400 px com fonte de .58 rem, gap de 5 px e padding de 7 × 8 px.
3. Pós-fix: falta captura após sincronização para encerrar a comparação visual.

final result: blocked

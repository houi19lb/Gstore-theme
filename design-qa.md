# Design QA — quebra intermediária do grid

**Findings**

- [P2] O grid de três colunas deixa o CTA dos cards estreito demais em 1049 px.
  Location: página dedicada, grade de produtos entre 1025 px e 1200 px.
  Evidence: a captura mostra “Adicionar ao carrinho” quebrando em duas linhas nos três cards da primeira fileira.
  Impact: aumenta a altura do botão e deixa os cards mais estreitos que o padrão visual do catálogo.
  Fix: nessa faixa intermediária, o grid passa para duas colunas de até 309 px; acima de 1200 px continuam três colunas.

**Open Questions**

- A captura pós-fix depende da sincronização da branch `alpha` na loja teste.

**Implementation Checklist**

1. Sincronizar a branch `alpha` na loja teste.
2. Conferir a página entre 1025 px e 1200 px.
3. Confirmar duas colunas e o CTA em uma linha.
4. Confirmar três colunas acima de 1200 px e uma coluna no mobile estreito.

**Follow-up Polish**

- Nenhuma mudança foi aplicada à estrutura, tipografia ou altura interna dos cards.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-d2564917-4074-4680-89e6-f358894b1d73.png` (703 × 852 px).
- Pre-fix implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-d2564917-4074-4680-89e6-f358894b1d73.png` (703 × 852 px).
- Post-fix implementation screenshot path: indisponível antes da sincronização do ambiente.
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: 1049 × 1274 CSS px, conforme o painel responsivo visível na captura; screenshot exibida a 50%.
- Density normalization: não aplicada; a avaliação usa a estrutura e a quebra visível do texto.
- State: campanha ativa, filtros visíveis, cinco produtos carregados.
- Full-view comparison evidence: três colunas comprimem cada card e fazem todos os CTAs visíveis quebrarem.
- Focused region comparison evidence: os botões da primeira fileira exibem “Adicionar ao” e “carrinho” em linhas separadas.
- Fonts and typography: não alteradas.
- Spacing and layout rhythm: somente a contagem de colunas muda; gap de 12 px e largura máxima de 309 px são preservados.
- Colors and visual tokens: não alterados.
- Image quality and asset fidelity: imagens e recortes dos produtos não foram alterados.
- Copy and content: não alterados.
- Primary interactions tested: não houve mudança funcional no botão ou no carrinho.
- Console errors checked: não disponível antes da publicação no ambiente autenticado.

## Comparison history

1. Antes do ajuste: em 1049 px, o grid permanece com três colunas e o CTA quebra em duas linhas.
2. Correção: breakpoint intermediário entre 1025 px e 1200 px define duas colunas de até 309 px.
3. Pós-fix: assets minificados, escopo CSS e testes automatizados passaram; falta captura após sincronização.

final result: blocked

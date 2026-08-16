# Design QA — resultados e ordenação das ofertas relâmpago

**Findings**

- [P2] A contagem de resultados e a ordenação ainda estão ocultas no ambiente de teste.
  Location: `/ofertas-relampago/`, antes de `.Gstore-products-grid ul.products`.
  Evidence: os elementos `.woocommerce-result-count` e `.woocommerce-ordering` existem no HTML e funcionam com o WooCommerce, mas o CSS publicado ainda aplica `display: none`.
  Impact: o usuário não vê quantos produtos estão disponíveis nem consegue alterar a ordem da listagem.
  Fix: aplicado no commit `25096af`; os controles foram reativados e organizados em uma linha de 951 px alinhada aos três cards.

**Open Questions**

- Nenhuma sobre o comportamento. A captura final depende de o ambiente de teste sincronizar o commit `25096af` da branch `alpha`.

**Implementation Checklist**

1. Sincronizar o commit `25096af` no ambiente de teste.
2. Confirmar contagem à esquerda e seletor à direita antes dos cards.
3. Testar a alteração de ordenação e confirmar a atualização da listagem.
4. Confirmar que, no celular, a contagem e o seletor ficam empilhados e o seletor ocupa 100% da largura.

**Follow-up Polish**

- Nenhum refinamento adicional foi identificado nesta alteração isolada.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-713f1efb-1edb-45e6-a5d7-198659645ed1.png` (1285 × 95 px).
- Implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/gstore-flash-result-sort-before-25096af-1536x639.png` (1521 × 633 px).
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: 1536 × 639 CSS px; captura retornada pelo navegador em 1521 × 633 px.
- State: campanha ativa, cabeçalho e primeira linha de cards visíveis; controles ainda ocultos no CSS publicado.
- Density normalization: a referência é um recorte focado do componente; a implementação foi medida no DOM em CSS px.
- Full-view comparison evidence: no ambiente publicado, os cards começam imediatamente após o cabeçalho da campanha, sem a linha informativa solicitada.
- Focused region comparison evidence: os elementos reais do WooCommerce foram encontrados como irmãos imediatamente anteriores à lista de produtos, ambos com `display: none`.
- Fonts and typography: contagem usa texto discreto de `.75rem`; seletor usa `.78rem`, mantendo a família tipográfica da página.
- Spacing and layout rhythm: linha limitada a 951 px, gap vertical de 14 px e alinhamento com as bordas dos três cards.
- Colors and visual tokens: texto branco atenuado; seletor preto com borda amarela suave e foco amarelo.
- Image quality and asset fidelity: nenhuma imagem ou textura foi modificada.
- Copy and content: textos e opções são fornecidos pelo WooCommerce conforme quantidade e ordenação reais.
- Primary interactions tested: a estrutura e o seletor nativo foram confirmados no DOM; a interação final aguarda o CSS novo ser publicado.
- Console errors checked: nenhum erro relacionado ao componente foi observado.

## Comparison history

1. Antes do ajuste: contagem e formulário de ordenação presentes no DOM, ambos ocultos; cards começando em `y=346,1`.
2. Correção `25096af`: controles reativados, grid de duas colunas no desktop, largura máxima de 951 px, seletor funcional e layout móvel empilhado; validações de assets, escopo CSS e testes passaram.
3. Pós-fix: o ambiente de teste ainda serve o CSS anterior (`flash-sale.min.css?ver=1786902736`), portanto ainda não existe captura renderizada do componente final.

final result: blocked

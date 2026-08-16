# Design QA — alinhamento da faixa superior de ofertas relâmpago

**Findings**

- [P2] O título e o breadcrumb publicados ainda não seguem os limites laterais da área principal.
  Location: `/ofertas-relampago/`, `.Gstore-flash-sale-page__topbar-content`.
  Evidence: no viewport de 1536 × 639 CSS px, a main ocupa `x=120,4…1400,4`; o título começa em `x=84,5` e o breadcrumb termina em `x=1436,3`.
  Impact: a faixa branca usa margens diferentes do filtro e da grade de cards, quebrando o alinhamento do catálogo.
  Fix: aplicado no commit `cd76661`; a faixa superior agora usa o mesmo contêiner máximo de 1280 px da main e o mesmo respiro lateral responsivo.

**Open Questions**

- Nenhuma sobre o layout. A captura final depende de o ambiente de teste sincronizar o commit `cd76661` da branch `alpha`.

**Implementation Checklist**

1. Sincronizar o commit `cd76661` no ambiente de teste.
2. Confirmar título em `x=120,4`, alinhado à borda esquerda do filtro.
3. Confirmar breadcrumb terminando em `x=1400,4`, alinhado ao limite direito da main.
4. Conferir o respiro lateral de 16 px em tablet e celular.

**Follow-up Polish**

- Nenhum refinamento adicional foi identificado nesta alteração isolada.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-c10c6ea1-54d2-4d9f-b216-172efa1c7431.png` (1919 × 520 px).
- Implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/gstore-flash-topbar-before-cd76661-1536x639.png` (1521 × 633 px).
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: 1536 × 639 CSS px; captura retornada pelo navegador em 1521 × 633 px.
- State: campanha ativa, cabeçalho branco, filtro, cronômetro e primeira linha de cards visíveis.
- Density normalization: a referência foi usada como evidência visual do desalinhamento; as posições objetivas foram medidas no DOM em CSS px.
- Full-view comparison evidence: a faixa superior publicada usa 84,48 px de padding lateral, enquanto a main centralizada começa em 120,4 px.
- Focused region comparison evidence: título deslocado 35,9 px para fora à esquerda e breadcrumb 35,9 px para fora à direita em relação à main.
- Fonts and typography: família, peso, tamanho, altura de linha e conteúdo não foram alterados.
- Spacing and layout rhythm: o contêiner superior passou a compartilhar `max-width: 1280px`, centralização e respiro responsivo da main.
- Colors and visual tokens: branco, preto, amarelo e demais cores permanecem inalterados.
- Image quality and asset fidelity: textura e imagens de produtos não foram modificadas.
- Copy and content: título, breadcrumb e demais textos permanecem inalterados.
- Primary interactions tested: carregamento da página; nenhuma interação foi alterada.
- Console errors checked: nenhum erro relacionado ao layout foi observado.

## Comparison history

1. Antes do ajuste: main em `x=120,4…1400,4`; título em `x=84,5`; breadcrumb terminando em `x=1436,3`.
2. Correção `cd76661`: topbar limitada a 1280 px, centralizada e com largura responsiva equivalente à área principal; validações de assets, escopo CSS e testes passaram.
3. Pós-fix: o ambiente de teste ainda serve o CSS anterior (`flash-sale.min.css?ver=1786902142`), portanto ainda não existe captura renderizada do alinhamento final.

final result: blocked

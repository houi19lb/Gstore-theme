# Design QA — título do topo no mobile

**Findings**

- [P2] O título “Ofertas relâmpago” ocupa uma linha desnecessária na faixa branca do mobile.
  Location: `.Gstore-flash-sale-page__topbar .Gstore-catalog-title`, até 640 px.
  Evidence: a captura de referência pede a remoção isolada desse título; o breadcrumb e o botão de filtros devem permanecer.
  Impact: a faixa branca fica mais alta e empurra a campanha para baixo.
  Fix: o título foi ocultado somente no mobile e a margem superior residual das ações foi removida.

**Open Questions**

- Nenhuma sobre o escopo. A captura pós-fix depende da sincronização do ambiente de teste em um celular real.

**Implementation Checklist**

1. Sincronizar a branch `alpha` no ambiente de teste.
2. Confirmar que apenas o título superior desapareceu no mobile.
3. Confirmar que breadcrumb, botão de filtros, cabeçalho amarelo, cards e desktop permanecem iguais.

**Follow-up Polish**

- Nenhum; a mudança é intencionalmente restrita à faixa branca no mobile.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-18c5dfd7-059a-474a-9d6a-598c9c20eb15.png` (269 × 42 px).
- Pre-fix implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-18c5dfd7-059a-474a-9d6a-598c9c20eb15.png` (269 × 42 px).
- Post-fix implementation screenshot path: indisponível antes da sincronização do ambiente.
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: recorte fornecido pelo usuário de uma tela mobile real; breakpoint implementado em até 640 px.
- Density normalization: não necessária para o recorte; nenhuma emulação de viewport foi usada no navegador desktop.
- State: topo branco da página dedicada de ofertas relâmpago no mobile.
- Full-view comparison evidence: não aplicável; o alvo é um único elemento isolado da faixa superior.
- Focused region comparison evidence: o recorte mostra apenas o título que deve ser removido.
- Fonts and typography: somente o título superior é ocultado; nenhuma propriedade tipográfica restante foi alterada.
- Spacing and layout rhythm: removida a margem superior residual das ações após ocultar o título.
- Colors and visual tokens: não alterados.
- Image quality and asset fidelity: não há imagens ou assets envolvidos.
- Copy and content: o texto continua no cabeçalho amarelo da campanha; apenas a duplicação na faixa branca é ocultada.
- Primary interactions tested: não aplicável à regra visual; filtros e breadcrumb não foram ocultados.
- Console errors checked: captura pós-fix não disponível; sem emulação no navegador do usuário.

## Comparison history

1. Antes do ajuste: o título aparece sozinho no topo branco e aumenta a altura da área superior.
2. Correção: `display: none` aplicado ao título apenas até 640 px, com a margem superior das ações zerada.
3. Pós-fix: validação visual real ainda depende da sincronização da `alpha` no ambiente de teste.

final result: blocked

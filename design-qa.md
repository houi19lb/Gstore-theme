# Design QA — cabeçalho mobile das ofertas relâmpago

**Findings**

- [P1] O ambiente de teste ainda exibe um vazio de 260 px antes do cabeçalho da campanha e comprime o título no mobile.
  Location: `/ofertas-relampago/`, `.Gstore-catalog-sidebar` e `.gstore-flash-sale-heading` em 390 px.
  Evidence: a referência da home mostra título, selo “Ao vivo” e timer em um bloco de 103,4 px. Na página dedicada publicada, o filtro fechado reserva 260 px e o grid do cabeçalho usa colunas de `12,4px 360px`, deixando título e subtítulo sem largura útil.
  Impact: o principal conteúdo promocional fica fora da primeira dobra e o bloco mais importante da página perde legibilidade.
  Fix: aplicado no commit `6c9423c`; o filtro fechado deixa de reservar altura, o cabeçalho volta a `minmax(0, 1fr) auto` e o timer recebe as mesmas medidas mobile da home.

**Open Questions**

- Nenhuma sobre a direção visual. A comparação pós-fix depende da sincronização do ambiente de teste com a branch `alpha`.

**Implementation Checklist**

1. Sincronizar o commit `6c9423c` no ambiente de teste.
2. Confirmar que o cabeçalho da campanha começa logo após o padding superior da área preta.
3. Confirmar título e selo “Ao vivo” na primeira linha, subtítulo abaixo e timer em largura total.
4. Confirmar o comportamento em 390 px e 320 px, incluindo a abertura do painel de filtros.

**Follow-up Polish**

- Nenhum refinamento P3 foi identificado antes da captura pós-fix.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/gstore-home-flash-mobile-reference-390x844.png` (375 × 811 px), versão mobile da home no mesmo ambiente.
- Implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/gstore-flash-page-mobile-before-top-383x844.png` (383 × 844 px).
- Combined comparison path: `C:/Users/mathe/AppData/Local/Temp/gstore-flash-mobile-comparison-before-v2.png`.
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: 390 × 844 CSS px; as capturas refletem a largura útil depois da barra de rolagem do navegador.
- Density normalization: ambas as páginas foram medidas no mesmo navegador e viewport; a comparação geométrica usa CSS px.
- State: campanha ativa, cabeçalho promocional e primeiro card visíveis; a home foi rolada até a seção equivalente.
- Full-view comparison evidence: a home apresenta o bloco promocional imediatamente antes dos cards; a página dedicada publicada contém um vazio preto de 260 px entre a navegação e o cabeçalho da campanha.
- Focused region comparison evidence: home usa colunas `260,5px 85,9px`, gap de 12 px, título de 20,3 px e timer de 47,4 px; página dedicada publicada usa `12,4px 360px`, título de 27,2 px e timer com largura extrapolada para 390,4 px.
- Fonts and typography: o fix replica os tamanhos mobile da home para título, selo, rótulo e unidades do timer; o subtítulo mantém a cópia da página e pode quebrar naturalmente.
- Spacing and layout rhythm: removidos 260 px reservados pelo filtro fechado e 24 px de margem entre a faixa branca e a área preta; padding superior da faixa branca reduzido para 12 px.
- Colors and visual tokens: cores, bordas, textura e contraste permanecem os mesmos da campanha existente.
- Image quality and asset fidelity: nenhuma imagem, textura, ícone ou foto de produto foi alterada.
- Copy and content: textos e contagem regressiva foram preservados.
- Primary interactions tested: carregamento responsivo e estrutura do painel lateral; a abertura pós-fix do filtro aguarda o CSS novo no ambiente.
- Console errors checked: nenhum erro ou aviso relacionado ao componente foi observado.

## Comparison history

1. Antes do ajuste: filtro fechado com 260 px de altura, cabeçalho iniciando em `y=526,4`, grid `12,4px 360px`, título de 27,2 px e subtítulo sem largura útil.
2. Correção `6c9423c`: filtro fechado passa a `flex-basis: 0` e altura zero; margem superior da área preta removida; cabeçalho e timer recebem a composição mobile da home.
3. Pós-fix: validações locais passaram, mas o ambiente ainda serve `flash-sale.min.css?ver=1786902736`; a captura publicada permanece anterior ao commit.

final result: blocked

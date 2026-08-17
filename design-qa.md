# Design QA — timer de oferta relâmpago na página de produto

## Findings

- Nenhum desvio de layout foi encontrado no componente isolado em desktop e mobile.
- A comparação final dentro da página real ainda depende de publicar a branch `alpha` em um ambiente com campanha ativa.

## Open Questions

- Confirmar se o produto usado na loja teste está incluído na campanha ativa do plugin no momento da validação.

## Implementation Checklist

1. Abrir um produto participante de uma oferta relâmpago ativa.
2. Confirmar que a faixa aparece acima do card de preço e compra.
3. Confirmar os quatro valores do contador: dias, horas, minutos e segundos.
4. Repetir a validação em desktop, 360 px e 320 px.
5. Confirmar que produtos fora da campanha não recebem a faixa.

## Follow-up Polish

- Se a largura real da coluna de compra diferir muito da referência, ajustar somente a proporção entre o rótulo e as quatro unidades, preservando a altura atual.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-09485033-fac1-4f36-8200-8990ae0ba6b6.png`.
- Post-implementation desktop component: `C:/Users/mathe/AppData/Local/Temp/gstore-product-flash-sale-desktop.png` (700 × 68 px).
- Post-implementation mobile component: `C:/Users/mathe/AppData/Local/Temp/gstore-product-flash-sale-mobile.png` (328 × 56 px).
- Implementation URL: indisponível antes da publicação da branch `alpha`.
- Viewports: componente com 700 px no desktop e 328 px no mobile.
- Density normalization: capturas locais em 1×.
- State: campanha ativa sintética com todos os quatro valores do timer preenchidos pelo JavaScript real.
- Full-view comparison evidence: indisponível sem uma instalação WordPress local com produto e campanha ativos.
- Focused region comparison evidence: faixa branca, borda dourada, ícone, rótulo e quatro colunas permanecem dentro do contêiner nos dois viewports.
- Fonts and typography: hierarquia compacta com título em caixa alta, subtítulo menor e números tabulares.
- Spacing and layout rhythm: separadores verticais e distribuição uniforme das quatro unidades; versão mobile reduz tipografia e padding.
- Colors and visual tokens: fundo branco, texto principal escuro e acento dourado do tema.
- Image quality and asset fidelity: nenhum asset raster novo; o ícone existente do Font Awesome é reutilizado.
- Copy and content: “Oferta relâmpago”, “Termina em”, “Dias”, “Horas”, “Min” e “Seg”.
- Primary interactions tested: atualização das quatro unidades do contador coberta por teste automatizado.
- Console errors checked: o componente isolado carregou sem erros no Chrome headless.

## Comparison history

1. Referência: faixa horizontal separada acima do painel de preço, com rótulo à esquerda e quatro unidades à direita.
2. Implementação: componente condicional ligado à campanha ativa real do plugin e posicionado acima da buybox.
3. Responsividade: proporção fixa no mobile para impedir estouro lateral e breakpoint adicional abaixo de 300 px.

final result: blocked

# Design QA — cabeçalho mobile igual à home

**Findings**

- [P1] O título da página dedicada invade o selo “Ao vivo”.
  Location: `.gstore-flash-sale-section--catalog .gstore-flash-sale-heading` no mobile.
  Evidence: a comparação mostra o título da página dedicada sobreposto ao selo; uma regra específica mantém `minmax(330px, 360px)` abaixo de 1200 px, inclusive em uma tela com cerca de 390 px.
  Impact: a leitura do título e do estado da campanha fica comprometida.
  Fix: no breakpoint mobile, a grade volta ao padrão da home, `minmax(0, 1fr) auto`, com título e espaçamentos equivalentes.

- [P2] Título, subtítulo e números do timer usam proporções diferentes da home.
  Location: título, subtítulo e relógio do cabeçalho da campanha.
  Evidence: a página dedicada conserva os overrides compactos do desktop, enquanto a home usa os tokens mobile compartilhados.
  Impact: o cabeçalho parece mais apertado e perde a hierarquia visual da vitrine principal.
  Fix: título, subtítulo, relógio, rótulos, ícones e unidades foram alinhados aos mesmos valores da home; o mobile usa também o subtítulo curto da home.

**Open Questions**

- A captura pós-fix depende da sincronização da branch `alpha`; o navegador interno não acessa a loja teste por causa da autenticação do ambiente.

**Implementation Checklist**

1. Sincronizar a branch `alpha` na loja teste.
2. Capturar a página dedicada em um celular real.
3. Confirmar que título, selo, subtítulo e timer correspondem à home sem sobreposição.
4. Confirmar que desktop, filtros e cards permanecem inalterados.

**Follow-up Polish**

- Nenhum ajuste adicional foi aplicado fora do cabeçalho mobile.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-3d50439e-3dd5-4c1d-af7a-59ee4694e08a.png` (266 × 79 px), cabeçalho mobile da home.
- Pre-fix implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-1e9a047e-e79e-476a-8e61-39ff35de9513.png` (260 × 98 px), página dedicada antes do ajuste.
- Combined comparison path: `C:/Users/mathe/AppData/Local/Temp/gstore-flash-mobile-home-vs-catalog.png` (526 × 98 px).
- Post-fix implementation screenshot path: indisponível antes da sincronização do ambiente.
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: capturas mobile fornecidas pelo usuário; breakpoint implementado até 768 px.
- Density normalization: comparação por proporção e estrutura; os recortes têm diferença de 6 px de largura e não foram redimensionados.
- State: campanha ativa, cabeçalho com selo “Ao vivo” e timer em execução.
- Full-view comparison evidence: o cabeçalho da home ocupa 79 px no recorte; o atual ocupa 98 px e apresenta sobreposição no primeiro bloco.
- Focused region comparison evidence: a composição lado a lado mostra a coluna rígida da página dedicada atravessando o limite do título e do selo.
- Fonts and typography: família e peso existentes foram preservados; tamanhos, letter-spacing e line-height mobile passam a usar os valores da home.
- Spacing and layout rhythm: grade, gaps, margens e padding interno do cabeçalho foram igualados ao padrão mobile da home, considerando o padding já fornecido pela página dedicada.
- Colors and visual tokens: cores, bordas, textura e sombras não foram alteradas.
- Image quality and asset fidelity: os ícones Font Awesome existentes foram preservados; não há novos assets.
- Copy and content: desktop mantém “Produtos selecionados com preços especiais por tempo limitado.”; mobile usa “Preços especiais por tempo limitado”, como a home.
- Primary interactions tested: o timer continua com os mesmos atributos de atualização; não houve mudança de JavaScript.
- Console errors checked: não disponível, pois o navegador interno encontrou a autenticação protegida da loja teste.

## Comparison history

1. Antes do ajuste: grade mobile herda uma coluna mínima de 330 px, título maior e valores compactos específicos do catálogo.
2. Correção: grade, tipografia e relógio são redefinidos no mobile com as proporções da home; subtítulo curto é exibido somente nessa resolução.
3. Pós-fix: sintaxe PHP, assets minificados, escopo CSS e testes automatizados passaram; falta captura visual após a sincronização.

final result: blocked

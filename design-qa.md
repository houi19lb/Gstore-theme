# Design QA — respiro mobile das ofertas relâmpago

**Findings**

- [P1] O cabeçalho da campanha começa muito abaixo da navegação no mobile.
  Location: `/ofertas-relampago/`, coluna `.Gstore-catalog-sidebar` fechada.
  Evidence: a captura atual mostra aproximadamente 187 px visuais de área preta vazia; no CSS, o contêiner do filtro continua com `flex-basis: 260px` mesmo depois que o painel interno vira `position: fixed`.
  Impact: título, timer, ordenação e produtos perdem espaço na primeira dobra.
  Fix: aplicado no commit `1a2b013`; no mobile, o contêiner fechado passa a `flex-basis: auto`, sem reservar altura.

- [P2] Os cards ignoram o respiro lateral do catálogo mobile.
  Location: `.gstore-flash-sale-section--catalog` abaixo de 768 px.
  Evidence: a captura atual mostra o card praticamente encostado às bordas, enquanto a referência do catálogo mantém cerca de 11–12 px visuais em cada lado. A seção dedicada herdava `width: 100vw` e margem full-bleed da vitrine da home.
  Impact: os cards parecem mais largos e pesados que o padrão do catálogo.
  Fix: aplicado no commit `1a2b013`; a seção dedicada volta a `width: 100%`, `max-width: 100%` e margem zero, respeitando os 16 px do contêiner do catálogo.

**Open Questions**

- Nenhuma sobre o escopo. Falta apenas uma captura mobile pós-sincronização para fechar a comparação visual.

**Implementation Checklist**

1. Sincronizar o commit `1a2b013` no ambiente de teste.
2. Confirmar que o cabeçalho começa logo após o padding superior da área preta.
3. Confirmar respiro lateral equivalente ao catálogo em todos os cards.
4. Abrir e fechar o painel de filtros para validar que continua funcional.

**Follow-up Polish**

- Nenhuma mudança interna de tipografia, imagem, preço ou botão foi incluída nesta correção.

## Comparison evidence

- Source visual truth path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-dc1cc929-1ff3-4aad-975c-56b3a49cca64.png` (273 × 570 px), catálogo mobile usado como referência de respiro.
- Pre-fix implementation screenshot path: `C:/Users/mathe/AppData/Local/Temp/codex-clipboard-c32ed0a9-0219-4fa5-8163-b89e76979728.png` (279 × 581 px).
- Combined comparison path: `C:/Users/mathe/AppData/Local/Temp/gstore-flash-mobile-current-vs-catalog-reference.png`.
- Implementation URL: `https://lojateste.kivodigital.com.br/ofertas-relampago/`.
- Viewport: capturas reais de celular fornecidas pelo usuário; larguras de 279 px e 273 px na imagem exportada.
- Density normalization: comparação feita por proporções laterais e posição relativa dos componentes, sem simulação de viewport no navegador desktop.
- State: página dedicada com campanha ativa e catálogo mobile com produtos carregados.
- Full-view comparison evidence: a página dedicada desperdiça a primeira dobra com um vazio preto; o catálogo coloca resultados, ordenação e primeiro card em sequência compacta.
- Focused region comparison evidence: card atual começa em aproximadamente `x=8`; referência começa em `x=11`, apesar de a referência ser 6 px mais estreita.
- Fonts and typography: não alteradas; o escopo preserva título, timer e textos dos cards.
- Spacing and layout rhythm: corrigidos o espaço vertical de 260 px e o full-bleed horizontal; o padding do contêiner permanece em 16 px.
- Colors and visual tokens: não alterados.
- Image quality and asset fidelity: imagens, textura, ícones e recortes não foram alterados.
- Copy and content: não alterados.
- Primary interactions tested: validações estruturais locais passaram; a interação do filtro pós-fix depende da publicação no ambiente.
- Console errors checked: não houve nova execução em viewport simulado; verificação visual pós-fix permanece pendente.

## Comparison history

1. Antes do ajuste: filtro fechado reserva 260 px e seção da campanha usa `100vw`, produzindo vazio superior e cards quase sem margem.
2. Correção `1a2b013`: somente o flex-basis do filtro fechado e o comportamento full-bleed da seção dedicada foram neutralizados no mobile.
3. Pós-fix: assets e testes passaram, mas ainda não existe captura real de celular com o commit sincronizado.

final result: blocked

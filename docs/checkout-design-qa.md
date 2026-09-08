# Checkout em quatro etapas — revisão local

Data: 2026-09-08. Alvo: alpha. Escopo: mudança visual no tema existente.

## Resultado

final result: passed

Resultado restrito ao preview local com JS/CSS reais e respostas simuladas.
Não representa homologação da integração WordPress/Blu nem deploy.

## Referências e decisões

- Referências escolhidas pelo usuário: mockups desktop e mobile com quatro
  etapas horizontais. Preservar lógica, nomes de transportadoras e modalidades.
- Fluxo: Pagamento → Dados Básicos → Frete → Finalizar.
- O conteúdo por produto e os grupos de transportadoras permanecem os reais
  do renderizador existente. Por isso o comprimento varia conforme as cotações,
  em vez de limitar a interface às três opções fictícias do mockup.
- O CEP e demais campos continuam em Dados Básicos para preservar validações.
  A etapa Frete espelha o destino; Alterar retorna ao campo original.
- A comunicação com o plugin mantém os estados 0/1/2. A etapa visual Frete
  envia 1 e a finalização envia 2, preservando o momento do parcelamento.

## Verificação visual e de interação

- Desktop 1440 × 1024: quatro etapas, opções à esquerda, totais à direita;
  valores alinhados à direita e nomes longos com quebra de linha.
- Mobile 390 × 844 e 320 × 740: quatro marcadores sem rolagem horizontal,
  nomes e valores empilhados, barra inferior com total e Continuar de 48px;
  Voltar com 44px. Há espaço reservado no conteúdo para a barra.
- Fontes/tipografia: família herdada do tema, valores e nomes de frete a 1rem,
  prazo secundário menor, etiquetas do stepper mobile legíveis em duas linhas.
- Espaçamento: coluna de resumo separada por divisor no desktop; no celular
  o resumo passa para baixo das opções e o CTA permanece acessível.
- Cores: tokens existentes dourado, verde, texto escuro e superfícies claras.
  Verde nas etapas concluídas preservado do checkout atual.
- Assets: produção conserva os templates/branding; preview usa logo público
  atual e o Font Awesome já incluído no tema. Nenhum asset de produção novo.
- Conteúdo: nomes, preços, prazos e avisos vêm dos mesmos renderizadores.
  Valores fictícios aparecem somente no preview e nos testes.
- Navegação local verificada: Pagamento → Dados → Frete → Finalizar;
  escolha aérea atualizou total de 229,90 para 249,90 no mock de backend;
  Alterar frete na revisão retornou à etapa Frete; dados/seleção sobreviveram
  ao reload e avanço do preview. Nenhum pedido foi enviado.
- Sem erros de console na verificação do preview.

## Evidências e testes

Capturas locais (não versionadas):
`${hub.root}/local/checkout-visual-2026-09-08/desktop-final.jpg`,
`mobile-final.jpg`, `mobile-320.jpg`.

48 testes passaram em 6 suites; 8 testes específicos cobrem o contrato de
etapas, validação Pix/cartão, seleção após refresh, nomes de serviços,
espelhamento de totais, edição do CEP, contrato e retorno de rascunho Blu.
Build de assets, verificação dos minificados e escopo de CSS passaram.

Próxima validação operacional: checkout integrado em homologação antes de
qualquer promoção/deploy, incluindo cotação real, cupons e parcelas.

## Correção após inspeção da loja de teste (2026-09-08)

- Percorrido o fluxo do capacete no Chrome: pagamento por cartão, dados e
  seleção do frete. Nenhum pedido enviado.
- O ambiente real restringia o checkout a 720px. A etapa Frete agora tem
  limite de 1040px, mantendo as demais etapas e os estados de backend.
- Corrigido conflito com a regra antiga `grid-template-columns: ... !important`
  nos radios: três colunas explícitas para controle, nome e preço/prazo.
- Modalidades recebem cabeçalho consistente; destino e resumo ganham
  superfícies delimitadas; espaços excessivos e linhas decorativas removidos.
- Mobile mantém preço à direita, área de toque de 48px no Continuar e menos
  espaço vazio ao final. Verificado em 390px e 320px, sem overflow dos controles.
- Correção restrita ao CSS e seu minificado. Nomes, cotações, cálculos,
  validação e persistência não foram modificados.
- Validação visual local usa DOM e cascata CSS capturados nesta visita,
  incluindo estilos do WordPress e plugin; o preview anterior isolado não
  reproduzia a restrição de largura nem todos os conflitos da cascata.
- Evidências locais: `${hub.root}/local/checkout-frete-fix-2026-09-08/`.
  Capturas `03-frete-antes.jpg`, `03-frete-mobile-antes.jpg`,
  `04-frete-desktop-depois.jpg`, `05-frete-mobile-depois.jpg` e
  `06-frete-320-depois.jpg`. A reprodução visual é estática; não substitui
  homologação das integrações após deploy.
- Observação preexistente nesta visita: total superior e resumo de frete
  exibiam valores diferentes. A correção de layout não altera essas fontes
  de cálculo; conciliar valores exige investigação separada.
- 48 testes em 6 suites, build dos assets e checagem do escopo CSS passaram.

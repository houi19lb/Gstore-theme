# Minha conta — base visual na alpha

A área principal da conta usa o painel aprovado: acolhimento em texto, pedido mais recente com acompanhamento, histórico recente, contadores e atalhos. O header e o footer continuam sendo os componentes normais do site. Cores vêm dos tokens da loja, incluindo contraste do botão; não há identidade ou contato de uma loja fixado no código.

## Contratos preservados

- Menu principal: Início, Pedidos, Meus dados e Atendimento. Sair mantém a URL nativa do WooCommerce. Endpoints de programas e extensões continuam no menu; Downloads e o item separado de Endereços saem da navegação.
- Meus dados agrupa os endpoints nativos `edit-account` e `edit-address`, com duas páginas. O WooCommerce continua responsável pelos campos, senha atual/nova/confirmação, nonces, validações e salvamento. Nenhum formulário de demonstração foi incorporado.
- Atendimento usa `?gstore_account_view=atendimento` no dashboard nativo. Não requer flush de permalinks e não intercepta endpoints de pedido, dados ou pagamento. Apenas exibe links externos; não cria tickets ou mensagens internas.
- Canais usam os mesmos campos de `gstore_store_info()` e helpers da página `/atendimento`: link principal e nomenclatura configurada (WhatsApp/Teleatendimento), e-mail, Telegram, sem redes sociais de divulgação ou telefone adicional. Campos vazios ou links inseguros não criam botões.
- As consultas de pedidos usam exclusivamente o usuário autenticado, com limites e APIs compatíveis com HPOS. Os contadores de andamento consideram pagamento pendente, em espera e processamento; concluídos usam o status `completed`.
- O painel aponta para `WC_Order::get_view_order_url()`. O detalhe mantém o hook nativo `woocommerce_view_order`, os seletores e APIs de documentos, incluindo upload, revisão, correção, rastreamento, ações e informações do pedido. A revisão visual do detalhe descrita abaixo altera sua apresentação e as mensagens, sem modificar essas operações.
- A sequência padrão é: Processando pagamento → Pagamento confirmado → Aguardando documentação → Processando documentação → Preparando entrega → Enviado. Enviado não significa entregue. As ramificações retornadas pelo serviço do plugin, como retirada, continuam válidas. Recusa de documentação é uma ocorrência na etapa de revisão; cancelados, reembolsados, falhos e etapas desconhecidas não recebem um progresso enganoso.

## Validação

```sh
php tests/account-dashboard.php
php tests/fulfillment-view.php
npx jest --runInBand tests/fulfillment-timeline.test.js
npm run check:css-scope
npm run check:assets
```

`tests/account-dashboard.php --render=<diretorio-local>` pode gerar HTML sintético dos templates PHP para revisão; os arquivos gerados não devem ser versionados. O teste cobre isolamento por cliente, consultas limitadas, menu, roteamento, canais, seis etapas, estados excepcionais, ramificação de retirada, ausência de payloads de documentos e conta sem pedidos.

## Revisão visual de 15/09/2026

A conta real foi inspecionada no Chrome autenticado (Início, Atendimento, Pedidos e Meus dados, além da navegação em tela estreita). Evidências com dados reais ficam apenas no armazenamento local, fora do Git.

Achados corrigidos:

1. Início: um ancestral `.woocommerce` limitava o conteúdo a 1.000 px. O ancestral agora aceita até 1.360 px somente quando contém a conta autenticada. A base branca é contínua dentro de `main`; header, footer e login não são afetados.
2. Início: a coluna lateral mais alta empurrava os indicadores, criando um vão de aproximadamente 300 px sob um pedido cancelado. Pedido, indicadores e atalhos agora pertencem à mesma coluna. A lateral usa divisórias, sem caixas e sombras redundantes.
3. Atendimento: a listagem incluía perfis públicos e telefone adicional. Só e-mail, WhatsApp/Teleatendimento e Telegram são elegíveis; nomes e URLs continuam vindo da configuração da loja. Os cartões se distribuem em até três colunas e empilham no celular.
4. Meus dados: nome e sobrenome agora dividem a linha no desktop. Em telas estreitas todos os campos usam uma coluna. Nonces, formulários e salvamento continuam nativos; a alteração de senha permanece disponível.
5. Pedidos: o histórico permanece intacto. O painel passa a respeitar o rótulo já usado no histórico para estados interrompidos, inclusive o caso legado de cancelamento com evidência de pagamento (`Pago/Confirmado`). Isso não altera pagamento, status persistido ou fulfillment.

A prévia dos templates PHP usa fixtures anônimas para pedido cancelado, em andamento, conta vazia, atendimento, dados e endereços. Em 320, 768, 1.024 e 1.440 CSS px, a medição do painel em andamento não apresentou overflow horizontal; o espaço após o pedido foi de 20 px e as seis etapas foram mantidas, em coluna no celular. Os dados foram conferidos também a 390 px.

Limites: a prévia não é uma instalação WordPress e não testa envio de formulários, plugins de terceiros ou alterações reais de senha/documentos. O header do site foi observado, mas não reimplementado na fixture. A revisão não equivale a uma certificação de acessibilidade. Conferir a versão integrada na loja de homologação antes de promover além da alpha.

Os testes de documentação usam arquivos fictícios. Não testar upload, exclusão ou mudança de senha na conta real de um cliente sem um cenário de homologação autorizado.


## Detalhe do pedido: etapas sem rolagem

A inspeção do detalhe encontrou uma timeline de 1.106 px com conteúdo de 1.136 px. O último item usava `flex: 0 0 auto`, enquanto os conectores pressupunham colunas iguais. Remover a assimetria resolve a causa; não se usa corte de conteúdo para esconder a barra.

- As colunas horizontais têm a mesma largura. Abaixo de 760 px disponíveis no pedido, uma container query transforma as etapas em sequência vertical. Assim, a lateral também entra no cálculo. Há fallback por viewport para navegadores sem container queries.
- O detalhe identifica o número do pedido e oferece retorno ao histórico. Etapas, mensagens e documentos usam espaçamento consistente, sem margens acumuladas ou sombras sobrepostas.
- A recusa explica a próxima ação e oferece acesso ao atendimento configurado na conta. O JavaScript atualiza esse acesso e `aria-current` após respostas da API. O movimento da etapa atual respeita a preferência por movimento reduzido.
- O contador explicita vagas utilizadas e que documentos negados não ocupam vagas. O nome do arquivo pode quebrar linha, os botões têm 44 px e nomes acessíveis, e o motivo de recusa perde o itálico pequeno.

A fixture CLI também gera `pedido-negado.html`, `pedido-analise.html`, `pedido-enviado.html` e `pedido-retirada.html` com os assets minificados reais. Apenas os detalhes do pedido fornecidos pelo hook são ilustrativos; o template, a timeline e a renderização da lista de documentos são do tema. Entradas e botões de documentos ficam desabilitados, e a política da fixture bloqueia conexões e submissões.

Validação: em 320, 390, 768, 1.024 e 1.440 CSS px, todas as etapas, rótulos e conectores permaneceram contidos, sem overflow horizontal da página ou da timeline. Os quatro estados foram conferidos novamente a 320 e 1.440 px usando os minificados. Testes PHP e Jest preservam o fluxo de envio, recusa e correção, a ramificação de retirada e a exclusão de campos privados dos documentos. As capturas antes/depois ficam fora do Git. A revisão segue limitada à alpha até homologação real solicitada.

## Medidas e espaçamento da conta (alpha, 15/09/2026)

A revisão dimensiona o conteúdo por tarefa: títulos de página entre 22 e 28 px, títulos de cartões em 17 px, leitura principal em 14–16 px, campos de 48 px e ações com pelo menos 44 px. Cartões usam 24 px de preenchimento no desktop e 18 px no celular; o intervalo entre título e conteúdo é de 24/16 px.

- Informações gerais: formulário limitado a 760 px, rótulos de 14 px e entradas de 16 px. O espaço entre rótulo e campo é de 6 px e entre grupos é de 20 px. Pseudo-elementos de clearfix do WooCommerce deixam de criar células vazias na grade. Na inspeção, uma linha simples passou de aproximadamente 107 para 75 px, sem reduzir o campo de 48 px.
- Endereços: título e edição ficam alinhados na mesma linha; os pseudo-elementos de clearfix também são removidos desse cabeçalho. O texto do endereço usa 14 px sem itálico.
- Pedidos: cabeçalhos de 13 px e dados de 14 px, com ações primárias e secundárias diferenciadas. O novo invólucro flexível mantém as ações nativas e iguala a altura dos botões quando um rótulo quebra linha. Até 1.100 px, a mesma tabela reorganiza cada pedido em um resumo de duas colunas, com status e documentação em largura integral. Os links existentes de ordenação continuam disponíveis no tablet; o comportamento de ocultação do cabeçalho até 768 px permanece nativo.
- Navegação no celular: menos preenchimento e avatar de 36 px, preservando os itens e seus alvos de 44 px. A navegação observada caiu de cerca de 254 para 194 px de altura; o conteúdo útil aparece mais cedo.

A fixture passa a renderizar também o template real do histórico, com documentação negada, pagamento pendente, duas ou três ações e dados fictícios. Os formulários e endereços continuam sendo representações sintéticas. A revisão final usa o CSS minificado e cobre histórico, dados, endereços, início em andamento, detalhe negado e atendimento em 320, 1.024 e 1.440 CSS px. Não houve overflow horizontal nas páginas medidas; botões com duas linhas crescem junto com os demais da mesma linha. Verificações intermediárias também cobriram 390 e 768 px.

Validação: lint PHP, testes PHP da conta e fulfillment, regressão Jest da timeline, escopo CSS e os 92 assets gerados. Não foram executadas alterações reais de senha, documentos ou pedidos. Este refinamento trata dimensões e organização visual, sem alterar as regras de status, canais ou salvamento.

## Cores e clareza (alpha, 15/09/2026)

A nova inspeção da Armastore encontrou status com a cor da marca ou cinza indistinto, repetição de status no cartão, setas decorativas nos botões e divisórias escuras no histórico. O refinamento usa os tokens semânticos existentes (sucesso, erro e informação), com fallbacks de atenção sobrescrevíveis. Estado e texto permanecem juntos; a cor não substitui rótulos.

- Cancelamento/falha usam vermelho suave, concluído/enviado verde, espera âmbar e processamento informação. O helper de apresentação não altera status, pagamento ou fulfillment. Rótulos personalizados/legados diferentes do estado nativo mantêm tom neutro, evitando declarar sucesso ou cancelamento pela cor.
- Painel e histórico usam os mesmos tons. No painel, a etapa de fulfillment pode especificar atenção, revisão ou envio. Enviado continua significando enviado, não entregue.
- Botões usam Ver pedido sem seta decorativa; o acesso a suporte também perde a seta. Indicadores direcionais de navegação, ordenação e retorno permanecem.
- O aviso do pedido perde o título de status repetido; o atalho Histórico de pedidos não repete o contador. Dados de criação/valor usam 14 px. A cor destaca os contadores de andamento e conclusão sem criar novas categorias.
- Divisórias da tabela redefinem os quatro lados para evitar a borda superior escura herdada do WooCommerce. O foco de teclado usa o texto principal da loja. Textos de etapas completas e pendentes ganham contraste.
- Atendimento preserva os canais, URLs e nomenclaturas cadastrados, mas apresenta uma orientação específica por tipo de contato.

A fixture acrescenta concluído/enviado e os cinco estados do histórico; simula a borda superior nativa para validar sua correção. Os testes cobrem os tons de estados conhecidos, fallback de estado personalizado, cancelamento com rótulo legado e recusa de documentos. Revisão responsiva em 320, 768 e 1.440 CSS px, sem overflow horizontal. Contraste observado nos badges padrão: 5,33:1 a 10,22:1, sem alegação de conformidade integral. Lint PHP, testes da conta e fulfillment, Jest, escopo CSS e assets passaram.

Referências: [Baymard — contas e autoatendimento](https://baymard.com/blog/current-state-accounts-selfservice), [NN/g — estados e hierarquia de botões](https://www.nngroup.com/articles/button-states-communicate-interaction/), [W3C — uso de cor](https://www.w3.org/WAI/WCAG22/Understanding/use-of-color.html).

Pendências observadas nesta auditoria: um pedido cancelado no painel ainda mostra processamento de pagamento e aviso Pix no detalhe; os ativos não têm acesso filtrado no início; o aviso de confirmação de e-mail aparece em inglês; o widget flutuante ocupa parte dos atalhos no celular. São tarefas separadas desta apresentação visual; não corrigir status financeiro por inferência. Capturas reais e relatório completo ficam apenas no armazenamento local, fora do Git.

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

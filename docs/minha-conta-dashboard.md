# Minha conta — base visual na alpha

A área principal da conta usa o painel aprovado: acolhimento em texto, pedido mais recente com acompanhamento, histórico recente, contadores e atalhos. O header e o footer continuam sendo os componentes normais do site. Cores vêm dos tokens da loja, incluindo contraste do botão; não há identidade ou contato de uma loja fixado no código.

## Contratos preservados

- Menu principal: Início, Pedidos, Meus dados e Atendimento. Sair mantém a URL nativa do WooCommerce. Endpoints de programas e extensões continuam no menu; Downloads e o item separado de Endereços saem da navegação.
- Meus dados agrupa os endpoints nativos `edit-account` e `edit-address`, com duas páginas. O WooCommerce continua responsável pelos campos, senha atual/nova/confirmação, nonces, validações e salvamento. Nenhum formulário de demonstração foi incorporado.
- Atendimento usa `?gstore_account_view=atendimento` no dashboard nativo. Não requer flush de permalinks e não intercepta endpoints de pedido, dados ou pagamento. Apenas exibe links externos; não cria tickets ou mensagens internas.
- Canais usam os mesmos campos de `gstore_store_info()` e helpers da página `/atendimento`: link principal e nomenclatura configurada (WhatsApp/Teleatendimento), e-mail, Telegram, redes sociais e telefone disponíveis. Campos vazios ou links inseguros não criam botões.
- As consultas de pedidos usam exclusivamente o usuário autenticado, com limites e APIs compatíveis com HPOS. Os contadores de andamento consideram pagamento pendente, em espera e processamento; concluídos usam o status `completed`.
- O painel aponta para `WC_Order::get_view_order_url()`. `view-order.php`, `orders.php`, JavaScript de fulfillment e APIs de documentos permanecem intactos, incluindo upload, revisão, correção, rastreamento, ações e informações do pedido.
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

Pendências para homologação visual: a ferramenta de navegador bloqueou a abertura dos arquivos locais de teste durante esta implementação. A integração ainda precisa ser vista em WordPress/WooCommerce na alpha, em 1440, 1024, 768, 390 e 320 px, inclusive zoom de 200%, navegação por teclado, nomes longos, ausência de pedidos/canais, cores claras/escuras de diferentes lojas, dados e senha, ambos os endereços e pedido com documentos. O CSS empilha o painel em telas menores e torna a linha de etapas vertical no celular; essa disposição ainda não foi conferida visualmente na integração.

Os testes de documentação usam arquivos fictícios. Não testar upload, exclusão ou mudança de senha na conta real de um cliente sem um cenário de homologação autorizado.

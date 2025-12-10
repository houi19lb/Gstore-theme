## Checkout Blu – Link de Pagamento

### Visão geral
- O método `Pagamento via Link Blu` cria um link na API da Blu (`/b2b/payment_links`) durante o `process_payment`.
- O cliente é **redirecionado automaticamente** para o checkout da Blu.
- O pedido fica com status `pending` (Aguardando pagamento) até a confirmação via webhook.
- Os dados retornados (`link_url`, `smart_checkout_url`, `id`, `expiration_date`) ficam armazenados nos metadados do pedido (`_gstore_blu_*`).

### Configuração
1. **Via Painel Admin:**
   - WordPress › WooCommerce › Pagamentos › `Pagamento via Link Blu`.
   - Informe o Token da Blu e selecione o Ambiente (Homologação ou Produção).
   - Configure outras opções como parcelas máximas, webhook, etc.

3. **Webhook:**
   - Crie o endpoint na Blu: `https://SEU_SITE/wp-json/gstore-blu/v1/webhook`.

### Webhook
- Aceita `POST` ou `PUT` com JSON contendo `id` e `status`.
- Se `webhook_secret` estiver preenchido, o header `X-Gstore-Blu-Webhook` precisa ter o mesmo valor.
- Status `paid`, `success` ou `confirmed` chamam `payment_complete()`.
- Status `expired` cancela o pedido.

### Consulta manual
- No admin do pedido há uma ação `Consultar status na Blu` que chama `GET /payment_links/{id}`.
- O bloco “Link de pagamento Blu” mostra ID e URL atuais para debug.

### Campos enviados
| Campo Blu | Fonte WooCommerce |
|-----------|-------------------|
| `amount` | `order->get_total()` (duas casas decimais) |
| `email_notification` | E-mail de cobrança |
| `phone_notification` | Telefone (apenas números, 10/11 dígitos) |
| `description` | `Pedido #123 – {nome do site}` |
| `document_type` | Detectado via metadados (`billing_cnpj`, `billing_cpf`, `billing_cpf_cnpj` etc.) |
| `customer_cnpj` | Enviado apenas quando `document_type = CNPJ` |
| `max/fixed_installment_number` | Configuração do gateway |
| `issuer_rate_forwarding` | Conforme checkbox |

### Testes sugeridos
1. **Criação do link** – finalizar pedido com gateway Blu em ambiente de homologação, validar nota e link exibidos no Obrigado.
2. **Consulta manual** – no admin, acionar `Consultar status na Blu` e conferir atualização do metadado/notas.
3. **Webhook** – via Postman, enviar payload com `status = success` e depois `status = expired` para validar transitions.
4. **Edge cases** – remover telefone/documento para garantir que o payload continua válido (campos opcionais são omitidos).

### Logs
- Ative “Log de depuração” para registrar requests/responses no logger do WooCommerce (`/wp-content/uploads/wc-logs/`).



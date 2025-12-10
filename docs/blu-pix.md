# Pix Blu – Gateway de Pagamento

## Visão geral

O gateway **Pix Blu** permite que clientes paguem pedidos via Pix diretamente no checkout, com QR Code e código copia e cola exibidos na página de obrigado e no checkout.

### Características

- Criação automática de cobrança Pix via API da Blu
- Exibição de QR Code e código copia e cola
- Webhook para confirmação automática de pagamento
- Consulta manual de status no painel admin
- Suporte a ambiente de homologação e produção

## Configuração

### 1. Via Painel Admin

1. Acesse **WordPress › WooCommerce › Pagamentos**
2. Localize o método **Pix Blu**
3. Clique em **Gerenciar** ou **Configurar**
4. Preencha os campos:

   - **Ativar/Desativar**: Marque para ativar o gateway
   - **Título**: Nome exibido ao cliente (padrão: "Pix")
   - **Descrição**: Texto explicativo (padrão: "Pague via Pix com QR Code ou código copia e cola")
   - **Token da Blu**: Token fornecido pelo seu Executivo Blu (sem prefixo "Bearer")
   - **Ambiente**: Escolha entre Homologação ou Produção
   - **Dias de expiração**: Número de dias até o Pix expirar (padrão: 1 dia)
   - **Descrição externa**: Descrição exibida ao cliente (opcional, será gerada automaticamente se vazio)
   - **Descrição interna**: Descrição para controle interno (opcional)
   - **Token do webhook**: Token para validar webhooks (opcional)
   - **Log de depuração**: Ative para registrar requisições/respostas

5. Clique em **Salvar alterações**

### 2. Webhook

Para receber notificações automáticas quando um Pix for pago:

1. Configure o webhook na Blu com a URL:
   ```
   https://SEU_SITE/wp-json/gstore-blu/v1/pix-webhook
   ```

2. Se configurou um token de webhook no painel, informe esse token à Blu para que seja enviado no header `X-Gstore-Blu-Webhook`

3. O webhook aceita métodos `POST` e `PUT`

## Fluxo de Pagamento

### 1. Cliente seleciona Pix no checkout

O cliente escolhe o método de pagamento "Pix" durante o checkout.

### 2. Criação do Pix

Ao finalizar o pedido:

- O sistema cria uma cobrança Pix via API da Blu (`POST /b2b/pix/`)
- O pedido fica com status `pending` (Aguardando pagamento)
- Os dados do Pix (QR Code, código EMV) são armazenados nos metadados do pedido

### 3. Exibição na página de obrigado

Após criar o pedido, o cliente vê:

- **QR Code**: Imagem para escanear com o app do banco
- **Código Pix (copia e cola)**: Código EMV para copiar e colar no app
- **Informações**: Token, data de expiração e status

### 4. Confirmação de pagamento

Quando o cliente paga o Pix:

- A Blu envia uma notificação via webhook
- O pedido é atualizado automaticamente para `completed`
- E-mail de confirmação é enviado ao cliente

## Estrutura de Dados

### Metadados do Pedido

O gateway armazena os seguintes metadados no pedido:

| Meta Key | Descrição |
|----------|-----------|
| `_gstore_blu_pix_transaction_token` | Token único retornado na criação do Pix |
| `_gstore_blu_pix_qr_code_base64` | QR Code em formato base64 |
| `_gstore_blu_pix_emv` | Código EMV (copia e cola) |
| `_gstore_blu_pix_status` | Status atual do Pix (active, paid, expired, etc.) |
| `_gstore_blu_pix_expires_at` | Data de expiração (YYYY-MM-DD) |
| `_gstore_blu_pix_movement_id` | ID da movimentação (preenchido após pagamento) |
| `_gstore_blu_pix_last_payload` | Última resposta completa da API (JSON) |

### Endpoints da API

#### Criar Pix
- **Método**: `POST`
- **URL**: `https://api.blu.com.br/b2b/pix/` (produção) ou `https://api-hlg.blu.com.br/b2b/pix/` (homologação)
- **Headers**: 
  - `Authorization: Bearer {token}`
  - `Content-Type: application/json`
- **Body**:
  ```json
  {
    "expires_at": "YYYY-MM-DD",
    "description": "Descrição Externa",
    "description_internal": "Descrição Interna",
    "value": "100.50"
  }
  ```
- **Resposta**:
  ```json
  {
    "transaction_token": "uuid-do-pix"
  }
  ```

#### Consultar Pix
- **Método**: `GET`
- **URL**: `https://api.blu.com.br/b2b/pix/{transaction_token}`
- **Headers**: `Authorization: Bearer {token}`
- **Resposta**:
  ```json
  {
    "id": "uuid",
    "tx_id": "txid",
    "transaction_token": "uuid",
    "status": "active",
    "expires_at": "YYYY-MM-DD",
    "description": "Descrição",
    "value": "100.50",
    "emv": "código-emv",
    "qr_code_base64": "base64-string"
  }
  ```

## Webhook

### Estrutura do Payload

O webhook recebe um JSON com os seguintes campos:

```json
{
  "created_at": "2025-05-12 14:59:49 -0300",
  "debit_party": {
    "account": "6353625",
    "bank": "18236120",
    "branch": "1",
    "personType": "NATURAL_PERSON",
    "taxId": "12000436774",
    "accountType": "TRAN",
    "name": "Nome do Pagador"
  },
  "debt_id": "uuid",
  "e2e_id": "E18236120202505121759s01407eef6b",
  "id": "uuid",
  "movement_id": "1000659076114",
  "value": "0.05",
  "status": "success"
}
```

### Processamento

O webhook:

1. Valida o token (se configurado)
2. Localiza o pedido pelo `transaction_token`, `id` ou `movement_id`
3. Atualiza o status do pedido:
   - `status: "success"` ou `"paid"` → Pedido marcado como `completed`
   - `status: "expired"` → Pedido cancelado
4. Armazena `movement_id` e outros dados relevantes

### Endpoint

- **URL**: `/wp-json/gstore-blu/v1/pix-webhook`
- **Métodos**: `POST`, `PUT`
- **Validação**: Header `X-Gstore-Blu-Webhook` (se configurado)

## Funcionalidades do Admin

### Consulta Manual de Status

No painel do pedido:

1. Acesse **WooCommerce › Pedidos**
2. Abra o pedido desejado
3. No menu **Ações do pedido**, selecione **Consultar status do Pix na Blu**
4. Clique em **Atualizar**

O sistema consulta o status na Blu e atualiza o pedido.

### Painel de Informações

No painel do pedido, há uma seção **Pix Blu** mostrando:

- Token do Pix
- Movement ID (após pagamento)
- Status atual
- Data de expiração

## Troubleshooting

### Pix não é criado

**Sintomas**: Erro ao finalizar pedido, mensagem de "Token não configurado"

**Soluções**:
1. Verifique se o token está preenchido nas configurações
2. Verifique se o ambiente está correto (homologação/produção)
3. Ative o log de depuração e verifique os logs em `/wp-content/uploads/wc-logs/`

### QR Code não aparece

**Sintomas**: QR Code não é exibido na página de obrigado

**Soluções**:
1. O QR Code pode não estar disponível imediatamente após criar o Pix
2. O sistema tenta consultar automaticamente se o QR Code não estiver disponível
3. Use a ação "Consultar status do Pix na Blu" no admin para forçar atualização

### Webhook não funciona

**Sintomas**: Pagamentos não são confirmados automaticamente

**Soluções**:
1. Verifique se a URL do webhook está correta na Blu
2. Verifique se o token do webhook está configurado corretamente (se usado)
3. Teste o webhook manualmente usando a API de teste da Blu:
   ```
   PUT https://api.blu.com.br/b2b/webhook/pix
   ```
4. Verifique os logs do WordPress para erros

### Erro 401 (Unauthorized)

**Sintomas**: Requisições retornam erro 401

**Soluções**:
1. Verifique se o token está correto
2. Certifique-se de que o token não tem o prefixo "Bearer" (o sistema adiciona automaticamente)
3. Verifique se o token não expirou

### Erro 422 (Unprocessable Entity)

**Sintomas**: Erro ao criar Pix

**Soluções**:
1. Verifique se a data de expiração não está no passado
2. Verifique se o valor está no formato correto (ex: "100.50")
3. Verifique se todos os campos obrigatórios estão preenchidos

## Logs

Para ativar logs de depuração:

1. Acesse as configurações do gateway Pix
2. Marque a opção **Log de depuração**
3. Salve as alterações

Os logs ficam em:
```
/wp-content/uploads/wc-logs/gstore-blu-pix-{data}.log
```

## Testes

### Ambiente de Homologação

1. Configure o gateway para usar ambiente de **Homologação**
2. Use valores de teste (ex: R$ 0,05)
3. Teste a criação do Pix
4. Use a API de teste da Blu para simular pagamento

### Checklist de Testes

- [ ] Gateway aparece na lista de métodos de pagamento
- [ ] Pix é criado ao finalizar pedido
- [ ] QR Code é exibido na página de obrigado
- [ ] Código copia e cola funciona
- [ ] Botão de copiar funciona
- [ ] Webhook atualiza status quando Pix é pago
- [ ] Consulta manual funciona no admin
- [ ] E-mail de confirmação é enviado após pagamento

## Diferenças do Gateway Link Blu

| Característica | Link Blu | Pix Blu |
|----------------|----------|---------|
| Redirecionamento | Sim (para checkout da Blu) | Não (pix no próprio site) |
| Dados coletados | No checkout da Blu | No checkout do WooCommerce |
| Exibição | Link para pagamento | QR Code e código copia e cola |
| API | `/b2b/payment_links` | `/b2b/pix` |
| Webhook | `/wp-json/gstore-blu/v1/webhook` | `/wp-json/gstore-blu/v1/pix-webhook` |

## Suporte

Para problemas ou dúvidas:

1. Verifique os logs do WooCommerce
2. Ative o log de depuração
3. Consulte a documentação da API Blu
4. Entre em contato com o suporte da Blu










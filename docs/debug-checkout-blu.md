# 🐛 Debug do Checkout Blu - Guia de Solução

## Contexto rápido (LLMs/novos devs)
- Guia de troubleshooting para **redirecionamento Blu** no checkout.
- Afeta JS do tema e handlers do plugin.

## Problema Relatado
O botão de "Finalizar Pedido" no checkout não está redirecionando para o link da Blu.

## ✅ Correções Implementadas

### 1. **Visibilidade do Botão**
- Adicionado código para garantir que o botão `#place_order` esteja sempre visível
- Forçado estilos CSS inline para evitar conflitos
- Removido classes que possam estar bloqueando o botão

### 2. **Event Handlers**
- Adicionado handler específico para o clique no botão "Finalizar Pedido"
- Garantido que o formulário possa ser submetido quando na etapa 3 (Pagamento)
- Reinicialização dos eventos do WooCommerce ao entrar na etapa de pagamento

### 3. **Redirecionamento**
- Interceptador AJAX adicionado para forçar redirecionamento quando houver resposta de sucesso
- Logs detalhados para monitorar o fluxo do checkout

### 4. **Reinicialização de Eventos**
- Trigger `update_checkout` ao entrar na etapa 3
- Remoção de classe `processing` que pode bloquear novos submits
- Habilitação forçada do botão `place_order`

## 🔍 Como Testar

### Passo 1: Limpar Cache
```bash
# No navegador, limpe o cache e force reload
Ctrl + Shift + R (Windows/Linux)
Cmd + Shift + R (Mac)
```

### Passo 2: Abrir Console do Navegador
1. Pressione `F12` ou clique com botão direito > "Inspecionar"
2. Vá para a aba "Console"
3. Limpe o console (ícone 🚫)

### Passo 3: Fazer um Pedido Teste
1. Adicione um produto ao carrinho
2. Vá para o checkout
3. Preencha os dados pessoais (Etapa 1)
4. Preencha o endereço (Etapa 2)
5. Na etapa de pagamento (Etapa 3), observe o console

### Logs Esperados no Console:
```
Gstore Steps: Organizando etapa de pagamento
Gstore Steps: Botão de finalizar pedido encontrado e exibido
Gstore Steps: Botão finalizar pedido habilitado na etapa 3
Gstore Steps: Botão "Finalizar Pedido" clicado
Gstore Steps: Formulário de checkout sendo submetido
Gstore Steps: Etapa atual: 2
Gstore Steps: Resposta do checkout recebida
Gstore Steps: Redirecionando para: [URL da Blu]
```

## ⚠️ Problemas Comuns e Soluções

### Problema 1: Botão não aparece
**Sintomas:** Console mostra "Botão #place_order não encontrado"

**Solução:**
1. Verifique se o gateway Blu está ativo:
   - WordPress > WooCommerce > Configurações > Pagamentos
   - Certifique-se que "Pagamento via Link Blu" está habilitado
   
2. Verifique se o token está configurado:
   - No mesmo local, clique em "Gerenciar" no gateway Blu
   - Verifique se o "Token da Blu" está preenchido

### Problema 2: Botão aparece mas não funciona
**Sintomas:** Clica no botão mas nada acontece

**Solução:**
1. Verifique no console se há erros JavaScript
2. Verifique se há algum plugin de segurança ou firewall bloqueando requisições AJAX
3. Teste com outros métodos de pagamento para confirmar se o problema é específico da Blu

### Problema 3: Formulário é submetido mas não redireciona
**Sintomas:** Console mostra submit mas não mostra "Redirecionando para"

**Diagnóstico:**
1. Veja se há erro na resposta do servidor
2. Verifique os logs do WooCommerce:
   ```
   wp-content/uploads/wc-logs/blu_checkout-[DATA].log
   ```
3. Execute o diagnóstico:
   ```
   https://SEU_SITE/wp-content/themes/gstore/diagnostic-blu-checkout.php
   ```

### Problema 4: Erro na API da Blu
**Sintomas:** Mensagem de erro do gateway

**Verificações:**
1. **Token válido:**
   - Verifique no wp-config.php ou nas configurações
   - Token deve ser fornecido pela Blu
   
2. **Ambiente correto:**
   - Homologação: `api-hlg.blu.com.br`
   - Produção: `api.blu.com.br`
   
3. **Conexão:**
   ```bash
   curl -H "Authorization: SEU_TOKEN" \
        -H "Accept: version=1" \
        https://api-hlg.blu.com.br/b2b/payment_links
   ```

## 🔧 Debug Avançado

### Verificar se a API da Blu está respondendo:
```javascript
// Cole isso no console do navegador quando estiver na etapa 3
jQuery.ajax({
    url: wc_checkout_params.checkout_url,
    type: 'POST',
    data: jQuery('form.checkout').serialize(),
    success: function(response) {
        console.log('Resposta completa:', response);
    },
    error: function(xhr) {
        console.error('Erro:', xhr);
    }
});
```

### Verificar se o gateway está carregado:
```javascript
// Cole no console
console.log('Gateway Blu:', jQuery('input[name="payment_method"][value="blu_checkout"]').length > 0 ? 'OK' : 'NÃO ENCONTRADO');
console.log('Botão finalizar:', jQuery('#place_order').length > 0 ? 'OK' : 'NÃO ENCONTRADO');
console.log('Formulário checkout:', jQuery('form.checkout').length > 0 ? 'OK' : 'NÃO ENCONTRADO');
```

## 📞 Próximos Passos

Se após essas correções o problema persistir:

1. **Capture os logs do console** (tire um print ou copie o texto)
2. **Verifique os logs do servidor**:
   - `wp-content/uploads/wc-logs/blu_checkout-[DATA].log`
   - Logs de erro do PHP (`debug.log` se WP_DEBUG estiver ativo)
3. **Teste em modo incógnito** para descartar conflitos de cache/extensões
4. **Desative temporariamente outros plugins** para identificar conflitos

## 🎯 Checklist Rápido

- [ ] Cache do navegador limpo
- [ ] Console aberto e monitorando
- [ ] Gateway Blu ativo e configurado
- [ ] Token válido preenchido
- [ ] Etapa 3 (Pagamento) é exibida
- [ ] Botão "Finalizar Pedido" está visível
- [ ] Clique no botão gera logs no console
- [ ] Formulário é submetido
- [ ] Resposta do servidor é recebida
- [ ] Redirecionamento ocorre

## 📝 Informações de Debug

Ao reportar o problema, inclua:
- URL do site
- Ambiente (Homologação/Produção)
- Versão do WooCommerce
- Logs do console (F12)
- Print da etapa 3 do checkout
- Logs do arquivo `blu_checkout-[DATA].log`





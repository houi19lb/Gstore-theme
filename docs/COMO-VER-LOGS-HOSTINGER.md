# Como Ver Logs de Erro na Hostinger

## Método 1: Via Painel hPanel da Hostinger

1. **Acesse o hPanel**
   - Faça login em https://hpanel.hostinger.com
   - Selecione seu domínio

2. **Acesse File Manager**
   - No menu lateral, clique em **"File Manager"**
   - Navegue até a pasta do seu site WordPress

3. **Localize os Logs**
   - Os logs geralmente estão em:
     - `/public_html/error_log` (log principal do servidor)
     - `/public_html/wp-content/debug.log` (se WP_DEBUG estiver ativo)
     - `/logs/error_log` (alguns servidores)

4. **Visualize o Log**
   - Clique com botão direito no arquivo `error_log`
   - Selecione **"View"** ou **"Edit"**
   - Role até o final do arquivo para ver os erros mais recentes

## Método 2: Via FTP/SFTP

1. **Conecte via FTP**
   - Use FileZilla ou outro cliente FTP
   - Use as credenciais FTP da Hostinger

2. **Navegue até a pasta do site**
   - Vá para `/public_html/` ou `/domains/seu-dominio.com/public_html/`

3. **Baixe o arquivo de log**
   - Procure por `error_log` na raiz
   - Ou `wp-content/debug.log` se WP_DEBUG estiver ativo

## Método 3: Ativar WP_DEBUG no WordPress

Para ver erros específicos do WordPress:

1. **Edite o arquivo `wp-config.php`**
   - Via File Manager ou FTP
   - Localize a linha `define('WP_DEBUG', false);`
   - Altere para:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. **Os erros serão salvos em**
   - `/wp-content/debug.log`

3. **Importante**: Desative depois de resolver o problema:
   ```php
   define('WP_DEBUG', false);
   ```

## Método 4: Via Terminal SSH (se disponível)

1. **Conecte via SSH**
   ```bash
   ssh usuario@seu-dominio.com
   ```

2. **Visualize o log em tempo real**
   ```bash
   tail -f /home/usuario/public_html/error_log
   ```

   Ou para ver as últimas 50 linhas:
   ```bash
   tail -n 50 /home/usuario/public_html/error_log
   ```

## O que procurar no log

Procure por:
- **"Fatal error"** - Erros críticos que param a execução
- **"Parse error"** - Erros de sintaxe PHP
- **"Class not found"** - Classe PHP não encontrada
- **"Call to undefined function"** - Função não definida
- **Nome do plugin** - "GStore Optimizer" ou "gstore-optimizer"

## Exemplo de erro comum

```
PHP Fatal error:  Cannot redeclare class GStore_Asset_Optimizer in 
/public_html/wp-content/plugins/gstore-optimizer/includes/class-asset-optimizer.php on line 16
```

Isso indica que uma classe está sendo declarada duas vezes.

## Dica Rápida

Se o site estiver completamente inacessível:
1. Renomeie a pasta do plugin temporariamente:
   - `/wp-content/plugins/Plugin GStore` → `/wp-content/plugins/Plugin GStore-disabled`
2. Isso desativará o plugin e permitirá acessar o admin novamente
3. Depois corrija o problema e renomeie de volta















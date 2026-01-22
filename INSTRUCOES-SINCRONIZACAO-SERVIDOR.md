# 🔧 Instruções para Corrigir Sincronização Git no Servidor

## ⚠️ Problema
O Git no servidor não consegue fazer pull porque há arquivos locais não rastreados que conflitam com o repositório remoto.

## ✅ Solução Recomendada: Via Script PHP

### Passo 1: Fazer Upload do Script
1. Acesse o **File Manager** do cPanel
2. Navegue até: `/wp-content/themes/Gstore-theme/`
3. Crie um novo arquivo chamado: `fix-git-sync.php`
4. Cole o conteúdo do arquivo `fix-git-sync.php` que está no repositório
5. Salve o arquivo

### Passo 2: Executar o Script
1. Abra no navegador:
   ```
   https://cacarmas.kivodigital.com.br/wp-content/themes/Gstore-theme/fix-git-sync.php
   ```
2. O script vai:
   - Fazer backup automático
   - Limpar arquivos não rastreados
   - Resetar o Git para o estado do repositório
   - Fazer pull das atualizações
   - Verificar se `inc/installments.php` existe

### Passo 3: Deletar o Script
Após usar, **DELETE** o arquivo `fix-git-sync.php` por segurança.

---

## 🔐 Alternativa: Via SSH (se tiver acesso)

Se você tem acesso SSH ao servidor, execute:

```bash
cd /home/u900358174/domains/cacarmas.kivodigital.com.br/public_html/wp-content/themes/Gstore-theme

# Fazer backup (opcional)
cp -r . ../Gstore-theme-backup-$(date +%Y%m%d-%H%M%S)

# Limpar arquivos não rastreados
git clean -fd

# Resetar para o estado do repositório remoto
git fetch origin
git reset --hard origin/main

# Fazer pull
git pull origin main

# Verificar se inc/installments.php existe
ls -la inc/installments.php
```

---

## 📋 Alternativa: Via File Manager (Manual)

Se não conseguir usar o script nem SSH:

1. **Acesse File Manager do cPanel**
2. **Navegue até:** `/wp-content/themes/Gstore-theme/`
3. **Delete os seguintes arquivos/pastas problemáticos:**
   - `assets/c__Users_*_*.png` (todas as imagens temporárias)
   - `.gitignore` (será restaurado pelo Git)
   - Qualquer arquivo que aparecer na lista de erro
4. **Tente sincronizar novamente** pelo painel

⚠️ **CUIDADO:** Não delete arquivos importantes como `functions.php`, `style.css`, etc. Apenas os arquivos listados no erro.

---

## ✅ Verificação Final

Após qualquer método, verifique se o arquivo crítico existe:

```
/wp-content/themes/Gstore-theme/inc/installments.php
```

**Sem este arquivo, o site não funcionará!**

---

## 🆘 Se Nada Funcionar

Como último recurso, você pode:

1. Fazer **download completo do tema** do GitHub
2. Fazer **backup da pasta atual** no servidor
3. **Substituir toda a pasta** `/wp-content/themes/Gstore-theme/` pela versão do GitHub
4. Verificar se `inc/installments.php` existe após a substituição

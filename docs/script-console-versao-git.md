# Script de console: versão do tema e status Git

Use este script no **console do navegador** (F12 → Console) para ver a **versão do tema** e se ele está **atualizado com o Git** (remoto).

## Onde usar

1. Abra o **painel WordPress** (admin).
2. Vá em **Aparência → Configurações do Tema** (GStore) ou em **Aparência → Temas** (com o tema GStore ativo).
3. Abra o **Console** (F12 → aba Console).
4. Cole o script abaixo e pressione **Enter**.

## Script para colar no console

```javascript
(function(){
  var link = document.querySelector('.gstore-theme-git-update');
  if (!link) {
    console.warn('GStore: Abra Aparência > Configurações do Tema ou Aparência > Temas (tema ativo) e execute o script novamente.');
    return;
  }
  var nonce = link.getAttribute('data-nonce');
  var form = new FormData();
  form.append('action', 'gstore_theme_git_status');
  form.append('nonce', nonce);
  fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
    method: 'POST',
    body: form,
    credentials: 'same-origin'
  })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
      if (!resp.success) {
        console.log('Erro:', resp.data && resp.data.message ? resp.data.message : resp.data);
        return;
      }
      var d = resp.data;
      console.log('=== GStore Tema ===');
      console.log('Versão:', d.version);
      console.log('Tema:', d.theme_name);
      console.log('Branch:', d.branch);
      console.log('Atualizado com Git:', d.is_up_to_date === true ? 'Sim' : (d.is_up_to_date === false ? 'Não' : 'N/A'));
      if (d.error) console.log('Aviso:', d.error);
      console.log('Commit local:', d.git_hash || '—');
      console.log('Commit remoto:', d.remote_hash || '—');
    })
    .catch(function(e) { console.error('GStore status:', e); });
})();
```

## O que aparece

- **Versão**: versão do tema (ex.: 1.5.0).
- **Atualizado com Git**: **Sim** = mesmo commit do remoto; **Não** = há commits no remoto que você ainda não puxou; **N/A** = Git não disponível ou erro ao checar.
- **Commit local / remoto**: hashes dos commits para conferência.

## Erro ao clicar em "Sincronizar Agora"

Se o **git pull** falhar, em vez de um simples alerta será exibido um **modal** com o título **"Git pull não realizado"** e a mensagem de erro completa (incluindo saída do Git quando possível). Assim você consegue ver exatamente o que deu errado (token, permissão, rede, etc.).

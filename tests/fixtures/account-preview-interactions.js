/* Local fixture only. No account API, authentication or outgoing document requests. */
document.addEventListener('DOMContentLoaded', () => {
  const read = key => { try { return JSON.parse(sessionStorage.getItem('gstore-preview-' + key) || 'null'); } catch { return null; } };
  const write = (key, value) => { try { sessionStorage.setItem('gstore-preview-' + key, JSON.stringify(value)); } catch { /* Private-mode fallback stays in this page. */ } };
  const applyProfile = saved => {
    if (!saved) return;
    const name = document.querySelector('.gstore-myaccount-nav__user-name');
    const email = document.querySelector('.gstore-myaccount-nav__user-email');
    if (name && saved.account_display_name) name.textContent = saved.account_display_name;
    if (email && saved.account_email) email.textContent = saved.account_email;
    const greeting = document.querySelector('.gstore-account-dashboard > .gstore-account-heading p');
    if (greeting && saved.account_first_name) greeting.textContent = 'Bom ter você por aqui, ' + saved.account_first_name + '.';
  };
  applyProfile(read('profile'));
  const notice = (host, message, error = false) => {
    let box = host.querySelector('[data-preview-feedback]');
    if (!box) {
      box = document.createElement('p');
      box.dataset.previewFeedback = '';
      host.append(box);
    }
    box.setAttribute('role', error ? 'alert' : 'status');
    box.style.cssText = 'padding:12px 16px;border:1px solid currentColor;border-radius:6px;grid-column:1/-1';
    box.style.color = error ? '#991b1b' : '#065f46';
    box.textContent = message;
    return box;
  };
  const form = document.querySelector('.woocommerce-EditAccountForm');
  if (form) {
    const fields = [...form.querySelectorAll('input:not([type=password])')];
    fields.forEach(input => { input.required = true; });
    const saved = read('profile');
    fields.forEach(input => { if (saved && typeof saved[input.id] === 'string') input.value = saved[input.id]; });
    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'gstore-account-button';
    button.textContent = 'Salvar alterações';
    form.append(button);
    const passwords = [...form.querySelectorAll('input[type=password]')];
    const instruction = document.createElement('p');
    instruction.textContent = 'Deixe os campos de senha em branco para manter sua senha atual. Use apenas dados fictícios nesta prévia.';
    form.querySelector('legend').after(instruction);
    form.addEventListener('submit', event => {
      event.preventDefault();
      const changing = passwords.some(input => input.value);
      if (changing && (passwords.some(input => !input.value) || passwords[1].value !== passwords[2].value)) {
        notice(form, 'Preencha os três campos de senha e confirme a mesma nova senha.', true);
        passwords.find(input => !input.value)?.focus();
        return;
      }
      passwords.forEach(input => { input.value = ''; });
      const profile = Object.fromEntries(fields.map(input => [input.id, input.value]));
      write('profile', profile); applyProfile(profile);
      notice(form, 'Alterações simuladas com sucesso. Nenhum dado ou senha foi enviado à loja.');
    });
  }
  document.querySelectorAll('.woocommerce-Address').forEach(section => {
    const edit = section.querySelector('.edit');
    const key = 'address-' + [...section.parentNode.children].indexOf(section);
    const renderAddress = values => {
      const address = section.querySelector('address');
      address.replaceChildren();
      values.filter(Boolean).forEach(value => { address.append(document.createTextNode(value), document.createElement('br')); });
    };
    const savedAddress = read(key);
    if (Array.isArray(savedAddress)) renderAddress(savedAddress);
    edit?.addEventListener('click', event => {
      event.preventDefault();
      if (section.querySelector('form')) return;
      const address = section.querySelector('address');
      const editor = document.createElement('form');
      const fields = [['Destinatário','Cliente Exemplo'],['CEP','90000-000'],['Rua e número','Rua de Exemplo, 100'],['Complemento',''],['Bairro','Centro'],['Cidade','Porto Alegre'],['Estado','RS']];
      const saved = read(key);
      fields.forEach(([label, value], index) => {
        const row = document.createElement('p');
        const text = document.createElement('label');
        const input = document.createElement('input');
        input.id = 'preview-address-' + [...section.parentNode.children].indexOf(section) + '-' + index;
        input.value = Array.isArray(saved) ? saved[index] || '' : value;
        input.required = index !== 3;
        input.className = 'input-text';
        text.htmlFor = input.id;
        text.textContent = label;
        row.append(text, input);
        editor.append(row);
      });
      const save = document.createElement('button');
      save.type = 'submit'; save.className = 'gstore-account-button'; save.textContent = 'Salvar endereço';
      const cancel = document.createElement('button');
      cancel.type = 'button'; cancel.className = 'gstore-account-button gstore-account-button--secondary'; cancel.textContent = 'Cancelar edição';
      cancel.addEventListener('click', () => { editor.remove(); address.hidden = false; edit.focus(); });
      editor.append(save, cancel);
      editor.addEventListener('submit', event => {
        event.preventDefault();
        const values = [...editor.querySelectorAll('input')].map(input => input.value);
        write(key, values); renderAddress(values);
        address.hidden = false; editor.remove();
        notice(section, 'Endereço atualizado nesta prévia. Nenhum dado foi enviado.'); edit.focus();
      });
      address.hidden = true; section.append(editor); editor.querySelector('input').focus();
    });
  });
  const id = new URLSearchParams(location.search).get('pedido');
  if (/^\d{1,8}$/.test(id || '')) {
    const title = document.querySelector('.gstore-view-order__header h1');
    if (title) title.textContent = 'Pedido #' + id;
  }
  const table = document.querySelector('.woocommerce-orders-table');
  if (table) {
    const body = table.querySelector('tbody');
    let rows = [...body.rows], page = 0;
    const pager = document.createElement('nav');
    pager.className = 'gstore-account-pagination'; pager.setAttribute('aria-label', 'Páginas de pedidos');
    const previous = document.createElement('button'), next = document.createElement('button'), label = document.createElement('span');
    previous.textContent = 'Anterior'; next.textContent = 'Próxima';
    [previous, next].forEach(button => { button.type = 'button'; button.className = 'gstore-account-button gstore-account-button--secondary'; button.style.width = 'auto'; });
    label.setAttribute('aria-live', 'polite');
    const render = () => {
      rows.forEach((row, index) => { row.hidden = index < page * 3 || index >= (page + 1) * 3; body.append(row); });
      previous.disabled = page === 0; next.disabled = (page + 1) * 3 >= rows.length;
      label.textContent = 'Página ' + (page + 1) + ' de ' + Math.ceil(rows.length / 3);
    };
    previous.addEventListener('click', () => { page--; render(); });
    next.addEventListener('click', () => { page++; render(); });
    pager.append(previous, label, next); table.after(pager);
    table.querySelectorAll('thead a').forEach(link => {
      let ascending = true;
      link.addEventListener('click', event => {
        event.preventDefault();
        const column = link.closest('th').cellIndex;
        rows.sort((a, b) => a.cells[column].textContent.trim().localeCompare(b.cells[column].textContent.trim(), 'pt-BR', {numeric: true}) * (ascending ? 1 : -1));
        table.querySelectorAll('th').forEach(th => th.removeAttribute('aria-sort'));
        table.querySelectorAll('thead a [aria-hidden]').forEach(span => { span.textContent = '↕'; });
        link.closest('th').setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
        const indicator = link.querySelector('[aria-hidden]'); if (indicator) indicator.textContent = ascending ? '↑' : '↓';
        ascending = !ascending; page = 0; render();
      });
    });
    render();
  }
  // External actions stay inside the fixture and explain the destination.
  document.querySelectorAll('a[href="#preview-scope"]').forEach(link => {
    if (link.matches('.edit')) return;
    link.addEventListener('click', event => {
      event.preventDefault();
      notice(link.closest('section, form, .gstore-account-card') || document.querySelector('.gstore-myaccount__content'),
        'Prévia: “' + link.textContent.trim() + '” abriria o serviço ou a ação correspondente na loja. Nenhuma ação real foi executada.');
    });
  });
  const upload = document.querySelector('#gstore-fulfillment-upload');
  if (upload) {
    const rowTemplate = upload.querySelector('.gstore-fulfillment-upload__file-row')?.cloneNode(true);
    const activeCount = () => upload.querySelectorAll('.gstore-fulfillment-upload__file-row:not(.gstore-fulfillment-upload__file-row--rejected)').length;
    const updateCount = () => {
      const counter = upload.querySelector('.gstore-fulfillment-upload__counter');
      if (counter) counter.textContent = activeCount() + ' de 5 vagas utilizadas · Arquivos negados não ocupam vagas.';
    };
    // Native markup is rendered first; intercept before its network handlers run.
    upload.querySelectorAll('input,button').forEach(control => { control.disabled = false; control.removeAttribute('title'); });
    upload.addEventListener('click', event => {
      const button = event.target.closest('button');
      if (!button) return;
      event.preventDefault(); event.stopImmediatePropagation();
      if (button.matches('.gstore-fulfillment-upload__btn--delete')) {
        button.closest('.gstore-fulfillment-upload__file-row')?.remove();
        updateCount();
        notice(upload, 'Documento removido apenas desta simulação.');
      } else notice(upload, 'Documento fictício para revisão visual. Nenhum arquivo real está associado a este pedido.');
    }, true);
    const receive = files => {
      const file = files[0]; if (!file) return;
      if (activeCount() >= 5) { notice(upload, 'Limite de 5 documentos atingido. Remova um arquivo para selecionar outro.', true); return; }
      if (!/\.(pdf|png|jpe?g)$/i.test(file.name) || file.size > 10 * 1024 * 1024) {
        notice(upload, 'Selecione PDF, PNG ou JPG com até 10 MB.', true); return;
      }
      if (rowTemplate) {
        const row = rowTemplate.cloneNode(true);
        row.classList.remove('gstore-fulfillment-upload__file-row--rejected', 'gstore-fulfillment-upload__file-row--approved');
        row.querySelector('.gstore-fulfillment-upload__review-note')?.remove();
        row.querySelector('.gstore-fulfillment-upload__filename').textContent = file.name;
        const badge = row.querySelector('.gstore-fulfillment-upload__status-badge');
        badge.className = 'gstore-fulfillment-upload__status-badge gstore-fulfillment-upload__status-badge--pending';
        badge.textContent = 'Em análise';
        row.querySelectorAll('button').forEach(button => { button.disabled = false; });
        upload.querySelector('.gstore-fulfillment-upload__files').append(row);
        updateCount();
      }
      notice(upload, 'Arquivo selecionado: ' + file.name + '. Recebimento simulado; nenhum arquivo foi enviado.');
      const input = upload.querySelector('input[type=file]'); if (input) input.value = '';
    };
    upload.addEventListener('change', event => {
      if (!event.target.matches('input[type=file]')) return;
      event.stopImmediatePropagation(); receive(event.target.files);
    }, true);
    upload.addEventListener('drop', event => {
      event.preventDefault(); event.stopImmediatePropagation(); receive(event.dataTransfer.files);
    }, true);
  }
});

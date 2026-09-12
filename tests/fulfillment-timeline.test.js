/** @jest-environment jsdom */
const fs = require('fs');
const path = require('path');

test('upload and correction update the visible customer stage from API responses', () => {
  const keys = ['processando_pagamento', 'pagamento_confirmado', 'aguardando_documentacao', 'processando_documentacao', 'preparando_entrega', 'enviado'];
  document.body.innerHTML = `<div class="gstore-fulfillment-timeline">${keys.map(key => `<div class="gstore-fulfillment-timeline__step" data-stage="${key}"><div class="gstore-fulfillment-timeline__icon"></div><span class="gstore-fulfillment-timeline__label"></span><div class="gstore-fulfillment-timeline__connector"></div></div>`).join('')}</div><p id="gstore-fulfillment-message" hidden></p><div id="gstore-fulfillment-upload" data-docs="[]"><div id="gstore-fulfillment-docs-list"></div><div id="gstore-fulfillment-dropzone-area"></div></div>`;
  window.gstoreFulfillment = { orderId: 42, nonce: 'fixture' };
  const requests = [];
  class XHR {
    constructor() { this.handlers = {}; this.upload = { addEventListener() {} }; requests.push(this); }
    addEventListener(type, callback) { this.handlers[type] = callback; }
    open(method) { this.method = method; }
    setRequestHeader() {}
    send() {}
    respond(stage, documents) { this.status = 201; this.responseText = JSON.stringify({success: true, data: {stage, documents}}); this.handlers.load(); }
  }
  window.XMLHttpRequest = XHR;
  window.confirm = () => true;
  window.eval(fs.readFileSync(path.join(__dirname, '../assets/js/fulfillment-timeline.js'), 'utf8'));
  document.dispatchEvent(new Event('DOMContentLoaded'));
  const sendFile = () => {
    const input = document.getElementById('gstore-file-input');
    Object.defineProperty(input, 'files', { configurable: true, value: [new File(['fixture'], 'synthetic.pdf', {type: 'application/pdf'})] });
    input.dispatchEvent(new Event('change'));
  };
  sendFile();
  requests.at(-1).respond('processando_documentacao', [{id:'a', status:'pending', filename:'synthetic.pdf'}]);
  expect(document.querySelector('.is-current').dataset.stage).toBe('processando_documentacao');
  expect(document.querySelector('.is-current').textContent).toContain('Verificando documentação');
  expect(document.getElementById('gstore-fulfillment-message').hidden).toBe(false);
  expect(document.getElementById('gstore-file-input')).not.toBeNull();
  sendFile();
  requests.at(-1).respond('documentacao_negada', [{id:'a', status:'rejected', filename:'synthetic.pdf', review_note:'<script>bad()</script>'}, {id:'b', status:'pending'}]);
  expect(document.querySelector('.is-current').classList.contains('is-rejected')).toBe(true);
  expect(document.getElementById('gstore-fulfillment-message').textContent).toContain('Entre em contato com o atendente');
  expect(document.querySelector('#gstore-fulfillment-docs-list script')).toBeNull();
  document.querySelector('[data-action="delete"][data-doc-id="a"]').click();
  expect(requests.at(-1).method).toBe('DELETE');
  requests.at(-1).respond('processando_documentacao', [{id:'b',status:'pending'}]);
  expect(document.querySelector('.is-current').classList.contains('is-rejected')).toBe(false);
  expect(document.getElementById('gstore-fulfillment-message').textContent).toContain('verificando');
});

/** @jest-environment jsdom */
const fs = require('fs');
const path = require('path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/my-account.js'), 'utf8');
const script = source.slice(source.indexOf('// Account layout refinements:'));
function run() { window.eval(script); document.dispatchEvent(new Event('DOMContentLoaded')); }
beforeEach(() => { document.body.innerHTML = ''; });
test('keeps forms, nonces and submit handlers while aligning data and pagination', () => {
 document.body.innerHTML = '<div class="gstore-account-shell"><section><h1>Meus dados</h1><nav class="gstore-account-pages"></nav><form><input name="nonce" value="test-nonce"><button>Salvar</button></form><nav class="gstore-account-pagination"><a href="/address">Próxima →</a></nav></section></div>';
 const form = document.querySelector('form'); const handler = jest.fn(e => e.preventDefault()); form.addEventListener('submit', handler);
 run(); run();
 expect(document.querySelectorAll('.account-refined-data-panel')).toHaveLength(1);
 expect(document.querySelector('.account-refined-data-panel form')).toBe(form);
 expect(form.querySelector('input').value).toBe('test-nonce');
 form.dispatchEvent(new Event('submit', {cancelable:true})); expect(handler).toHaveBeenCalledTimes(1);
 expect(document.querySelector('.gstore-account-pagination a').getAttribute('href')).toBe('/address');
 expect(document.querySelectorAll('.account-refined-pagination-arrow')).toHaveLength(1);
});
test('keeps upload and contract independent, and preserves payment markup and links', () => {
 document.body.innerHTML = '<div class="gstore-account-shell"><div class="gstore-view-order"><header class="account-detail-heading"></header><section class="gstore-view-order__tracking"><p id="gstore-fulfillment-message" class="is-rejected">Corrija o arquivo.</p></section><div class="gstore-fulfillment-upload"><input type="file"></div><div class="gstore-view-order__details"><section class="woocommerce-order-details"><h2>Detalhes</h2><table><tbody><tr><td>Item</td></tr></tbody><tfoot><tr><th>Total</th><td><a href="/invoice?key=test"><bdi>R$ 250,00</bdi></a></td></tr></tfoot></table></section><section class="account-detail-actions"><h2>Contrato e ações</h2><p>Consulte o contrato.</p><button class="gstore-contract-open" data-order-key="test">Ver contrato</button><a href="/repeat?nonce=test">Refazer compra</a></section></div></div></div>';
 const file = document.querySelector('input'); const contract = document.querySelector('button'); const click = jest.fn(); contract.addEventListener('click',click);
 const money = document.querySelector('bdi');
 run(); run();
 expect(document.querySelector('.account-refined-documents input')).toBe(file);
 expect(document.querySelector('.account-refined-documents button')).toBeNull();
 expect(document.querySelector('.account-detail-actions button')).toBe(contract); contract.click(); expect(click).toHaveBeenCalledTimes(1);
 expect(document.querySelector('.account-refined-payment bdi')).toBe(money);
 expect(document.querySelector('.account-refined-payment a').getAttribute('href')).toBe('/invoice?key=test');
 expect(document.querySelectorAll('.account-refined-next')).toHaveLength(1);
 expect(document.querySelector('.account-refined-next a').getAttribute('href')).toBe('#documentos-do-pedido');
});
test('does not fabricate document sections or actions on unavailable states', () => {
 document.body.innerHTML = '<div class="gstore-account-shell"><div class="gstore-view-order"><header class="account-detail-heading"></header></div></div>';
 run(); expect(document.querySelector('.account-refined-documents')).toBeNull(); expect(document.querySelector('.account-refined-next')).toBeNull();
});

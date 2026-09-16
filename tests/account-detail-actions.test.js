/** @jest-environment jsdom */
const fs = require('fs');
const path = require('path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/my-account.js'), 'utf8');
const script = source.slice(source.indexOf('// Move existing controls,'));
function run() { window.eval(script); document.dispatchEvent(new Event('DOMContentLoaded')); }
beforeEach(() => { document.body.innerHTML = ''; });
test('groups native controls before addresses without replacing contract or nonce URLs', () => {
 document.body.innerHTML = '<div class="gstore-view-order__details"><table><tbody><tr><th class="order-actions--heading">Ações</th><td><a class="order-actions-button pay" href="/pay?nonce=fixture">Pagar</a></td></tr></tbody></table><p class="order-again"><a href="/repeat?nonce=fixture">Comprar novamente</a></p><p class="gstore-contract-trigger-wrap"><button class="gstore-contract-open" data-order-key="fixture">Ver contrato</button></p><section class="woocommerce-customer-details"></section></div>';
 const contract = document.querySelector('button'); const click = jest.fn(); contract.addEventListener('click', click);
 run(); run();
 const panel = document.querySelector('.account-detail-actions');
 expect(document.querySelectorAll('.account-detail-actions')).toHaveLength(1);
 expect(panel.nextElementSibling.className).toBe('woocommerce-customer-details');
 expect(panel.querySelector('button')).toBe(contract); contract.click(); expect(click).toHaveBeenCalledTimes(1);
 expect(contract.dataset.orderKey).toBe('fixture');
 expect(panel.querySelector('a[href="/repeat?nonce=fixture"]').textContent).toBe('Refazer compra');
 expect(panel.querySelector('a[href="/pay?nonce=fixture"]')).not.toBeNull();
 expect(document.querySelector('.order-actions--heading')).toBeNull();
});
test('does not invent unavailable contracts or repeat actions', () => {
 document.body.innerHTML = '<div class="gstore-view-order__details"><section class="woocommerce-customer-details"></section></div>';
 run(); expect(document.querySelector('.account-detail-actions')).toBeNull();
});
test('contract alone is grouped without adding a repeat action', () => {
 document.body.innerHTML = '<div class="gstore-view-order__details"><p class="gstore-contract-trigger-wrap"><button class="gstore-contract-open">Ver contrato</button></p></div>';
 run(); expect(document.querySelectorAll('.account-detail-actions button')).toHaveLength(1);
 expect(document.querySelector('.account-detail-actions a')).toBeNull();
});

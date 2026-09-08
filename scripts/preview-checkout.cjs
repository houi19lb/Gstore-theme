// Preview local do JS/CSS reais com respostas fictícias. Não conecta ao WordPress.
const http = require('http');
const fs = require('fs');
const path = require('path');
const { markup, summary, instrument } = require('../tests/fixtures/checkout');
const root = path.resolve(__dirname, '..');
const port = Number(process.env.GSTORE_CHECKOUT_PREVIEW_PORT || 4187);
const boot = `
window.gstoreCheckout = { contract: { enabled: false } };
jQuery.fx.off = true;
const fixtureSummary = ${JSON.stringify(summary)};
jQuery.ajax = function(options) {
 const pending = jQuery.Deferred();
 setTimeout(function() {
  let data = JSON.parse(JSON.stringify(fixtureSummary));
  if (options.data && options.data.action === 'gstore_get_cart_summary') {
   const id = jQuery('form.checkout input[name="gstore_selected_shipping_rate[item-a]"]').val();
   const rate = data.items[0].gstore_shipping_rates.find(r => r.rate_id === id) || data.items[0].gstore_shipping_rates[0];
   data.totals.shipping = rate.cost.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
   data.total = data.base_total = (200 + rate.cost).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
   data.items[0].gstore_selected_shipping_rate = rate.rate_id;
  } else { data = { rates: fixtureSummary.items[0].gstore_shipping_rates, destination: {city:'São Paulo',state:'SP'} }; }
  const response = {success:true, data};
  if (options.success) options.success(response);
  pending.resolve(response);
 }, 30);
 return pending.promise();
};
jQuery(function() {
 checkoutFixture.init(); checkoutFixture.primeShipping(); checkoutFixture.seedSummary(fixtureSummary); checkoutFixture.setActiveStep(2, false);
 const labels = {first_name:'Nome completo',last_name:'Sobrenome',cpf:'CPF',postcode:'CEP',phone:'Celular (com DDD)',email:'Endereço de e-mail',address_1:'Endereço',number:'Número',address_2:'Complemento',city:'Cidade',state:'Estado'};
 Object.entries(labels).forEach(([key,label]) => jQuery('label[for="billing_'+key+'"]').text(label));
 jQuery(document.body).on('update_checkout.preview', function() { checkoutFixture.loadCartSummary(); });
 jQuery(document).on('click.preview', '#place_order', function(e) { e.preventDefault(); e.stopImmediatePropagation(); alert('Demonstração local: nenhum pedido será enviado.'); });
});`;
const html = `<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Checkout · Preview local</title>
<link rel="stylesheet" href="/assets/vendor/fontawesome/6.5.1/css/all.min.css"><link rel="stylesheet" href="/assets/css/checkout-steps.css"><style>
:root{--gstore-font-size-xs:.75rem;--gstore-font-size-sm:.875rem;--gstore-font-size-base:1rem;--gstore-font-size-xl:1.25rem;--gstore-font-size-2xl:1.5rem;--gstore-font-size-175:1.75rem;--gstore-font-size-08:.8rem;--gstore-font-size-09:.9rem;--gstore-font-size-095:.95rem;--gstore-font-weight-bold:700;--gstore-font-weight-semibold:600;--gstore-font-weight-medium:500;--gstore-letter-spacing-15:.08em;}*{box-sizing:border-box}body{margin:0;font:16px Arial,sans-serif;color:#111;background:#fff}header{padding:20px 6%;background:#000;color:#b5a642;display:flex;justify-content:space-between;align-items:center}header{gap:16px}header img{max-width:55%;height:auto}header strong{font-size:24px}header span{font-size:12px;color:#719d14}.preview-note{font-size:12px;text-align:center;padding:12px;color:#555}.Gstore-checkout-steps-shell{max-width:1200px;margin:32px auto;padding:0 16px}.Gstore-checkout-steps-shell{background:#fff}.Gstore-summary-item__image[src=""]{display:none}button,input{font:inherit}input{max-width:100%}.form-row{margin:0}.form-row label{display:block;margin-bottom:8px}.form-row input{width:100%;min-height:44px;padding:10px;border:1px solid #d7d7d2;border-radius:4px}.shop_table{width:100%}td,th{padding:10px;text-align:left}@media(max-width:768px){header{padding:16px}.Gstore-checkout-steps-shell{margin:20px auto}.Gstore-checkout-step{padding:20px 16px}}
</style><header><img src="https://armastore.com.br/wp-content/uploads/2025/12/armastoreLogo.png" width="170" alt="ArmaStore"><span>COMPRA SEGURA</span></header>${markup()}<p class="preview-note">Preview local · dados e valores fictícios · sem envio de pedidos</p><script src="/jquery.js"></script><script>${boot.split('jQuery(function()')[0]}</script><script src="/checkout.js"></script><script>jQuery(function()${boot.split('jQuery(function()')[1]}</script></html>`;
http.createServer((req,res) => {
 let file, type = 'text/javascript';
 if (req.url === '/jquery.js') file = require.resolve('jquery');
 else if (/^\/assets\/vendor\/fontawesome\/6\.5\.1\/(css\/all\.min\.css|webfonts\/fa-[a-z0-9-]+\.woff2?)$/.test(req.url)) {file=path.join(root,req.url.slice(1));type=req.url.endsWith('.css')?'text/css':'font/woff2';}
 else if (req.url === '/assets/css/checkout-steps.css') {file=path.join(root,'assets/css/checkout-steps.css');type='text/css';}
 else if (req.url === '/checkout.js') {res.setHeader('Content-Type','text/javascript; charset=utf-8');res.end(instrument(fs.readFileSync(path.join(root,'assets/js/checkout-steps.js'),'utf8')));return;}
 else if (req.url === '/' || req.url === '/favicon.ico') {res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);return;}
 else {res.writeHead(404);res.end();return;}
 res.setHeader('Content-Type',type+'; charset=utf-8');res.end(fs.readFileSync(file));
}).listen(port,'127.0.0.1',()=>console.log('Checkout preview: http://127.0.0.1:'+port));

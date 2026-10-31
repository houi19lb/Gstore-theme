const fs = require('fs');
const path = require('path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/checkout-whatsapp-after-order.js'), 'utf8');
const key = 'gstore_whatsapp_after_order:v1';
const orderPath = '/checkout/order-received/15046/';
const whatsappUrl = 'https://wa.me/5511999999999?text=Pedido%20%2315046';

function runOnOrderPage(pending, buttonHref = whatsappUrl) {
  const stored = new Map([[key, JSON.stringify(pending)]]);
  const assign = jest.fn();
  const loadListeners = [];
  const window = {
    location: { pathname: orderPath, assign },
    sessionStorage: {
      getItem: (name) => stored.get(name) || null,
      removeItem: (name) => stored.delete(name)
    },
    setTimeout,
    addEventListener: (name, listener) => {
      if (name === 'load') loadListeners.push(listener);
    }
  };
  const document = {
    readyState: 'loading',
    querySelector: () => buttonHref ? { href: buttonHref } : null
  };
  new Function('window', 'document', 'URL', 'Date', source)(window, document, URL, Date);
  return { assign, loadListeners, stored };
}

beforeEach(() => jest.useFakeTimers());
afterEach(() => {
  jest.clearAllTimers();
  jest.useRealTimers();
});

test('direciona na mesma aba somente após carregar o pedido recebido', () => {
  const page = runOnOrderPage({ path: orderPath, createdAt: Date.now() });
  expect(page.assign).not.toHaveBeenCalled();
  page.loadListeners[0]();
  jest.runOnlyPendingTimers();
  expect(page.assign).toHaveBeenCalledTimes(1);
  expect(page.assign).toHaveBeenCalledWith(whatsappUrl);
  expect(page.stored.has(key)).toBe(false);
});

test('não direciona outro pedido ou uma tentativa expirada', () => {
  for (const pending of [
    { path: '/checkout/order-received/99999/', createdAt: Date.now() },
    { path: orderPath, createdAt: Date.now() - 6 * 60 * 1000 }
  ]) {
    const page = runOnOrderPage(pending);
    page.loadListeners.forEach((listener) => listener());
    jest.runOnlyPendingTimers();
    expect(page.assign).not.toHaveBeenCalled();
  }
});

test('não direciona uma URL que não seja do WhatsApp', () => {
  const page = runOnOrderPage({ path: orderPath, createdAt: Date.now() }, 'https://example.com/');
  page.loadListeners[0]();
  jest.runOnlyPendingTimers();
  expect(page.assign).not.toHaveBeenCalled();
});

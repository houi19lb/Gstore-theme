/** @jest-environment node */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const root = path.join(__dirname, '..');
const key = '42:2026-09-05 23:59:59';
const storageKey = 'gstore_flash_sale_dismissed:' + key;
const markup = (campaignKey = key) => `
  <aside class="gstore-flash-sale-floating" hidden data-gstore-flash-sale-key="${campaignKey}">
    <button data-gstore-flash-sale-close><span>Fechar oferta</span></button>
    <time data-gstore-flash-sale-end="2026-09-05T23:59:59Z">--:--:--</time>
  </aside>`;

describe.each(['flash-sale.js', 'flash-sale.min.js'])('floating offer session: %s', (asset) => {
  const script = fs.readFileSync(path.join(root, 'assets', 'js', asset), 'utf8');
  let dom;

  function openPage({ pathname = '/', saved = {}, html = markup(), loading = false } = {}) {
    if (dom) dom.window.close();
    dom = new JSDOM(html, { url: 'https://store.example' + pathname, runScripts: 'outside-only' });
    const { window } = dom;
    window.setInterval = jest.fn();
    Object.defineProperty(window.document, 'readyState', { configurable: true, value: loading ? 'loading' : 'complete' });
    Object.entries(saved).forEach(([name, value]) => window.sessionStorage.setItem(name, value));
    return window;
  }

  function boot(options) {
    const window = openPage(options);
    window.eval(script);
    return window;
  }

  function savedSession(window) {
    return Object.fromEntries(Object.keys(window.sessionStorage).map((name) => [name, window.sessionStorage.getItem(name)]));
  }

  afterEach(() => dom && dom.window.close());

  test.each(['/', '/produto/item-em-oferta/', '/produto/outro-item/'])('shows the offer on a fresh visit to %s', (pathname) => {
    const window = boot({ pathname });
    expect(window.document.querySelector('aside').hidden).toBe(false);
    expect(window.document.querySelector('time').textContent).not.toBe('--:--:--');
  });

  test.each([
    ['/', '/produto/outro-item/'],
    ['/produto/item-em-oferta/', '/'],
    ['/produto/item-em-oferta/', '/produto/item-em-oferta/'],
  ])('keeps dismissal from %s when navigating/reloading %s', (from, to) => {
    const first = boot({ pathname: from });
    first.document.querySelector('button span').click();
    expect(first.document.querySelector('aside')).toBeNull();
    expect(first.sessionStorage.getItem(storageKey)).toBe('1');
    const next = boot({ pathname: to, saved: savedSession(first) });
    expect(next.document.querySelector('aside')).toBeNull();
  });

  test('shows the offer again in a new tab session', () => {
    const first = boot();
    first.document.querySelector('button').click();
    const reopened = boot({ pathname: '/produto/item-em-oferta/' });
    expect(reopened.document.querySelector('aside').hidden).toBe(false);
    expect(reopened.localStorage.length).toBe(0);
  });

  test('does not hide a new campaign when the previous one was dismissed', () => {
    const window = boot({ saved: { [storageKey]: '1' }, html: markup('42:2026-09-06 23:59:59') });
    expect(window.document.querySelector('aside').hidden).toBe(false);
  });

  test.each([false, true])('handles markup printed after footer scripts (dismissed=%s)', (dismissed) => {
    const window = boot({ html: '', loading: true, saved: dismissed ? { [storageKey]: '1' } : {} });
    window.document.body.innerHTML = markup();
    expect(window.document.querySelector('aside').hidden).toBe(true);
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
    if (dismissed) {
      expect(window.document.querySelector('aside')).toBeNull();
    } else {
      expect(window.document.querySelector('aside').hidden).toBe(false);
      expect(window.document.querySelector('time').textContent).not.toBe('--:--:--');
    }
  });

  test('rechecks dismissal when returning through the browser back/forward cache', () => {
    const window = boot();
    window.sessionStorage.setItem(storageKey, '1');
    window.dispatchEvent(new window.Event('pageshow'));
    expect(window.document.querySelector('aside')).toBeNull();
  });

  test('still shows and closes the card when storage is blocked', () => {
    const window = openPage();
    Object.defineProperty(window, 'sessionStorage', { get() { throw new Error('Storage blocked'); } });
    expect(() => window.eval(script)).not.toThrow();
    expect(window.document.querySelector('aside').hidden).toBe(false);
    window.document.querySelector('button').click();
    expect(window.document.querySelector('aside')).toBeNull();
  });

  test('closing the floating card leaves the product countdown in place', () => {
    const window = boot({ html: markup() + '<section data-gstore-flash-sale-clock><time data-gstore-flash-sale-end="2026-09-05T23:59:59Z">--:--:--</time></section>' });
    window.document.querySelector('button').click();
    expect(window.document.querySelector('[data-gstore-flash-sale-clock]')).not.toBeNull();
  });
});

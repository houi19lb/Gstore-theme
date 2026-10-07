/** @jest-environment node */
const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const field = '<div data-gstore-region-fields><select data-gstore-region-select><option value="">Sem região</option><option value="SP">São Paulo</option><option value="PR">Paraná</option></select><p data-gstore-region-status></p></div>';
const markup = '<button data-gstore-region-trigger>Região</button><dialog id="gstore-region-dialog"><form>' + field + '<button type="button" data-gstore-region-close>Fechar</button><button type="submit">Salvar</button></form></dialog><div id="age">' + field + '</div>';
const tick = () => new Promise(resolve => setImmediate(resolve));

describe.each(['gstore-regional-pricing.js', 'gstore-regional-pricing.min.js'])('%s', asset => {
  let dom, w;
  function boot(cookie = '') {
    dom = new JSDOM(markup, { url: 'https://shop.example', runScripts: 'outside-only' });
    w = dom.window;
    Object.defineProperty(w.document, 'readyState', { value: 'complete' });
    const dialog = w.document.querySelector('dialog');
    dialog.showModal = () => { dialog.open = true; };
    dialog.close = () => { dialog.open = false; dialog.dispatchEvent(new w.Event('close')); };
    if (cookie) w.document.cookie = 'gstore_region=' + cookie;
    w.gstoreRegionalConfig = { endpoint: 'https://shop.example/wp-json/gstore/v1/regional-context', states: { SP: 'São Paulo', PR: 'Paraná' } };
    w.fetch = jest.fn().mockResolvedValue({ ok: true, json: async () => ({ nonce: 'test', suggested_state: 'SP' }) });
    w.eval(fs.readFileSync(path.join(__dirname, '../assets/js/', asset), 'utf8'));
    return w.document.getElementById('age');
  }
  afterEach(() => dom && dom.window.close());

  test.each(['SP', 'none'])('remembered region %s does not trigger a lookup', async cookie => {
    const age = boot(cookie);
    w.gstoreRegion.initAge(age, true);
    w.document.querySelector('[data-gstore-region-trigger]').click();
    await tick();
    expect(w.fetch).not.toHaveBeenCalled();
    expect(w.document.querySelector('dialog').open).toBe(true);
  });
  test('suggests state in age modal, without recording age or region', async () => {
    const age = boot();
    w.gstoreRegion.initAge(age, false);
    await tick();
    expect(age.querySelector('select').value).toBe('SP');
    expect(w.document.cookie).toBe('');
    expect(w.localStorage.length).toBe(0);
    expect(w.document.querySelector('dialog').open).toBe(false);
    expect(w.fetch).toHaveBeenCalledTimes(1);
  });
  test('user correction wins over a late IP suggestion', async () => {
    const age = boot();
    let resolve;
    w.fetch.mockReturnValue(new Promise(r => { resolve = r; }));
    w.gstoreRegion.initAge(age, false);
    age.querySelector('select').value = 'PR';
    age.querySelector('select').dispatchEvent(new w.Event('change'));
    resolve({ ok: true, json: async () => ({ suggested_state: 'SP', nonce: 'test' }) });
    await tick();
    expect(age.querySelector('select').value).toBe('PR');
  });
  test('confirmed adult without region sees only the region dialog', () => {
    const age = boot();
    w.gstoreRegion.initAge(age, true);
    expect(w.document.querySelector('dialog').open).toBe(true);
  });
  test('lookup failure still allows age confirmation without a state', async () => {
    const age = boot();
    w.fetch.mockRejectedValue(new Error('offline'));
    w.gstoreRegion.initAge(age, false);
    await tick();
    expect(age.querySelector('[data-gstore-region-status]').textContent).toContain('indisponível');
    await expect(w.gstoreRegion.confirmAge(age)).resolves.toBe(false);
  });
  test('selected state is posted, then stale cart fragments are removed', async () => {
    const age = boot();
    w.gstoreRegion.initAge(age, false);
    await tick();
    w.sessionStorage.setItem('wc_fragments_test', 'old-price');
    w.sessionStorage.setItem('unrelated', 'keep');
    w.fetch.mockImplementationOnce(async (url, options) => {
      expect(options.method).toBe('POST');
      expect(JSON.parse(options.body)).toEqual({ state: 'SP' });
      w.document.cookie = 'gstore_region=SP';
      return { ok: true, json: async () => ({ state: 'SP' }) };
    });
    await expect(w.gstoreRegion.confirmAge(age)).resolves.toBe(true);
    expect(w.sessionStorage.getItem('wc_fragments_test')).toBeNull();
    expect(w.sessionStorage.getItem('unrelated')).toBe('keep');
  });
});

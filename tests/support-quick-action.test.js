/** @jest-environment node */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

test.each(['.js', '.min.js'])('floating support offers only WhatsApp and Telegram in both mode (%s)', (extension) => {
  const dom = new JSDOM('<div class="Gstore-top-bar__contacts"><a class="Gstore-top-bar__link" aria-label="Telegram" href="https://t.me/armastoreoficial">Telegram</a></div>', {
    url: 'https://armastore.com.br/',
    runScripts: 'outside-only',
  });
  const { window } = dom;
  Object.defineProperty(window.document, 'readyState', { configurable: true, value: 'complete' });
  window.MutationObserver = class { observe() {} disconnect() {} };
  window.open = jest.fn();
  window.gstoreChatwootBridgeConfig = {
    quickAction: {
      effectiveChannel: 'both',
      effectiveType: 'selector',
      effectiveLabel: 'Atendimento',
      telegramUrl: 'https://t.me/armastoreoficial',
      whatsappUrl: 'https://wa.me/555195150501',
      chatwootEnabled: false,
    },
  };

  window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', `support-loader${extension}`), 'utf8'));
  window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', `telegram-floating${extension}`), 'utf8'));
  window.document.querySelector('#gstore-support-float').click();
  const modal = window.document.querySelector('#gstore-support-selector-modal');
  expect(modal.getAttribute('aria-hidden')).toBe('false');
  expect([...modal.querySelectorAll('[data-channel]')].map((item) => item.dataset.channel)).toEqual(['whatsapp', 'telegram']);

  modal.querySelector('[data-channel="whatsapp"]').click();
  expect(window.open).toHaveBeenLastCalledWith('https://wa.me/555195150501', '_blank', 'noopener');
  window.document.querySelector('#gstore-support-float').click();
  modal.querySelector('[data-channel="telegram"]').click();
  expect(window.open).toHaveBeenLastCalledWith('https://t.me/armastoreoficial', '_blank', 'noopener');
  dom.window.close();
});

test.each([
  [['chatwoot', 'whatsapp'], ['chat', 'whatsapp']],
  [['chatwoot', 'telegram'], ['chat', 'telegram']],
  [['whatsapp', 'telegram'], ['whatsapp', 'telegram']],
  [['chatwoot', 'whatsapp', 'telegram'], ['chat', 'whatsapp', 'telegram']],
].flatMap(([channels, expected]) => ['.js', '.min.js'].map((extension) => [channels, expected, extension])))('floating support displays %j as %j (%s)', (enabledChannels, expected, extension) => {
  const dom = new JSDOM('<div class="Gstore-top-bar__contacts"><a class="Gstore-top-bar__link" aria-label="Telegram" href="https://t.me/armastoreoficial">Telegram</a></div>', {
    url: 'https://armastore.com.br/',
    runScripts: 'outside-only',
  });
  const { window } = dom;
  Object.defineProperty(window.document, 'readyState', { configurable: true, value: 'complete' });
  window.MutationObserver = class { observe() {} disconnect() {} };
  window.gstoreChatwootBridgeConfig = {
    quickAction: {
      enabledChannels,
      effectiveType: 'selector',
      effectiveChannel: 'selector',
      telegramUrl: 'https://t.me/armastoreoficial',
      whatsappUrl: 'https://wa.me/555195150501',
      chatwootEnabled: enabledChannels.includes('chatwoot'),
    },
  };
  window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', `telegram-floating${extension}`), 'utf8'));
  window.document.querySelector('#gstore-support-float').click();
  const modal = window.document.querySelector('#gstore-support-selector-modal');
  expect([...modal.querySelectorAll('[data-channel]')].map((item) => item.dataset.channel)).toEqual(expected);
  dom.window.close();
});

test('WhatsApp alone opens directly without a Telegram link in the header', () => {
  const dom = new JSDOM('', { url: 'https://armastore.com.br/', runScripts: 'outside-only' });
  const { window } = dom;
  Object.defineProperty(window.document, 'readyState', { configurable: true, value: 'complete' });
  window.MutationObserver = class { observe() {} disconnect() {} };
  window.open = jest.fn();
  window.gstoreChatwootBridgeConfig = {
    quickAction: {
      enabledChannels: ['whatsapp'],
      effectiveChannel: 'whatsapp',
      effectiveType: 'whatsapp',
      whatsappUrl: 'https://wa.me/555195150501',
    },
  };
  window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'telegram-floating.js'), 'utf8'));
  window.document.querySelector('#gstore-support-float').click();
  expect(window.open).toHaveBeenCalledWith('https://wa.me/555195150501', '_blank', 'noopener');
  dom.window.close();
});

/** @jest-environment node */
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

test.each(['header.js', 'header.min.js'])('%s keeps the header visible until its reserved space has scrolled past', (asset) => {
  jest.useFakeTimers();
  const dom = new JSDOM('<div class="wp-site-blocks"><header class="Gstore-header-shell"></header></div>', {
    runScripts: 'outside-only',
  });
  const { window } = dom;
  const { document } = window;
  const header = document.querySelector('.Gstore-header-shell');
  Object.defineProperty(document, 'readyState', { value: 'complete' });
  Object.defineProperty(header, 'offsetHeight', { value: 166 });
  window.setTimeout = setTimeout;

  try {
    window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', asset), 'utf8'));
    expect(document.querySelector('.wp-site-blocks').style.paddingTop).toBe('166px');

    const scrollTo = (top) => {
      window.pageYOffset = top;
      window.dispatchEvent(new window.Event('scroll'));
      jest.advanceTimersByTime(10);
      return header.classList.contains('is-hidden');
    };

    expect(scrollTo(120)).toBe(false);
    expect(scrollTo(190)).toBe(true);
    expect(scrollTo(95)).toBe(false);
    expect(scrollTo(120)).toBe(false);
    expect(scrollTo(170)).toBe(true);
    expect(scrollTo(169)).toBe(false);
  } finally {
    window.close();
    jest.useRealTimers();
  }
});

const DEFAULT_PATHS = [
  ['home', '/'],
  ['catalog', '/catalogo/'],
  ['product', '/produto/espingarda-montenegro-cbc-monotiro-calibre-36-cano-de-28-pol-oxidado/'],
];

const args = process.argv.slice(2).filter((arg) => /^https?:\/\//i.test(arg));
const baseUrl = process.env.GSTORE_IMAGE_CHECK_BASE_URL || '';

function resolveUrls() {
  if (args.length > 0) {
    return args.map((url) => [detectKind(url), url]);
  }

  if (!baseUrl) {
    return [];
  }

  const base = new URL(baseUrl);
  return DEFAULT_PATHS.map(([kind, pathname]) => [kind, new URL(pathname, base).toString()]);
}

function detectKind(url) {
  const pathname = new URL(url).pathname;
  if (pathname.includes('/produto/')) {
    return 'product';
  }
  if (pathname.includes('/catalogo/') || pathname.includes('/categoria-produto/')) {
    return 'catalog';
  }
  return 'home';
}

function readAttrs(tag) {
  const attrs = {};
  const regex = /\s([a-zA-Z0-9_:-]+)(?:=("[^"]*"|'[^']*'|[^\s>]+))?/g;
  let match;

  while ((match = regex.exec(tag)) !== null) {
    attrs[match[1].toLowerCase()] = (match[2] || '').replace(/^['"]|['"]$/g, '');
  }

  return attrs;
}

function extractTags(html, tagName) {
  const regex = new RegExp(`<${tagName}\\b[^>]*>`, 'gi');
  return [...html.matchAll(regex)].map((match) => readAttrs(match[0]));
}

function shortSrc(attrs) {
  return (attrs.src || attrs.href || '').replace(/^https?:\/\/[^/]+/i, '').slice(0, 130);
}

function hasDimensions(attrs) {
  return Boolean(attrs.width && attrs.height);
}

function isWooTemplateThumbnail(attrs) {
  return attrs['data-wp-bind--src'] === 'state.itemThumbnail';
}

function isHigh(attrs) {
  return String(attrs.fetchpriority || '').toLowerCase() === 'high';
}

function isImagePreload(attrs) {
  return String(attrs.rel || '').toLowerCase() === 'preload' && String(attrs.as || '').toLowerCase() === 'image';
}

function validateCommon(kind, url, images, failures) {
  const highImages = images.filter(isHigh);

  if (highImages.length > 1) {
    failures.push(`${url}: expected at most 1 high-priority <img>, found ${highImages.length}`);
  }

  for (const image of highImages) {
    if (!hasDimensions(image)) {
      failures.push(`${url}: high-priority image is missing width/height: ${shortSrc(image)}`);
    }
  }

  const firstVisibleImages = images
    .filter((image) => !/^data:/i.test(image.src || ''))
    .slice(0, 8);

  for (const image of firstVisibleImages) {
    if (!hasDimensions(image)) {
      failures.push(`${url}: early image is missing width/height: ${shortSrc(image)}`);
    }
  }

  for (const image of images) {
    const src = image.src || '';
    const isUploadedRaster = /\/wp-content\/uploads\/.*\.(?:png|jpe?g|webp|gif|avif)(?:[?#].*)?$/i.test(src);
    if ((isUploadedRaster || isWooTemplateThumbnail(image)) && !hasDimensions(image)) {
      failures.push(`${url}: image/template is missing width/height: ${shortSrc(image) || image['data-wp-bind--src'] || 'dynamic thumbnail'}`);
    }
  }

  if (kind !== 'home') {
    return;
  }

  const heroHighImages = highImages.filter((image) => /Gstore-hero-slider|skip-lazy/i.test(image.class || ''));
  if (heroHighImages.length > 1) {
    failures.push(`${url}: desktop/mobile hero images both have fetchpriority=high`);
  }
}

function validateHome(url, links, failures) {
  const highHeroPreloads = links.filter((link) => isImagePreload(link) && isHigh(link));

  if (highHeroPreloads.length === 0) {
    failures.push(`${url}: expected at least one high-priority hero image preload`);
    return;
  }

  if (highHeroPreloads.length > 1) {
    const allMediaScoped = highHeroPreloads.every((link) => Boolean(link.media));
    if (!allMediaScoped) {
      failures.push(`${url}: multiple hero preloads must be media-scoped`);
    }
  }

  for (const preload of highHeroPreloads) {
    if (String(preload.type || '').toLowerCase() !== 'image/webp' || !/\.webp(?:[?#]|$)/i.test(preload.href || '')) {
      failures.push(`${url}: high-priority hero preload should use WebP when available: ${shortSrc(preload)}`);
    }
  }
}

function validateProduct(url, images, failures) {
  const highImages = images.filter(isHigh);

  if (highImages.length !== 1) {
    failures.push(`${url}: expected exactly 1 high-priority product image, found ${highImages.length}`);
    return;
  }

  const highImage = highImages[0];
  if (!/wp-post-image/i.test(highImage.class || '')) {
    failures.push(`${url}: high-priority product image is not the main gallery image: ${shortSrc(highImage)}`);
  }
}

function validateCatalog(url, images, failures) {
  const productThumbs = images.filter((image) => /woocommerce_thumbnail/i.test(image.class || ''));
  const highThumbs = productThumbs.filter(isHigh);

  if (highThumbs.length > 1) {
    failures.push(`${url}: expected at most 1 high-priority catalog thumbnail, found ${highThumbs.length}`);
  }

  for (const image of productThumbs.slice(1)) {
    if (isHigh(image) || String(image.loading || '').toLowerCase() !== 'lazy') {
      failures.push(`${url}: non-leading catalog thumbnail should stay lazy: ${shortSrc(image)}`);
    }
  }
}

async function validateUrl(kind, url) {
  const response = await fetch(url, { redirect: 'follow' });
  if (!response.ok) {
    return [`${url}: HTML returned ${response.status}`];
  }

  const html = await response.text();
  const images = extractTags(html, 'img');
  const links = extractTags(html, 'link');
  const failures = [];

  if (/cdnjs\.cloudflare\.com\/ajax\/libs\/font-awesome\/6\.5\.1\/css\/all\.min\.css/i.test(html)) {
    failures.push(`${url}: Font Awesome is still loaded from cdnjs`);
  }

  validateCommon(kind, url, images, failures);

  if (kind === 'home') {
    validateHome(url, links, failures);
  } else if (kind === 'product') {
    validateProduct(url, images, failures);
  } else if (kind === 'catalog') {
    validateCatalog(url, images, failures);
  }

  return failures;
}

const urls = resolveUrls();

if (urls.length === 0) {
  console.log('Image priority check skipped. Set GSTORE_IMAGE_CHECK_BASE_URL or pass rendered URLs.');
  process.exit(0);
}

const failures = [];
for (const [kind, url] of urls) {
  failures.push(...await validateUrl(kind, url));
}

if (failures.length > 0) {
  throw new Error(`Image priority check failed:\n${failures.map((item) => `- ${item}`).join('\n')}`);
}

console.log(`Image priority check OK: ${urls.length} URL(s).`);

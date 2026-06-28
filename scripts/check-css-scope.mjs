import { promises as fs } from 'node:fs';
import path from 'node:path';

const rootDir = process.cwd();
const maxStyleBytes = 60 * 1024;
const requiredScopedFiles = [
  'assets/css/single-product.css',
  'assets/css/catalog.css',
  'assets/css/order-received.css',
  'assets/css/layouts/home-legacy.css',
  'assets/css/layouts/header-legacy.css',
  'assets/css/layouts/support-blog.css',
  'assets/css/layouts/blog-single-legacy.css',
  'assets/css/layouts/institutional-polish.css',
  'assets/css/components/product-card-legacy.css',
];

const forbiddenMainImports = [
  './components/product-card.css',
  './components/mini-cart.css',
  './layouts/header.css',
  './layouts/home.css',
  './layouts/blog-single.css',
];

const forbiddenStyleSelectors = [
  '.Gstore-home',
  '.Gstore-catalog',
  '.Gstore-single-product',
  '.Gstore-product-card',
  '.Gstore-support',
  '.woocommerce-order-received',
];

function absolute(relativePath) {
  return path.join(rootDir, ...relativePath.split('/'));
}

function stripCssComments(css) {
  return css.replace(/\/\*[\s\S]*?\*\//g, '');
}

function stripNotSelectors(css) {
  return css.replace(/:not\([^)]*\)/g, '');
}

async function pathExists(relativePath) {
  try {
    await fs.access(absolute(relativePath));
    return true;
  } catch {
    return false;
  }
}

const failures = [];

const stylePath = absolute('style.css');
const styleStat = await fs.stat(stylePath);
const styleCss = await fs.readFile(stylePath, 'utf8');
const styleScan = stripNotSelectors(stripCssComments(styleCss));

if (styleStat.size > maxStyleBytes) {
  failures.push(`style.css is ${(styleStat.size / 1024).toFixed(1)} KB; keep it under ${(maxStyleBytes / 1024).toFixed(0)} KB`);
}

if (/@import\s+/i.test(styleScan)) {
  failures.push('style.css must not import page/component CSS');
}

for (const selector of forbiddenStyleSelectors) {
  if (styleScan.includes(selector)) {
    failures.push(`style.css contains scoped selector ${selector}; move it to assets/css/`);
  }
}

const mainCss = await fs.readFile(absolute('assets/css/gstore-main.css'), 'utf8');
for (const importPath of forbiddenMainImports) {
  if (mainCss.includes(importPath)) {
    failures.push(`assets/css/gstore-main.css must not import ${importPath}; enqueue it conditionally`);
  }
}

for (const file of requiredScopedFiles) {
  if (!(await pathExists(file))) {
    failures.push(`missing scoped CSS file: ${file}`);
  }
}

if (failures.length > 0) {
  throw new Error(`CSS scope check failed:\n${failures.map((item) => `- ${item}`).join('\n')}`);
}

console.log(`CSS scope check OK: style.css ${(styleStat.size / 1024).toFixed(1)} KB and scoped CSS files present.`);

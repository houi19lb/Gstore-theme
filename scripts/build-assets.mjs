import CleanCSS from 'clean-css';
import { minify as minifyJs } from 'terser';
import { promises as fs } from 'node:fs';
import path from 'node:path';

const rootDir = process.cwd();
const cssMinifier = new CleanCSS({
  inline: false,
  level: 2,
  rebase: false,
});
const cssImportMinifier = new CleanCSS({
  inline: ['local'],
  level: 2,
  rebase: false,
});

const jsOptions = {
  compress: false,
  mangle: false,
  format: {
    comments: false,
  },
};

function toPosix(filePath) {
  return filePath.split(path.sep).join('/');
}

function minPathFor(filePath) {
  return filePath.replace(/\.(css|js)$/i, '.min.$1');
}

function shouldSkip(filePath) {
  const rel = toPosix(path.relative(rootDir, filePath));
  return (
    /\.min\.(css|js)$/i.test(rel) ||
    rel === 'temp_header_live.css' ||
    /\.asset\.php$/i.test(rel)
  );
}

async function pathExists(filePath) {
  try {
    await fs.access(filePath);
    return true;
  } catch {
    return false;
  }
}

async function collectFiles(dir, extensions, files = []) {
  if (!(await pathExists(dir))) {
    return files;
  }

  const entries = await fs.readdir(dir, { withFileTypes: true });
  for (const entry of entries) {
    const entryPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      await collectFiles(entryPath, extensions, files);
      continue;
    }

    if (entry.isFile() && extensions.includes(path.extname(entry.name).toLowerCase()) && !shouldSkip(entryPath)) {
      files.push(entryPath);
    }
  }

  return files;
}

async function collectThemeAssets() {
  const files = [];
  files.push(path.join(rootDir, 'style.css'));
  files.push(...await collectFiles(path.join(rootDir, 'assets'), ['.css', '.js']));
  return files.filter((file) => !shouldSkip(file));
}

async function minifyCss(filePath) {
  const rel = toPosix(path.relative(rootDir, filePath));
  const minifier = rel === 'assets/css/gstore-main.css' ? cssImportMinifier : cssMinifier;
  const input = rel === 'assets/css/gstore-main.css'
    ? [filePath]
    : await fs.readFile(filePath, 'utf8');
  const output = minifier.minify(input);

  if (output.errors.length > 0) {
    throw new Error(`CSS minification failed for ${rel}: ${output.errors.join('; ')}`);
  }

  if (output.warnings.length > 0) {
    console.warn(`CSS warnings for ${rel}: ${output.warnings.join('; ')}`);
  }

  await fs.writeFile(minPathFor(filePath), `${output.styles}\n`);
  return {
    rel,
    originalBytes: (await fs.stat(filePath)).size,
    minBytes: Buffer.byteLength(`${output.styles}\n`),
  };
}

async function minifyJavaScript(filePath) {
  const rel = toPosix(path.relative(rootDir, filePath));
  const code = await fs.readFile(filePath, 'utf8');
  const output = await minifyJs(code, jsOptions);

  if (!output.code) {
    throw new Error(`JS minification produced empty output for ${rel}`);
  }

  await fs.writeFile(minPathFor(filePath), `${output.code}\n`);
  return {
    rel,
    originalBytes: (await fs.stat(filePath)).size,
    minBytes: Buffer.byteLength(`${output.code}\n`),
  };
}

function formatKb(bytes) {
  return `${(bytes / 1024).toFixed(1)} KB`;
}

const assets = await collectThemeAssets();
const results = [];

for (const asset of assets) {
  const ext = path.extname(asset).toLowerCase();
  if (ext === '.css') {
    results.push(await minifyCss(asset));
  } else if (ext === '.js') {
    results.push(await minifyJavaScript(asset));
  }
}

const totals = results.reduce(
  (memo, item) => {
    memo.originalBytes += item.originalBytes;
    memo.minBytes += item.minBytes;
    return memo;
  },
  { originalBytes: 0, minBytes: 0 }
);

for (const result of results) {
  console.log(`${result.rel}: ${formatKb(result.originalBytes)} -> ${formatKb(result.minBytes)}`);
}

console.log(`\nGenerated ${results.length} minified asset(s).`);
console.log(`Total: ${formatKb(totals.originalBytes)} -> ${formatKb(totals.minBytes)}`);

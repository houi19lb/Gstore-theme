import { promises as fs } from 'node:fs';
import path from 'node:path';

const rootDir = process.cwd();
const urls = process.argv.slice(2).filter((arg) => /^https?:\/\//i.test(arg));

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

function resolveCssImport(baseRelative, importPath) {
  if (/^(?:https?:)?\/\//i.test(importPath) || importPath.startsWith('data:')) {
    return null;
  }

  const cleanImport = importPath.split('?')[0].split('#')[0];
  const resolved = path.posix.normalize(path.posix.join(path.posix.dirname(baseRelative), cleanImport));
  if (resolved.startsWith('../') || path.posix.isAbsolute(resolved)) {
    return null;
  }

  return resolved;
}

async function collectCssImports(relativePath, seen = new Set()) {
  const normalized = toPosix(relativePath);
  if (seen.has(normalized)) {
    return [];
  }

  seen.add(normalized);
  const filePath = path.join(rootDir, ...normalized.split('/'));
  if (!(await pathExists(filePath))) {
    return [];
  }

  const css = await fs.readFile(filePath, 'utf8');
  const imports = [];
  const regex = /@import\s+(?:url\(\s*)?["']?([^"')\s;]+)["']?\s*\)?[^;]*;/gi;
  let match;
  while ((match = regex.exec(css)) !== null) {
    const resolved = resolveCssImport(normalized, match[1]);
    if (!resolved) {
      continue;
    }
    imports.push(resolved);
    imports.push(...await collectCssImports(resolved, seen));
  }

  return imports;
}

async function checkGeneratedAssets() {
  const failures = [];
  const assets = await collectThemeAssets();

  for (const asset of assets) {
    const rel = toPosix(path.relative(rootDir, asset));
    const min = minPathFor(asset);
    if (!(await pathExists(min))) {
      failures.push(`${rel}: missing ${toPosix(path.relative(rootDir, min))}`);
      continue;
    }

    const sourceStat = await fs.stat(asset);
    const minStat = await fs.stat(min);
    if (minStat.mtimeMs + 1 < sourceStat.mtimeMs) {
      failures.push(`${rel}: minified file is older than source`);
      continue;
    }

    if (rel === 'assets/css/gstore-main.css') {
      const imports = await collectCssImports(rel);
      for (const importedRel of imports) {
        const importedPath = path.join(rootDir, ...importedRel.split('/'));
        if (!(await pathExists(importedPath))) {
          failures.push(`${rel}: import not found: ${importedRel}`);
          continue;
        }

        const importedStat = await fs.stat(importedPath);
        if (minStat.mtimeMs + 1 < importedStat.mtimeMs) {
          failures.push(`${rel}: minified file is older than imported ${importedRel}`);
        }
      }
    }
  }

  if (failures.length > 0) {
    throw new Error(`Minified asset check failed:\n${failures.map((item) => `- ${item}`).join('\n')}`);
  }

  console.log(`Generated asset check OK: ${assets.length} source asset(s).`);
}

function extractThemeAssets(html) {
  const assets = new Set();
  const regex = /(?:href|src)=["']([^"']*\/wp-content\/themes\/Gstore-theme\/[^"']+\.(?:css|js)(?:\?[^"']*)?)["']/gi;
  let match;
  while ((match = regex.exec(html)) !== null) {
    assets.add(match[1]);
  }
  return [...assets];
}

async function checkLiveUrls() {
  if (urls.length === 0) {
    console.log('Live URL check skipped. Pass URLs after -- to validate rendered HTML.');
    return;
  }

  const failures = [];
  for (const url of urls) {
    const response = await fetch(url, { redirect: 'follow' });
    if (!response.ok) {
      failures.push(`${url}: HTML returned ${response.status}`);
      continue;
    }

    const html = await response.text();
    for (const asset of extractThemeAssets(html)) {
      const cleanPath = new URL(asset, url).pathname;
      if (!/\.min\.(css|js)$/i.test(cleanPath)) {
        failures.push(`${url}: non-minified theme asset emitted: ${asset}`);
        continue;
      }

      const assetResponse = await fetch(new URL(asset, url), { method: 'HEAD', redirect: 'follow' });
      if (!assetResponse.ok) {
        failures.push(`${url}: asset ${asset} returned ${assetResponse.status}`);
      }
    }
  }

  if (failures.length > 0) {
    throw new Error(`Live asset check failed:\n${failures.map((item) => `- ${item}`).join('\n')}`);
  }

  console.log(`Live URL check OK: ${urls.length} URL(s).`);
}

await checkGeneratedAssets();
await checkLiveUrls();

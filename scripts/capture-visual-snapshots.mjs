#!/usr/bin/env node

import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const manifestPath = path.join(rootDir, 'docs', 'visual-snapshots.manifest.json');
const stylePath = path.join(rootDir, 'style.css');
const args = process.argv.slice(2);

function printUsage() {
	console.log(`Uso:
  node scripts/capture-visual-snapshots.mjs --base-url=http://armastore.local/

Opcoes:
  --base-url=<url>                  URL base do WordPress.
  --routes-file=<arquivo.json>      JSON com baseUrl e routes por loja.
  --site-id=<id>                    ID da loja no caminho final. Padrao: local.
  --only=<page-a,page-b>            Captura apenas paginas especificas.
  --states=<state-a,state-b>        Captura apenas estados especificos.
  --viewports=<vp-a,vp-b>           Captura apenas viewports especificos.
  --route=<page-id>=<path-ou-url>   Override de URL para paginas com placeholders.
  --include-optional                Inclui estados nao obrigatorios.
  --dry-run                         Lista o plano sem abrir navegador.
  --headed                          Abre navegador visivel.
  --channel=<chrome|msedge>         Usa um canal instalado no sistema.
  --wait-ms=<ms>                    Espera extra antes do print. Padrao: 1000.
  --timeout-ms=<ms>                 Timeout por acao/navegacao. Padrao: 30000.
  --no-full-page                    Captura so a area do viewport.
  --help                            Mostra esta ajuda.

Exemplo:
  npm run visual:capture -- --base-url=http://armastore.local/ --only=home,catalog
  npm run visual:capture -- --base-url=http://armastore.local/ --route=single-product=/produto/produto-exemplo/
  npm run visual:capture -- --routes-file=docs/visual-snapshots.routes.local.json
`);
}

if (args.includes('--help') || args.includes('-h')) {
	printUsage();
	process.exit(0);
}

function getArgValue(name, fallback = '') {
	const prefix = `${name}=`;
	const found = args.find((arg) => arg.startsWith(prefix));
	return found ? found.slice(prefix.length) : fallback;
}

function hasFlag(name) {
	return args.includes(name);
}

function parseCsvArg(name) {
	const raw = getArgValue(name);
	return raw ? new Set(raw.split(',').map((item) => item.trim()).filter(Boolean)) : null;
}

function parseRouteOverrides() {
	const routes = new Map();
	for (const arg of args) {
		if (!arg.startsWith('--route=')) {
			continue;
		}

		const raw = arg.slice('--route='.length);
		const separator = raw.indexOf('=');
		if (separator <= 0) {
			throw new Error(`Formato invalido para --route: ${arg}`);
		}

		const pageId = raw.slice(0, separator).trim();
		const pageUrl = raw.slice(separator + 1).trim();
		if (!pageId || !pageUrl) {
			throw new Error(`Formato invalido para --route: ${arg}`);
		}

		routes.set(pageId, pageUrl);
	}
	return routes;
}

function loadRoutesConfig(routesFileArg) {
	const defaultRoutesFile = path.join(rootDir, 'docs', 'visual-snapshots.routes.local.json');
	const routesFile = routesFileArg
		? path.resolve(rootDir, routesFileArg)
		: (existsSync(defaultRoutesFile) ? defaultRoutesFile : '');

	if (!routesFile) {
		return { baseUrl: '', routes: new Map() };
	}

	if (!existsSync(routesFile)) {
		throw new Error(`Arquivo de rotas nao encontrado: ${path.relative(rootDir, routesFile)}`);
	}

	const data = readJson(routesFile);
	const routes = new Map();
	for (const [pageId, route] of Object.entries(data.routes || {})) {
		if (typeof route === 'string' && route.trim()) {
			routes.set(pageId, route.trim());
		}
	}

	return {
		siteId: typeof data.siteId === 'string' ? data.siteId.trim() : '',
		baseUrl: typeof data.baseUrl === 'string' ? data.baseUrl.trim() : '',
		routes
	};
}

function readJson(filePath) {
	return JSON.parse(readFileSync(filePath, 'utf8').replace(/^\uFEFF/, ''));
}

function getThemeVersion() {
	if (!existsSync(stylePath)) {
		return '';
	}

	const css = readFileSync(stylePath, 'utf8');
	const match = css.match(/^\s*Version:\s*(.+?)\s*$/m);
	return match ? match[1].trim() : '';
}

function renderSnapshotPath(pattern, values) {
	return pattern.replace(/\{(snapshotRoot|siteId|pageId|stateId|viewportId)\}/g, (_, key) => values[key]);
}

function isPlaceholderUrl(url) {
	return /\{[^}]+\}/.test(url);
}

function resolveUrl(baseUrl, pageUrl) {
	if (/^https?:\/\//i.test(pageUrl)) {
		return pageUrl;
	}
	return new URL(pageUrl, baseUrl).toString();
}

function buildPlan(manifest, options) {
	const viewportIds = Object.keys(manifest.viewports || {});
	const knownViewports = new Set(viewportIds);
	const pages = Array.isArray(manifest.pages) ? manifest.pages : [];
	const unknownPages = options.onlyPages
		? Array.from(options.onlyPages).filter((id) => !pages.some((page) => page.id === id))
		: [];

	if (unknownPages.length > 0) {
		throw new Error(`Paginas desconhecidas em --only: ${unknownPages.join(', ')}`);
	}

	const plan = [];
	const skipped = [];

	for (const page of pages) {
		if (options.onlyPages && !options.onlyPages.has(page.id)) {
			continue;
		}

		const route = options.routeOverrides.get(page.id) || page.exampleUrl || page.url;
		if (!route || isPlaceholderUrl(route)) {
			skipped.push({
				pageId: page.id,
				reason: `URL precisa de --route=${page.id}=... (${route || 'sem URL'})`
			});
			continue;
		}

		const pageUrl = resolveUrl(options.baseUrl, route);
		for (const state of page.states || []) {
			if (!options.includeOptional && state.required !== true) {
				continue;
			}
			if (options.onlyStates && !options.onlyStates.has(state.id)) {
				continue;
			}

			const stateViewports = state.viewports || viewportIds;
			for (const viewportId of stateViewports) {
				if (!knownViewports.has(viewportId)) {
					throw new Error(`Viewport desconhecido em ${page.id}/${state.id}: ${viewportId}`);
				}
				if (options.onlyViewports && !options.onlyViewports.has(viewportId)) {
					continue;
				}

				const viewport = manifest.viewports[viewportId];
				const relativeSnapshotPath = renderSnapshotPath(manifest.paths.snapshotPattern, {
					snapshotRoot: manifest.paths.snapshotRoot,
					siteId: options.siteId,
					pageId: page.id,
					stateId: state.id,
					viewportId
				});

				plan.push({
					page,
					state,
					viewportId,
					viewport,
					url: pageUrl,
					outputPath: path.join(rootDir, relativeSnapshotPath),
					relativeOutputPath: relativeSnapshotPath
				});
			}
		}
	}

	return { plan, skipped };
}

async function loadPlaywright() {
	try {
		return await import('playwright');
	} catch (error) {
		console.error('Nao foi possivel carregar o Playwright.');
		console.error('Instale as dependencias e o navegador antes de capturar:');
		console.error('  npm install');
		console.error('  npx playwright install chromium');
		console.error(`Erro original: ${error.message}`);
		process.exit(1);
	}
}

async function waitForSettledPage(page, timeoutMs) {
	await page.waitForLoadState('domcontentloaded', { timeout: timeoutMs });
	await page.waitForLoadState('networkidle', { timeout: Math.min(timeoutMs, 8000) }).catch(() => {});
}

async function seedVisualSnapshotStorage(context, origin) {
	const expires = Date.now() + (30 * 24 * 60 * 60 * 1000);
	await context.addInitScript(({ expiresAt }) => {
		try {
			window.localStorage.setItem('gstore_age_verified', JSON.stringify({
				verified: true,
				expires: expiresAt
			}));
		} catch (error) {
			// Ignore localStorage failures; the click fallback will handle visible modals.
		}
	}, { expiresAt: expires });

	await context.addCookies([{
		name: 'gstore_age_verified',
		value: '1',
		url: origin,
		expires: Math.floor(expires / 1000),
		sameSite: 'Lax'
	}]).catch(() => {});
}

async function dismissKnownOverlays(page, options) {
	const ageConfirm = page.locator('#gstore-age-confirm').first();
	if (await ageConfirm.isVisible({ timeout: 1000 }).catch(() => false)) {
		await ageConfirm.click({ timeout: options.timeoutMs });
		await page.locator('#gstore-age-modal').first().waitFor({
			state: 'detached',
			timeout: Math.min(options.timeoutMs, 5000)
		}).catch(async () => {
			await page.locator('#gstore-age-modal[aria-hidden="true"]').first().waitFor({
				state: 'attached',
				timeout: Math.min(options.timeoutMs, 5000)
			}).catch(() => {});
		});
	}
}

async function runActions(page, actions, options) {
	for (const action of actions || []) {
		const timeout = Number(action.timeoutMs || options.timeoutMs);
		if (action.type === 'waitForSelector') {
			await page.locator(action.selector).first().waitFor({
				state: action.state || 'visible',
				timeout
			});
		} else if (action.type === 'click') {
			await page.locator(action.selector).first().click({ timeout });
		} else if (action.type === 'fill') {
			await page.locator(action.selector).first().fill(String(action.value || ''), { timeout });
		} else if (action.type === 'press') {
			await page.locator(action.selector).first().press(String(action.key || 'Enter'), { timeout });
		} else if (action.type === 'wait') {
			await page.waitForTimeout(Number(action.ms || options.waitMs));
		} else {
			throw new Error(`Acao de captura desconhecida: ${action.type}`);
		}
	}
}

function printPlan(plan, skipped) {
	for (const item of plan) {
		console.log(`${item.page.id}/${item.state.id}/${item.viewportId}`);
		console.log(`  URL: ${item.url}`);
		console.log(`  PNG: ${item.relativeOutputPath}`);
	}

	if (skipped.length > 0) {
		console.log('\nPaginas puladas:');
		for (const item of skipped) {
			console.log(`- ${item.pageId}: ${item.reason}`);
		}
	}
}

const routesConfig = loadRoutesConfig(getArgValue('--routes-file'));
const cliRouteOverrides = parseRouteOverrides();
const routeOverrides = new Map(routesConfig.routes);
for (const [pageId, route] of cliRouteOverrides.entries()) {
	routeOverrides.set(pageId, route);
}

const baseUrl = getArgValue('--base-url') || routesConfig.baseUrl;
const options = {
	baseUrl,
	siteId: getArgValue('--site-id') || routesConfig.siteId || 'local',
	onlyPages: parseCsvArg('--only'),
	onlyStates: parseCsvArg('--states'),
	onlyViewports: parseCsvArg('--viewports'),
	routeOverrides,
	includeOptional: hasFlag('--include-optional'),
	dryRun: hasFlag('--dry-run'),
	headed: hasFlag('--headed'),
	channel: getArgValue('--channel'),
	waitMs: Number(getArgValue('--wait-ms', '1000')),
	timeoutMs: Number(getArgValue('--timeout-ms', '30000')),
	fullPage: !hasFlag('--no-full-page')
};

if (!options.baseUrl) {
	console.error('Informe --base-url=<url>.');
	printUsage();
	process.exit(1);
}

const manifest = readJson(manifestPath);
const themeVersion = getThemeVersion();

if (!themeVersion) {
	console.error('Nao foi possivel localizar Version: em style.css.');
	process.exit(1);
}

if (manifest.themeVersion !== themeVersion) {
	console.error(`Versao do manifesto (${manifest.themeVersion}) difere de style.css (${themeVersion}).`);
	process.exit(1);
}

const { plan, skipped } = buildPlan(manifest, options);

if (plan.length === 0) {
	console.log('Nenhum snapshot para capturar com os filtros informados.');
	printPlan(plan, skipped);
	process.exit(0);
}

if (options.dryRun) {
	printPlan(plan, skipped);
	process.exit(0);
}

const { chromium } = await loadPlaywright();
const browser = await chromium.launch({
	headless: !options.headed,
	...(options.channel ? { channel: options.channel } : {})
});

let failures = 0;

try {
	for (const item of plan) {
		const context = await browser.newContext({
			viewport: {
				width: item.viewport.width,
				height: item.viewport.height
			},
			deviceScaleFactor: 1
		});
		const page = await context.newPage();

		try {
			console.log(`Capturando ${item.page.id}/${item.state.id}/${item.viewportId}`);
			await seedVisualSnapshotStorage(context, item.url);
			await page.goto(item.url, { waitUntil: 'domcontentloaded', timeout: options.timeoutMs });
			await waitForSettledPage(page, options.timeoutMs);
			await dismissKnownOverlays(page, options);
			await runActions(page, item.state.capture?.actions || [], options);
			await dismissKnownOverlays(page, options);
			await page.waitForTimeout(options.waitMs);

			mkdirSync(path.dirname(item.outputPath), { recursive: true });
			await page.screenshot({
				path: item.outputPath,
				fullPage: options.fullPage
			});
			console.log(`  OK ${item.relativeOutputPath}`);
		} catch (error) {
			failures += 1;
			console.error(`  FALHOU ${item.relativeOutputPath}: ${error.message}`);
		} finally {
			await context.close();
		}
	}
} finally {
	await browser.close();
}

if (skipped.length > 0) {
	console.log('\nPaginas puladas:');
	for (const item of skipped) {
		console.log(`- ${item.pageId}: ${item.reason}`);
	}
}

if (failures > 0) {
	console.error(`Captura concluida com ${failures} falha(s).`);
	process.exit(1);
}

console.log(`Captura concluida: ${plan.length} snapshot(s).`);

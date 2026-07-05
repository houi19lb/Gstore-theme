#!/usr/bin/env node

import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const manifestPath = path.join(rootDir, 'docs', 'visual-snapshots.manifest.json');
const stylePath = path.join(rootDir, 'style.css');
const args = process.argv.slice(2);
const allowMissing = args.includes('--allow-missing');
const allowBlocked = args.includes('--allow-blocked');
const onlyArg = args.find((arg) => arg.startsWith('--only='));
const onlyPages = onlyArg
	? new Set(onlyArg.replace('--only=', '').split(',').map((id) => id.trim()).filter(Boolean))
	: null;
const siteArg = args.find((arg) => arg.startsWith('--site='));
const onlySites = siteArg
	? new Set(siteArg.replace('--site=', '').split(',').map((id) => id.trim()).filter(Boolean))
	: null;
const blockersFileArg = args.find((arg) => arg.startsWith('--blockers-file='));

function fail(message) {
	console.error(message);
	process.exitCode = 1;
}

function readJson(filePath) {
	try {
		return JSON.parse(readFileSync(filePath, 'utf8').replace(/^\uFEFF/, ''));
	} catch (error) {
		fail(`Erro ao ler JSON: ${path.relative(rootDir, filePath)} (${error.message})`);
		return null;
	}
}

function readPngSize(filePath) {
	const buffer = readFileSync(filePath);
	if (
		buffer.length < 24 ||
		buffer.toString('ascii', 1, 4) !== 'PNG' ||
		buffer.toString('ascii', 12, 16) !== 'IHDR'
	) {
		return null;
	}

	return {
		width: buffer.readUInt32BE(16),
		height: buffer.readUInt32BE(20)
	};
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

function getBlockedStates(blockersPath) {
	if (!existsSync(blockersPath)) {
		return {
			path: blockersPath,
			states: new Set(),
			missing: true
		};
	}

	const blockers = readJson(blockersPath);
	const states = new Set();
	for (const blocker of blockers?.blockers || []) {
		for (const state of blocker.states || []) {
			if (typeof state === 'string' && state.includes('/')) {
				states.add(state);
			}
		}

		if (typeof blocker.scope === 'string' && blocker.scope.includes('/')) {
			states.add(blocker.scope);
		}
	}

	return {
		path: blockersPath,
		states,
		missing: false
	};
}

const manifest = readJson(manifestPath);

if (!manifest) {
	process.exit(1);
}

const viewportIds = Object.keys(manifest.viewports || {});
const knownViewports = new Set(viewportIds);
const pages = Array.isArray(manifest.pages) ? manifest.pages : [];
const selectedPages = onlyPages ? pages.filter((page) => onlyPages.has(page.id)) : pages;
const referenceSites = Array.isArray(manifest.referenceSites) && manifest.referenceSites.length > 0
	? manifest.referenceSites
	: [{ id: 'local', label: 'Local', required: true }];
const selectedSites = onlySites
	? referenceSites.filter((site) => onlySites.has(site.id))
	: referenceSites.filter((site) => site.required !== false);
const themeVersion = getThemeVersion();
const blockersPath = blockersFileArg
	? path.resolve(rootDir, blockersFileArg.replace('--blockers-file=', ''))
	: path.join(rootDir, manifest.paths?.snapshotRoot || 'docs/visual-snapshots/latest', '_capture-blockers.json');
const blockedStates = getBlockedStates(blockersPath);

if (!themeVersion) {
	fail('Nao foi possivel localizar Version: em style.css.');
} else if (manifest.themeVersion !== themeVersion) {
	fail(`Versao do manifesto (${manifest.themeVersion}) difere de style.css (${themeVersion}).`);
}

let requiredCount = 0;
let missingCount = 0;
let blockedMissingCount = 0;
let sourceMissingCount = 0;
let dimensionMismatchCount = 0;
let viewportMismatchCount = 0;
const missingSnapshots = [];
const blockedMissingSnapshots = [];
const missingSources = [];
const invalidViewportRefs = [];
const dimensionMismatches = [];
const viewportMismatches = [];
const unknownSiteRefs = onlySites
	? Array.from(onlySites).filter((id) => !referenceSites.some((site) => site.id === id))
	: [];

if (unknownSiteRefs.length > 0) {
	fail(`Lojas desconhecidas em --site: ${unknownSiteRefs.join(', ')}`);
}

for (const page of selectedPages) {
	for (const source of page.sources || []) {
		const sourcePath = path.join(rootDir, source);
		if (!existsSync(sourcePath)) {
			sourceMissingCount += 1;
			missingSources.push(`${page.id}: ${source}`);
		}
	}

	for (const site of selectedSites) {
		for (const state of page.states || []) {
			if (state.required !== true) {
				continue;
			}

			const stateViewports = state.viewports || viewportIds;
			for (const viewportId of stateViewports) {
				if (!knownViewports.has(viewportId)) {
					invalidViewportRefs.push(`${page.id}/${state.id}: ${viewportId}`);
					continue;
				}

				requiredCount += 1;
				const relativeSnapshotPath = renderSnapshotPath(manifest.paths.snapshotPattern, {
					snapshotRoot: manifest.paths.snapshotRoot,
					siteId: site.id,
					pageId: page.id,
					stateId: state.id,
					viewportId
				});
				const absoluteSnapshotPath = path.join(rootDir, relativeSnapshotPath);

				if (!existsSync(absoluteSnapshotPath)) {
					missingCount += 1;
					if (blockedStates.states.has(`${page.id}/${state.id}`)) {
						blockedMissingCount += 1;
						blockedMissingSnapshots.push(relativeSnapshotPath);
					} else {
						missingSnapshots.push(relativeSnapshotPath);
					}
					continue;
				}

				const expectedViewport = manifest.viewports[viewportId];
				const pngSize = readPngSize(absoluteSnapshotPath);
				if (!pngSize || pngSize.width !== expectedViewport.width || pngSize.height !== expectedViewport.height) {
					dimensionMismatchCount += 1;
					dimensionMismatches.push(`${relativeSnapshotPath}: esperado ${expectedViewport.width}x${expectedViewport.height}, encontrado ${pngSize ? `${pngSize.width}x${pngSize.height}` : 'arquivo PNG invalido'}`);
				}

				if (manifest.paths.captureMetaPattern) {
					const relativeMetaPath = renderSnapshotPath(manifest.paths.captureMetaPattern, {
						snapshotRoot: manifest.paths.snapshotRoot,
						siteId: site.id,
						pageId: page.id,
						stateId: state.id,
						viewportId
					});
					const absoluteMetaPath = path.join(rootDir, relativeMetaPath);
					if (existsSync(absoluteMetaPath)) {
						const meta = readJson(absoluteMetaPath);
						const capture = meta?.captures?.find((item) => item.viewportId === viewportId);
						const measuredWidth = Number(capture?.measured?.innerWidth);
						const expectedWidth = Number(expectedViewport.width);
						if (Number.isFinite(measuredWidth) && Math.abs(measuredWidth - expectedWidth) > 2) {
							viewportMismatchCount += 1;
							viewportMismatches.push(`${relativeSnapshotPath}: viewport medido ${measuredWidth}px, alvo ${expectedWidth}px`);
						}
					}
				}
			}
		}
	}
}

if (invalidViewportRefs.length > 0) {
	fail(`Viewports desconhecidos no manifesto:\n${invalidViewportRefs.map((item) => `- ${item}`).join('\n')}`);
}

if (sourceMissingCount > 0) {
	fail(`Fontes referenciadas nao encontradas:\n${missingSources.map((item) => `- ${item}`).join('\n')}`);
}

const unresolvedMissingCount = missingSnapshots.length;

if (blockedStates.missing && allowBlocked) {
	fail(`Arquivo de bloqueios nao encontrado: ${path.relative(rootDir, blockedStates.path)}`);
}

if (unresolvedMissingCount > 0 && !allowMissing) {
	fail(`Snapshots obrigatorios ausentes sem bloqueio (${unresolvedMissingCount}/${requiredCount}):\n${missingSnapshots.map((item) => `- ${item}`).join('\n')}`);
}

if (blockedMissingCount > 0 && !allowMissing && !allowBlocked) {
	fail(`Snapshots obrigatorios ausentes mas bloqueados (${blockedMissingCount}/${requiredCount}). Use --allow-blocked para aceitar somente bloqueios documentados.\n${blockedMissingSnapshots.map((item) => `- ${item}`).join('\n')}`);
}

if (dimensionMismatchCount > 0) {
	fail(`Snapshots com dimensoes divergentes (${dimensionMismatchCount}):\n${dimensionMismatches.map((item) => `- ${item}`).join('\n')}`);
}

if (viewportMismatchCount > 0 && !allowMissing) {
	fail(`Snapshots com viewport de captura divergente (${viewportMismatchCount}):\n${viewportMismatches.map((item) => `- ${item}`).join('\n')}`);
}

const checkedPages = selectedPages.length;
const pageSuffix = onlyPages ? ` (${Array.from(onlyPages).join(', ')})` : '';
const checkedSites = selectedSites.length;
const siteSuffix = onlySites ? ` (${Array.from(onlySites).join(', ')})` : '';

console.log(`Lojas verificadas${siteSuffix}: ${checkedSites}`);
console.log(`Paginas verificadas${pageSuffix}: ${checkedPages}`);
console.log(`Snapshots obrigatorios: ${requiredCount}`);
console.log(`Snapshots capturados: ${requiredCount - missingCount}`);
console.log(`Snapshots ausentes: ${missingCount}`);
console.log(`Ausencias bloqueadas documentadas: ${blockedMissingCount}`);
console.log(`Ausencias sem bloqueio: ${unresolvedMissingCount}`);
console.log(`Dimensoes divergentes: ${dimensionMismatchCount}`);
console.log(`Viewports medidos divergentes: ${viewportMismatchCount}`);
console.log(`Fontes ausentes: ${sourceMissingCount}`);

if (missingCount > 0 && allowMissing) {
	console.log('Ausencias permitidas por --allow-missing.');
}

if (blockedMissingCount > 0 && allowBlocked && unresolvedMissingCount === 0) {
	console.log(`Ausencias bloqueadas permitidas por --allow-blocked (${path.relative(rootDir, blockedStates.path)}).`);
}

if (viewportMismatchCount > 0 && allowMissing) {
	console.log('Divergencias de viewport permitidas por --allow-missing.');
	for (const item of viewportMismatches) {
		console.log(`- ${item}`);
	}
}

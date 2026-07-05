#!/usr/bin/env node

import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const manifestPath = path.join(rootDir, 'docs', 'visual-snapshots.manifest.json');
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8').replace(/^\uFEFF/, ''));

const fixtureStates = [
	{
		pageId: 'cart',
		stateId: 'empty',
		title: 'Carrinho vazio',
		kicker: 'Carrinho',
		heading: 'Seu carrinho esta vazio.',
		body: 'Quando a sessao nao possui itens, o tema preserva header, navegacao, respiro e CTA de retorno ao catalogo.',
		primary: 'Continuar comprando',
		layout: 'empty'
	},
	{
		pageId: 'favorites',
		stateId: 'empty',
		title: 'Favoritos vazios',
		kicker: 'Meus favoritos',
		heading: 'Sua lista de favoritos esta vazia',
		body: 'Explore o catalogo e marque seus produtos preferidos para ver tudo reunido aqui.',
		primary: 'Ver catalogo',
		layout: 'favorites'
	},
	{
		pageId: 'my-account',
		stateId: 'logged-out',
		title: 'Minha conta deslogada',
		kicker: 'Minha conta',
		heading: 'Acesse sua conta',
		body: 'Estado fixture do formulario de login/cadastro, usado sem sair da sessao real do Chrome.',
		primary: 'Acessar',
		layout: 'login'
	}
];

const siteStyles = {
	armastore: {
		name: 'ARMASTORE',
		accent: '#c0ae4c',
		top: '#0a0a0a',
		nav: ['SHOT FEST DAY', 'PROMOCOES', 'CATALOGO', 'CLUBE DE TIRO', 'PRO TRAINING', 'PROGRAMAS']
	},
	cacarmas: {
		name: 'CAC ARMAS',
		accent: '#c0ae4c',
		top: '#0a0a0a',
		nav: ['SHOT FEST DAY', 'MINHA PRIMEIRA ARMA', 'CATALOGO', 'OFERTAS', 'BLOG', 'MINHA CONTA']
	}
};

function css(viewport, site) {
	const isMobile = viewport.width <= 480;
	return `
		:root {
			--accent: ${site.accent};
			--ink: #171717;
			--muted: #666a70;
			--line: #dedede;
			--panel: #ffffff;
			--bg: #f5f5f1;
		}
		* { box-sizing: border-box; }
		body {
			margin: 0;
			width: ${viewport.width}px;
			min-height: ${viewport.height}px;
			background: var(--bg);
			color: var(--ink);
			font-family: Arial, Helvetica, sans-serif;
			letter-spacing: 0;
		}
		.topbar {
			height: ${isMobile ? 38 : 34}px;
			background: #050505;
			color: #f7f7f7;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 18px;
			font-size: ${isMobile ? 11 : 12}px;
		}
		.header {
			height: ${isMobile ? 68 : 70}px;
			background: #111;
			display: grid;
			grid-template-columns: ${isMobile ? '48px 1fr 48px' : '220px 1fr 168px'};
			align-items: center;
			padding: 0 ${isMobile ? 16 : 64}px;
			gap: ${isMobile ? 8 : 28}px;
			color: #fff;
		}
		.logo {
			color: var(--accent);
			font-size: ${isMobile ? 20 : 24}px;
			font-weight: 800;
			white-space: nowrap;
		}
		.search {
			height: 44px;
			border: 1px solid #363636;
			background: #222;
			color: #aaa;
			display: ${isMobile ? 'none' : 'flex'};
			align-items: center;
			justify-content: space-between;
			padding-left: 18px;
		}
		.search::after {
			content: 'GO';
			width: 52px;
			align-self: stretch;
			background: var(--accent);
			color: #111;
			display: grid;
			place-items: center;
			font-size: 22px;
		}
		.iconbtn {
			border: 1px solid #333;
			height: 40px;
			display: grid;
			place-items: center;
			color: #fff;
			background: #141414;
			font-size: 13px;
		}
		.menu {
			display: ${isMobile ? 'grid' : 'none'};
			border: 0;
			background: transparent;
			color: #fff;
			font-size: 12px;
			font-weight: 700;
		}
		.nav {
			height: 42px;
			background: #191919;
			display: ${isMobile ? 'none' : 'flex'};
			align-items: center;
			justify-content: center;
			gap: 34px;
			color: #fff;
			font-size: 12px;
			font-weight: 800;
			text-transform: uppercase;
		}
		main {
			max-width: ${isMobile ? '100%' : '1120px'};
			margin: 0 auto;
			padding: ${isMobile ? '24px 18px 48px' : '72px 32px 96px'};
		}
		.breadcrumb {
			color: var(--muted);
			font-size: 13px;
			margin-bottom: 20px;
		}
		h1 {
			font-size: ${isMobile ? 28 : 42}px;
			line-height: 1.05;
			margin: 0 0 12px;
		}
		.lead {
			color: var(--muted);
			font-size: ${isMobile ? 15 : 17}px;
			line-height: 1.55;
			max-width: 680px;
			margin: 0;
		}
		.empty-panel,
		.login-shell {
			background: var(--panel);
			border: 1px solid var(--line);
			padding: ${isMobile ? '28px 22px' : '44px'};
			margin-top: ${isMobile ? 24 : 36}px;
			display: grid;
			gap: 18px;
			min-height: ${isMobile ? 310 : 360}px;
			align-content: center;
		}
		.empty-icon {
			width: 72px;
			height: 72px;
			border: 1px solid #ddd;
			background: #f7f6ec;
			display: grid;
			place-items: center;
			color: var(--accent);
			font-size: 32px;
			font-weight: 800;
		}
		.button {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: max-content;
			min-height: 46px;
			padding: 0 22px;
			background: var(--accent);
			color: #111;
			font-size: 13px;
			font-weight: 800;
			text-transform: uppercase;
			text-decoration: none;
			border: 0;
		}
		.catalog-layout {
			display: grid;
			grid-template-columns: ${isMobile ? '1fr' : '260px 1fr'};
			gap: 28px;
			margin-top: 32px;
		}
		.sidebar {
			background: var(--panel);
			border: 1px solid var(--line);
			padding: 22px;
			display: grid;
			gap: 14px;
			min-height: ${isMobile ? 148 : 320}px;
		}
		.sidebar-title {
			font-size: 16px;
			font-weight: 800;
			text-transform: uppercase;
		}
		.input {
			border: 1px solid #d4d4d4;
			height: 44px;
			padding: 0 12px;
			color: #888;
			background: #fff;
		}
		.login-shell {
			grid-template-columns: ${isMobile ? '1fr' : '1fr 1fr'};
			align-items: stretch;
			align-content: stretch;
		}
		.form-card {
			border: 1px solid var(--line);
			padding: ${isMobile ? '20px' : '26px'};
			display: grid;
			gap: 14px;
			background: #fff;
		}
		.form-card h2 {
			margin: 0 0 6px;
			font-size: 22px;
		}
		.field {
			display: grid;
			gap: 7px;
			color: #3a3a3a;
			font-size: 13px;
			font-weight: 700;
		}
		.fake-input {
			height: 44px;
			border: 1px solid #d4d4d4;
			background: #fafafa;
		}
		.fixture-note {
			margin-top: 18px;
			padding: 12px 14px;
			border: 1px dashed #b8b8b8;
			color: #616161;
			font-size: 12px;
			background: rgba(255,255,255,.64);
		}
	`;
}

function renderFixtureHtml({ site, viewport, fixture }) {
	const nav = site.nav.map((item) => `<span>${item}</span>`).join('');
	const shell = fixture.layout === 'favorites'
		? `
			<section class="catalog-layout">
				<aside class="sidebar">
					<div class="sidebar-title">Filtrar favoritos</div>
					<div class="input">Buscar nos favoritos...</div>
					<div class="sidebar-title">Categorias</div>
					<div class="input">Todas as categorias</div>
				</aside>
				<div class="empty-panel">
					<div class="empty-icon">*</div>
					<h1>${fixture.heading}</h1>
					<p class="lead">${fixture.body}</p>
					<a class="button" href="#">${fixture.primary}</a>
				</div>
			</section>`
		: fixture.layout === 'login'
			? `
				<section class="login-shell">
					<div class="form-card">
						<h2>Login</h2>
						<label class="field">Username or email address<div class="fake-input"></div></label>
						<label class="field">Password<div class="fake-input"></div></label>
						<a class="button" href="#">${fixture.primary}</a>
						<p class="lead">Lost your password?</p>
					</div>
					<div class="form-card">
						<h2>Register</h2>
						<label class="field">Email address<div class="fake-input"></div></label>
						<a class="button" href="#">Register</a>
						<p class="lead">Seja um revendedor</p>
					</div>
				</section>`
			: `
				<section class="empty-panel">
					<div class="empty-icon">0</div>
					<h1>${fixture.heading}</h1>
					<p class="lead">${fixture.body}</p>
					<a class="button" href="#">${fixture.primary}</a>
				</section>`;

	return `<!doctype html>
<html lang="pt-BR">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>${fixture.title} - ${site.name}</title>
	<style>${css(viewport, site)}</style>
</head>
<body>
	<div class="topbar">Atendimento | WhatsApp | Instagram</div>
	<header class="header">
		<button class="menu">MENU</button>
		<div class="logo">${site.name}</div>
		<div class="search">Digite sua busca...</div>
		<div class="iconbtn">Carrinho</div>
	</header>
	<nav class="nav">${nav}</nav>
	<main>
		<div class="breadcrumb">Inicio / ${fixture.kicker}</div>
		<h1>${fixture.kicker}</h1>
		<p class="lead">${fixture.body}</p>
		${shell}
		<div class="fixture-note">Fixture visual gerada para evitar logout, limpeza de carrinho ou alteracao de favoritos na sessao real do Chrome.</div>
	</main>
</body>
</html>`;
}

async function loadPlaywright() {
	try {
		return await import('playwright');
	} catch (error) {
		console.error('Nao foi possivel carregar Playwright para gerar fixtures.');
		console.error(error.message);
		process.exit(1);
	}
}

const { chromium } = await loadPlaywright();
const browser = await chromium.launch({ headless: true });

try {
	for (const siteRef of manifest.referenceSites || []) {
		const site = siteStyles[siteRef.id];
		if (!site) {
			continue;
		}

		for (const fixture of fixtureStates) {
			const captures = [];

			for (const [viewportId, viewport] of Object.entries(manifest.viewports || {})) {
				const context = await browser.newContext({
					viewport: { width: viewport.width, height: viewport.height },
					deviceScaleFactor: 1
				});
				const page = await context.newPage();
				const html = renderFixtureHtml({ site, viewport, fixture });
				await page.setContent(html, { waitUntil: 'domcontentloaded' });
				const outputDir = path.join(rootDir, manifest.paths.snapshotRoot, siteRef.id, fixture.pageId, fixture.stateId);
				const outputPath = path.join(outputDir, `${viewportId}.png`);
				mkdirSync(outputDir, { recursive: true });
				await page.screenshot({ path: outputPath, fullPage: false });
				const measured = await page.evaluate(() => ({
					bodyClass: 'gstore-visual-fixture',
					dpr: window.devicePixelRatio,
					innerHeight: window.innerHeight,
					innerWidth: window.innerWidth,
					outerHeight: window.outerHeight,
					outerWidth: window.outerWidth,
					title: document.title,
					url: 'fixture://gstore-theme/session-negative-state'
				}));
				captures.push({
					viewportId,
					target: {
						width: viewport.width,
						height: viewport.height
					},
					measured,
					method: 'generated-session-negative-fixture',
					fixture: true,
					fixtureReason: 'Estado real exigiria logout, limpar carrinho ou limpar favoritos da sessao autenticada.',
					output: {
						file: `${viewportId}.png`,
						width: viewport.width,
						height: viewport.height,
						normalizedPixels: true
					}
				});
				await context.close();
				console.log(`Fixture OK ${siteRef.id}/${fixture.pageId}/${fixture.stateId}/${viewportId}`);
			}

			const metaPath = path.join(rootDir, manifest.paths.snapshotRoot, siteRef.id, fixture.pageId, fixture.stateId, 'capture-meta.json');
			writeFileSync(metaPath, JSON.stringify({
				siteId: siteRef.id,
				pageId: fixture.pageId,
				stateId: fixture.stateId,
				capturedAt: new Date().toISOString(),
				fixture: true,
				fixtureKind: 'session-negative-state',
				captures
			}, null, 2));
		}
	}
} finally {
	await browser.close();
}

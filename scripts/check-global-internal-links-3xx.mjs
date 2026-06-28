#!/usr/bin/env node

const DEFAULT_PAGES = [
	'https://armastore.com.br/',
	'https://armastore.com.br/catalogo/',
	'https://armastore.com.br/carrinho/',
	'https://armastore.com.br/categoria-produto/programas/',
	'https://armastore.com.br/categoria-produto/pro-training/',
	'https://armastore.com.br/categoria-produto/clube-de-tiro/',
];

const ALIAS_TARGETS = new Map([
	['/minha-conta', '/minha-conta/'],
	['/atendimento', '/atendimento/'],
	['/loja', '/catalogo/'],
	['/loja/', '/catalogo/'],
	['/programas', '/categoria-produto/programas/'],
	['/programas/', '/categoria-produto/programas/'],
	['/pro-training', '/categoria-produto/pro-training/'],
	['/pro-training/', '/categoria-produto/pro-training/'],
	['/clube-de-tiro', '/categoria-produto/clube-de-tiro/'],
	['/clube-de-tiro/', '/categoria-produto/clube-de-tiro/'],
]);

const CANONICAL_GLOBAL_PATHS = new Set([
	'/minha-conta/',
	'/atendimento/',
	'/catalogo/',
	'/carrinho/',
	'/categoria-produto/programas/',
	'/categoria-produto/pro-training/',
	'/categoria-produto/clube-de-tiro/',
]);

function normalizePath(pathname) {
	const path = `/${String(pathname || '').replace(/^\/+|\/+$/g, '')}`;
	return path === '/' ? '/' : `${path}/`.replace(/\/{2,}/g, '/');
}

function getPagesFromArgs() {
	const args = process.argv.slice(2).filter((arg) => !arg.startsWith('--'));
	return args.length ? args : DEFAULT_PAGES;
}

function extractHrefs(html) {
	const hrefs = [];
	const pattern = /\bhref\s*=\s*(["'])(.*?)\1/gi;
	let match;

	while ((match = pattern.exec(html))) {
		hrefs.push(match[2]);
	}

	return hrefs;
}

function toInternalUrl(rawHref, pageUrl) {
	if (!rawHref || rawHref.includes('{{') || /^(#|mailto:|tel:|sms:|javascript:|data:)/i.test(rawHref)) {
		return null;
	}

	try {
		const url = new URL(rawHref, pageUrl);
		const pageHost = new URL(pageUrl).hostname.replace(/^www\./i, '').toLowerCase();
		const linkHost = url.hostname.replace(/^www\./i, '').toLowerCase();

		return pageHost === linkHost ? url : null;
	} catch {
		return null;
	}
}

async function fetchHtml(url) {
	const response = await fetch(url, { redirect: 'follow' });
	if (!response.ok) {
		throw new Error(`${url} returned ${response.status}`);
	}

	return response.text();
}

async function firstHopStatus(url) {
	let response = await fetch(url, { method: 'HEAD', redirect: 'manual' });

	if (response.status === 405 || response.status === 403) {
		response = await fetch(url, {
			method: 'GET',
			redirect: 'manual',
			headers: { Range: 'bytes=0-0' },
		});
	}

	return {
		status: response.status,
		location: response.headers.get('location') || '',
	};
}

async function main() {
	const pages = getPagesFromArgs();
	const aliasIssues = [];
	const statusIssues = [];
	const urlsToCheck = new Map();

	for (const pageUrl of pages) {
		const html = await fetchHtml(pageUrl);
		const hrefs = extractHrefs(html);

		for (const href of hrefs) {
			const url = toInternalUrl(href, pageUrl);
			if (!url) {
				continue;
			}

			const normalizedPath = normalizePath(url.pathname);
			const aliasPath = ALIAS_TARGETS.has(url.pathname) ? url.pathname : normalizedPath;

			if (ALIAS_TARGETS.has(aliasPath)) {
				aliasIssues.push({
					page: pageUrl,
					href,
					expected: ALIAS_TARGETS.get(aliasPath),
				});
			}

			if (ALIAS_TARGETS.has(aliasPath) || CANONICAL_GLOBAL_PATHS.has(normalizedPath)) {
				urlsToCheck.set(url.href, { page: pageUrl, href });
			}
		}
	}

	for (const [url, source] of urlsToCheck) {
		const result = await firstHopStatus(url);
		if (result.status >= 300 && result.status < 400) {
			statusIssues.push({ ...source, url, status: result.status, location: result.location });
		}
		if (result.status >= 400) {
			statusIssues.push({ ...source, url, status: result.status, location: result.location });
		}
	}

	if (!aliasIssues.length && !statusIssues.length) {
		console.log(`OK: ${pages.length} page(s), ${urlsToCheck.size} global internal link(s) checked.`);
		return;
	}

	if (aliasIssues.length) {
		console.error('\nKnown 3xx aliases found in rendered HTML:');
		for (const issue of aliasIssues) {
			console.error(`- ${issue.page} emits ${issue.href}; use ${issue.expected}`);
		}
	}

	if (statusIssues.length) {
		console.error('\nGlobal internal links with non-200 first hop:');
		for (const issue of statusIssues) {
			const location = issue.location ? ` -> ${issue.location}` : '';
			console.error(`- ${issue.page} emits ${issue.url}: ${issue.status}${location}`);
		}
	}

	process.exitCode = 1;
}

main().catch((error) => {
	console.error(error && error.stack ? error.stack : error);
	process.exitCode = 1;
});

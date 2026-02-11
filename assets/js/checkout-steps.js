/**
 * Checkout em 3 Etapas - Gstore
 * 
 * Fluxo simplificado:
 * - Etapa 1: Escolha do método de pagamento (Cartão ou PIX)
 * - Etapa 2: Dados básicos (email e telefone)
 * - Etapa 3: Finalizar pedido
 * 
 * O mesmo fluxo para Cartão e PIX - simplificado e consistente.
 */

(function($) {
	'use strict';

	// Configuração única de etapas - sempre 3 etapas
	const STEPS = [
		{
			id: 'payment-method',
			name: 'Pagamento',
			icon: 'fa-credit-card',
			title: 'Escolha o Método de Pagamento',
			description: 'Selecione como deseja pagar seu pedido.',
			fields: []
		},
		{
			id: 'contact',
			name: 'Dados Básicos',
			icon: 'fa-envelope',
			title: 'Seus Dados',
			description: 'Informe seu email, telefone e CEP para calcular o frete.',
			fields: [
				'billing_email',
				'billing_phone',
				'billing_postcode'
			]
		},
		{
			id: 'payment',
			name: 'Finalizar',
			icon: 'fa-check',
			title: 'Finalizar Pedido',
			description: 'Clique no botão abaixo para finalizar seu pedido.',
			fields: []
		}
	];

	let currentStep = 0;
	let $checkoutForm = null;
	let $stepsContainer = null;
	let initialized = false;
	let isUpdatingPayment = false; // Flag para evitar loops ao atualizar pagamento
	let calculatedShipping = null; // Armazena o frete calculado
	let isCalculatingShipping = false; // Flag para evitar múltiplos cálculos simultâneos
	let lastCalculatedShippingCep = ''; // CEP (somente dígitos) do último frete calculado com sucesso
	let lastRequestedShippingCep = ''; // CEP (somente dígitos) da última requisição de frete disparada
	let lastCalculatedDestination = null; // Destino (cidade/UF) do último frete calculado
	const CART_MODE_STORAGE_KEY = 'gstore_cart_shipping_mode';
	const CART_RATES_STORAGE_KEY = 'gstore_cart_shipping_rates';
	let checkoutSelectedShippingMode = 'land';
	let checkoutShippingRates = [];
	let checkoutShippingStatus = 'idle';
	let checkoutShippingError = '';
	let checkoutShippingRatesByItem = {};
	let checkoutSelectedShippingByItem = {};
	let lastSummaryTotals = null;
	let lastCartSummaryData = null;
	let lastNonEmptyCartSummaryData = null; // Mantém o último resumo com itens para não zerar o topo quando o Woo esvazia o carrinho
	let installmentQuotes = null;
	let isLoadingInstallmentQuotes = false;
	let lastInstallmentQuotesSignature = '';
	let lastBluOrderPaymentUrl = null; // URL do pagamento Blu do último pedido criado (para exibir aviso quando modal fecha)

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function nl2brSafe(value) {
		return escapeHtml(value).replace(/\r\n|\r|\n/g, '<br>');
	}

	function decodeHtmlEntities(value) {
		const input = String(value || '');
		if (!/&(?:lt|gt|amp|quot|#0*39);/i.test(input)) {
			return input;
		}
		const textarea = document.createElement('textarea');
		textarea.innerHTML = input;
		return textarea.value;
	}

	function looksLikeContractDocument(value) {
		const raw = String(value || '');
		return /<!doctype\s+html/i.test(raw) ||
			/<\s*html[\s>]/i.test(raw) ||
			/<\s*body[\s>]/i.test(raw) ||
			/<\s*style[\s>]/i.test(raw) ||
			/class=["']pages["']/i.test(raw) ||
			/class=["']page["']/i.test(raw);
	}

	function extractBodyFromDocument(html) {
		const bodyMatch = String(html || '').match(/<body[^>]*>([\s\S]*?)<\/body>/i);
		return bodyMatch ? bodyMatch[1].trim() : html;
	}

	function isPrebuiltContractMarkup(value) {
		const raw = String(value || '');
		return /class=["'][^"']*\bgstore-contract-page\b/i.test(raw) ||
			/class=["'][^"']*\bgstore-contract-header\b/i.test(raw) ||
			/class=["'][^"']*\bgstore-contract-section\b/i.test(raw) ||
			/class=["'][^"']*\bgstore-contract-body\b/i.test(raw);
	}

	function safeUpperHeading(line) {
		const t = line.trim();
		if (!t) return false;
		const isClause = /^CLÁUSULA\b/.test(t) || /^PARÁGRAFO\b/.test(t);
		const mostlyUpper = t === t.toUpperCase() && /[A-ZÁÉÍÓÚÂÊÔÃÕÇ]/.test(t);
		return isClause || (mostlyUpper && t.length <= 120);
	}

	function splitIntoBlocks(pageText) {
		const lines = pageText
			.split(/\r?\n/)
			.map(function(l) { return l.trim(); })
			.filter(function(l) { return l.length > 0; });

		const blocks = [];
		let buf = [];

		function flush() {
			if (!buf.length) return;
			blocks.push({ type: 'p', text: buf.join(' ').replace(/\s+/g, ' ') });
			buf = [];
		}

		for (var i = 0; i < lines.length; i++) {
			const line = lines[i];
			if (safeUpperHeading(line)) {
				flush();
				blocks.push({ type: 'heading', text: line });
			} else {
				buf.push(line);
			}
		}
		flush();
		return blocks;
	}

	function getContractDocumentStyles() {
		return 'html,body{margin:0;padding:0;}' +
			'body{background:#e5e7eb;color:#1f2937;font-family:Arial,Helvetica,sans-serif;line-height:1.55;}' +
			'*,*::before,*::after{box-sizing:border-box;}' +
			'.gstore-contract-document{max-width:1080px;margin:0 auto;padding:18px 14px 28px;}' +
			'.gstore-contract-document__page{background:#fff;border:1px solid #d1d5db;box-shadow:0 10px 24px rgba(17,24,39,.11);padding:32px 34px;margin:0 auto 18px;max-width:920px;}' +
			'.gstore-contract-document__page:last-child{margin-bottom:0;}' +
			'.gstore-contract-document__header{background:linear-gradient(180deg,#0f172a 0%,#1f2937 100%);color:#fff;padding:18px 22px;border:1px solid #0f172a;margin-bottom:22px;}' +
			'.gstore-contract-document__header-title{font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;line-height:1.3;}' +
			'.gstore-contract-document__header-sub{font-family:Arial,Helvetica,sans-serif;font-size:12px;opacity:.86;margin-top:5px;}' +
			'.gstore-contract-document__heading{margin-top:18px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#111827;line-height:1.4;}' +
			'.gstore-contract-document__heading:first-child{margin-top:0;}' +
			'.gstore-contract-document__p{margin:8px 0 0;font-size:14px;color:#111827;text-align:justify;}' +
			'.gstore-contract-document__p:first-child{margin-top:0;}' +
			'.gstore-contract-document__page-footer{margin-top:22px;padding-top:12px;border-top:1px solid #d1d5db;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#6b7280;display:flex;justify-content:space-between;gap:12px;}' +
			'.gstore-contract-document table{border-collapse:collapse;width:100%;margin-top:10px;font-family:Arial,Helvetica,sans-serif;}' +
			'.gstore-contract-document th,.gstore-contract-document td{border:1px solid #d1d5db;padding:10px 11px;text-align:left;font-size:13px;vertical-align:top;}' +
			'.gstore-contract-document th{width:34%;background:#f3f4f6;color:#111827;font-weight:700;}' +
			'.gstore-contract-document tr:nth-child(odd) td{background:#f9fafb;}' +
			'.gstore-contract-document tr:nth-child(even) td{background:#fff;}' +
			'.gstore-contract-document__signature{margin-top:28px;display:grid;grid-template-columns:1fr 1fr;gap:14px;}' +
			'.gstore-contract-document__signature-box{padding:13px;border:1px solid #d1d5db;background:#fff;}' +
			'.gstore-contract-document__signature-label{font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#6b7280;}' +
			'.gstore-contract-document__signature-line{margin-top:38px;border-top:1px solid #9ca3af;padding-top:8px;font-size:13px;}' +
			'.gstore-contract-page{background:#fff;border:1px solid #d1d5db;box-shadow:0 8px 18px rgba(17,24,39,.08);margin:0 auto 14px;min-height:1120px;page-break-after:always;width:100%;max-width:920px;}' +
			'.gstore-contract-page__inner{padding:32px 30px 26px;}' +
			'.gstore-contract-page__footer{border-top:1px solid #e5e7eb;color:#6b7280;font-size:11px;padding:10px 30px 14px;display:flex;justify-content:space-between;gap:12px;}' +
			'.gstore-contract-header h2{font-size:19px;margin:0 0 6px;text-align:center;}' +
			'.gstore-contract-header__meta,.gstore-contract-header__tags{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-bottom:8px;}' +
			'.gstore-contract-chip{background:#111827;border-radius:4px;color:#fff;display:inline-block;font-size:10px;font-weight:700;letter-spacing:.08em;padding:5px 8px;}' +
			'.gstore-contract-section,.gstore-contract-body section{margin-bottom:14px;}' +
			'.gstore-contract-section h3,.gstore-contract-body h3{font-size:13px;margin:0 0 7px;text-transform:uppercase;}' +
			'.gstore-contract-section p,.gstore-contract-body p{color:#1f2937;font-size:12px;line-height:1.45;margin:0 0 7px;text-align:justify;}' +
			'.gstore-contract-section table,.gstore-contract-body table{border-collapse:collapse;margin-bottom:12px;width:100%;}' +
			'.gstore-contract-section th,.gstore-contract-section td,.gstore-contract-body th,.gstore-contract-body td{border:1px solid #d1d5db;font-size:11.5px;padding:6px 8px;text-align:left;vertical-align:top;}' +
			'.gstore-contract-section th,.gstore-contract-body th{background:#f9fafb;font-weight:700;width:32%;}' +
			'.gstore-contract-signatures{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:22px;}' +
			'.gstore-contract-signature{border:1px solid #d1d5db;min-height:84px;padding:10px;}' +
			'.gstore-contract-signature__label{color:#6b7280;font-size:11px;margin-bottom:12px;}' +
			'.gstore-contract-signature__line{border-top:1px solid #111827;font-size:12px;padding-top:5px;}' +
			'@media (max-width: 840px){.gstore-contract-document{padding:10px;}.gstore-contract-document__page{padding:20px 16px;}.gstore-contract-document__header-title{font-size:15px;}.gstore-contract-document__signature{grid-template-columns:1fr;}}' +
			'@media print{body{background:#fff!important;}.gstore-contract-document{max-width:none;padding:0;}.gstore-contract-document__page{break-after:page;page-break-after:always;box-shadow:none;border:0;margin:0;max-width:none;padding:18mm 14mm;}.gstore-contract-document__page:last-child{break-after:auto;page-break-after:auto;}.no-print{display:none!important;}}';
	}

	function blocksToHtml(blocks) {
		var html = '';
		for (var i = 0; i < blocks.length; i++) {
			var b = blocks[i];
			var escaped = escapeHtml(b.text);
			if (b.type === 'heading') {
				html += '<div class="gstore-contract-document__heading">' + escaped + '</div>';
			} else {
				html += '<p class="gstore-contract-document__p">' + escaped + '</p>';
			}
		}
		return html;
	}

	function firstPageHeaderHtml() {
		var generatedAt = (new Date()).toLocaleDateString('pt-BR');
		return '<div class="gstore-contract-document__header">' +
			'<div class="gstore-contract-document__header-title">CONTRATO DE PROMESSA DE COMPRA E VENDA</div>' +
			'<div class="gstore-contract-document__header-sub">Documento gerado eletronicamente em ' + escapeHtml(generatedAt) + '</div>' +
			'</div>';
	}

	function contractPageFooterHtml(pageNum) {
		return '<div class="gstore-contract-document__page-footer">' +
			'<span>Contrato gerado eletronicamente</span>' +
			'<span>Página ' + pageNum + '</span>' +
			'</div>';
	}

	function buildContractDocumentHtml(content) {
		const raw = decodeHtmlEntities(String(content || '').trim());
		if (!raw) return '';
		if (isPrebuiltContractMarkup(raw)) {
			return raw;
		}

		// Conteúdo que já é documento HTML completo: extrair body ou usar dentro de uma página.
		if (looksLikeContractDocument(raw)) {
			const bodyMatch = raw.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
			const inner = bodyMatch ? bodyMatch[1].trim() : raw;
			return '<div class="gstore-contract-document"><div class="gstore-contract-document__page">' + inner + contractPageFooterHtml(1) + '</div></div>';
		}

		// HTML rico (tabelas, secções): uma página com o conteúdo.
		if (/<[a-z][\s\S]*>/i.test(raw)) {
			return '<div class="gstore-contract-document"><div class="gstore-contract-document__page">' + firstPageHeaderHtml() + raw + contractPageFooterHtml(1) + '</div></div>';
		}

		// Texto puro: dividir em páginas (blocos separados por linha em branco dupla) e depois em heading/parágrafo.
		const pageChunks = raw.split(/\n\s*\n/).filter(function(s) { return s.trim().length > 0; });
		if (!pageChunks.length) pageChunks.push(raw);

		var bodyInner = '';
		for (var p = 0; p < pageChunks.length; p++) {
			const blocks = splitIntoBlocks(pageChunks[p]);
			bodyInner += '<div class="gstore-contract-document__page">';
			if (p === 0) bodyInner += firstPageHeaderHtml();
			bodyInner += blocksToHtml(blocks);
			bodyInner += contractPageFooterHtml(p + 1);
			bodyInner += '</div>';
		}
		return '<div class="gstore-contract-document">' + bodyInner + '</div>';
	}

	function buildContractFullDoc(bodyContent) {
		const styleContent = getContractDocumentStyles();
		const safeBody = String(bodyContent || '').replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
		return '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>' +
			'<style>' + styleContent + '</style></head><body>' + safeBody + '</body></html>';
	}

	function buildContractSrcDoc(value) {
		const raw = String(value || '');
		const safe = raw.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
		// Documento com doctype mas sem <body>: normalizar para html/head/body válidos (ex.: template colado sem wrapper).
		if (/<!doctype\s+html/i.test(safe) && !/<\s*body[\s>]/i.test(safe)) {
			const titleMatch = safe.match(/<title[^>]*>[\s\S]*?<\/title>/i);
			const styleMatch = safe.match(/<style[^>]*>[\s\S]*?<\/style>/gi);
			var headContent = (titleMatch ? titleMatch[0] : '') + (styleMatch ? styleMatch.join('') : '');
			var bodyContent = safe
				.replace(/<!doctype\s+html[^>]*>/gi, '')
				.replace(/<title[^>]*>[\s\S]*?<\/title>/gi, '')
				.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '')
				.trim();
			// Injeta estilos do contrato quando o body usa classes gstore-contract-*.
			var contractStyles = isPrebuiltContractMarkup(bodyContent) || /class=["'][^"']*gstore-contract-/i.test(bodyContent)
				? '<style>' + getContractDocumentStyles() + '</style>'
				: '';
			return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>' + contractStyles + headContent + '</head><body>' + bodyContent + '</body></html>';
		}
		if (/<!doctype\s+html/i.test(safe) || /<\s*html[\s>]/i.test(safe)) {
			// Documento completo: injeta estilos do contrato se usar classes gstore-contract-* (ex.: preview AJAX rota PIX).
			if (isPrebuiltContractMarkup(safe) || /class=["'][^"']*gstore-contract-/i.test(safe)) {
				var contractStyles = '<style>' + getContractDocumentStyles() + '</style>';
				if (/<\s*head[\s>]/i.test(safe)) {
					return safe.replace(/<\s*head[\s>]/i, '<head>' + contractStyles);
				}
				if (/<\s*\/\s*head\s*>/i.test(safe)) {
					return safe.replace(/<\s*\/\s*head\s*>/i, contractStyles + '</head>');
				}
			}
			return safe;
		}
		// Fragmento de body: injeta estilos se usar classes gstore-contract-*.
		var contractStyles = (isPrebuiltContractMarkup(safe) || /class=["'][^"']*gstore-contract-/i.test(safe))
			? '<style>' + getContractDocumentStyles() + '</style>'
			: '';
		return '<!doctype html><html lang="pt-br"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />' + contractStyles + '</head><body>' + safe + '</body></html>';
	}

	function normalizeContractHtml(value) {
		const raw = decodeHtmlEntities(value).trim();
		if (!raw) return '';
		if (isPrebuiltContractMarkup(raw)) {
			return raw;
		}

		if (looksLikeContractDocument(raw)) {
			return raw;
		}

		if (/<[a-z][\s\S]*>/i.test(raw)) {
			return raw;
		}

		return nl2brSafe(raw);
	}

	function formatDateTimeBr(value) {
		if (!value) return '';
		const dt = new Date(value);
		if (Number.isNaN(dt.getTime())) return String(value);
		const dd = String(dt.getDate()).padStart(2, '0');
		const mm = String(dt.getMonth() + 1).padStart(2, '0');
		const yyyy = dt.getFullYear();
		const hh = String(dt.getHours()).padStart(2, '0');
		const min = String(dt.getMinutes()).padStart(2, '0');
		return dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + min;
	}

	function checkoutFieldValue(fieldId) {
		return String($('#' + fieldId).val() || '').trim();
	}

	function stripHtmlText(value) {
		return String(value || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
	}

	function decodeHtmlEntities(value) {
		var str = String(value || '');
		return str
			.replace(/&#(\d+);/g, function(_, num) { return String.fromCharCode(parseInt(num, 10)); })
			.replace(/&#x([0-9a-fA-F]+);/g, function(_, hex) { return String.fromCharCode(parseInt(hex, 16)); });
	}

	function resolvePaymentMethodTitle() {
		const $selected = $('input[name="payment_method"]:checked');
		if (!$selected.length) return '';
		const methodId = String($selected.val() || '').trim();
		let title = '';
		const radioId = $selected.attr('id');
		if (radioId) {
			title = stripHtmlText($('label[for="' + radioId + '"]').first().text());
		}
		if (!title) {
			title = methodId;
		}
		return title;
	}

	function getCheckoutSummaryData() {
		return lastNonEmptyCartSummaryData || lastCartSummaryData || null;
	}

	function buildItemsListSummary(items) {
		if (!Array.isArray(items) || !items.length) return '';
		const lines = [];
		for (var i = 0; i < items.length; i++) {
			const it = items[i] || {};
			const name = stripHtmlText(it.name || '');
			const qty = parseInt(it.quantity, 10) || 1;
const subtotal = decodeHtmlEntities(stripHtmlText(it.subtotal || ''));
		if (!name) continue;
			lines.push(name + ' x' + qty + (subtotal ? ' - ' + subtotal : ''));
		}
		return lines.join('\n');
	}

	function buildItemsTableRows(items) {
		if (!Array.isArray(items) || !items.length) return '';
		let html = '';
		for (var i = 0; i < items.length; i++) {
			const it = items[i] || {};
			const name = escapeHtml(stripHtmlText(it.name || ''));
			const qty = parseInt(it.quantity, 10) || 1;
			const subtotal = escapeHtml(decodeHtmlEntities(stripHtmlText(it.subtotal || '')));
			if (!name) continue;
			html += '<tr><td>' + (i + 1) + '</td><td>' + name + '</td><td>' + qty + '</td><td>' + subtotal + '</td></tr>';
		}
		return html;
	}

	var CONTRACT_VAR_DISPLAY_NAMES = {
		// Legado (templates antigos)
		'nome': 'Nome do comprador',
		'email': 'E-mail',
		'cpf': 'CPF',
		'endereco': 'Endereço',
		'bairro': 'Bairro',
		'cidade': 'Cidade',
		'uf': 'Estado',
		'cep': 'CEP',
		'telefone': 'Telefone',
		'produtos': 'Produtos',
		'numero_pedido': 'Número do pedido',
		'data': 'Data',
		// Pedido
		'order.id': 'Número do pedido',
		'order.number': 'Número do pedido',
		'order.created_at': 'Data do pedido',
		'order.status': 'Status',
		'order.payment_method': 'Forma de pagamento',
		'order.payment_method_title': 'Forma de pagamento',
		'order.items_count': 'Quantidade de itens',
		'order.subtotal': 'Subtotal',
		'order.shipping_total': 'Frete',
		'order.discount_total': 'Desconto',
		'order.total': 'Total',
		// Comprador
		'buyer.first_name': 'Nome',
		'buyer.last_name': 'Sobrenome',
		'buyer.full_name': 'Nome completo',
		'buyer.email': 'E-mail',
		'buyer.phone': 'Telefone',
		'buyer.cpf': 'CPF',
		'buyer.cnpj': 'CNPJ',
		'buyer.document': 'CPF ou CNPJ',
		'buyer.rg': 'RG',
		'buyer.cr': 'CR',
		'buyer.company': 'Nome do comprador',
		'buyer.billing_address_1': 'Endereço (cobrança)',
		'buyer.billing_number': 'Número (cobrança)',
		'buyer.billing_address_2': 'Complemento (cobrança)',
		'buyer.billing_neighborhood': 'Bairro (cobrança)',
		'buyer.billing_city': 'Cidade (cobrança)',
		'buyer.billing_state': 'Estado (cobrança)',
		'buyer.billing_postcode': 'CEP (cobrança)',
		'buyer.billing_full': 'Endereço completo (cobrança)',
		'buyer.shipping_address_1': 'Endereço (entrega)',
		'buyer.shipping_address_2': 'Complemento (entrega)',
		'buyer.shipping_city': 'Cidade',
		'buyer.shipping_state': 'Estado',
		'buyer.shipping_postcode': 'CEP',
		'buyer.shipping_full': 'Endereço de entrega',
		// Itens
		'items.list': 'Lista de itens',
		'items.table_rows': 'Itens do pedido',
		// Contrato
		'contract.generated_at': 'Data de geração do contrato',
		// Vendedor (quando vazio no preview)
		'seller.legal_name': 'Razão social do vendedor',
		'seller.cnpj': 'CNPJ do vendedor',
		'seller.address_full': 'Endereço do vendedor'
	};

	function buildContractTokenMapFromCheckout() {
		const firstName = checkoutFieldValue('billing_first_name');
		const lastName = checkoutFieldValue('billing_last_name');
		const fullName = [firstName, lastName].join(' ').replace(/\s+/g, ' ').trim();
		const cpf = checkoutFieldValue('billing_cpf');
		const cnpj = checkoutFieldValue('billing_cnpj');
		const rg = checkoutFieldValue('billing_rg');
		const cr = checkoutFieldValue('billing_cr');
		const summary = getCheckoutSummaryData() || {};
		const items = Array.isArray(summary.items) ? summary.items : [];
		const nowIso = new Date().toISOString();

		const billingAddress = [checkoutFieldValue('billing_address_1'), checkoutFieldValue('billing_number')].join(', ').replace(/,\s*$/, '').trim();
		const billingAddress2 = checkoutFieldValue('billing_address_2');
		const billingNeighborhood = checkoutFieldValue('billing_neighborhood');
		const billingCity = checkoutFieldValue('billing_city');
		const billingState = checkoutFieldValue('billing_state');
		const billingPostcode = checkoutFieldValue('billing_postcode');
		const billingFull = [billingAddress, billingAddress2, billingNeighborhood, [billingCity, billingState].join('/'), billingPostcode]
			.filter(function(v) { return String(v || '').trim() !== ''; })
			.join(' - ');

		const paymentMethodId = String(($('input[name="payment_method"]:checked').val() || summary.payment_method || '')).trim();
		const paymentMethodTitle = stripHtmlText(summary.payment_method_title || resolvePaymentMethodTitle());

		const email = checkoutFieldValue('billing_email');
		const phone = checkoutFieldValue('billing_phone');
		const shippingAddr1 = checkoutFieldValue('shipping_address_1') || checkoutFieldValue('billing_address_1');
		const shippingAddr2 = checkoutFieldValue('shipping_address_2') || billingAddress2;
		const shippingCity = checkoutFieldValue('shipping_city') || billingCity;
		const shippingState = checkoutFieldValue('shipping_state') || billingState;
		const shippingPostcode = checkoutFieldValue('shipping_postcode') || billingPostcode;
		const shippingFull = [shippingAddr1, shippingAddr2, [shippingCity, shippingState].join('/'), shippingPostcode]
			.filter(function(v) { return String(v || '').trim() !== ''; })
			.join(' - ');

		return {
			'order.id': '',
			'order.number': '',
			'order.created_at': formatDateTimeBr(nowIso),
			'order.status': 'checkout',
			'order.payment_method': paymentMethodId,
			'order.payment_method_title': paymentMethodTitle,
			'order.items_count': String(summary.items_count || items.length || ''),
			'order.subtotal': decodeHtmlEntities(stripHtmlText(summary.subtotal || '')),
			'order.shipping_total': decodeHtmlEntities(stripHtmlText(summary.shipping_total || '')),
			'order.discount_total': decodeHtmlEntities(stripHtmlText(summary.discount_total || '')),
			'order.total': decodeHtmlEntities(stripHtmlText(summary.total || '')),
			'buyer.first_name': firstName,
			'buyer.last_name': lastName,
			'buyer.full_name': fullName,
			'buyer.email': email,
			'buyer.phone': phone,
			'buyer.cpf': cpf,
			'buyer.cnpj': cnpj,
			'buyer.document': cpf || cnpj,
			'buyer.rg': rg,
			'buyer.cr': cr,
			'buyer.company': checkoutFieldValue('billing_company'),
			'buyer.billing_address_1': checkoutFieldValue('billing_address_1'),
			'buyer.billing_number': checkoutFieldValue('billing_number'),
			'buyer.billing_address_2': billingAddress2,
			'buyer.billing_neighborhood': billingNeighborhood,
			'buyer.billing_city': billingCity,
			'buyer.billing_state': billingState,
			'buyer.billing_postcode': billingPostcode,
			'buyer.billing_full': billingFull,
			'buyer.shipping_address_1': shippingAddr1,
			'buyer.shipping_address_2': shippingAddr2,
			'buyer.shipping_city': shippingCity,
			'buyer.shipping_state': shippingState,
			'buyer.shipping_postcode': shippingPostcode,
			'buyer.shipping_full': shippingFull,
			'items.list': buildItemsListSummary(items),
			'items.table_rows': buildItemsTableRows(items),
			'contract.generated_at': formatDateTimeBr(nowIso),
			// Aliases para template admin ({{nome}}, {{cpf}}, etc.) – compatível com Contract_Service
			'nome': fullName,
			'email': email,
			'cpf': cpf || cnpj,
			'endereco': billingAddress || billingFull,
			'bairro': billingNeighborhood,
			'cidade': billingCity,
			'uf': billingState,
			'cep': billingPostcode,
			'telefone': phone
		};
	}

	function applyContractTemplateTokens(content, useDisplayNamesForEmpty) {
		const raw = String(content || '');
		if (!raw || raw.indexOf('{{') === -1) return raw;

		const defaults = (typeof gstoreCheckout !== 'undefined' && gstoreCheckout.contractTokenDefaults)
			? gstoreCheckout.contractTokenDefaults
			: {};
		const runtime = buildContractTokenMapFromCheckout();
		const merged = {};
		Object.keys(defaults || {}).forEach(function(k) {
			merged[String(k).toLowerCase()] = defaults[k];
		});
		Object.keys(runtime || {}).forEach(function(k) {
			merged[String(k).toLowerCase()] = runtime[k];
		});

		return raw.replace(/\{\{\s*([a-z0-9._-]+)\s*\}\}/gi, function(match, token) {
			const key = String(token || '').toLowerCase();
			if (!Object.prototype.hasOwnProperty.call(merged, key)) {
				return match;
			}
			const value = merged[key];
			if (useDisplayNamesForEmpty && (value == null || String(value).trim() === '')) {
				const displayName = CONTRACT_VAR_DISPLAY_NAMES[key] || key;
				return String(displayName);
			}
			return value == null ? '' : String(value);
		});
	}

	function renderContractModalContent($modal, content, useDisplayNamesForEmpty, forceNormalize) {
		const $body = $modal.find('.Gstore-contract-modal__body');
		$body.html('<iframe class="Gstore-contract-modal__iframe" title="Contrato" loading="eager"></iframe>');
		const iframe = $body.find('.Gstore-contract-modal__iframe').get(0);
		if (iframe) {
			const resolvedContent = applyContractTemplateTokens(content, !!useDisplayNamesForEmpty);
			// forceNormalize (ex.: rota PIX): sempre usar pipeline de normalização (header, páginas, footer).
			if (forceNormalize) {
				const bodyContent = looksLikeContractDocument(resolvedContent)
					? extractBodyFromDocument(resolvedContent)
					: resolvedContent;
				const documentHtml = buildContractDocumentHtml(bodyContent);
				iframe.srcdoc = buildContractFullDoc(documentHtml);
				return;
			}
			// Documento HTML completo (ex.: template com .wrap > .pages > .page): usar inteiro no iframe
			// para preservar estilos do <head> e todas as páginas do body.
			if (looksLikeContractDocument(resolvedContent)) {
				iframe.srcdoc = buildContractSrcDoc(resolvedContent);
				return;
			}
			const documentHtml = buildContractDocumentHtml(resolvedContent);
			iframe.srcdoc = buildContractFullDoc(documentHtml);
		}
	}

	function isLegacyContractPreviewHtml(content) {
		const raw = String(content || '');
		if (!raw) return true;
		if (/Contrato \(conteúdo do PDF em HTML\)/i.test(raw)) return true;
		if (/\{\{[^}]+\}\}/.test(raw)) return true;
		return false;
	}

	function checkoutPageUrl(path) {
		const base = (typeof gstoreCheckout !== 'undefined' && gstoreCheckout.homeUrl)
			? gstoreCheckout.homeUrl
			: '/';
		const cleanPath = String(path || '').replace(/^\/+/, '');
		return String(base).replace(/\/+$/, '/') + cleanPath;
	}

	function getContractSettings() {
		const settings = (typeof gstoreCheckout !== 'undefined' && gstoreCheckout.contractSettings)
			? gstoreCheckout.contractSettings
			: {};

		const enabled = settings.enabled !== false;
		const checkboxText = settings.checkboxText || 'Li e concordo com os';
		const modalTitle = settings.modalTitle || 'Termos do contrato';
		const modalContent = settings.modalContent || settings.termsText || settings.fullText || checkboxText;
		const privacyUrl = settings.privacyUrl ||
			((typeof gstoreCheckout !== 'undefined' && gstoreCheckout.privacyPolicyUrl) ? gstoreCheckout.privacyPolicyUrl : checkoutPageUrl('politica-de-privacidade/'));

		return {
			enabled: !!enabled,
			checkboxText,
			modalTitle,
			modalContent,
			privacyUrl
		};
	}

	/**
	 * Garante cálculo/validação do frete quando o CEP já está preenchido (sem precisar clicar/sair do campo).
	 * Útil para sessão/autofill e para quando a etapa "Dados" fica ativa.
	 */
	function ensureShippingAutofilled() {
		const $postcodeInput = $('#billing_postcode');
		if (!$postcodeInput.length) return;

		const raw = String($postcodeInput.val() || '');
		const cleanCep = raw.replace(/\D/g, '');

		// Só tenta calcular se CEP estiver completo
		if (cleanCep.length !== 8) return;

		// Se já temos frete calculado para o mesmo CEP, não precisa recalcular
		if (calculatedShipping && lastCalculatedShippingCep === cleanCep) return;

		// Evita disparar várias requisições iguais
		if (isCalculatingShipping && lastRequestedShippingCep === cleanCep) return;

		// Dispara cálculo (sem exigir blur/click)
		calculateShipping(raw);
	}

	/**
	 * Inicializa o checkout de etapas
	 */
	function init() {
		if (initialized) return;
		
		$checkoutForm = $('form.checkout.woocommerce-checkout');
		
		if (!$checkoutForm.length) {
			return;
		}

		// Verifica se já foi inicializado
		if ($('.Gstore-checkout-steps').length) {
			return;
		}

		buildStepsUI();
		bindEvents();
		// O primeiro load vem do evento updated_checkout (não carregar aqui para evitar totais sem CEP/frete)
		$(document.body).trigger('update_checkout');
		// Se o CEP já veio preenchido (sessão/autofill), calcula o frete imediatamente
		setTimeout(ensureShippingAutofilled, 0);
		
		initialized = true;
	}

	/**
	 * Constrói a interface do checkout em etapas
	 */
	function buildStepsUI() {
		const $shell = $('.Gstore-checkout-steps-shell');
		if (!$shell.length) return;

		// Esconde o wrapper original do checkout (mas NÃO o form)
		$shell.find('.Gstore-checkout').hide();

		// Cria container principal
		$stepsContainer = $('<div class="Gstore-checkout-steps"></div>');

		// 1. Resumo do pedido no topo
		$stepsContainer.append(buildSummaryTop());

		// 2. Stepper
		$stepsContainer.append(buildStepper());

		// 3. Container das etapas
		const $stepsContent = $('<div class="Gstore-checkout-steps__content"></div>');

		STEPS.forEach((step, index) => {
			$stepsContent.append(buildStepPanel(step, index));
		});

		$stepsContainer.append($stepsContent);

		// Adiciona à shell para manter o layout
		$shell.append($stepsContainer);

		// Move campos para as etapas corretas
		organizeFields();

		// Ativa primeira etapa sem forçar scroll (evita pular para o fim na carga inicial)
		setActiveStep(0, false);
	}

	/**
	 * Constrói o resumo do pedido no topo
	 */
	function buildSummaryTop() {
		return `
			<div class="Gstore-checkout-summary-top">
				<div class="Gstore-checkout-summary-top__inner">
					<div class="Gstore-checkout-summary-top__info">
						<div class="Gstore-checkout-summary-top__icon">
							<i class="fa-solid fa-shopping-bag"></i>
						</div>
						<div class="Gstore-checkout-summary-top__text">
							<h2>Seu Pedido</h2>
							<p class="Gstore-summary-items-count">Carregando...</p>
						</div>
					</div>
					<div class="Gstore-checkout-summary-top__actions">
						<span class="Gstore-checkout-summary-top__total-amount" aria-live="polite">R$ --,--</span>
						<span class="Gstore-checkout-summary-top__actions-divider" aria-hidden="true"></span>
						<button type="button" class="Gstore-checkout-summary-top__toggle">
							Ver detalhes
							<i class="fa-solid fa-chevron-down"></i>
						</button>
					</div>
				</div>
				<div class="Gstore-checkout-summary-top__details">
					<div class="Gstore-checkout-summary-top__items"></div>
					<div class="Gstore-checkout-summary-top__shipping" data-gstore-shipping-summary>
						<div class="Gstore-checkout-summary-top__shipping-title">Frete</div>
						<div class="Gstore-checkout-summary-top__shipping-options" data-gstore-shipping-options></div>
						<div class="Gstore-checkout-summary-top__shipping-totals" data-gstore-shipping-totals></div>
					</div>
					<div class="Gstore-checkout-summary-top__totals"></div>
				</div>
			</div>
		`;
	}

	/**
	 * Constrói o stepper
	 */
	function buildStepper() {
		let html = '<nav class="Gstore-checkout-stepper" aria-label="Etapas do checkout">';

		STEPS.forEach((step, index) => {
			if (index > 0) {
				html += `<div class="Gstore-checkout-stepper__connector" data-connector="${index}"></div>`;
			}
			html += `
				<button type="button" class="Gstore-checkout-stepper__step" data-step-index="${index}">
					<span class="Gstore-checkout-stepper__number">
						<span>${index + 1}</span>
					</span>
					<span class="Gstore-checkout-stepper__label">${step.name}</span>
				</button>
			`;
		});

		html += '</nav>';
		return html;
	}

	/**
	 * Constrói o painel de uma etapa
	 */
	function buildStepPanel(step, index) {
		const isLast = index === STEPS.length - 1;
		
		let actionsHtml = '';
		if (!isLast) {
			actionsHtml = `
				<div class="Gstore-checkout-step__actions">
					${index > 0 ? '<button type="button" class="Gstore-btn Gstore-btn--back" data-action="prev"><i class="fa-solid fa-arrow-left"></i> Voltar</button>' : '<div></div>'}
					<button type="button" class="Gstore-btn Gstore-btn--continue" data-action="next">
						Continuar
						<i class="fa-solid fa-arrow-right"></i>
					</button>
				</div>
			`;
		} else {
			actionsHtml = `
				<div class="Gstore-checkout-step__actions Gstore-checkout-step__actions--payment">
					<button type="button" class="Gstore-btn Gstore-btn--back" data-action="prev">
						<i class="fa-solid fa-arrow-left"></i> Voltar
					</button>
				</div>
			`;
		}

		return `
			<div class="Gstore-checkout-step" data-step="${step.id}" data-step-index="${index}">
				<div class="Gstore-checkout-step__header">
					<span class="Gstore-checkout-step__eyebrow">
						<i class="fa-solid ${step.icon}"></i>
						Etapa ${index + 1} de ${STEPS.length}
					</span>
					<h2 class="Gstore-checkout-step__title">${step.title}</h2>
					<p class="Gstore-checkout-step__description">${step.description}</p>
				</div>
				<div class="Gstore-checkout-step__fields"></div>
				${actionsHtml}
				${isLast ? '<div class="Gstore-checkout-step__payment-container"><div class="Gstore-checkout-step__order-review-slot"></div><div class="Gstore-blu-installments-slot"></div></div>' : ''}
			</div>
		`;
	}

	/**
	 * Unifica métodos de pagamento Blu em um card único
	 */
	function unifyBluPaymentMethods() {
		// Verifica se já existe o card unificado
		const $existingUnified = $('.Gstore-blu-payment-unified');
		if ($existingUnified.length) {
			// Se já existe, apenas sincroniza a seleção
			const $selected = $('input[name="payment_method"]:checked');
			if ($selected.length) {
				const selectedValue = $selected.val();
				const $option = $existingUnified.find(`input[type="radio"][value="${selectedValue}"]`);
				if ($option.length && !$option.is(':checked')) {
					$option.prop('checked', true).trigger('change');
				}
			}
			return;
		}
		
		const $bluCheckout = $('.payment_method_blu_checkout').not('.Gstore-blu-payment-unified .payment_method_blu_checkout');
		const $bluPix = $('.payment_method_blu_pix').not('.Gstore-blu-payment-unified .payment_method_blu_pix');
		
		// Se ambos os métodos Blu estão disponíveis, unifica em um card
		if ($bluCheckout.length && $bluPix.length) {
			// Esconde os elementos originais com classe CSS (mais confiável que inline styles)
			$bluCheckout.addClass('gstore-hidden-for-unified');
			$bluPix.addClass('gstore-hidden-for-unified');
			
			// Cria card unificado
			const $bluUnified = $('<li class="payment_method_blu_unified Gstore-blu-payment-unified"></li>');
			
			// Adiciona título unificado "Pagamento via Blu"
			$bluUnified.append('<div class="Gstore-blu-payment-unified__title">Pagamento via Blu</div>');
			
			// Cria container para as opções
			const $optionsContainer = $('<div class="Gstore-blu-payment-options"></div>');
			
			// Prepara opção Cartão - usa o radio original mas escondido
			const $checkoutOption = $('<div class="Gstore-blu-payment-option"></div>');
			const $checkoutRadio = $bluCheckout.find('input[type="radio"]').first();
			const checkoutId = $checkoutRadio.attr('id') || 'payment_method_blu_checkout';
			const checkoutChecked = $checkoutRadio.is(':checked');
			
			// Clona o radio para usar no card unificado, mantendo o original escondido
			const $checkoutRadioClone = $checkoutRadio.clone();
			$checkoutRadioClone.appendTo($checkoutOption);
			$checkoutOption.append(`
				<label for="${checkoutId}" class="Gstore-blu-payment-option__label">
					<i class="fa-solid fa-credit-card"></i>
					<span>Cartão (Link de Pagamento)</span>
				</label>
			`);
			$optionsContainer.append($checkoutOption);
			
			// Sincroniza cliques no radio clone com o original
			$checkoutRadioClone.on('change', function() {
				if ($(this).is(':checked')) {
					$checkoutRadio.prop('checked', true).trigger('change');
					$pixRadio.prop('checked', false);
				}
			});
			
			// Prepara opção Pix - usa o radio original mas escondido
			const $pixOption = $('<div class="Gstore-blu-payment-option"></div>');
			const $pixRadio = $bluPix.find('input[type="radio"]').first();
			const pixId = $pixRadio.attr('id') || 'payment_method_blu_pix';
			const pixChecked = $pixRadio.is(':checked');
			
			
			// Clona o radio para usar no card unificado, mantendo o original escondido
			const $pixRadioClone = $pixRadio.clone();
			$pixRadioClone.appendTo($pixOption);
			$pixOption.append(`
				<label for="${pixId}" class="Gstore-blu-payment-option__label">
					<i class="fa-solid fa-qrcode"></i>
					<span>Pix</span>
				</label>
			`);
			$optionsContainer.append($pixOption);
			
			// Sincroniza cliques no radio clone com o original
			$pixRadioClone.on('change', function() {
				if ($(this).is(':checked')) {
					$pixRadio.prop('checked', true).trigger('change');
					$checkoutRadio.prop('checked', false);
				}
			});
			
			$bluUnified.append($optionsContainer);
			
			// Move payment_box do método selecionado para dentro do card unificado
			$bluUnified.append('<div class="Gstore-blu-payment-unified__content"></div>');
			const $content = $bluUnified.find('.Gstore-blu-payment-unified__content');
			
			// Adiciona event listeners para mostrar/esconder conteúdo baseado na seleção
			/**
			 * Atualiza conteúdo do método de pagamento selecionado
			 * Função usada apenas para sincronização interna
			 */
			function updatePaymentContent() {
				// Usa lastSelectedPaymentMethod como fonte de verdade (evita race conditions)
				const method = lastSelectedPaymentMethod || 'blu_checkout';
				const isCheckout = method === 'blu_checkout';

				// Sincroniza radios com a escolha persistida
				$('input[name="payment_method"][value="blu_checkout"]').prop('checked', isCheckout);
				$('input[name="payment_method"][value="blu_pix"]').prop('checked', !isCheckout);
				if ($checkoutRadioClone) $checkoutRadioClone.prop('checked', isCheckout);
				if ($pixRadioClone) $pixRadioClone.prop('checked', !isCheckout);
				persistSelectedPaymentMethod(method);
				
				$content.empty();
				
				if (isCheckout) {
					const $box = $('.payment_method_blu_checkout.gstore-hidden-for-unified .payment_box').first().clone();
					$content.append($box);
					toggleBillingFieldsForPaymentMethod(false);
				} else {
					const $box = $('.payment_method_blu_pix.gstore-hidden-for-unified .payment_box').first().clone();
					$content.append($box);
					toggleBillingFieldsForPaymentMethod(true);
				}
			}
			
	// Handler para cliques nos labels de pagamento (sem disparar update_checkout)
	function selectPaymentMethod(selectedMethod) {
		const $livePixRadio = $('input[name="payment_method"][value="blu_pix"]');
		const $liveCheckoutRadio = $('input[name="payment_method"][value="blu_checkout"]');
		
		const isCheckout = selectedMethod === 'blu_checkout';
		persistSelectedPaymentMethod(selectedMethod);
		
		// Atualiza os radios originais
		$liveCheckoutRadio.prop('checked', isCheckout);
		$livePixRadio.prop('checked', !isCheckout);
				
				// Atualiza os clones visuais
				if ($checkoutRadioClone) $checkoutRadioClone.prop('checked', isCheckout);
				if ($pixRadioClone) $pixRadioClone.prop('checked', !isCheckout);
				
				// Atualiza conteúdo e billing fields
				toggleBillingFieldsForPaymentMethod(!isCheckout);
				$content.empty();
				
				if (isCheckout) {
					const $box = $('.payment_method_blu_checkout.gstore-hidden-for-unified .payment_box').first().clone();
					$content.append($box);
				} else {
					const $box = $('.payment_method_blu_pix.gstore-hidden-for-unified .payment_box').first().clone();
					$content.append($box);
				}

				// Esconde/mostra parcelamento imediatamente (PIX não tem parcelamento)
				$('.Gstore-blu-installments').toggle(isCheckout);

				// CORREÇÃO: Reseta parcelas para 1 quando muda para PIX
				if (!isCheckout) {
					$('#gstore_blu_installments').val('1');
					$('#gstore_blu_installments_select').val('1');
				}

				// Atualiza totais/sessão do WooCommerce
				$(document.body).trigger('update_checkout');
				setTimeout(loadCartSummary, 150);
			}
			
			$checkoutOption.find('label').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				selectPaymentMethod('blu_checkout');
			});
			
		$pixOption.find('label').on('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			selectPaymentMethod('blu_pix');
		});
			
		// Sincroniza quando WooCommerce atualiza o checkout (ex: cupom aplicado)
		// Remove handler anterior para evitar acumulação (cada unifyBluPaymentMethods registra um novo)
		$(document.body).off('updated_checkout.gstore-unify').on('updated_checkout.gstore-unify', function() {
			// Re-esconde os elementos originais que podem ter sido recriados
			$('.payment_method_blu_checkout').not('.Gstore-blu-payment-unified .payment_method_blu_checkout').addClass('gstore-hidden-for-unified');
			$('.payment_method_blu_pix').not('.Gstore-blu-payment-unified .payment_method_blu_pix').addClass('gstore-hidden-for-unified');
			
			// Usa lastSelectedPaymentMethod como fonte de verdade (evita race conditions com radios)
			setTimeout(function() {
				const method = lastSelectedPaymentMethod || 'blu_checkout';
				const isCheckout = method === 'blu_checkout';

				// Garante que os radios refletem a escolha do usuário
				$('input[name="payment_method"][value="blu_checkout"]').prop('checked', isCheckout);
				$('input[name="payment_method"][value="blu_pix"]').prop('checked', !isCheckout);

				if ($checkoutRadioClone) $checkoutRadioClone.prop('checked', isCheckout);
				if ($pixRadioClone) $pixRadioClone.prop('checked', !isCheckout);
				toggleBillingFieldsForPaymentMethod(!isCheckout);

				// Esconde/mostra parcelamento (PIX não tem parcelamento)
				$('.Gstore-blu-installments').toggle(isCheckout);

				if ($content && $content.length) {
					$content.empty();
					if (isCheckout) {
						const $box = $('.payment_method_blu_checkout.gstore-hidden-for-unified .payment_box').first().clone();
						$content.append($box);
					} else {
						const $box = $('.payment_method_blu_pix.gstore-hidden-for-unified .payment_box').first().clone();
						$content.append($box);
					}
				}
			}, 50);
		});
			
		// Mostra conteúdo inicial usando lastSelectedPaymentMethod como fonte de verdade
		setTimeout(function() {
			const method = lastSelectedPaymentMethod || 'blu_checkout';
			const isCheckout = method === 'blu_checkout';

			// Garante que radios reflitam a escolha do usuário
			$checkoutRadio.prop('checked', isCheckout);
			$pixRadio.prop('checked', !isCheckout);
			if ($checkoutRadioClone) $checkoutRadioClone.prop('checked', isCheckout);
			if ($pixRadioClone) $pixRadioClone.prop('checked', !isCheckout);
			
			// Esconde/mostra parcelamento na inicialização (PIX não tem parcelamento)
			$('.Gstore-blu-installments').toggle(isCheckout);
			
			updatePaymentContent();
		}, 100);
			
			// Adiciona badges de confiança simplificados
			$bluUnified.append(`
				<div class="Gstore-blu-trust-badges-simple">
					<span class="Gstore-blu-trust-badge-simple">
						<i class="fa-solid fa-shield-halved"></i> Pagamento seguro
					</span>
				</div>
			`);
			
			// Insere o card unificado na lista de métodos de pagamento
			const $paymentMethods = $('#payment .payment_methods');
			if ($paymentMethods.length) {
				$paymentMethods.prepend($bluUnified);
			} else {
				const $paymentSection = $('#payment');
				if ($paymentSection.length) {
					const $newPaymentMethods = $('<ul class="payment_methods"></ul>');
					$newPaymentMethods.append($bluUnified);
					$paymentSection.prepend($newPaymentMethods);
				}
			}
		}
	}

	/**
	 * Move/ativa o seletor de parcelas da Blu (renderizado no PHP) dentro do card unificado
	 * e sincroniza com o método selecionado.
	 */
	function ensureBluInstallmentsUI() {
		const $installments = $('.Gstore-blu-installments').first();
		if (!$installments.length) return;

		// Move para dentro da Etapa 3 (Finalizar)
		const $slot = $('.Gstore-blu-installments-slot').first();
		if ($slot.length && !$slot.find('.Gstore-blu-installments').length) {
			$slot.append($installments.detach());
		}

		const allow = $installments.data('allow') === 1 || $installments.data('allow') === '1';
		// Usa lastSelectedPaymentMethod (mais confiável que checar radios em meio a race conditions de updated_checkout)
		const isCheckoutSelected = lastSelectedPaymentMethod === 'blu_checkout' ||
			(!lastSelectedPaymentMethod && $('input[name="payment_method"][value="blu_checkout"]').filter(':checked').length > 0);

		// Só faz sentido mostrar quando cartão estiver selecionado
		$installments.toggle(allow && isCheckoutSelected);

		const $hidden = $('#gstore_blu_installments');
		const $select = $('#gstore_blu_installments_select');

		if (allow && $hidden.length && $select.length) {
			// Sincroniza select <- hidden
			if ($hidden.val() && $select.val() !== $hidden.val()) {
				$select.val($hidden.val());
			}

			// Bind 1x
			if (!$select.data('gstore-bound')) {
				$select.data('gstore-bound', true);
				$select.on('change', function() {
					const val = $(this).val() || '1';
					$hidden.val(val);
					$(document.body).trigger('update_checkout');
				});
			}
		}

		// Atualiza labels das opções (Nx de R$ ...) quando disponível
		applyInstallmentQuotesToSelect();

		// Busca tabela de parcelas quando necessário
		maybeFetchInstallmentQuotes();

		// Atualiza preview com o último resumo disponível
		if (lastCartSummaryData && lastCartSummaryData.installments) {
			updateInstallmentsPreview(lastCartSummaryData);
		}
	}

	function maybeFetchInstallmentQuotes() {
		const $installments = $('.Gstore-blu-installments').first();
		if (!$installments.length) return;

		const allow = $installments.data('allow') === 1 || $installments.data('allow') === '1';
		if (!allow) return;

		// Só faz sentido para blu_checkout
		if (!lastCartSummaryData || lastCartSummaryData.payment_method !== 'blu_checkout') return;

		const max = parseInt($installments.data('max'), 10) || 1;
		const selected = (lastCartSummaryData.installments && lastCartSummaryData.installments.selected)
			? String(lastCartSummaryData.installments.selected)
			: $('#gstore_blu_installments').val() || '1';

		// Assinatura inclui total do resumo (que pode ter frete) para invalidar quando mudar
		const summaryTotalForSig = (lastSummaryTotals && Number.isFinite(lastSummaryTotals.totalValue)) ? lastSummaryTotals.totalValue : (lastCartSummaryData && lastCartSummaryData.total ? parsePriceValue(lastCartSummaryData.total) : '');
		const signature = `${max}|${selected}|${summaryTotalForSig}`;
		if (signature === lastInstallmentQuotesSignature) return;
		if (isLoadingInstallmentQuotes) return;
		lastInstallmentQuotesSignature = signature;

		const ajaxUrl = (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.ajax_url)
			? wc_checkout_params.ajax_url
			: '/wp-admin/admin-ajax.php';

		// Enviar post_data do formulário para o backend calcular frete e imposto no mesmo contexto do checkout
		const $form = $('form.checkout').first();
		const postData = $form.length ? $form.serialize() : '';

		isLoadingInstallmentQuotes = true;
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'gstore_blu_get_installment_quotes',
				max: max,
				post_data: postData
			},
			success: function(res) {
				isLoadingInstallmentQuotes = false;
				if (res && res.success && res.data && res.data.quotes) {
					installmentQuotes = res.data.quotes;
					applyInstallmentQuotesToSelect();
				}
			},
			error: function() {
				isLoadingInstallmentQuotes = false;
			}
		});
	}

	function applyInstallmentQuotesToSelect() {
		const $select = $('#gstore_blu_installments_select');
		if (!$select.length) return;
		if (!installmentQuotes) return;
	const displayTotals = getInstallmentDisplayTotals(lastCartSummaryData);

		$select.find('option').each(function() {
			const $opt = $(this);
			const val = String($opt.attr('value') || '');
			if (!val || !installmentQuotes[val]) return;

		const q = installmentQuotes[val];
		const installments = parseInt(q.installments, 10) || parseInt(val, 10) || 1;
		let totalValue = Number.isFinite(q.total_raw) ? q.total_raw : parsePriceValue(q.total_text || q.total || '');
		if (!Number.isFinite(totalValue) || totalValue <= 0) {
			totalValue = parsePriceValue(q.total || '');
		}
		let perValue = Number.isFinite(q.per_installment_raw) ? q.per_installment_raw : parsePriceValue(q.per_installment_text || q.per_installment || '');
		// O backend (ajax_installment_quotes) já inclui frete no total_raw via $cart->calculate_totals(),
		// portanto NÃO somar frete novamente aqui (causava duplicação do valor do frete).
		if (!Number.isFinite(perValue) || perValue <= 0) {
			perValue = totalValue / installments;
		}
		const perText = perValue > 0 ? formatCurrency(perValue) : (q.per_installment_text || q.per_installment || '');
		const totalText = totalValue > 0 ? formatCurrency(totalValue) : (q.total_text || q.total || '');
		$opt.text(`${installments}x de ${perText} — total ${totalText}`);
		});
	}

	// Campos de billing completos (usados quando Pix é selecionado)
	const PIX_BILLING_FIELDS = [
		'billing_first_name',
		'billing_last_name',
		'billing_cpf',
		'billing_postcode',
		'billing_address_1',
		'billing_number',
		'billing_address_2',
		'billing_neighborhood',
		'billing_city',
		'billing_state'
	];
	
	/**
	 * Mostra/esconde campos de billing baseado no método de pagamento selecionado
	 */
	function toggleBillingFieldsForPaymentMethod(showForPix) {
		const $contactStep = $('[data-step="contact"] .Gstore-checkout-step__fields');
		if (!$contactStep.length) return;
		
		if (showForPix) {
			// PIX selecionado: Move campos de billing para a etapa de contato e mostra
			PIX_BILLING_FIELDS.forEach(fieldId => {
				// Primeiro tenta encontrar na etapa de contato
				let $field = $contactStep.find(`#${fieldId}_field`);
				
				if ($field.length) {
					// Já está na etapa de contato, apenas mostra
					$field.show();
				} else {
					// Busca em qualquer lugar da página
					$field = $(`#${fieldId}_field`);
					if ($field.length) {
						// Move para a etapa de contato
						$contactStep.append($field.detach());
						$field.show();
					}
				}
			});
			
			// Também mostra a seção de billing se existir
			$('.woocommerce-billing-fields').show();
			
			// Atualiza descrição da etapa
			const $stepDescription = $('[data-step="contact"] .Gstore-checkout-step__description');
			$stepDescription.text('Preencha seus dados completos para finalizar o pedido via Pix.');
		} else {
			// CARTÃO selecionado: Mostra apenas email, telefone e CEP
			PIX_BILLING_FIELDS.forEach(fieldId => {
				const $field = $(`#${fieldId}_field`);
				if ($field.length) {
					// Mostra apenas CEP, esconde os demais
					if (fieldId === 'billing_postcode') {
						$field.show();
					} else {
						$field.hide();
					}
				}
			});
			
			// Garante que CEP está visível na etapa de contato
			const $contactStep = $('[data-step="contact"] .Gstore-checkout-step__fields');
			const $postcodeField = $('#billing_postcode_field');
			if ($postcodeField.length && $contactStep.length) {
				// Move CEP para a etapa de contato se não estiver lá
				if (!$contactStep.find('#billing_postcode_field').length) {
					$contactStep.append($postcodeField.detach());
				}
				$postcodeField.show();
			}
			
			// Atualiza descrição da etapa
			const $stepDescription = $('[data-step="contact"] .Gstore-checkout-step__description');
			$stepDescription.text('Informe seu email, telefone e CEP para calcular o frete.');
		}
	}

	/**
	 * Organiza os campos nas etapas corretas
	 */
	function organizeFields() {
		// Etapa 1: Move métodos de pagamento
		const $paymentMethodStep = $('[data-step="payment-method"] .Gstore-checkout-step__fields');
		if ($paymentMethodStep.length) {
			const $paymentSection = $('#payment');
			if ($paymentSection.length) {
				// Remove botão de finalizar (será recriado na última etapa)
				$paymentSection.find('.place-order').remove();
				$paymentMethodStep.append($paymentSection.detach());
				setTimeout(unifyBluPaymentMethods, 150);
			}
		}

		// Etapa 2: Move campos de contato (email e telefone)
		const $contactStep = $('[data-step="contact"] .Gstore-checkout-step__fields');
		if ($contactStep.length) {
			STEPS[1].fields.forEach(fieldId => {
				const $field = $(`#${fieldId}_field`);
				if ($field.length) {
					$contactStep.append($field.detach());
					
				}
			});
		}

		// Etapa 3: Footer de finalização (termos + privacidade + CTA)
		const $finalizeStep = $('[data-step="payment"] .Gstore-checkout-step__payment-container');
		if ($finalizeStep.length && !$finalizeStep.find('#place_order').length) {
			const contractSettings = getContractSettings();
			const contractEnabled = contractSettings.enabled;
			const contractText = contractSettings.checkboxText;
			const privacyUrl = contractSettings.privacyUrl;

			let contractCheckboxHtml = '';
			if (contractEnabled) {
				contractCheckboxHtml = `
					<div class="gstore-contract-terms woocommerce-terms-and-conditions-wrapper">
						<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox gstore-contract-terms__label">
							<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="gstore_contract_terms" id="gstore_contract_terms" value="1" required />
							<span class="gstore-contract-terms__text">
								${escapeHtml(contractText)}
								<button type="button" class="gstore-contract-open-modal">termos do contrato</button>
								e com a
								<a class="gstore-contract-privacy-link" href="${escapeHtml(privacyUrl)}" target="_blank" rel="noopener noreferrer">política de privacidade</a>.
							</span>
						</label>
						<input type="hidden" name="gstore_contract_terms_required" value="1" />
					</div>
				`;
			}

			$finalizeStep.append(`
				<div class="Gstore-finalize-container">
					<div class="Gstore-finalize-helper">
						<i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
						<span>Dados sensíveis não ficam armazenados neste site.</span>
					</div>
					${contractCheckboxHtml}
					<button type="submit" class="Gstore-btn Gstore-btn--submit" name="woocommerce_checkout_place_order" id="place_order" value="Finalizar pedido" data-value="Finalizar pedido">
						<i class="fa-solid fa-lock"></i>
						Finalizar pedido
					</button>
					<p class="Gstore-finalize-privacy">
						Seus dados estão protegidos. Ao finalizar, você concorda com nossa 
						<a href="${escapeHtml(privacyUrl)}" target="_blank" rel="noopener noreferrer">política de privacidade</a>.
					</p>
					<div id="gstore-contract-terms-modal" class="Gstore-contract-modal" aria-hidden="true">
						<div class="Gstore-contract-modal__backdrop" data-action="close-contract-modal"></div>
						<div class="Gstore-contract-modal__content" role="dialog" aria-modal="true" aria-labelledby="gstore-contract-modal-title">
							<button type="button" class="Gstore-contract-modal__close" data-action="close-contract-modal" aria-label="Fechar">
								<i class="fa-solid fa-xmark" aria-hidden="true"></i>
							</button>
							<h3 id="gstore-contract-modal-title" class="Gstore-contract-modal__title"></h3>
							<div class="Gstore-contract-modal__body"></div>
						</div>
					</div>
				</div>
			`);
		}

		// Etapa 3: Move o resumo do pedido (order review) para dentro do container principal da última etapa
		const $orderReviewSlot = $('[data-step="payment"] .Gstore-checkout-step__order-review-slot');
		const $orderReview = $('#order_review');
		if ($orderReviewSlot.length && $orderReview.length) {
			// Header do card (não depende do heading padrão do Woo, que é escondido nos steps)
			if (!$orderReviewSlot.find('.Gstore-order-review-header').length) {
				$orderReviewSlot.prepend(`
					<div class="Gstore-order-review-header">
						<div class="Gstore-order-review-header__title">Resumo do pedido</div>
					</div>
				`);
			}
			// Evita duplicar se o WooCommerce re-renderizar e o elemento já estiver no slot
			if (!$orderReviewSlot.find('#order_review').length) {
				$orderReviewSlot.append($orderReview.detach());
			}
		}

		// Esconde a seção de frete do carrinho (cart-shipping.php) dentro do order review.
		// O checkout já possui seu próprio sistema de frete (renderShippingSummary).
		$orderReviewSlot.find('.gstore-shipping-totals, .woocommerce-shipping-totals').hide();
		$orderReviewSlot.find('.gstore-shipping-calculator').hide();

		// Esconde seções do WooCommerce não utilizadas
		$('.woocommerce-additional-fields').hide();
		$('.woocommerce-billing-fields').hide();
		$('.woocommerce-shipping-fields').hide();
	}

	/**
	 * Calcula o frete baseado no CEP informado
	 */
	function getCheckoutShippingItem() {
		let productId = 0;
		let quantity = 1;

		if (lastCartSummaryData && Array.isArray(lastCartSummaryData.items) && lastCartSummaryData.items.length) {
			const item = lastCartSummaryData.items[0];
			const rawProductId = item.product_id || item.productId || item.id;
			productId = parseInt(rawProductId, 10) || 0;
			quantity = parseInt(item.quantity, 10) || 1;
		}

		if (!productId) {
			const $firstCartItem = $('.woocommerce-checkout-review-order-table .cart_item').first();
			const domProductId = $firstCartItem.data('product_id') || $firstCartItem.attr('data-product_id');
			productId = parseInt(domProductId, 10) || 0;
		}

		return { productId, quantity };
	}

	function normalizeRateMode(mode) {
		const value = String(mode || '').toLowerCase();
		if (value === 'air' || value === 'aereo' || value === 'aéreo') {
			return 'air';
		}
		if (value === 'ground' || value === 'land' || value === 'terrestre') {
			return 'land';
		}
		return '';
	}

	/**
	 * Busca rates de frete para um item específico do carrinho
	 * Similar ao fetchRatesForItem do cart.js
	 */
	function fetchCheckoutRatesForItem(productId, quantity, postcode, nonce, ajaxUrl) {
		if (!productId || !postcode) {
			return Promise.resolve(null);
		}

		return $.ajax({
			url: ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'gstore_calculate_shipping',
				nonce: nonce,
				postcode: postcode,
				product_id: productId,
				quantity: quantity || 1,
			},
		}).then(function(response) {
			if (!response || !response.success || !response.data || !Array.isArray(response.data.rates)) {
				return null;
			}
			return response.data.rates;
		}).catch(function() {
			return null;
		});
	}

	function parsePriceValue(rawText) {
		if (!rawText) {
			return 0;
		}
		const text = String(rawText).trim();
		if (!text) {
			return 0;
		}
		const matches = text.match(/\d[\d.,]*/g) || [];
		const candidate = matches.length ? matches[matches.length - 1] : '';
		if (!candidate) {
			return 0;
		}
		const normalized = candidate
			.replace(/\.(?=\d{3})/g, '')
			.replace(',', '.');
		const value = parseFloat(normalized);
		return Number.isFinite(value) ? value : 0;
	}

	function formatCurrency(value) {
		const amount = Number.isFinite(value) ? value : 0;
		try {
			return new Intl.NumberFormat('pt-BR', {
				style: 'currency',
				currency: 'BRL',
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			}).format(amount);
		} catch (e) {
			return `R$ ${amount.toFixed(2)}`.replace('.', ',');
		}
	}

/**
 * Retorna totais para exibição a partir dos dados do backend (resumo do carrinho).
 * Inclui frete no total exibido quando lastSummaryTotals tem selectedTotal (frete escolhido).
 */
function getInstallmentDisplayTotals(summaryData) {
	const rawTotal = summaryData && summaryData.total ? parsePriceValue(summaryData.total) : 0;
	const baseTotal = summaryData && summaryData.base_total ? parsePriceValue(summaryData.base_total) : rawTotal;
	const shippingTotal = (lastSummaryTotals && Number.isFinite(lastSummaryTotals.selectedTotal)) ? lastSummaryTotals.selectedTotal : 0;
	const summaryTotal = (lastSummaryTotals && Number.isFinite(lastSummaryTotals.totalValue)) ? lastSummaryTotals.totalValue : rawTotal;
	// Quando há frete calculado no resumo, usar o total que já inclui frete e permitir somar frete nas parcelas
	const shouldAddShipping = shippingTotal > 0;
	const displayTotal = summaryTotal > 0 ? summaryTotal : rawTotal;

	return {
		rawTotal,
		displayTotal: displayTotal,
		baseTotal,
		shippingTotal: shippingTotal,
		shouldAddShipping: shouldAddShipping,
		summaryTotal: summaryTotal,
	};
}

	function getCartItemKeysFromSummary(data) {
		if (!data || !Array.isArray(data.items)) {
			return [];
		}
		const keys = [];
		data.items.forEach((item) => {
			const key = item.cart_item_key || item.cartItemKey || item.key || item.cartKey || '';
			if (key) {
				keys.push(String(key));
			}
		});
		return keys;
	}

	function getStoredShippingModeFromStorage(itemKeys) {
		if (typeof window === 'undefined' || !window.localStorage) {
			return '';
		}
		try {
			const raw = window.localStorage.getItem(CART_MODE_STORAGE_KEY);
			if (!raw) {
				return '';
			}
			const parsed = JSON.parse(raw);
			if (!parsed || typeof parsed !== 'object') {
				return '';
			}
			const keys = Array.isArray(itemKeys) ? itemKeys : [];
			if (keys.length) {
				for (let i = 0; i < keys.length; i += 1) {
					const mode = normalizeRateMode(parsed[keys[i]]);
					if (mode) {
						return mode;
					}
				}
			}
			const values = Object.keys(parsed).map((key) => normalizeRateMode(parsed[key]));
			const firstValid = values.find((mode) => mode);
			return firstValid || '';
		} catch (e) {
			return '';
		}
	}

	function getStoredShippingModeForItem(cartItemKey) {
		if (typeof window === 'undefined' || !window.localStorage || !cartItemKey) {
			return '';
		}
		try {
			const raw = window.localStorage.getItem(CART_MODE_STORAGE_KEY);
			if (!raw) {
				return '';
			}
			const parsed = JSON.parse(raw);
			if (!parsed || typeof parsed !== 'object') {
				return '';
			}
			return normalizeRateMode(parsed[cartItemKey] || '');
		} catch (e) {
			return '';
		}
	}

	function getStoredRatesByItem() {
		if (typeof window === 'undefined' || !window.localStorage) {
			return {};
		}
		try {
			const raw = window.localStorage.getItem(CART_RATES_STORAGE_KEY);
			if (!raw) {
				return {};
			}
			const parsed = JSON.parse(raw);
			if (!parsed || typeof parsed !== 'object') {
				return {};
			}
			const output = {};
			Object.keys(parsed).forEach((key) => {
				const rates = Array.isArray(parsed[key]) ? parsed[key] : [];
				output[key] = rates
					.map((rate) => ({
						mode: normalizeRateMode(rate.mode),
						label: rate.label || '',
						cost: rate.cost || '',
						cost_formatted: rate.cost_formatted || '',
					}))
					.filter((rate) => rate.mode);
			});
			return output;
		} catch (e) {
			return {};
		}
	}

	function persistShippingModeForItem(cartItemKey, mode) {
		if (typeof window === 'undefined' || !window.localStorage || !cartItemKey) {
			return;
		}
		const normalized = normalizeRateMode(mode);
		if (!normalized) {
			return;
		}
		let payload = {};
		try {
			const raw = window.localStorage.getItem(CART_MODE_STORAGE_KEY);
			payload = raw ? JSON.parse(raw) : {};
		} catch (e) {
			payload = {};
		}
		payload[cartItemKey] = normalized;
		try {
			window.localStorage.setItem(CART_MODE_STORAGE_KEY, JSON.stringify(payload));
		} catch (e) {
			// ignore storage errors
		}
	}

	function persistShippingModeToStorage(mode, itemKeys) {
		if (typeof window === 'undefined' || !window.localStorage) {
			return;
		}
		const normalized = normalizeRateMode(mode);
		if (!normalized) {
			return;
		}
		let payload = {};
		try {
			const raw = window.localStorage.getItem(CART_MODE_STORAGE_KEY);
			payload = raw ? JSON.parse(raw) : {};
		} catch (e) {
			payload = {};
		}
		const keys = Array.isArray(itemKeys) ? itemKeys : [];
		if (keys.length) {
			keys.forEach((key) => {
				payload[key] = normalized;
			});
		} else if (payload && typeof payload === 'object' && Object.keys(payload).length) {
			Object.keys(payload).forEach((key) => {
				payload[key] = normalized;
			});
		} else {
			payload.default = normalized;
		}
		try {
			window.localStorage.setItem(CART_MODE_STORAGE_KEY, JSON.stringify(payload));
		} catch (e) {
			// ignore storage errors
		}
	}

	function resolveCheckoutShippingMode(rates, preferredMode) {
		const normalizedPreferred = normalizeRateMode(preferredMode);
		const modes = (rates || []).map((rate) => normalizeRateMode(rate.mode)).filter(Boolean);
		if (normalizedPreferred && modes.includes(normalizedPreferred)) {
			return normalizedPreferred;
		}
		if (modes.includes('land')) {
			return 'land';
		}
		return modes[0] || 'land';
	}

	function getNormalizedRatesFromShippingData(data) {
		const rates = data && Array.isArray(data.rates) ? data.rates : [];
		return rates
			.map((rate) => {
				const mode = normalizeRateMode(rate.mode || rate.label || '');
				const label = rate.label || (mode === 'air' ? 'Frete Aéreo' : 'Frete Terrestre');
				return {
					mode,
					label,
					cost: rate.cost,
					cost_formatted: rate.cost_formatted || rate.costFormatted || '',
				};
			})
			.filter((rate) => rate.mode);
	}

	function getSelectedShippingCost(rates, selectedMode) {
		const normalized = normalizeRateMode(selectedMode);
		const selectedRate = (rates || []).find((rate) => normalizeRateMode(rate.mode) === normalized);
		if (!selectedRate) {
			return 0;
		}
		if (Number.isFinite(Number(selectedRate.cost))) {
			return Number(selectedRate.cost);
		}
		return parsePriceValue(selectedRate.cost_formatted || '');
	}

	function updateCheckoutShippingSelection(mode, shouldPersist) {
		const normalized = normalizeRateMode(mode) || 'land';
		checkoutSelectedShippingMode = normalized;
		// Invalida quotes de parcelamento (frete mudou, base de cálculo diferente)
		installmentQuotes = null;
		lastInstallmentQuotesSignature = '';
		if (shouldPersist) {
			const itemKeys = getCartItemKeysFromSummary(lastCartSummaryData);
			persistShippingModeToStorage(normalized, itemKeys);
		}
		
		// Atualiza campos hidden no formulário de checkout
		updateCheckoutShippingHiddenFields();
		
		renderShippingSummary(lastCartSummaryData);
	}

	/**
	 * Atualiza campos hidden no formulário de checkout com os dados de frete selecionados
	 * Esses campos são enviados ao backend via update_order_review
	 */
	function updateCheckoutShippingHiddenFields() {
		const $checkoutForm = $('form.checkout');
		if (!$checkoutForm.length) {
			return;
		}

		// Remove campos antigos
		$checkoutForm.find('input[name^="gstore_shipping_mode["]').remove();
		$checkoutForm.find('input[name^="gstore_shipping_rates["]').remove();

		// Adiciona campos para cada item do carrinho com frete selecionado
		const items = lastCartSummaryData && Array.isArray(lastCartSummaryData.items) ? lastCartSummaryData.items : [];
		
		items.forEach((item) => {
			const cartItemKey = item.key || item.cart_item_key || item.cartItemKey || '';
			if (!cartItemKey) {
				return;
			}

			// Obtém o modo de frete selecionado para este item
			const selectedMode = checkoutSelectedShippingByItem[cartItemKey] || 'land';
			
			// Obtém as rates disponíveis para este item
			const rates = checkoutShippingRatesByItem[cartItemKey] || [];
			
			if (rates.length > 0) {
				// Adiciona campo hidden com o modo selecionado
				$checkoutForm.append(
					$('<input>', {
						type: 'hidden',
						name: `gstore_shipping_mode[${cartItemKey}]`,
						value: selectedMode
					})
				);

				// Adiciona campo hidden com as rates (JSON)
				$checkoutForm.append(
					$('<input>', {
						type: 'hidden',
						name: `gstore_shipping_rates[${cartItemKey}]`,
						value: JSON.stringify(rates)
					})
				);
			}
		});

		// Adiciona campo shipping_method[0] com o rate ID correto do WooCommerce.
		// Garante que na submissão final do form, o WC reconheça o método escolhido.
		// IMPORTANTE: data-index="0" é obrigatório para que o JS nativo do WC
		// (update-checkout.js) leia corretamente o valor ao montar o POST de update_order_review.
		// Sem data-index, o WC ignora nosso campo e usa o radio/hidden nativo do template
		// cart-shipping.php, que pode ter o valor antigo (ex.: air quando o usuário escolheu land).
		const allModes = new Set();
		items.forEach((item) => {
			const key = item.key || item.cart_item_key || item.cartItemKey || '';
			if (key) {
				allModes.add(checkoutSelectedShippingByItem[key] || 'land');
			}
		});
		const suffix = allModes.size > 1 ? 'mixed' : (allModes.values().next().value || 'land');
		const gstoreRateId = 'gstore_custom_shipping:' + suffix;

		// Remove TODOS os inputs shipping_method[0] existentes (nossos E os nativos do WC)
		// para evitar conflito de valores no serialize() do form.
		$checkoutForm.find('input[name="shipping_method[0]"]').remove();
		$checkoutForm.append(
			$('<input>', {
				type: 'hidden',
				name: 'shipping_method[0]',
				'data-index': '0',
				'class': 'shipping_method',
				value: gstoreRateId
			})
		);

	}

	function syncShippingFromStorage(data) {
		checkoutShippingRatesByItem = getStoredRatesByItem();
		checkoutSelectedShippingByItem = {};
		const items = data && Array.isArray(data.items) ? data.items : [];
		items.forEach((item) => {
			const cartItemKey = item.key || item.cart_item_key || item.cartItemKey || '';
			if (!cartItemKey) {
				return;
			}
			const storedMode = getStoredShippingModeForItem(cartItemKey);
			checkoutSelectedShippingByItem[cartItemKey] = storedMode || 'land';
		});
	}

	function renderItemShippingOptions(data) {
		const items = data && Array.isArray(data.items) ? data.items : [];
		items.forEach((item) => {
			const cartItemKey = item.key || item.cart_item_key || item.cartItemKey || '';
			if (!cartItemKey) {
				return;
			}
			const $item = $(`.Gstore-summary-item[data-cart-item-key="${cartItemKey}"]`);
			const $slot = $item.find('[data-gstore-item-shipping]');
			if (!$slot.length) {
				return;
			}
			const rates = checkoutShippingRatesByItem[cartItemKey] || [];
			let selectedMode = checkoutSelectedShippingByItem[cartItemKey] || 'land';
			if (rates.length) {
				selectedMode = resolveCheckoutShippingMode(rates, selectedMode);
				checkoutSelectedShippingByItem[cartItemKey] = selectedMode;
			}
			const optionsHtml = rates.length
				? rates.map((rate) => {
						const label = rate.label || (rate.mode === 'air' ? 'Frete Aéreo' : 'Frete Terrestre');
						const cost = rate.cost_formatted || '-';
						const checked = normalizeRateMode(rate.mode) === selectedMode ? 'checked' : '';
						return `
							<label class="Gstore-checkout-item-shipping-option">
								<input type="radio" name="gstore_checkout_shipping_mode[${cartItemKey}]" data-cart-item-key="${cartItemKey}" value="${rate.mode}" ${checked} />
								<span class="Gstore-checkout-item-shipping-option__label">${label}</span>
								<span class="Gstore-checkout-item-shipping-option__price">${cost}</span>
							</label>
						`;
					}).join('')
				: `
					<label class="Gstore-checkout-item-shipping-option">
						<input type="radio" name="gstore_checkout_shipping_mode[${cartItemKey}]" data-cart-item-key="${cartItemKey}" value="land" ${selectedMode === 'land' ? 'checked' : ''} />
						<span class="Gstore-checkout-item-shipping-option__label">Frete Terrestre</span>
						<span class="Gstore-checkout-item-shipping-option__price">-</span>
					</label>
					<label class="Gstore-checkout-item-shipping-option">
						<input type="radio" name="gstore_checkout_shipping_mode[${cartItemKey}]" data-cart-item-key="${cartItemKey}" value="air" ${selectedMode === 'air' ? 'checked' : ''} />
						<span class="Gstore-checkout-item-shipping-option__label">Frete Aéreo</span>
						<span class="Gstore-checkout-item-shipping-option__price">-</span>
					</label>
				`;
			$slot.html(optionsHtml);
		});
	}

	function renderShippingSummary(data) {
		const $shippingSummary = $('[data-gstore-shipping-summary]');
		if (!$shippingSummary.length) {
			return;
		}
		const $options = $shippingSummary.find('[data-gstore-shipping-options]');
		const $totals = $shippingSummary.find('[data-gstore-shipping-totals]');

		const ratesByItem = checkoutShippingRatesByItem || {};
		const items = data && Array.isArray(data.items) ? data.items : [];
		const hasItemRates = Object.keys(ratesByItem).length > 0;
		const rates = checkoutShippingRates;
		let selectedMode = checkoutSelectedShippingMode;
		if (!hasItemRates && rates.length) {
			selectedMode = resolveCheckoutShippingMode(rates, selectedMode);
			checkoutSelectedShippingMode = selectedMode;
		}

		$options.html('');

		const subtotalValue = data && data.totals && data.totals.subtotal
			? parsePriceValue(data.totals.subtotal)
			: 0;
		const discountValue = data && data.totals && data.totals.discount
			? parsePriceValue(data.totals.discount)
			: 0;
		let selectedTotal = 0;
		let groundTotal = 0;
		let airTotal = 0;
		let selectedLandTotal = 0;
		let selectedAirTotal = 0;
		const selectedModes = new Set();

		if (hasItemRates && items.length) {
			items.forEach((item) => {
				const cartItemKey = item.key || item.cart_item_key || item.cartItemKey || '';
				if (!cartItemKey || !ratesByItem[cartItemKey]) {
					return;
				}
				const itemRates = ratesByItem[cartItemKey] || [];
				itemRates.forEach((rate) => {
					const mode = normalizeRateMode(rate.mode);
					const costValue = Number.isFinite(Number(rate.cost))
						? Number(rate.cost)
						: parsePriceValue(rate.cost_formatted || '');
					if (!mode || !Number.isFinite(costValue)) {
						return;
					}
					if (mode === 'land') {
						groundTotal += costValue;
					} else if (mode === 'air') {
						airTotal += costValue;
					}
				});
				const selectedModeForItem = checkoutSelectedShippingByItem[cartItemKey] || resolveCheckoutShippingMode(itemRates, 'land');
				selectedModes.add(selectedModeForItem);
				const selectedRate = itemRates.find((rate) => normalizeRateMode(rate.mode) === selectedModeForItem);
				if (selectedRate) {
					const selectedCost = Number.isFinite(Number(selectedRate.cost))
						? Number(selectedRate.cost)
						: parsePriceValue(selectedRate.cost_formatted || '');
					if (Number.isFinite(selectedCost)) {
						selectedTotal += selectedCost;
						if (selectedModeForItem === 'air') {
							selectedAirTotal += selectedCost;
						} else {
							selectedLandTotal += selectedCost;
						}
					}
				}
			});
		} else if (rates.length) {
			selectedTotal = getSelectedShippingCost(rates, selectedMode);
			if (selectedMode === 'air') {
				airTotal = selectedTotal;
				selectedAirTotal = selectedTotal;
			} else {
				groundTotal = selectedTotal;
				selectedLandTotal = selectedTotal;
			}
			selectedModes.add(selectedMode);
		}

		const fees = (data && data.totals && data.totals.fees && Array.isArray(data.totals.fees)) ? data.totals.fees : [];
		let feesTotal = 0;
		const otherFees = [];
		fees.forEach(function (fee) {
			const label = (fee && (fee.label || fee.name)) ? String(fee.label || fee.name).trim() : '';
			// Prioriza total_raw (numérico, preserva sinal negativo para descontos).
			const total = (fee && fee.total_raw !== undefined && Number.isFinite(Number(fee.total_raw)))
				? Number(fee.total_raw)
				: (fee && Number.isFinite(Number(fee.total)) ? Number(fee.total) : parsePriceValue((fee && fee.total) ? String(fee.total) : '0'));
			if (!label) return;
			const isFrete = label.toLowerCase().indexOf('frete') !== -1;
			if (isFrete) return;
			otherFees.push({ label: label, total: total });
			feesTotal += total;
		});

		const totalValue = subtotalValue + selectedTotal - discountValue + feesTotal;
		if (subtotalValue > 0) {
			$('.Gstore-checkout-summary-top__total-amount').html(formatCurrency(totalValue));
		}

		lastSummaryTotals = {
			subtotalValue,
			selectedTotal,
			totalValue,
			selectedModes: Array.from(selectedModes),
			selectedLandTotal,
			selectedAirTotal,
		};

		let totalsHtml = `
			<div class="Gstore-checkout-shipping-totals__row">
				<span>Subtotal</span>
				<span>${subtotalValue ? formatCurrency(subtotalValue) : (data && data.totals && data.totals.subtotal ? data.totals.subtotal : '-')}</span>
			</div>
		`;

		if (selectedModes.size > 1) {
			totalsHtml += `
				<div class="Gstore-checkout-shipping-totals__row">
					<span>Frete terrestre</span>
					<span>${selectedLandTotal ? formatCurrency(selectedLandTotal) : '-'}</span>
				</div>
				<div class="Gstore-checkout-shipping-totals__row">
					<span>Frete aéreo</span>
					<span>${selectedAirTotal ? formatCurrency(selectedAirTotal) : '-'}</span>
				</div>
			`;
		} else {
			const onlyMode = selectedModes.values().next().value || 'land';
			const singleValue = onlyMode === 'air' ? selectedAirTotal : selectedLandTotal;
			totalsHtml += `
				<div class="Gstore-checkout-shipping-totals__row">
					<span>${onlyMode === 'air' ? 'Frete aéreo' : 'Frete terrestre'}</span>
					<span>${singleValue ? formatCurrency(singleValue) : '-'}</span>
				</div>
			`;
		}

		otherFees.forEach(function (fee) {
			totalsHtml += `
				<div class="Gstore-checkout-shipping-totals__row">
					<span>${fee.label}</span>
					<span>${formatCurrency(fee.total)}</span>
				</div>
			`;
		});

		if (discountValue > 0) {
			totalsHtml += `
				<div class="Gstore-checkout-shipping-totals__row">
					<span>Desconto</span>
					<span>-${formatCurrency(discountValue)}</span>
				</div>
			`;
		}

		totalsHtml += `
			<div class="Gstore-checkout-shipping-totals__row Gstore-checkout-shipping-totals__row--total">
				<span>Total</span>
				<span>${subtotalValue ? formatCurrency(totalValue) : (data && (data.base_total || data.total) ? (data.base_total || data.total) : '-')}</span>
			</div>
		`;

		if (checkoutShippingStatus === 'loading') {
			totalsHtml += `
				<div class="Gstore-checkout-shipping-totals__hint">
					<i class="fa-solid fa-spinner fa-spin"></i>
					Calculando frete...
				</div>
			`;
		} else if (checkoutShippingStatus === 'error') {
			totalsHtml += `
				<div class="Gstore-checkout-shipping-totals__error">
					<i class="fa-solid fa-circle-exclamation"></i>
					${checkoutShippingError || 'Erro ao calcular frete.'}
				</div>
			`;
		}

		$totals.html(totalsHtml);
	}

	function updateOrderReviewTotals() {
		if (!lastSummaryTotals) {
			return;
		}
		const $orderReview = $('#order_review');
		const $table = $orderReview.find('.shop_table').first();
		if (!$table.length) {
			return;
		}
		const $orderTotal = $table.find('.order-total .woocommerce-Price-amount').first();
		const $subtotalRow = $table.find('.cart-subtotal').first();
		const $existing = $table.find('.gstore-shipping-land, .gstore-shipping-air');
		$existing.remove();

		const modes = lastSummaryTotals.selectedModes || [];
		let rowsHtml = '';
		if (modes.length > 1) {
			rowsHtml += `
				<tr class="gstore-shipping-land">
					<th>Frete terrestre</th>
					<td>${formatCurrency(lastSummaryTotals.selectedLandTotal || 0)}</td>
				</tr>
				<tr class="gstore-shipping-air">
					<th>Frete aéreo</th>
					<td>${formatCurrency(lastSummaryTotals.selectedAirTotal || 0)}</td>
				</tr>
			`;
		} else if (modes.length === 1) {
			const onlyMode = modes[0] === 'air' ? 'aéreo' : 'terrestre';
			const onlyValue = modes[0] === 'air' ? lastSummaryTotals.selectedAirTotal : lastSummaryTotals.selectedLandTotal;
			rowsHtml += `
				<tr class="gstore-shipping-${modes[0]}">
					<th>Frete ${onlyMode}</th>
					<td>${formatCurrency(onlyValue || 0)}</td>
				</tr>
			`;
		}

		if ($subtotalRow.length && rowsHtml) {
			$subtotalRow.after(rowsHtml);
		}
		if ($orderTotal.length) {
			$orderTotal.html(formatCurrency(lastSummaryTotals.totalValue || 0));
		}
	}

	function calculateShipping(postcode) {
		// Limpa CEP (remove caracteres não numéricos)
		const cleanCep = postcode.replace(/\D/g, '');
		
		// Valida CEP (deve ter 8 dígitos)
		if (cleanCep.length !== 8) {
			hideShippingResult();
			return;
		}

		// Evita múltiplos cálculos simultâneos
		if (isCalculatingShipping) {
			return;
		}

		lastRequestedShippingCep = cleanCep;
		isCalculatingShipping = true;
		showShippingLoading();

		// Prepara dados para AJAX
		const ajaxUrl = typeof gstoreShippingCalculator !== 'undefined' && gstoreShippingCalculator.ajaxUrl
			? gstoreShippingCalculator.ajaxUrl
			: (typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.ajax_url : '/wp-admin/admin-ajax.php');
		
		const nonce = typeof gstoreShippingCalculator !== 'undefined' && gstoreShippingCalculator.nonce
			? gstoreShippingCalculator.nonce
			: '';
		
		// Pega todos os itens do carrinho para calcular individualmente
		const items = lastCartSummaryData && Array.isArray(lastCartSummaryData.items) 
			? lastCartSummaryData.items 
			: [];
		
		// Se não há itens no resumo, usa abordagem fallback
		if (!items.length) {
			const checkoutItem = getCheckoutShippingItem();
			
			// Verifica se productId é válido
			if (!checkoutItem.productId || checkoutItem.productId === 0) {
				isCalculatingShipping = false;
				showShippingError('Não foi possível identificar os itens do carrinho. Atualize a página.');
				calculatedShipping = null;
				lastCalculatedShippingCep = '';
				return;
			}
			
			fetchCheckoutRatesForItem(checkoutItem.productId, checkoutItem.quantity, cleanCep, nonce, ajaxUrl)
				.then(function(rates) {
					isCalculatingShipping = false;
					if (rates && rates.length) {
						calculatedShipping = { rates: rates };
						lastCalculatedShippingCep = cleanCep;
						showShippingResult({ rates: rates });
						updateSummaryWithShipping({ rates: rates });
					} else {
						showShippingError('Não foi possível calcular o frete para este destino.');
						calculatedShipping = null;
						lastCalculatedShippingCep = '';
					}
				})
				.catch(function(err) {
					isCalculatingShipping = false;
					showShippingError('Erro ao calcular frete. Tente novamente.');
					calculatedShipping = null;
					lastCalculatedShippingCep = '';
				});
			return;
		}
		
		// Faz chamadas AJAX individuais para cada item do carrinho
		const requests = items.map(function(item) {
			const cartItemKey = item.key || item.cart_item_key || item.cartItemKey || '';
			const productId = parseInt(item.product_id || item.productId || item.id, 10) || 0;
			const quantity = parseInt(item.quantity, 10) || 1;
			
			return fetchCheckoutRatesForItem(productId, quantity, cleanCep, nonce, ajaxUrl)
				.then(function(rates) {
					return { cartItemKey: cartItemKey, productId: productId, rates: rates };
				});
		});
		
		Promise.all(requests).then(function(results) {
			isCalculatingShipping = false;
			
			let hasAnyRates = false;
			let firstDestination = null;
			
			// Limpa rates anteriores
			checkoutShippingRatesByItem = {};
			
			// Processa cada resultado individualmente
			results.forEach(function(result, index) {
				if (result && result.rates && result.rates.length > 0) {
					hasAnyRates = true;
					const normalizedRates = result.rates.map(function(rate) {
						return {
							mode: normalizeRateMode(rate.mode),
							label: rate.label || '',
							cost: rate.cost,
							cost_formatted: rate.cost_formatted || '',
						};
					}).filter(function(rate) { return rate.mode; });
					
					// Usa cartItemKey ou gera uma chave baseada no productId/index
					const key = result.cartItemKey || ('item_' + (result.productId || index));
					checkoutShippingRatesByItem[key] = normalizedRates;
				}
			});
			
			if (!hasAnyRates) {
				showShippingError('Não foi possível calcular o frete para este destino.');
				calculatedShipping = null;
				lastCalculatedShippingCep = '';
				lastCalculatedDestination = null;
				return;
			}
			
			// Salva os rates no localStorage para cada item
			if (typeof window !== 'undefined' && window.localStorage) {
				try {
					window.localStorage.setItem(CART_RATES_STORAGE_KEY, JSON.stringify(checkoutShippingRatesByItem));
				} catch (e) {
					// Ignora erros de storage
				}
			}
			
			lastCalculatedShippingCep = cleanCep;
			calculatedShipping = { ratesByItem: checkoutShippingRatesByItem };
			
			// Atualiza UI
			checkoutShippingStatus = 'ready';
			checkoutShippingError = '';
			renderItemShippingOptions(lastCartSummaryData);
			renderShippingSummary(lastCartSummaryData);
			updateSummaryWithShipping({ ratesByItem: checkoutShippingRatesByItem });
		}).catch(function() {
			isCalculatingShipping = false;
			showShippingError('Erro ao calcular frete. Tente novamente.');
			calculatedShipping = null;
			lastCalculatedShippingCep = '';
			lastCalculatedDestination = null;
		});
	}

	/**
	 * Mostra loading do cálculo de frete
	 */
	function showShippingLoading() {
		checkoutShippingStatus = 'loading';
		checkoutShippingError = '';
		renderShippingSummary(lastCartSummaryData);
	}

	/**
	 * Mostra resultado do frete calculado
	 */
	function showShippingResult(data) {
		const rates = Array.isArray(data.rates) ? data.rates : [];
		if (!rates.length) {
			showShippingError('Não foi possível calcular o frete para este destino.');
			return;
		}
		checkoutShippingStatus = 'ready';
		checkoutShippingError = '';
		checkoutShippingRates = getNormalizedRatesFromShippingData(data);
		const storedMode = getStoredShippingModeFromStorage(getCartItemKeysFromSummary(lastCartSummaryData));
		const preferredMode = storedMode || checkoutSelectedShippingMode || 'land';
		checkoutSelectedShippingMode = resolveCheckoutShippingMode(checkoutShippingRates, preferredMode);
		renderShippingSummary(lastCartSummaryData);
	}

	/**
	 * Mostra erro no cálculo de frete
	 */
	function showShippingError(message) {
		checkoutShippingStatus = 'error';
		checkoutShippingError = message || 'Erro ao calcular frete.';
		checkoutShippingRates = [];
		renderShippingSummary(lastCartSummaryData);
	}

	/**
	 * Esconde resultado do frete
	 */
	function hideShippingResult() {
		checkoutShippingStatus = 'idle';
		checkoutShippingError = '';
		checkoutShippingRates = [];
		renderShippingSummary(lastCartSummaryData);
	}

	function getDestinationLabel(destination) {
		if (!destination) return '';
		const city = destination.city ? String(destination.city).trim() : '';
		const state = destination.state ? String(destination.state).trim() : '';
		if (city && state) return `${city}/${state}`;
		return city || state || '';
	}

	/**
	 * Atualiza resumo com valor do frete
	 */
	function updateSummaryWithShipping(shippingData) {
		// Atualiza o endereço no WooCommerce para que ele calcule o frete oficialmente
		const $postcodeField = $('#billing_postcode');
		const $checkoutForm = $('form.checkout');
		
		if ($postcodeField.length && $postcodeField.val()) {
			// Garante que o campo de método de envio existe no formulário
			let $shippingMethodField = $checkoutForm.find('input[name="shipping_method[0]"]');
			if (!$shippingMethodField.length) {
				// Cria campo hidden para o método de envio
				$checkoutForm.append('<input type="hidden" name="shipping_method[0]" value="gstore_custom_shipping" />');
			} else {
				$shippingMethodField.val('gstore_custom_shipping');
			}
			
			// Dispara evento para atualizar checkout do WooCommerce
			// Isso fará com que o WooCommerce calcule o frete oficialmente
			persistSelectedPaymentMethod(resolveSelectedPaymentMethod($checkoutForm));
			$(document.body).trigger('update_checkout');
			// loadCartSummary já é chamado pelo handler global de updated_checkout
			// Após CEP/frete mudar, atualiza preview de parcelas e quotes
			$(document.body).one('updated_checkout', function() {
				setTimeout(function() {
					if (lastCartSummaryData) {
						updateInstallmentsPreview(lastCartSummaryData);
					}
					maybeFetchInstallmentQuotes();
				}, 200);
			});
		}

		renderShippingSummary(lastCartSummaryData);
	}

	/**
	 * Atualiza o resumo dos dados do cliente
	 */
	function updateReviewData() {
		// Dados pessoais
		const firstName = $('#billing_first_name').val() || '';
		const lastName = $('#billing_last_name').val() || '';
		const cpf = $('#billing_cpf').val() || '';
		const email = $('#billing_email').val() || '';
		const phone = $('#billing_phone').val() || '';

		let personalHtml = '';
		if (firstName || lastName) {
			personalHtml += `<p><strong>${firstName} ${lastName}</strong></p>`;
		}
		if (cpf) {
			personalHtml += `<p>CPF: ${cpf}</p>`;
		}
		if (email) {
			personalHtml += `<p>${email}</p>`;
		}
		if (phone) {
			personalHtml += `<p>${phone}</p>`;
		}
		$('#review-personal').html(personalHtml || '<p class="Gstore-checkout-review__empty">Dados não preenchidos</p>');

		// Endereço
		const address = $('#billing_address_1').val() || '';
		const number = $('#billing_number').val() || '';
		const complement = $('#billing_address_2').val() || '';
		const neighborhood = $('#billing_neighborhood').val() || '';
		const city = $('#billing_city').val() || '';
		const state = $('#billing_state').val() || '';
		const postcode = $('#billing_postcode').val() || '';

		let shippingHtml = '';
		if (address) {
			shippingHtml += `<p>${address}${number ? ', ' + number : ''}${complement ? ' - ' + complement : ''}</p>`;
		}
		if (neighborhood || city || state) {
			shippingHtml += `<p>${neighborhood}${neighborhood && city ? ' - ' : ''}${city}${state ? '/' + state : ''}</p>`;
		}
		if (postcode) {
			shippingHtml += `<p>CEP: ${postcode}</p>`;
		}
		$('#review-shipping').html(shippingHtml || '<p class="Gstore-checkout-review__empty">Endereço não preenchido</p>');
	}

	/**
	 * Define a etapa ativa
	 */
	function setActiveStep(index, shouldScroll = true) {
		if (index < 0 || index >= STEPS.length) return;

		currentStep = index;

		// Atualiza campo enviado ao backend para só carregar taxa de parcelamento na etapa 3
		const $stepInput = $('#gstore_checkout_step');
		if ($stepInput.length) $stepInput.val(index);

		// Garante que o método de pagamento persista entre etapas
		persistSelectedPaymentMethod(resolveSelectedPaymentMethod($checkoutForm));

		// Atualiza painéis
		$('.Gstore-checkout-step').removeClass('is-active')
			.eq(index).addClass('is-active');

		// Atualiza stepper
		$('.Gstore-checkout-stepper__step').each(function(i) {
			$(this).removeClass('is-active is-complete');
			if (i === index) {
				$(this).addClass('is-active');
			} else if (i < index) {
				$(this).addClass('is-complete');
			}
		});

		// Atualiza conectores
		$('.Gstore-checkout-stepper__connector').each(function(i) {
			$(this).toggleClass('is-complete', i < index);
		});

		// Scroll suave para o topo apenas quando solicitado
		if (shouldScroll) {
			$('html, body').animate({
				scrollTop: $('.Gstore-checkout-steps__content').offset().top - 100
			}, 300);
		}

		// Controla visibilidade do botão "Finalizar pedido"
		const lastStepIndex = STEPS.length - 1;
		const $placeOrderBtn = $('#place_order, .place-order');
		if ($placeOrderBtn.length) {
			if (index === lastStepIndex) {
				// Mostra o botão apenas na última etapa
				$placeOrderBtn.show();
			} else {
				// Esconde o botão em todas as outras etapas
				$placeOrderBtn.hide();
			}
		}

		// Atualiza quando entrar na última etapa
		// NÃO dispara update_checkout aqui pois nextStep() já o faz (evita duplo refresh que causa race conditions)
		if (index === lastStepIndex) {
			setTimeout(function() {
				updateOrderReviewTotals();
				
				// Garante que o botão place_order esteja visível e clicável
				const $placeOrderBtn = $('#place_order');
				if ($placeOrderBtn.length) {
					$placeOrderBtn.prop('disabled', false)
						.removeClass('disabled')
						.show();
				}
				
				// Remove class 'processing' se existir (pode ter ficado de tentativa anterior)
				$checkoutForm.removeClass('processing');
			}, 200);
		}

		// Ao entrar na etapa de dados, verifica/calcule o frete automaticamente se o CEP já estiver preenchido
		if (STEPS[index] && STEPS[index].id === 'contact') {
			setTimeout(ensureShippingAutofilled, 0);
		}

		// Trigger evento para outros scripts
		$(document.body).trigger('gstore_checkout_step_changed', [index, STEPS[index]]);
	}

	/**
	 * Valida os campos da etapa atual
	 */
	function validateCurrentStep() {
		const step = STEPS[currentStep];
		let isValid = true;
		let $firstError = null;

		// Se a etapa é escolha de método de pagamento, valida se um método foi selecionado
		if (step.id === 'payment-method') {
			const $paymentMethod = $('input[name="payment_method"]:checked');
			if (!$paymentMethod.length) {
				showNotice('Por favor, selecione um método de pagamento.', 'error');
				return false;
			}
			return true;
		}

		// Etapa final: valida aceite do contrato quando o checkbox estiver presente
		if (step.id === 'payment') {
			const $terms = $('#gstore_contract_terms');
			if ($terms.length && !$terms.is(':checked')) {
				showNotice('Você precisa aceitar os termos do contrato para finalizar o pedido.', 'error');
				$terms.focus();
				return false;
			}
		}

		step.fields.forEach(fieldId => {
			const $fieldWrapper = $(`#${fieldId}_field`);
			const $input = $fieldWrapper.find('input, select, textarea');
			
			if (!$input.length) return;

			const isRequired = $fieldWrapper.hasClass('validate-required') || 
			                   $input.prop('required') ||
			                   $input.attr('aria-required') === 'true';

			const value = $input.val() ? $input.val().trim() : '';

			// Remove estado de erro anterior
			$fieldWrapper.removeClass('woocommerce-invalid woocommerce-invalid-required-field');

			// Validação de campo obrigatório
			if (isRequired && !value) {
				isValid = false;
				$fieldWrapper.addClass('woocommerce-invalid woocommerce-invalid-required-field');
				if (!$firstError) $firstError = $input;
			}

			// Validação de email
			if (fieldId === 'billing_email' && value) {
				const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if (!emailRegex.test(value)) {
					isValid = false;
					$fieldWrapper.addClass('woocommerce-invalid woocommerce-invalid-email');
					if (!$firstError) $firstError = $input;
				}
			}

			// Validação de CPF - só valida se for obrigatório ou se houver valor preenchido
			if (fieldId === 'billing_cpf' && value) {
				const cpf = value.replace(/\D/g, '');
				if (cpf.length !== 11) {
					isValid = false;
					$fieldWrapper.addClass('woocommerce-invalid');
					if (!$firstError) $firstError = $input;
				}
			}
			// Se CPF não é obrigatório e está vazio, não valida
			// (não faz nada - já tratado acima com verificação de value)

			// Validação de CEP - só valida se for obrigatório ou se houver valor preenchido
			if (fieldId === 'billing_postcode') {
				// Só valida se houver valor preenchido (se estiver vazio e não for obrigatório, não valida)
				if (value && value.trim() !== '') {
					const cep = value.replace(/\D/g, '');
					if (cep.length !== 8) {
						isValid = false;
						$fieldWrapper.addClass('woocommerce-invalid');
						if (!$firstError) $firstError = $input;
					} else {
						// CEP válido, verifica se frete foi calculado
						if (!calculatedShipping) {
							isValid = false;
							$fieldWrapper.addClass('woocommerce-invalid');
							if (!$firstError) $firstError = $input;
							showNotice('Por favor, aguarde o cálculo do frete ou verifique se o CEP está correto.', 'error');
						} else if (lastCalculatedShippingCep && lastCalculatedShippingCep !== cep) {
							isValid = false;
							$fieldWrapper.addClass('woocommerce-invalid');
							if (!$firstError) $firstError = $input;
							const destinationLabel = getDestinationLabel(lastCalculatedDestination);
							const destinationText = destinationLabel
								? `para ${destinationLabel}`
								: 'para outro CEP';
							showNotice(`O frete foi calculado ${destinationText}. Atualize o CEP para recalcular.`, 'error');
						}
					}
				} else if (isRequired) {
					// CEP é obrigatório mas está vazio
					isValid = false;
					$fieldWrapper.addClass('woocommerce-invalid woocommerce-invalid-required-field');
					if (!$firstError) $firstError = $input;
				}
			}
		});

		// Foca no primeiro campo com erro
		if ($firstError) {
			$firstError.focus();
			
			// Mostra mensagem de erro apenas se não foi mostrada anteriormente
			if (isValid || !calculatedShipping) {
				// Mensagem já foi mostrada na validação do CEP
			} else {
				showNotice('Por favor, preencha todos os campos obrigatórios corretamente.', 'error');
			}
		}

		return isValid;
	}

	/**
	 * Mostra uma notificação
	 */
	function showNotice(message, type) {
		const $notice = $(`
			<div class="woocommerce-notice woocommerce-notice--${type} woocommerce-${type}" role="alert">
				${message}
			</div>
		`);

		// Remove notificações anteriores
		$('.Gstore-checkout-step.is-active .woocommerce-notice').remove();

		// Adiciona nova notificação
		$('.Gstore-checkout-step.is-active .Gstore-checkout-step__header').after($notice);

		// Remove após 5 segundos
		setTimeout(() => {
			$notice.fadeOut(300, function() {
				$(this).remove();
			});
		}, 5000);
	}

	/**
	 * Avança para a próxima etapa
	 */
	function nextStep() {
		if (!validateCurrentStep()) {
			return;
		}

		if (currentStep < STEPS.length - 1) {
			setActiveStep(currentStep + 1);
			$(document.body).trigger('update_checkout');
		}
	}

	/**
	 * Volta para a etapa anterior
	 */
	function prevStep() {
		if (currentStep > 0) {
			setActiveStep(currentStep - 1);
		}
	}

	/**
	 * Resolve o método de pagamento selecionado, com fallbacks seguros
	 * para casos em que o rádio ainda não existe/foi desmarcado no DOM.
	 */
	function resolveSelectedPaymentMethod($form) {
		if ($form && $form.length) {
			const $hidden = $form.find('input[name="payment_method"][type="hidden"]').first();
			if ($hidden.length && $hidden.val()) return $hidden.val();
		}

		const $selected = $('input[name="payment_method"]:checked');
		if ($selected.length) return $selected.val();
		
		if (lastSelectedPaymentMethod) return lastSelectedPaymentMethod;
		return '';
	}

	function persistSelectedPaymentMethod(method) {
		if (!method) return;
		lastSelectedPaymentMethod = method;
		const $checkoutForm = $('form.checkout');
		if (!$checkoutForm.length) return;
		const $hidden = $checkoutForm.find('input[name="payment_method"][type="hidden"]');
		const $radio = $checkoutForm
			.find('input[name="payment_method"]')
			.filter(function() {
				return $(this).val() === method;
			});
		if ($radio.length) $radio.prop('checked', true);
		if ($hidden.length) {
			$hidden.val(method);
		} else {
			$checkoutForm.append(
				$('<input>', {
					type: 'hidden',
					name: 'payment_method',
					id: 'gstore_payment_method_fallback',
					'data-gstore-fallback': '1',
					value: method
				})
			);
		}
	}

	/**
	 * Carrega o resumo do carrinho via AJAX.
	 * Preferência: window.gstoreCartSummary (plugin) para URL e nonce corretos do endpoint gstore_get_cart_summary.
	 */
	function loadCartSummary(onSuccess) {
		const installmentsValue = $('#gstore_blu_installments').val() || $('#gstore_blu_installments_select').val() || '';
		const $form = $('form.checkout').first();
		const paymentMethod = resolveSelectedPaymentMethod($form);
		const postData = $form.length ? $form.serialize() : '';
		const cartSummaryConfig = window.gstoreCartSummary;
		const ajaxUrl = (cartSummaryConfig && cartSummaryConfig.ajaxUrl) || (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.ajax_url) || '/wp-admin/admin-ajax.php';
		const nonce = (cartSummaryConfig && cartSummaryConfig.nonce) || (window.gstoreCheckout && window.gstoreCheckout.cartSummaryNonce) || '';
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'gstore_get_cart_summary',
				nonce: nonce,
				payment_method: paymentMethod,
				gstore_blu_installments: installmentsValue,
				post_data: postData
			},
			success: function(response) {
				if (response.success) {
					renderSummary(response.data);
					if (typeof onSuccess === 'function') {
						onSuccess();
					}
				}
			},
			error: function() {
				// Fallback: extrai do DOM
				extractSummaryFromDOM();
				if (typeof onSuccess === 'function') {
					onSuccess();
				}
			}
		});
	}

	/**
	 * Renderiza o resumo do carrinho
	 */
	function renderSummary(data) {
		lastCartSummaryData = data;

		// Se o Woo já esvaziou o carrinho (pedido Blu criado), não sobrescreve o topo com 0.
		// Reusa o último resumo não-vazio para manter os dados corretos.
		if (data && data.items_count === 0 && lastNonEmptyCartSummaryData) {
			data = lastNonEmptyCartSummaryData;
		} else if (data && data.items_count > 0) {
			lastNonEmptyCartSummaryData = data;
		}

		// Usa base_total (total sem taxa de parcelamento) para o topo e linha "Total".
		// O total real (com taxa) só aparece em "Você pagará" quando escolher parcelas.
		const baseTotal = data.base_total || data.total;

		// Seleção inicial do frete baseada no carrinho/localStorage
		const storedMode = getStoredShippingModeFromStorage(getCartItemKeysFromSummary(data));
		checkoutSelectedShippingMode = storedMode || checkoutSelectedShippingMode || 'land';

		// Atualiza contagem de itens
		$('.Gstore-summary-items-count').text(
			`${data.items_count} ${data.items_count === 1 ? 'item' : 'itens'} no carrinho`
		);

		// Atualiza total no topo (usa base_total)
		$('.Gstore-checkout-summary-top__total-amount').html(baseTotal);

		// Renderiza itens
		let itemsHtml = '';
		if (data.items && data.items.length) {
			data.items.forEach(item => {
				const cartItemKey = item.key || item.cart_item_key || item.cartItemKey || '';
				itemsHtml += `
					<div class="Gstore-summary-item" data-cart-item-key="${cartItemKey}">
						<img src="${item.image}" alt="${item.name}" class="Gstore-summary-item__image">
						<div class="Gstore-summary-item__info">
							<h4>${item.name}</h4>
							<span>Qtd: ${item.quantity}</span>
						</div>
						<span class="Gstore-summary-item__price">${item.subtotal}</span>
						<div class="Gstore-summary-item__shipping" data-gstore-item-shipping></div>
					</div>
				`;
			});
		}
		$('.Gstore-checkout-summary-top__items').html(itemsHtml);

		// Sincroniza frete e renderiza resumo ANTES de montar totalsHtml
		// para que lastSummaryTotals esteja disponível em getInstallmentDisplayTotals
		syncShippingFromStorage(data);
		renderItemShippingOptions(data);
		renderShippingSummary(data);

		// Renderiza totais
		let totalsHtml = '';

		// Método de pagamento selecionado (Pix, Cartão, etc.)
		if (data.payment_method_title) {
			totalsHtml += `
				<div class="Gstore-summary-row">
					<span>Pagamento</span>
					<span>${data.payment_method_title}</span>
				</div>
			`;
		}

		// "Você pagará" só aparece quando cartão Blu e parcelas > 1, mostrando o total real com taxa
		// Usa getInstallmentDisplayTotals para incluir frete quando o API retorna total sem frete
		const selectedN = parseInt((data.installments && data.installments.selected) ? data.installments.selected : '1', 10) || 1;
		if (data.payment_method === 'blu_checkout' && selectedN > 1) {
			const displayTotals = getInstallmentDisplayTotals(data);
			const totalToShow = displayTotals.displayTotal > 0 ? displayTotals.displayTotal : parsePriceValue(data.total || 0);
			const perToShow = totalToShow / selectedN;
			const perText = perToShow > 0 ? formatCurrency(perToShow) : (data.installments && data.installments.per_installment) || '';
			const totalText = totalToShow > 0 ? formatCurrency(totalToShow) : (data.total || '');
			totalsHtml += `
				<div class="Gstore-summary-row Gstore-summary-row--payable">
					<span>Você pagará</span>
					<span><strong>${selectedN}x de ${perText}</strong> — ${totalText}</span>
				</div>
			`;
		}

		$('.Gstore-checkout-summary-top__totals').html(totalsHtml);

		updateCheckoutShippingHiddenFields();
		updateInstallmentsPreview(data);
		setTimeout(maybeFetchInstallmentQuotes, 0);

		// Garante visibilidade correta do parcelamento baseado no método de pagamento do backend
		// (resolve race condition onde o elemento fica visível ao trocar de Cartão para PIX)
		const isCardMethod = data.payment_method === 'blu_checkout';
		$('.Gstore-blu-installments').toggle(isCardMethod);
		
		// FIX: Chamar updateOrderReviewTotals AQUI, após lastSummaryTotals ser definido
		// em vez de no evento updated_checkout com setTimeout(0) que executava antes do AJAX completar
		updateOrderReviewTotals();
	}

	function updateInstallmentsPreview(data) {
		const $preview = $('.Gstore-blu-installments__preview');
		if (!$preview.length) return;

		if (!data || !data.installments || !data.installments.selected) {
			$preview.text('');
			return;
		}

		if (data.payment_method !== 'blu_checkout') {
			$preview.html('');
			return;
		}

		const selected = parseInt(data.installments.selected, 10) || 1;
	const displayTotals = getInstallmentDisplayTotals(data);
		if (selected <= 1) {
			$preview.html('');
			return;
		}

		// Detecta se existe “Taxa de parcelamento” na resposta
		let hasFee = false;
		if (data.totals && data.totals.fees && Array.isArray(data.totals.fees)) {
			hasFee = data.totals.fees.some(f => f && f.label && String(f.label).toLowerCase().indexOf('taxa') !== -1);
		}

	const suffix = hasFee ? ' (valores já com taxa — a taxa pode variar conforme as parcelas)' : '';
	const totalValue = displayTotals.displayTotal || displayTotals.rawTotal;
	const perValue = totalValue > 0 ? (totalValue / selected) : 0;
	const perText = perValue > 0 ? formatCurrency(perValue) : data.installments.per_installment;
	const totalText = totalValue > 0 ? formatCurrency(totalValue) : data.total;
	$preview.html(`${selected}x de <strong>${perText}</strong> — total <strong>${totalText}</strong>${suffix}`);
	}

	/**
	 * Extrai resumo do DOM (fallback)
	 */
	function extractSummaryFromDOM() {
		const $orderReview = $('.woocommerce-checkout-review-order-table');
		
		if (!$orderReview.length) return;

		// Conta itens
		const itemsCount = $orderReview.find('.cart_item').length;
		$('.Gstore-summary-items-count').text(
			`${itemsCount} ${itemsCount === 1 ? 'item' : 'itens'} no carrinho`
		);

		// Total
		const total = $orderReview.find('.order-total .amount').html();
		if (total) {
			$('.Gstore-checkout-summary-top__total-amount').html(total);
		}

		// Pagamento (fallback): tenta ler o label do método selecionado
		let paymentTitle = '';
		const $checked = $('input[name="payment_method"]:checked');
		if ($checked.length) {
			// Woo padrão: label dentro do <li> do método
			paymentTitle = $checked.closest('li').find('label').first().text().trim();
			// Layout unificado Blu pode ter label customizado
			if (!paymentTitle) {
				paymentTitle = $checked.closest('.Gstore-blu-payment-option').find('span').first().text().trim();
			}
		}
		if (paymentTitle) {
			const $totals = $('.Gstore-checkout-summary-top__totals');
			if ($totals.length) {
				// Evita duplicar se o resumo já tiver a linha
				const hasRow = $totals.find('.Gstore-summary-row').filter(function() {
					return $(this).find('span').first().text().trim() === 'Pagamento';
				}).length > 0;
				if (!hasRow) {
					$totals.prepend(`
						<div class="Gstore-summary-row">
							<span>Pagamento</span>
							<span>${paymentTitle}</span>
						</div>
					`);
				}
			}
		}

		renderShippingSummary(lastCartSummaryData);
	}

	/**
	 * Vincula eventos
	 */
	function bindEvents() {
		// Navegação entre etapas
		$(document).on('click', '[data-action="next"]', function(e) {
			e.preventDefault();
			nextStep();
		});

		$(document).on('click', '[data-action="prev"]', function(e) {
			e.preventDefault();
			prevStep();
		});

		// Clique no stepper
		$(document).on('click', '.Gstore-checkout-stepper__step', function(e) {
			e.preventDefault();
			const index = parseInt($(this).data('step-index'), 10);
			
			// Só permite ir para etapas anteriores ou validar para ir para próximas
			if (index < currentStep) {
				setActiveStep(index);
			} else if (index === currentStep + 1) {
				nextStep();
			}
		});

		// Quando a aba volta ao foco, recarrega o resumo para sincronizar método/totais
		document.addEventListener('visibilitychange', function() {
			if (!document.hidden) {
				loadCartSummary();
			}
		});

		// Toggle do resumo
		$(document).on('click', '.Gstore-checkout-summary-top__toggle', function() {
			const $toggle = $(this);
			const $details = $('.Gstore-checkout-summary-top__details');
			
			$toggle.toggleClass('is-open');
			$details.toggleClass('is-visible');
			
			// Atualiza texto e ícone
			const isOpen = $toggle.hasClass('is-open');
			$toggle.html(
				(isOpen ? 'Ocultar detalhes' : 'Ver detalhes') +
				' <i class="fa-solid fa-chevron-down"></i>'
			);
		});

		// Remove erro do checkbox de contrato quando marcado
		$(document).on('change', '#gstore_contract_terms', function() {
			if ($(this).is(':checked')) {
				$(this).closest('.gstore-contract-terms').removeClass('woocommerce-invalid');
			}
		});

		// Abre modal de termos do contrato
		$(document).on('click', '.gstore-contract-open-modal', function(e) {
			e.preventDefault();
			openContractTermsModal();
		});

		// Fecha modal de termos
		$(document).on('click', '#gstore-contract-terms-modal [data-action="close-contract-modal"]', function() {
			closeContractTermsModal();
		});

		// Seleção do frete por item no resumo
		$(document).on('change', 'input[name^="gstore_checkout_shipping_mode["]', function() {
			const cartItemKey = $(this).data('cart-item-key') || String($(this).attr('name') || '').replace(/^gstore_checkout_shipping_mode\[|\]$/g, '');
			const value = $(this).val();
			if (cartItemKey) {
				const normalizedMode = normalizeRateMode(value) || 'land';
				checkoutSelectedShippingByItem[cartItemKey] = normalizedMode;
				persistShippingModeForItem(cartItemKey, value);

				// Invalida cache de quotes de parcelamento (frete mudou, base de cálculo diferente)
				installmentQuotes = null;
				lastInstallmentQuotesSignature = '';
				
				// Atualiza campo hidden no formulário de checkout para enviar ao backend
				updateCheckoutShippingHiddenFields();
				
				// CORREÇÃO: Adiciona campo hidden global para garantir que o modo seja enviado
				// mesmo quando rates por item não estão disponíveis
				const $checkoutForm = $('form.checkout');
				if ($checkoutForm.length) {
					// Remove campo global antigo se existir
					$checkoutForm.find('input[name="gstore_shipping_mode"]').remove();
					// Adiciona novo campo global com o modo selecionado
					$checkoutForm.append(
						$('<input>', {
							type: 'hidden',
							name: 'gstore_shipping_mode',
							value: normalizedMode
						})
					);
					const globalInputCount = $checkoutForm.find('input[name="gstore_shipping_mode"]').length;
					console.log('[Gstore DEBUG] Modo de frete selecionado:', {
						cartItemKey: cartItemKey,
						normalizedMode: normalizedMode,
						hasCheckoutForm: $checkoutForm.length > 0,
						globalModeInputCount: globalInputCount,
						formAction: $checkoutForm.attr('action') || 'N/A'
					});
				} else {
					console.error('[Gstore DEBUG] ERRO: form.checkout não encontrado!');
				}

				
				// Dispara update_checkout para enviar os dados ao backend e recalcular
				$(document.body).trigger('update_checkout');
				
				// Após o checkout ser atualizado, recarrega o resumo e só então atualiza as quotes de parcelamento
				// (o refetch deve ser após loadCartSummary completar, senão o backend ainda não tem o novo frete)
				$(document.body).one('updated_checkout', function() {
					setTimeout(function() {
						loadCartSummary(function() {
							// Resumo já tem totais com o novo frete; agora busca quotes de parcelamento
							if (lastCartSummaryData) {
								updateInstallmentsPreview(lastCartSummaryData);
							}
							maybeFetchInstallmentQuotes();
						});
					}, 100);
				});
				
				renderShippingSummary(lastCartSummaryData);
			}
		});


		// Atualiza resumo quando checkout é atualizado
		$(document.body).on('updated_checkout', function() {
			// Restaura seleção antes de carregar o resumo (evita default do Woo após fragments)
			if (lastSelectedPaymentMethod) {
				const $radio = $('input[name="payment_method"]').filter(function() {
					return $(this).val() === lastSelectedPaymentMethod;
				});
				if ($radio.length && !$radio.is(':checked')) {
					$radio.prop('checked', true);
				}
				const $hiddenPayment = $('form.checkout')
					.find('input[name="payment_method"][type="hidden"]')
					.first();
				if ($hiddenPayment.length && $hiddenPayment.val() !== lastSelectedPaymentMethod) {
					$hiddenPayment.val(lastSelectedPaymentMethod);
				}
			}
			loadCartSummary();
			// O WooCommerce pode re-renderizar fragments; garante que o DOM continue dentro das etapas
			setTimeout(organizeFields, 0);
			setTimeout(ensureBluInstallmentsUI, 0);
			// REMOVIDO: setTimeout(updateOrderReviewTotals, 0) - movido para dentro de renderSummary()
			// para executar APÓS lastSummaryTotals ser definido pelo AJAX
			
			// Atualiza campos hidden de frete após o checkout ser atualizado (para próxima vez)
			setTimeout(updateCheckoutShippingHiddenFields, 100);
			
			// Garante que o botão "Finalizar pedido" esteja visível apenas na última etapa
			const lastStepIndex = STEPS.length - 1;
			const $placeOrderBtn = $('#place_order, .place-order');
			if ($placeOrderBtn.length) {
				if (currentStep === lastStepIndex) {
					$placeOrderBtn.show();
				} else {
					$placeOrderBtn.hide();
				}
			}
		});

		// Toggle para observações do pedido
		$(document).on('change', '#toggle-order-notes', function() {
			const $container = $('.Gstore-checkout-notes-container');
			if ($(this).is(':checked')) {
				$container.slideDown(200);
			} else {
				$container.slideUp(200);
				// Limpa o campo quando esconde
				$container.find('textarea').val('');
			}
		});

		// Botões de editar no resumo
		$(document).on('click', '.Gstore-checkout-review__edit', function(e) {
			e.preventDefault();
			const stepIndex = parseInt($(this).data('goto-step'), 10);
			setActiveStep(stepIndex);
		});

		// Máscara para CPF
		$(document).on('input', '#billing_cpf', function() {
			let value = $(this).val().replace(/\D/g, '');
			if (value.length > 11) value = value.slice(0, 11);
			
			if (value.length > 9) {
				value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
			} else if (value.length > 6) {
				value = value.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
			} else if (value.length > 3) {
				value = value.replace(/(\d{3})(\d{1,3})/, '$1.$2');
			}
			
			$(this).val(value);
		});

		// Máscara para CEP e cálculo automático de frete
		$(document).on('input', '#billing_postcode', function() {
			let value = $(this).val().replace(/\D/g, '');
			if (value.length > 8) value = value.slice(0, 8);
			
			if (value.length > 5) {
				value = value.replace(/(\d{5})(\d{1,3})/, '$1-$2');
			}
			
			$(this).val(value);
			
			// Limpa resultado anterior quando CEP muda
			if (value.replace(/\D/g, '').length < 8) {
				hideShippingResult();
				calculatedShipping = null;
				lastCalculatedDestination = null;
				lastCalculatedShippingCep = '';
			}
		});

		// Calcula frete quando CEP perde o foco e está completo
		$(document).on('blur', '#billing_postcode', function() {
			const cep = $(this).val().replace(/\D/g, '');
			if (cep.length === 8) {
				// Aguarda um pouco para garantir que a máscara foi aplicada
				setTimeout(function() {
					calculateShipping($('#billing_postcode').val());
				}, 300);
			} else {
				hideShippingResult();
				calculatedShipping = null;
			}
		});

		// Máscara para telefone
		$(document).on('input', '#billing_phone', function() {
			let value = $(this).val().replace(/\D/g, '');
			if (value.length > 11) value = value.slice(0, 11);
			
			if (value.length > 10) {
				value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
			} else if (value.length > 6) {
				value = value.replace(/(\d{2})(\d{4,5})(\d{0,4})/, '($1) $2-$3');
			} else if (value.length > 2) {
				value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
			}
			
			$(this).val(value);
		});

		// Garante que o botão de finalizar pedido funcione corretamente
		$(document).on('click', '#place_order', function(e) {
			const lastStepIndex = STEPS.length - 1;
			if (currentStep !== lastStepIndex) {
				e.preventDefault();
				setActiveStep(lastStepIndex);
				return false;
			}
			
			const $paymentMethod = $('input[name="payment_method"]:checked');
			if (!$paymentMethod.length) {
				e.preventDefault();
				showNotice('Por favor, selecione um método de pagamento.', 'error');
				return false;
			}

			// Valida aceite do contrato quando o checkbox estiver presente
			const $terms = $('#gstore_contract_terms');
			if ($terms.length && !$terms.is(':checked')) {
				e.preventDefault();
				showNotice('Você precisa aceitar os termos do contrato para finalizar o pedido.', 'error');
				$terms.closest('.gstore-contract-terms').addClass('woocommerce-invalid');
				$terms.focus();
				return false;
			}
			
			if ($checkoutForm.hasClass('processing')) {
				e.preventDefault();
				return false;
			}
			
			e.preventDefault();
			
			if (typeof showProcessingModal === 'function') {
				showProcessingModal();
			}
			
			setTimeout(function() {
				submitCheckoutDirectly();
			}, 200);
		});

		/**
		 * Mostra o modal de processamento
		 */
		function showProcessingModal() {
			// Remove modal existente se houver
			$('.Gstore-processing-modal').remove();
			
			const modalHtml = `
				<div class="Gstore-processing-modal">
					<div class="Gstore-processing-modal__backdrop"></div>
					<div class="Gstore-processing-modal__content">
						<button class="Gstore-processing-modal__close" aria-label="Fechar modal">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<path d="M18 6L6 18M6 6l12 12"/>
							</svg>
						</button>
						<div class="Gstore-processing-modal__spinner">
							<div class="Gstore-spinner"></div>
						</div>
						<div class="Gstore-processing-modal__text">
							<h3>Processando seu pedido...</h3>
							<p>Aguarde enquanto preparamos seu pagamento seguro.</p>
						</div>
						<div class="Gstore-processing-modal__steps">
							<div class="Gstore-processing-step is-active" data-step="1">
								<i class="fa-solid fa-circle-check"></i>
								<span>Validando dados</span>
							</div>
							<div class="Gstore-processing-step" data-step="2">
								<i class="fa-solid fa-circle"></i>
								<span>Criando pedido</span>
							</div>
							<div class="Gstore-processing-step" data-step="3">
								<i class="fa-solid fa-circle"></i>
								<span>Redirecionando para pagamento</span>
							</div>
						</div>
					</div>
				</div>
			`;
			
			$('body').append(modalHtml);
			
			// Adiciona event listener para o botão de fechar
			$('.Gstore-processing-modal__close').on('click', function() {
				hideProcessingModal();
			});
			
			// Anima a entrada
			setTimeout(function() {
				$('.Gstore-processing-modal').addClass('is-visible');
			}, 10);
			
			// Avança os passos automaticamente para dar feedback visual
			setTimeout(function() {
				updateProcessingStep(2);
			}, 800);
		}

		/**
		 * Atualiza o passo do modal de processamento
		 */
		function updateProcessingStep(step) {
			$('.Gstore-processing-step').each(function() {
				const $step = $(this);
				const stepNum = parseInt($step.data('step'));
				
				if (stepNum < step) {
					$step.removeClass('is-active').addClass('is-complete');
					$step.find('i').removeClass('fa-circle fa-circle-notch fa-spin').addClass('fa-circle-check');
				} else if (stepNum === step) {
					$step.addClass('is-active');
					$step.find('i').removeClass('fa-circle fa-circle-check').addClass('fa-circle-notch fa-spin');
				}
			});
		}

		/**
		 * Mostra sucesso no modal antes de redirecionar
		 */
		function showProcessingSuccess() {
			updateProcessingStep(4); // Marca todos como completos
			
			$('.Gstore-processing-modal__text h3').text('Pedido criado com sucesso!');
			$('.Gstore-processing-modal__text p').text('Redirecionando para o pagamento seguro...');
			$('.Gstore-processing-modal__spinner .Gstore-spinner').replaceWith(
				'<i class="fa-solid fa-circle-check Gstore-success-icon"></i>'
			);
		}

		/**
		 * Esconde o modal de processamento
		 */
		function hideProcessingModal() {
			$('.Gstore-processing-modal').removeClass('is-visible');
			setTimeout(function() {
				$('.Gstore-processing-modal').remove();
			}, 300);
		}

		/**
		 * Submete o checkout diretamente
		 */
		function refreshAndSubmit() {
			submitCheckoutDirectly();
		}

		/**
		 * Submit direto do checkout via AJAX
		 */
		function submitCheckoutDirectly() {
			const $form = $('form.checkout');
			if (!$form.length) return;
			if ($form.hasClass('processing')) return;

			// Garante que os campos de frete (shipping_method[0], gstore_shipping_mode) estejam no form
			// com a escolha atual antes de coletar os dados (evita frete errado no Pix).
			if (typeof updateCheckoutShippingHiddenFields === 'function') {
				updateCheckoutShippingHiddenFields();
			}
			
			const requiredFields = ['billing_email'];
			
			let missingFields = [];
			
			requiredFields.forEach(function(field) {
				const $field = $form.find('#' + field);
				// Verifica se o campo existe e se é obrigatório
				if ($field.length) {
					const $fieldWrapper = $field.closest('.form-row, .woocommerce-input-wrapper, #' + field + '_field');
					const isFieldRequired = $fieldWrapper.hasClass('validate-required') || 
					                        $field.prop('required') ||
					                        $field.attr('aria-required') === 'true';
					
					// Só considera obrigatório se o campo realmente estiver marcado como obrigatório
					if (isFieldRequired && (!$field.val() || $field.val().trim() === '')) {
						missingFields.push(field);
					}
				}
			});
			
			$form.addClass('processing');

			// Bloqueia o formulário visualmente
			$form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			// Coleta campos do formulário
			const formDataObj = {};
			
			// 1. Coleta campos de billing com valor
			$('[id^="billing_"]').each(function() {
				const $input = $(this);
				const name = $input.attr('name') || $input.attr('id');
				if (!name) return;
				
				const value = $input.val() ? $input.val().trim() : '';
				if (value) {
					formDataObj[name] = $input.val();
				}
			});
			
			// 2. Coleta campos de shipping (se houver)
			$('[id^="shipping_"]').each(function() {
				const $input = $(this);
				const name = $input.attr('name') || $input.attr('id');
				if (name && $input.val()) {
					formDataObj[name] = $input.val();
				}
			});
			
			// 3. Coleta payment_method - prioriza a seleção persistida (mais confiável que radio state)
			if (lastSelectedPaymentMethod) {
				formDataObj['payment_method'] = lastSelectedPaymentMethod;
			} else {
				const $paymentRadio = $('input[name="payment_method"]:checked');
				if ($paymentRadio.length) {
					formDataObj['payment_method'] = $paymentRadio.val();
				} else {
					// Fallback: hidden input dentro do form
					const $hiddenPM = $('form.checkout').find('input[name="payment_method"][type="hidden"]').first();
					if ($hiddenPM.length && $hiddenPM.val()) {
						formDataObj['payment_method'] = $hiddenPM.val();
					} else {
						// Último fallback: primeiro método disponível
						const $firstPayment = $('input[name="payment_method"]').first();
						if ($firstPayment.length) {
							formDataObj['payment_method'] = $firstPayment.val();
						}
					}
				}
			}
			
			// 4. Coleta o nonce - procura em TODOS os lugares possíveis
			let nonceValue = null;
			
			// Procura em toda a página (os campos podem estar em qualquer lugar)
			const nonceSelectors = [
				'#woocommerce-process-checkout-nonce',
				'input[name="woocommerce-process-checkout-nonce"]',
				'#_wpnonce',
				'input[name="_wpnonce"]'
			];
			
			for (let selector of nonceSelectors) {
				const $el = $(selector);
				if ($el.length && $el.val()) {
					nonceValue = $el.val();
					break;
				}
			}
			
			// Fallback: procura qualquer input com "nonce" no nome
			if (!nonceValue) {
				$('input').each(function() {
					const name = $(this).attr('name') || '';
					const id = $(this).attr('id') || '';
					if ((name.indexOf('nonce') !== -1 || id.indexOf('nonce') !== -1) && $(this).val()) {
						nonceValue = $(this).val();
						return false;
					}
				});
			}

			// Fallback preferencial: nonce específico para process_checkout exposto pelo tema
			if (!nonceValue && window.gstoreCheckout && window.gstoreCheckout.processCheckoutNonce) {
				nonceValue = window.gstoreCheckout.processCheckoutNonce;
			}
			
			// Último fallback: nonce de update_order_review (não é o ideal para process_checkout)
			if (!nonceValue && typeof wc_checkout_params !== 'undefined') {
				if (wc_checkout_params.update_order_review_nonce) {
					nonceValue = wc_checkout_params.update_order_review_nonce;
				}
			}

			if (nonceValue) {
				formDataObj['woocommerce-process-checkout-nonce'] = nonceValue;
				formDataObj['_wpnonce'] = nonceValue;
			}
			
			// 5. Coleta campos hidden importantes
			$('input[type="hidden"]').each(function() {
				const $input = $(this);
				const name = $input.attr('name');
				if (name && $input.val()) {
					// Inclui apenas campos relevantes para o checkout
					if (name.indexOf('wc_') === 0 || 
					    name.indexOf('woocommerce') === 0 || 
					    name.indexOf('_wp') === 0 ||
						name.indexOf('gstore_') === 0 || // Campos do GStore (parcelas, etc.)
					    name === 'terms' ||
					    name === 'terms-field' ||
					    name === 'ship_to_different_address') {
						formDataObj[name] = $input.val();
					}
				}
			});
			
			// 5.1 Garante que o valor das parcelas Blu seja coletado (mesmo se estiver fora do form)
			const $bluInstallments = $('#gstore_blu_installments');
			if ($bluInstallments.length && $bluInstallments.val()) {
				formDataObj['gstore_blu_installments'] = $bluInstallments.val();
			}
			
			// 6. Coleta campos do formulário original que ainda existem
			$form.find('input, select, textarea').each(function() {
				const $input = $(this);
				const name = $input.attr('name');
				if (!name || formDataObj[name]) return;
				
				if ($input.is(':checkbox')) {
					if ($input.is(':checked')) {
						formDataObj[name] = $input.val() || '1';
					}
				} else if ($input.is(':radio')) {
					if ($input.is(':checked')) {
						formDataObj[name] = $input.val();
					}
				} else {
					const val = $input.val();
					if (val) formDataObj[name] = val;
				}
			});
			
			// 7. Garante campos obrigatórios para o WooCommerce
			formDataObj['woocommerce_checkout_place_order'] = '1';

			// 7.1 Força país como BR (campo removido do form, mas WooCommerce precisa para validar endereço)
			if (!formDataObj['billing_country']) {
				formDataObj['billing_country'] = 'BR';
			}
			if (!formDataObj['shipping_country']) {
				formDataObj['shipping_country'] = 'BR';
			}
			
			// Converte para query string
			let formData = $.param(formDataObj);

			$.ajax({
				type: 'POST',
				url: wc_checkout_params.checkout_url,
				data: formData,
				dataType: 'json',
				success: function(response) {
					updateProcessingStep(3);
					
					if (response.result === 'success') {
						setTimeout(function() { showProcessingSuccess(); }, 500);
						if (isBluCheckoutSelected()) {
							setTimeout(function() {
								hideProcessingModal();
								openBluCheckoutModal(response.redirect);
							}, 900);
						} else {
							setTimeout(function() { window.location.href = response.redirect; }, 1500);
						}
					} else if (response.result === 'failure') {
						hideProcessingModal();
						$form.removeClass('processing').unblock();
						
						if (response.refresh) {
							$(document.body).trigger('update_checkout');
						}
						
						if (response.messages) {
							$('.woocommerce-notices-wrapper, .woocommerce-error').remove();
							const $activeStep = $('.Gstore-checkout-step.is-active');
							if ($activeStep.length) {
								$activeStep.find('.Gstore-checkout-step__header').after(
									'<div class="woocommerce-notices-wrapper">' + response.messages + '</div>'
								);
							} else {
								$form.prepend('<div class="woocommerce-notices-wrapper">' + response.messages + '</div>');
							}
							$('html, body').animate({
								scrollTop: $('.Gstore-checkout-steps__content').offset().top - 100
							}, 500);
						}
						
						if (response.reload) {
							setTimeout(function() { window.location.reload(); }, 2000);
						}
					}
				},
				error: function() {
					hideProcessingModal();
					$form.removeClass('processing').unblock();
					showNotice('Ocorreu um erro ao processar o pedido. Por favor, tente novamente.', 'error');
				}
			});
		}

	}

	function openContractTermsModal() {
		let $modal = $('#gstore-contract-terms-modal');
		if (!$modal.length) return;

		// Garante overlay em tela cheia: modal precisa estar no body (não dentro de containers com transform).
		if (!$modal.parent().is('body')) {
			$modal.detach().appendTo('body');
			$modal = $('#gstore-contract-terms-modal');
		}

		const contractSettings = getContractSettings();
		const title = contractSettings.modalTitle;
		const fallbackBodyHtml = contractSettings.modalContent;

		// Rota Cartão (blu_checkout): dados completos só após webhook. Usar template com variáveis vazias.
		// Rota PIX (blu_pix): usar mesmo template com dados reais do formulário (applyContractTemplateTokens no renderContractModalContent).
		const paymentMethod = $('input[name="payment_method"]:checked').val();
		const isCardRoute = paymentMethod === 'blu_checkout';

		var cardNotice = '<p class="gstore-contract-modal__card-notice" style="margin-bottom:16px;padding:12px;background:#f0f9ff;border-left:4px solid #0284c7;border-radius:4px;font-size:14px;">Os dados do comprador serão preenchidos após a confirmação do pagamento no link externo.</p>';

		$modal.find('.Gstore-contract-modal__title').text(title);
		$modal.find('.Gstore-contract-modal__body').html('<p>Carregando contrato...</p>');
		$modal.addClass('is-visible').attr('aria-hidden', 'false');
		$('body').addClass('gstore-contract-modal-open');

		var contentToRender = isCardRoute ? cardNotice + fallbackBodyHtml : fallbackBodyHtml;
		renderContractModalContent($modal, contentToRender, isCardRoute);
	}

	function closeContractTermsModal() {
		const $modal = $('#gstore-contract-terms-modal');
		if (!$modal.length) return;
		$modal.removeClass('is-visible').attr('aria-hidden', 'true');
		$('body').removeClass('gstore-contract-modal-open');
	}

	/**
	 * === MODAL: Checkout Blu (Link Externo) ===
	 * Mantém o usuário na página e abre o checkout da Blu em um iframe quando possível.
	 * Se o embed for bloqueado (X-Frame-Options/CSP), o botão "Abrir em nova aba" funciona como fallback.
	 */
	function isBluCheckoutSelected() {
		const selected = $('input[name="payment_method"]:checked').val();
		return selected === 'blu_checkout';
	}

	function ensureBluCheckoutModal() {
		if ($('#gstore-blu-checkout-modal').length) return;

		$('body').append(`
			<div id="gstore-blu-checkout-modal" class="Gstore-blu-checkout-modal" aria-hidden="true">
				<div class="Gstore-blu-checkout-modal__backdrop" data-action="close"></div>
				<div class="Gstore-blu-checkout-modal__content" role="dialog" aria-modal="true" aria-label="Checkout Blu">
					<button type="button" class="Gstore-blu-checkout-modal__close" data-action="close" aria-label="Fechar">
						<i class="fa-solid fa-xmark" aria-hidden="true"></i>
					</button>
					<div class="Gstore-blu-checkout-modal__header">
						<div class="Gstore-blu-checkout-modal__title">
							Finalize seu pagamento na Blu
						</div>
						<div class="Gstore-blu-checkout-modal__actions">
							<a class="Gstore-btn Gstore-btn--submit Gstore-blu-checkout-modal__open" href="#" target="_blank" rel="noopener noreferrer">
								Abrir em nova aba
							</a>
						</div>
					</div>
					<div class="Gstore-blu-checkout-modal__hint" aria-live="polite">
						Se o checkout não carregar aqui, use “Abrir em nova aba”.
					</div>
					<div class="Gstore-blu-checkout-modal__frame-wrap">
						<iframe class="Gstore-blu-checkout-modal__frame" title="Checkout Blu" loading="eager" referrerpolicy="no-referrer-when-downgrade" allow="payment; clipboard-write" allowpaymentrequest></iframe>
					</div>
				</div>
			</div>
		`);

		// Fechar ao clicar no backdrop ou no botão
		$(document).on('click', '#gstore-blu-checkout-modal [data-action="close"]', function() {
			closeBluCheckoutModal();
		});

		// Fechar com ESC
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape') {
				const $modal = $('#gstore-blu-checkout-modal');
				if ($modal.length && $modal.hasClass('is-visible')) {
					closeBluCheckoutModal();
				}
				const $contractModal = $('#gstore-contract-terms-modal');
				if ($contractModal.length && $contractModal.hasClass('is-visible')) {
					closeContractTermsModal();
				}
			}
		});
	}

	function openBluCheckoutModal(url) {
		if (!url) return;

		// Armazena a URL do pagamento para exibir aviso se o modal for fechado sem pagar
		lastBluOrderPaymentUrl = url;

		ensureBluCheckoutModal();

		const $modal = $('#gstore-blu-checkout-modal');
		const $iframe = $modal.find('.Gstore-blu-checkout-modal__frame');
		const $open = $modal.find('.Gstore-blu-checkout-modal__open');

		$open.attr('href', url);

		// Reset para evitar manter estado antigo
		$iframe.attr('src', 'about:blank');
		setTimeout(function() {
			$iframe.attr('src', url);
		}, 50);

		$modal.addClass('is-visible').attr('aria-hidden', 'false');
		$('body').addClass('gstore-blu-checkout-modal-open');

		// Foco inicial no botão de abrir em nova aba
		setTimeout(function() {
			$open.trigger('focus');
		}, 50);
	}

	function closeBluCheckoutModal() {
		const $modal = $('#gstore-blu-checkout-modal');
		if (!$modal.length) return;

		$modal.removeClass('is-visible').attr('aria-hidden', 'true');
		$('body').removeClass('gstore-blu-checkout-modal-open');

		// Limpa o iframe para parar carregamentos/sons
		$modal.find('.Gstore-blu-checkout-modal__frame').attr('src', 'about:blank');

		// Reseta o estado do form para permitir nova interação
		const $form = $('form.checkout');
		if ($form.length) {
			$form.removeClass('processing').unblock();
		}

		// Atualiza o resumo do checkout (totais/parcelas) para refletir alterações antes do próximo submit.
		$(document.body).trigger('update_checkout');

		// Se havia um pedido Blu criado, mostra aviso com link para pagar
		if (lastBluOrderPaymentUrl) {
			const paymentUrl = lastBluOrderPaymentUrl;
			lastBluOrderPaymentUrl = null; // Limpa para não mostrar novamente

			// Remove avisos anteriores
			$('.gstore-blu-pending-notice').remove();

			// Mostra aviso com link para o pagamento
			const noticeHtml = `
				<div class="gstore-blu-pending-notice woocommerce-info" style="margin-bottom: 20px;">
					<strong>Seu pedido foi criado!</strong><br>
					O pagamento ainda está pendente. 
					<a href="${paymentUrl}" target="_blank" rel="noopener noreferrer" style="font-weight: bold;">Clique aqui para finalizar o pagamento</a>
					 ou acesse "Minha Conta > Pedidos" para pagar depois.
				</div>
			`;

			const $stepsContent = $('.Gstore-checkout-steps__content');
			if ($stepsContent.length) {
				$stepsContent.prepend(noticeHtml);
			} else {
				$form.prepend(noticeHtml);
			}

			// Scroll para o topo para ver o aviso
			$('html, body').animate({
				scrollTop: ($('.gstore-blu-pending-notice').offset() || { top: 0 }).top - 100
			}, 300);
		}
	}

	// Inicializa quando o DOM estiver pronto
	$(document).ready(function() {
		// Aguarda um momento para o WooCommerce carregar
		setTimeout(init, 100);
	});

	// Persistência do método de pagamento em qualquer troca (fallback geral)
	$(document).on('change', 'input[name="payment_method"]', function() {
		const method = $(this).val();
		if (method) {
			persistSelectedPaymentMethod(method);
			setTimeout(loadCartSummary, 150);
		}
	});

	// Variável para armazenar o método selecionado antes do update
	let lastSelectedPaymentMethod = null;
	
	// Armazena a seleção antes do update e garante campos hidden de frete
	$(document.body).on('update_checkout', function() {
		const $selected = $('input[name="payment_method"]:checked');
		if ($selected.length) {
			lastSelectedPaymentMethod = $selected.val();
		}
		
		// CORREÇÃO CRÍTICA: Garante que os campos de frete estejam no form
		// ANTES do WooCommerce serializar o form para update_order_review
		updateCheckoutShippingHiddenFields();
		
		const $checkoutForm = $('form.checkout');
		if ($checkoutForm.length) {
			// Garante que o método de pagamento esteja presente no POST,
			// mesmo quando o rádio não está disponível/selecionado.
			const $selectedRadio = $checkoutForm.find('input[name="payment_method"]:checked');
			const $hiddenPayment = $checkoutForm.find('input[name="payment_method"][type="hidden"]');
			if ($selectedRadio.length) {
				persistSelectedPaymentMethod($selectedRadio.val());
			} else if (lastSelectedPaymentMethod) {
				if ($hiddenPayment.length) {
					$hiddenPayment.val(lastSelectedPaymentMethod);
				} else {
					$checkoutForm.append(
						$('<input>', {
							type: 'hidden',
							name: 'payment_method',
							id: 'gstore_payment_method_fallback',
							'data-gstore-fallback': '1',
							value: lastSelectedPaymentMethod
						})
					);
				}
			}

			// Etapa atual do checkout (0, 1, 2) para o backend só aplicar taxa de parcelamento na etapa 3
			let $stepInput = $checkoutForm.find('input[name="gstore_checkout_step"]');
			if (!$stepInput.length) {
				$stepInput = $('<input>', { type: 'hidden', name: 'gstore_checkout_step', id: 'gstore_checkout_step' });
				$checkoutForm.append($stepInput);
			}
			$stepInput.val(typeof currentStep !== 'undefined' ? currentStep : 0);

			// Se não existe campo global, tenta obter do último modo selecionado
			if ($checkoutForm.find('input[name="gstore_shipping_mode"]').length === 0) {
				// Tenta obter do último modo selecionado por item
				const lastSelectedMode = Object.values(checkoutSelectedShippingByItem)[0] || 'land';
				$checkoutForm.append(
					$('<input>', {
						type: 'hidden',
						name: 'gstore_shipping_mode',
						value: lastSelectedMode
					})
				);
				console.log('[Gstore DEBUG] Campo global adicionado no update_checkout:', lastSelectedMode);
			}

			// Garante shipping_method[0] com rate ID completo para persistir frete ao fechar modal Blu.
			// Remove inputs WC nativos conflitantes e recria com data-index para compatibilidade.
			let mode = checkoutSelectedShippingMode || (Object.values(checkoutSelectedShippingByItem)[0]) || 'land';
			if (mode !== 'land' && mode !== 'air') mode = 'land';
			const rateId = 'gstore_custom_shipping:' + mode;
			$checkoutForm.find('input[name="shipping_method[0]"]').remove();
			$checkoutForm.append(
				$('<input>', {
					type: 'hidden',
					name: 'shipping_method[0]',
					'data-index': '0',
					'class': 'shipping_method',
					value: rateId
				})
			);
		}
	});
	
	// Reinicializa quando o checkout é atualizado via AJAX
	$(document.body).on('init_checkout updated_checkout', function() {
		// Evita processar se já estamos atualizando o pagamento
		if (isUpdatingPayment) {
			return;
		}
		
		// Restaura a seleção após o update
		if (lastSelectedPaymentMethod) {
			setTimeout(function() {
				const $radio = $('input[name="payment_method"]').filter(function() {
					return $(this).val() === lastSelectedPaymentMethod;
				});
				if ($radio.length && !$radio.is(':checked')) {
					// Não dispara change para evitar loops
					$radio.prop('checked', true);
				}
			}, 50);
		}
		
		// Re-aplica unificação dos métodos Blu após atualização
		setTimeout(function() {
			unifyBluPaymentMethods();
		}, 200);
		
		if (!initialized) {
			setTimeout(init, 100);
		}
	});

	// Intercepta a resposta do checkout para garantir redirect
	$(document).ajaxComplete(function(event, xhr, settings) {
		if (settings.url && settings.url.indexOf('wc-ajax=checkout') !== -1) {
			try {
				const response = JSON.parse(xhr.responseText);
				if (response.result === 'success' && response.redirect) {
					if (isBluCheckoutSelected()) {
						openBluCheckoutModal(response.redirect);
					} else {
						window.location.href = response.redirect;
					}
				}
			} catch (e) {
				// Não é JSON - normal para outras respostas
			}
		}
	});

	/**
	 * Verifica se algum gateway Blu está disponível no DOM
	 * @return {boolean}
	 */
	function isBluGatewayAvailable() {
		const $bluCheckout = $('.payment_method_blu_checkout');
		const $bluPix = $('.payment_method_blu_pix');
		return $bluCheckout.length > 0 || $bluPix.length > 0;
	}

	// Garante que os estilos do card Blu sejam mantidos após atualizações do checkout
	function ensureBluCardStyles() {
		const $bluPaymentBox = $('.payment_method_blu_checkout .payment_box');
		if ($bluPaymentBox.length) {
			// Verifica se os badges já existem
			if (!$bluPaymentBox.find('.Gstore-blu-trust-badges').length) {
				$bluPaymentBox.append(`
					<div class="Gstore-blu-trust-badges">
						<span class="Gstore-blu-trust-badge">
							<i class="fa-solid fa-lock"></i> 256-bit SSL
						</span>
						<span class="Gstore-blu-trust-badge">
							<i class="fa-solid fa-shield-halved"></i> Anti-fraude
						</span>
						<span class="Gstore-blu-trust-badge">
							<i class="fa-solid fa-credit-card"></i> PCI DSS
						</span>
						<span class="Gstore-blu-trust-badge">
							<i class="fa-solid fa-user-shield"></i> LGPD
						</span>
					</div>
				`);
			}
			
			// Força a aplicação dos estilos adicionando uma classe se necessário
			const $bluCard = $('.payment_method_blu_checkout');
			if ($bluCard.length && !$bluCard.hasClass('gstore-blu-styled')) {
				$bluCard.addClass('gstore-blu-styled');
			}
		}
		
		// Garante que o Pix esteja visível quando estiver ativo (especialmente no pré-checkout)
		const $pixGateway = $('.payment_method_blu_pix');
		if ($pixGateway.length && isBluGatewayAvailable()) {
			$pixGateway.show();
		}
	}

	// Listener para quando o checkout é atualizado pelo WooCommerce
	$(document.body).on('updated_checkout', function() {
		// Garante que os estilos do card Blu sejam mantidos
		setTimeout(ensureBluCardStyles, 100);
	});

	// Também executa após o carregamento completo da página
	$(document).ready(function() {
		setTimeout(ensureBluCardStyles, 500);
	});

	// Executa quando os métodos de pagamento são carregados
	$(document.body).on('payment_method_selected', function() {
		setTimeout(ensureBluCardStyles, 100);
	});

})(jQuery);

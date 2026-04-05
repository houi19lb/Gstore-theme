/**
 * GStore Fulfillment Timeline — Upload de documentos (dinâmico).
 *
 * Renderiza lista de documentos, dropzone, e gerencia upload/delete
 * sem reload de página.
 *
 * @package GStore
 */
(function () {
	'use strict';

	var config = window.gstoreFulfillment || {};
	var restUrl = config.restUrl || '/wp-json/gstore/v1/';
	var nonce = config.nonce || '';
	var orderId = config.orderId || 0;
	var maxFileSize = config.maxFileSize || 10 * 1024 * 1024;
	var allowedTypes = config.allowedTypes || ['application/pdf', 'image/png', 'image/jpeg'];

	if (!orderId) return;

	/* ───────── State ───────── */

	var container = null;
	var docsListEl = null;
	var dropzoneAreaEl = null;
	var docs = [];
	var maxDocs = 5;
	var isUploading = false;

	/* ───────── Helpers ───────── */

	function formatFileSize(bytes) {
		if (bytes < 1024) return bytes + ' B';
		if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
		return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
	}

	function validateFile(file) {
		if (!file) return 'Nenhum arquivo selecionado.';
		if (allowedTypes.indexOf(file.type) === -1) {
			return 'Tipo de arquivo não permitido. Envie PDF, PNG ou JPG.';
		}
		if (file.size > maxFileSize) {
			return 'Arquivo muito grande. Máximo ' + formatFileSize(maxFileSize) + '.';
		}
		return null;
	}

	function getActiveCount() {
		var count = 0;
		for (var i = 0; i < docs.length; i++) {
			if (docs[i].status !== 'rejected') count++;
		}
		return count;
	}

	var statusLabels = {
		pending: 'Pendente',
		approved: 'Aprovado',
		rejected: 'Rejeitado'
	};

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str || ''));
		return div.innerHTML;
	}

	/* ───────── SVG Icons ───────── */

	var icons = {
		file: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>',
		upload: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path><polyline points="16 16 12 12 8 16"></polyline></svg>',
		trash: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>',
		eye: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
	};

	/* ───────── Render ───────── */

	function render() {
		if (!docsListEl || !dropzoneAreaEl) return;

		renderDocsList();
		renderDropzone();
	}

	function renderDocsList() {
		var activeCount = getActiveCount();
		var html = '';

		// Counter.
		html += '<p class="gstore-fulfillment-upload__counter">' +
			activeCount + ' de ' + maxDocs + ' documentos enviados</p>';

		if (docs.length > 0) {
			html += '<div class="gstore-fulfillment-upload__files">';
			for (var i = 0; i < docs.length; i++) {
				html += renderDocRow(docs[i]);
			}
			html += '</div>';
		}

		docsListEl.innerHTML = html;

		// Bind actions.
		var viewBtns = docsListEl.querySelectorAll('[data-action="view"]');
		for (var v = 0; v < viewBtns.length; v++) {
			viewBtns[v].addEventListener('click', handleView);
		}

		var deleteBtns = docsListEl.querySelectorAll('[data-action="delete"]');
		for (var d = 0; d < deleteBtns.length; d++) {
			deleteBtns[d].addEventListener('click', handleDelete);
		}
	}

	function renderDocRow(doc) {
		var statusClass = doc.status || 'pending';
		var statusLabel = statusLabels[doc.status] || doc.status;
		var rowClass = 'gstore-fulfillment-upload__file-row';
		if (statusClass === 'approved') rowClass += ' gstore-fulfillment-upload__file-row--approved';
		if (statusClass === 'rejected') rowClass += ' gstore-fulfillment-upload__file-row--rejected';

		var html = '<div class="' + rowClass + '" data-doc-id="' + escapeHtml(doc.id) + '">';

		// Icon.
		html += '<span class="gstore-fulfillment-upload__file-icon">' + icons.file + '</span>';

		// Info.
		html += '<div class="gstore-fulfillment-upload__file-info">';
		html += '<span class="gstore-fulfillment-upload__filename">' + escapeHtml(doc.label || doc.filename) + '</span>';
		html += '</div>';

		// Status badge.
		html += '<span class="gstore-fulfillment-upload__status-badge gstore-fulfillment-upload__status-badge--' + statusClass + '">' +
			escapeHtml(statusLabel) + '</span>';

		// Actions.
		html += '<div class="gstore-fulfillment-upload__file-actions">';

		// View button.
		if (doc.url) {
			html += '<button type="button" class="gstore-fulfillment-upload__btn gstore-fulfillment-upload__btn--view" ' +
				'data-action="view" data-doc-id="' + escapeHtml(doc.id) + '" title="Visualizar">' +
				icons.eye + '</button>';
		}

		// Delete button (only for pending docs).
		if (doc.status === 'pending') {
			html += '<button type="button" class="gstore-fulfillment-upload__btn gstore-fulfillment-upload__btn--delete" ' +
				'data-action="delete" data-doc-id="' + escapeHtml(doc.id) + '" title="Excluir">' +
				icons.trash + '</button>';
		}

		html += '</div>';

		// Review note (if rejected).
		if (doc.status === 'rejected' && doc.review_note) {
			html += '<div class="gstore-fulfillment-upload__review-note">' +
				'<strong>Motivo:</strong> ' + escapeHtml(doc.review_note) + '</div>';
		}

		html += '</div>';
		return html;
	}

	function renderDropzone() {
		var activeCount = getActiveCount();

		if (activeCount >= maxDocs) {
			dropzoneAreaEl.innerHTML =
				'<div class="gstore-fulfillment-upload__limit-reached">' +
				'Limite de ' + maxDocs + ' documentos atingido.' +
				'</div>';
			return;
		}

		dropzoneAreaEl.innerHTML =
			'<div class="gstore-fulfillment-upload__dropzone" id="gstore-dropzone">' +
				'<input type="file" class="gstore-fulfillment-upload__input" id="gstore-file-input" ' +
					'accept=".pdf,.png,.jpg,.jpeg" />' +
				'<div class="gstore-fulfillment-upload__dropzone-content">' +
					icons.upload +
					'<span>Arraste e solte seu arquivo aqui ou <strong>clique para selecionar</strong></span>' +
					'<span class="gstore-fulfillment-upload__hint">PDF, PNG ou JPG — máx. ' + formatFileSize(maxFileSize) + '</span>' +
				'</div>' +
				'<div class="gstore-fulfillment-upload__progress" style="display:none">' +
					'<div class="gstore-fulfillment-upload__progress-bar"></div>' +
					'<span class="gstore-fulfillment-upload__progress-text">Enviando...</span>' +
				'</div>' +
			'</div>';

		bindDropzone();
	}

	/* ───────── Dropzone Events ───────── */

	function bindDropzone() {
		var dropzone = document.getElementById('gstore-dropzone');
		var fileInput = document.getElementById('gstore-file-input');
		if (!dropzone || !fileInput) return;

		fileInput.addEventListener('change', function () {
			if (fileInput.files && fileInput.files[0]) {
				uploadFile(fileInput.files[0]);
			}
		});

		dropzone.addEventListener('dragover', function (e) {
			e.preventDefault();
			e.stopPropagation();
			dropzone.classList.add('is-dragover');
		});

		dropzone.addEventListener('dragleave', function (e) {
			e.preventDefault();
			e.stopPropagation();
			dropzone.classList.remove('is-dragover');
		});

		dropzone.addEventListener('drop', function (e) {
			e.preventDefault();
			e.stopPropagation();
			dropzone.classList.remove('is-dragover');
			var files = e.dataTransfer && e.dataTransfer.files;
			if (files && files[0]) {
				uploadFile(files[0]);
			}
		});
	}

	/* ───────── Upload ───────── */

	function uploadFile(file) {
		if (isUploading) return;

		var error = validateFile(file);
		if (error) {
			showGlobalError(error);
			return;
		}

		isUploading = true;

		var dropzone = document.getElementById('gstore-dropzone');
		var progressEl = dropzone ? dropzone.querySelector('.gstore-fulfillment-upload__progress') : null;
		var progressBar = dropzone ? dropzone.querySelector('.gstore-fulfillment-upload__progress-bar') : null;
		var progressText = dropzone ? dropzone.querySelector('.gstore-fulfillment-upload__progress-text') : null;

		if (dropzone) {
			dropzone.classList.add('is-uploading');
			dropzone.classList.remove('is-error', 'is-success');
		}
		if (progressEl) progressEl.style.display = '';
		if (progressBar) progressBar.style.setProperty('--progress', '0%');
		if (progressText) progressText.textContent = 'Enviando...';

		var xhr = new XMLHttpRequest();
		var formData = new FormData();
		formData.append('file', file);
		formData.append('doc_type', 'documento_geral');

		xhr.upload.addEventListener('progress', function (e) {
			if (e.lengthComputable && progressBar) {
				var pct = Math.round((e.loaded / e.total) * 100);
				progressBar.style.setProperty('--progress', pct + '%');
				if (progressText) progressText.textContent = 'Enviando... ' + pct + '%';
			}
		});

		xhr.addEventListener('load', function () {
			isUploading = false;
			if (dropzone) dropzone.classList.remove('is-uploading');
			if (progressEl) progressEl.style.display = 'none';

			if (xhr.status >= 200 && xhr.status < 300) {
				try {
					var res = JSON.parse(xhr.responseText);
					if (res.success && res.data && res.data.documents) {
						docs = res.data.documents;
						render();
						return;
					}
					showGlobalError(res.message || 'Erro no upload.');
				} catch (_e) {
					showGlobalError('Resposta inválida do servidor.');
				}
			} else {
				try {
					var errRes = JSON.parse(xhr.responseText);
					showGlobalError(errRes.message || 'Erro ' + xhr.status);
				} catch (_e2) {
					showGlobalError('Erro ' + xhr.status + ' ao enviar arquivo.');
				}
			}
		});

		xhr.addEventListener('error', function () {
			isUploading = false;
			if (dropzone) dropzone.classList.remove('is-uploading');
			if (progressEl) progressEl.style.display = 'none';
			showGlobalError('Erro de conexão. Tente novamente.');
		});

		xhr.open('POST', restUrl + 'my-orders/' + orderId + '/fulfillment/upload');
		xhr.setRequestHeader('X-WP-Nonce', nonce);
		xhr.send(formData);
	}

	/* ───────── View Document ───────── */

	function handleView(e) {
		var btn = e.currentTarget;
		var docId = btn.getAttribute('data-doc-id');
		if (!docId) return;

		// Fetch file as blob via authenticated REST endpoint.
		var xhr = new XMLHttpRequest();
		xhr.open('GET', restUrl + 'my-orders/' + orderId + '/fulfillment/documents/' + docId + '/download');
		xhr.setRequestHeader('X-WP-Nonce', nonce);
		xhr.responseType = 'blob';

		btn.disabled = true;
		btn.style.opacity = '0.5';

		xhr.addEventListener('load', function () {
			btn.disabled = false;
			btn.style.opacity = '';

			if (xhr.status >= 200 && xhr.status < 300) {
				var blob = xhr.response;
				var url = URL.createObjectURL(blob);
				window.open(url, '_blank');
				setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
			} else {
				showGlobalError('Não foi possível abrir o documento.');
			}
		});

		xhr.addEventListener('error', function () {
			btn.disabled = false;
			btn.style.opacity = '';
			showGlobalError('Erro de conexão ao abrir documento.');
		});

		xhr.send();
	}

	/* ───────── Delete Document ───────── */

	function handleDelete(e) {
		var btn = e.currentTarget;
		var docId = btn.getAttribute('data-doc-id');
		if (!docId) return;

		if (!confirm('Tem certeza que deseja excluir este documento?')) return;

		btn.disabled = true;
		btn.style.opacity = '0.5';

		var xhr = new XMLHttpRequest();
		xhr.open('DELETE', restUrl + 'my-orders/' + orderId + '/fulfillment/documents/' + docId);
		xhr.setRequestHeader('X-WP-Nonce', nonce);

		xhr.addEventListener('load', function () {
			if (xhr.status >= 200 && xhr.status < 300) {
				try {
					var res = JSON.parse(xhr.responseText);
					if (res.success && res.data && res.data.documents) {
						docs = res.data.documents;
						render();
						return;
					}
				} catch (_e) {}
				// Fallback: remove locally.
				docs = docs.filter(function (d) { return d.id !== docId; });
				render();
			} else {
				btn.disabled = false;
				btn.style.opacity = '';
				try {
					var errRes = JSON.parse(xhr.responseText);
					showGlobalError(errRes.message || 'Erro ao excluir documento.');
				} catch (_e2) {
					showGlobalError('Erro ao excluir documento.');
				}
			}
		});

		xhr.addEventListener('error', function () {
			btn.disabled = false;
			btn.style.opacity = '';
			showGlobalError('Erro de conexão. Tente novamente.');
		});

		xhr.send();
	}

	/* ───────── Global Error ───────── */

	function showGlobalError(message) {
		var existing = container ? container.querySelector('.gstore-fulfillment-upload__global-error') : null;
		if (existing) existing.remove();

		if (!container) return;

		var el = document.createElement('div');
		el.className = 'gstore-fulfillment-upload__global-error';
		el.textContent = message;
		container.insertBefore(el, docsListEl);

		setTimeout(function () {
			if (el.parentNode) el.remove();
		}, 5000);
	}

	/* ───────── Init ───────── */

	function init() {
		container = document.getElementById('gstore-fulfillment-upload');
		if (!container) return;

		docsListEl = document.getElementById('gstore-fulfillment-docs-list');
		dropzoneAreaEl = document.getElementById('gstore-fulfillment-dropzone-area');
		if (!docsListEl || !dropzoneAreaEl) return;

		maxDocs = parseInt(container.getAttribute('data-max-docs'), 10) || 5;

		try {
			docs = JSON.parse(container.getAttribute('data-docs') || '[]');
		} catch (_e) {
			docs = [];
		}

		render();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

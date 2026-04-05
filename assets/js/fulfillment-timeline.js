/**
 * GStore Fulfillment Timeline — Upload de documentos e interações.
 *
 * @package GStore
 */
(function () {
	'use strict';

	// Dados injetados via wp_localize_script.
	var config = window.gstoreFulfillment || {};
	var restUrl = config.restUrl || '/wp-json/gstore/v1/';
	var nonce = config.nonce || '';
	var orderId = config.orderId || 0;
	var maxFileSize = config.maxFileSize || 10 * 1024 * 1024;
	var allowedTypes = config.allowedTypes || ['application/pdf', 'image/png', 'image/jpeg'];

	if (!orderId) return;

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

	/* ───────── Upload ───────── */

	function uploadFile(slot, file) {
		var docType = slot.getAttribute('data-doc-type');
		var dropzone = slot.querySelector('.gstore-fulfillment-upload__dropzone');
		var progressEl = slot.querySelector('.gstore-fulfillment-upload__progress');
		var progressBar = slot.querySelector('.gstore-fulfillment-upload__progress-bar');
		var progressText = slot.querySelector('.gstore-fulfillment-upload__progress-text');

		if (!dropzone) return;

		// Validação.
		var error = validateFile(file);
		if (error) {
			showDropzoneError(dropzone, error);
			return;
		}

		// UI: uploading state.
		dropzone.classList.add('is-uploading');
		dropzone.classList.remove('is-error', 'is-success');
		if (progressEl) progressEl.style.display = '';
		if (progressBar) progressBar.style.setProperty('--progress', '0%');
		if (progressText) progressText.textContent = 'Enviando...';

		// Upload via XMLHttpRequest para progress.
		var xhr = new XMLHttpRequest();
		var formData = new FormData();
		formData.append('file', file);
		formData.append('doc_type', docType);

		xhr.upload.addEventListener('progress', function (e) {
			if (e.lengthComputable && progressBar) {
				var pct = Math.round((e.loaded / e.total) * 100);
				progressBar.style.setProperty('--progress', pct + '%');
				if (progressText) progressText.textContent = 'Enviando... ' + pct + '%';
			}
		});

		xhr.addEventListener('load', function () {
			dropzone.classList.remove('is-uploading');
			if (progressEl) progressEl.style.display = 'none';

			if (xhr.status >= 200 && xhr.status < 300) {
				try {
					var res = JSON.parse(xhr.responseText);
					if (res.success) {
						onUploadSuccess(slot, res);
						return;
					}
					showDropzoneError(dropzone, res.message || 'Erro no upload.');
				} catch (_e) {
					showDropzoneError(dropzone, 'Resposta inválida do servidor.');
				}
			} else {
				try {
					var errRes = JSON.parse(xhr.responseText);
					showDropzoneError(dropzone, errRes.message || 'Erro ' + xhr.status);
				} catch (_e2) {
					showDropzoneError(dropzone, 'Erro ' + xhr.status + ' ao enviar arquivo.');
				}
			}
		});

		xhr.addEventListener('error', function () {
			dropzone.classList.remove('is-uploading');
			if (progressEl) progressEl.style.display = 'none';
			showDropzoneError(dropzone, 'Erro de conexão. Tente novamente.');
		});

		xhr.open('POST', restUrl + 'my-orders/' + orderId + '/fulfillment/upload');
		xhr.setRequestHeader('X-WP-Nonce', nonce);
		xhr.send(formData);
	}

	function showDropzoneError(dropzone, message) {
		dropzone.classList.add('is-error');
		dropzone.classList.remove('is-success');
		var content = dropzone.querySelector('.gstore-fulfillment-upload__dropzone-content');
		if (content) {
			var existingError = content.querySelector('.gstore-upload-error');
			if (existingError) existingError.remove();
			var errorEl = document.createElement('span');
			errorEl.className = 'gstore-upload-error';
			errorEl.style.cssText = 'color:#ff4757;font-size:0.8rem;margin-top:4px;';
			errorEl.textContent = message;
			content.appendChild(errorEl);

			// Remove após 5s.
			setTimeout(function () {
				if (errorEl.parentNode) errorEl.remove();
				dropzone.classList.remove('is-error');
			}, 5000);
		}
	}

	function onUploadSuccess(slot, response) {
		// Recarrega a página para refletir o novo estado.
		// Alternativa mais sofisticada seria atualizar o DOM dinamicamente,
		// mas o reload garante consistência com o PHP server-side.
		window.location.reload();
	}

	/* ───────── Event Binding ───────── */

	function initSlots() {
		var slots = document.querySelectorAll('.gstore-fulfillment-upload__slot');
		if (!slots.length) return;

		slots.forEach(function (slot) {
			var dropzone = slot.querySelector('.gstore-fulfillment-upload__dropzone');
			var fileInput = slot.querySelector('.gstore-fulfillment-upload__input');

			if (!dropzone || !fileInput) return;

			// File input change.
			fileInput.addEventListener('change', function () {
				if (fileInput.files && fileInput.files[0]) {
					uploadFile(slot, fileInput.files[0]);
				}
			});

			// Drag events.
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
					uploadFile(slot, files[0]);
				}
			});
		});
	}

	/* ───────── Init ───────── */

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSlots);
	} else {
		initSlots();
	}
})();

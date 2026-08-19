/**
 * Product Search Autocomplete - Gstore Theme
 * Sugestões (produtos + categorias) para buscas do tema.
 */
(function () {
	'use strict';

	var config = window.gstoreProductSearch || {};
	var endpoint = config.endpoint || '';
	var minCharsRaw = config.minChars;
	var minChars = typeof minCharsRaw === 'number' ? minCharsRaw : parseInt(minCharsRaw, 10);
	if (!isFinite(minChars) || minChars < 3) minChars = 3;

	var debounceMsRaw = config.debounceMs;
	var debounceMs = typeof debounceMsRaw === 'number' ? debounceMsRaw : parseInt(debounceMsRaw, 10);
	if (!isFinite(debounceMs) || debounceMs < 250) debounceMs = 350;

	var cacheTtlRaw = config.cacheTtlMs;
	var cacheTtlMs = typeof cacheTtlRaw === 'number' ? cacheTtlRaw : parseInt(cacheTtlRaw, 10);
	if (!isFinite(cacheTtlMs) || cacheTtlMs < 1000) cacheTtlMs = 60000;

	var suggestionCache = {};

	if (!endpoint) {
		return;
	}

	var FORM_SELECTOR = [
		'form.wp-block-search.Gstore-header__search',
		'form.wp-block-search.Gstore-nav__search',
		'form.wp-block-search.Gstore-catalog-search'
	].join(',');

	function debounce(fn, wait) {
		var t;
		return function () {
			var ctx = this;
			var args = arguments;
			clearTimeout(t);
			t = setTimeout(function () {
				fn.apply(ctx, args);
			}, wait);
		};
	}

	function normalizeQuery(value) {
		return (value || '').trim();
	}

	function getCacheKey(query) {
		return normalizeQuery(query).toLowerCase();
	}

	function getCachedSuggestion(query) {
		var key = getCacheKey(query);
		var entry = suggestionCache[key];
		if (!entry) return null;

		if (entry.expiresAt <= Date.now()) {
			delete suggestionCache[key];
			return null;
		}

		return entry.payload;
	}

	function cacheSuggestion(query, payload) {
		var key = getCacheKey(query);
		var now = Date.now();
		var keys = Object.keys(suggestionCache);
		var oldestKey = '';
		var oldestExpiry = Infinity;
		var activeEntries = 0;

		for (var i = 0; i < keys.length; i++) {
			var existing = suggestionCache[keys[i]];
			if (!existing || existing.expiresAt <= now) {
				delete suggestionCache[keys[i]];
				continue;
			}

			activeEntries++;
			if (existing.expiresAt < oldestExpiry) {
				oldestExpiry = existing.expiresAt;
				oldestKey = keys[i];
			}
		}

		if (activeEntries >= 50 && oldestKey && oldestKey !== key) {
			delete suggestionCache[oldestKey];
		}

		suggestionCache[key] = {
			expiresAt: now + cacheTtlMs,
			payload: payload
		};
	}

	function createEl(tag, className, text) {
		var el = document.createElement(tag);
		if (className) el.className = className;
		if (typeof text === 'string') el.textContent = text;
		return el;
	}

	function isInside(el, container) {
		return container && el && container.contains(el);
	}

	function SearchSuggest(form) {
		this.form = form;
		this.input = form.querySelector('input.wp-block-search__input');
		this.wrapper = form.querySelector('.wp-block-search__inside-wrapper') || form;
		this.dropdown = null;
		this.items = [];
		this.activeIndex = -1;
		this.lastQuery = '';
		this.pendingQuery = '';
		this.abort = null;
		this.requestId = 0;

		var self = this;
		this.scheduleSuggest = debounce(function (query) {
			self.requestSuggest(query);
		}, debounceMs);

		if (!this.input) return;
		if (this.input.dataset.gstoreSuggestInit === '1') return;
		this.input.dataset.gstoreSuggestInit = '1';
		this.input.setAttribute('autocomplete', 'off');

		this.ensureDropdown();
		this.bind();
	}

	SearchSuggest.prototype.ensureDropdown = function () {
		if (this.dropdown) return;
		this.form.classList.add('Gstore-search-form--suggest');
		this.dropdown = createEl('div', 'Gstore-search-suggest');
		this.dropdown.setAttribute('role', 'listbox');
		this.dropdown.hidden = true;
		this.wrapper.appendChild(this.dropdown);
	};

	SearchSuggest.prototype.clear = function () {
		this.cancelPendingRequest();
		this.items = [];
		this.activeIndex = -1;
		this.dropdown.innerHTML = '';
		this.dropdown.hidden = true;
	};

	SearchSuggest.prototype.setActive = function (idx) {
		this.activeIndex = idx;
		for (var i = 0; i < this.items.length; i++) {
			if (i === idx) {
				this.items[i].classList.add('is-active');
				this.items[i].setAttribute('aria-selected', 'true');
				this.items[i].scrollIntoView({ block: 'nearest' });
			} else {
				this.items[i].classList.remove('is-active');
				this.items[i].setAttribute('aria-selected', 'false');
			}
		}
	};

	SearchSuggest.prototype.navigateToActive = function () {
		if (this.activeIndex < 0 || this.activeIndex >= this.items.length) return false;
		var el = this.items[this.activeIndex];
		var url = el && el.dataset ? el.dataset.url : '';
		if (!url) return false;
		window.location.href = url;
		return true;
	};

	SearchSuggest.prototype.render = function (payload) {
		this.dropdown.innerHTML = '';
		this.items = [];
		this.activeIndex = -1;

		var products = (payload && payload.products) || [];
		var categories = (payload && payload.categories) || [];

		if (products.length === 0 && categories.length === 0) {
			this.dropdown.hidden = true;
			return;
		}

		var frag = document.createDocumentFragment();

		if (products.length) {
			frag.appendChild(createEl('div', 'Gstore-search-suggest__title', 'Produtos'));
			for (var i = 0; i < products.length; i++) {
				var p = products[i];
				var item = createEl('div', 'Gstore-search-suggest__item');
				item.setAttribute('role', 'option');
				item.setAttribute('aria-selected', 'false');
				item.dataset.url = p.permalink || '';

				var left = createEl('div', 'Gstore-search-suggest__left');
				if (p.image) {
					var img = document.createElement('img');
					img.className = 'Gstore-search-suggest__img';
					img.src = p.image;
					img.alt = p.name || 'Produto sugerido';
					img.loading = 'lazy';
					left.appendChild(img);
				} else {
					left.appendChild(createEl('div', 'Gstore-search-suggest__img Gstore-search-suggest__img--placeholder', ''));
				}

				var mid = createEl('div', 'Gstore-search-suggest__mid');
				mid.appendChild(createEl('div', 'Gstore-search-suggest__name', p.name || ''));
				if (p.price_html) {
					var price = createEl('div', 'Gstore-search-suggest__price');
					price.innerHTML = p.price_html;
					mid.appendChild(price);
				}

				item.appendChild(left);
				item.appendChild(mid);

				frag.appendChild(item);
				this.items.push(item);
			}
		}

		if (categories.length) {
			frag.appendChild(createEl('div', 'Gstore-search-suggest__title', 'Categorias'));
			for (var j = 0; j < categories.length; j++) {
				var c = categories[j];
				var cItem = createEl('div', 'Gstore-search-suggest__item Gstore-search-suggest__item--category');
				cItem.setAttribute('role', 'option');
				cItem.setAttribute('aria-selected', 'false');
				cItem.dataset.url = c.url || '';

				var icon = createEl('div', 'Gstore-search-suggest__cat-icon', '');
				icon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 4H4v16h16V8h-8l-2-4z"/></svg>';
				var text = createEl('div', 'Gstore-search-suggest__name', c.name || '');

				cItem.appendChild(icon);
				cItem.appendChild(text);

				frag.appendChild(cItem);
				this.items.push(cItem);
			}
		}

		this.dropdown.appendChild(frag);
		this.dropdown.hidden = false;
	};

	SearchSuggest.prototype.cancelPendingRequest = function () {
		this.requestId++;
		if (this.abort && typeof this.abort.abort === 'function') {
			this.abort.abort();
		}
		this.abort = null;
	};

	SearchSuggest.prototype.fetchSuggest = function (query) {
		var self = this;
		self.cancelPendingRequest();
		var requestId = ++self.requestId;

		self.abort = typeof AbortController !== 'undefined' ? new AbortController() : null;

		var url = endpoint;
		url += (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(query);

		var fetchOpts = { credentials: 'same-origin' };
		if (self.abort && self.abort.signal) {
			fetchOpts.signal = self.abort.signal;
		}

		fetch(url, fetchOpts)
			.then(function (r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then(function (data) {
				if (requestId !== self.requestId || query !== self.pendingQuery) return;
				self.abort = null;
				self.lastQuery = query;
				cacheSuggestion(query, data);
				self.render(data);
			})
			.catch(function () {
				if (requestId === self.requestId) {
					self.abort = null;
				}
				// silenciar (offline/abort)
			});
	};

	SearchSuggest.prototype.requestSuggest = function (query) {
		if (query !== this.pendingQuery) return;

		var cached = getCachedSuggestion(query);
		if (cached) {
			this.lastQuery = query;
			this.render(cached);
			return;
		}

		this.fetchSuggest(query);
	};

	SearchSuggest.prototype.onInput = function (value) {
		var q = normalizeQuery(value);
		this.pendingQuery = q;
		this.cancelPendingRequest();

		if (q.length < minChars) {
			this.clear();
			return;
		}

		this.scheduleSuggest(q);
	};

	SearchSuggest.prototype.bind = function () {
		var self = this;

		self.input.addEventListener('input', function () {
			self.onInput(self.input.value);
		});

		self.input.addEventListener('keydown', function (e) {
			if (self.dropdown.hidden) return;

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				var next = Math.min(self.items.length - 1, self.activeIndex + 1);
				self.setActive(next);
				return;
			}

			if (e.key === 'ArrowUp') {
				e.preventDefault();
				var prev = Math.max(0, self.activeIndex - 1);
				self.setActive(prev);
				return;
			}

			if (e.key === 'Enter') {
				// Se houver item ativo, navega; senão, deixa o submit normal acontecer.
				if (self.navigateToActive()) {
					e.preventDefault();
				}
				return;
			}

			if (e.key === 'Escape') {
				self.clear();
			}
		});

		self.dropdown.addEventListener('mousedown', function (e) {
			// evita blur antes do click
			e.preventDefault();
		});

		self.dropdown.addEventListener('click', function (e) {
			var item = e.target.closest('.Gstore-search-suggest__item');
			if (!item) return;
			var url = item.dataset.url || '';
			if (!url) return;
			window.location.href = url;
		});

		document.addEventListener('click', function (e) {
			if (isInside(e.target, self.form)) return;
			self.clear();
		});

		self.input.addEventListener('focus', function () {
			var q = (self.input.value || '').trim();
			if (q.length >= minChars && self.lastQuery === q && self.items.length) {
				self.dropdown.hidden = false;
			}
		});

		self.input.addEventListener('blur', function () {
			// delay pra permitir click no dropdown
			setTimeout(function () {
				if (!document.activeElement || !isInside(document.activeElement, self.form)) {
					self.clear();
				}
			}, 120);
		});
	};

	function init(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var forms = [];
		if (scope.matches && scope.matches(FORM_SELECTOR)) {
			forms.push(scope);
		}
		scope.querySelectorAll(FORM_SELECTOR).forEach(function (form) {
			forms.push(form);
		});
		forms.forEach(function (f) {
			new SearchSuggest(f);
		});
	}

	window.gstoreProductSearchAutocomplete = {
		init: init
	};

	window.addEventListener('gstore:product-search-autocomplete:init', function (event) {
		var detail = event && event.detail ? event.detail : {};
		init(detail.root || document);
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();


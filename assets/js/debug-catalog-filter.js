/**
 * Script de diagnostico do filtro do catalogo.
 * Cole no Console do navegador e acompanhe os logs ao aplicar, paginar e limpar filtros.
 */
(function () {
    'use strict';

    var DEBUG_KEY = '__gstoreCatalogFilterDebug';
    var FILTER_KEY_RE = /^filter_cat(?:\[\d*\]|\[\])?$/i;
    var PAGINATION_PARAM_KEYS = {
        'paged': true,
        'product-page': true,
        'page': true
    };
    var listeners = [];
    var originalPushState = history.pushState;
    var originalReplaceState = history.replaceState;

    if (window[DEBUG_KEY] && typeof window[DEBUG_KEY].stop === 'function') {
        window[DEBUG_KEY].stop();
    }

    function log(message, type, payload) {
        var styles = {
            info: 'color:#2196F3;font-weight:bold',
            ok: 'color:#4CAF50;font-weight:bold',
            warn: 'color:#FF9800;font-weight:bold',
            error: 'color:#f44336;font-weight:bold'
        };

        if (payload !== undefined) {
            console.log('%c[GStore Filter Debug] ' + message, styles[type] || styles.info, payload);
            return;
        }

        console.log('%c[GStore Filter Debug] ' + message, styles[type] || styles.info);
    }

    function addListener(target, eventName, handler, options) {
        if (!target || !target.addEventListener) {
            return;
        }

        target.addEventListener(eventName, handler, options);
        listeners.push(function () {
            target.removeEventListener(eventName, handler, options);
        });
    }

    function isFilterParamKey(key) {
        return FILTER_KEY_RE.test(String(key || '').trim());
    }

    function isPaginationParamKey(key) {
        return Object.prototype.hasOwnProperty.call(PAGINATION_PARAM_KEYS, String(key || '').trim());
    }

    function normalizePathname(pathname) {
        var normalized = String(pathname || '').replace(/\/page\/\d+\/?$/i, '/');
        return normalized || '/';
    }

    function getUrlState(rawUrl) {
        var url = new URL(rawUrl, window.location.origin);
        var params = [];

        url.searchParams.forEach(function (value, key) {
            params.push({ key: key, value: value });
        });

        var filterParams = params.filter(function (entry) {
            return isFilterParamKey(entry.key);
        });
        var numberedFilterParams = params.filter(function (entry) {
            return /^filter_cat\[\d+\]$/i.test(entry.key);
        });
        var paginationParams = params.filter(function (entry) {
            return isPaginationParamKey(entry.key);
        });

        return {
            href: url.href,
            pathname: url.pathname,
            normalizedPathname: normalizePathname(url.pathname),
            search: url.search,
            hasPageSegment: /\/page\/\d+\/?$/i.test(url.pathname),
            filterParams: filterParams,
            numberedFilterParams: numberedFilterParams,
            paginationParams: paginationParams,
            otherParams: params.filter(function (entry) {
                return !isFilterParamKey(entry.key) && !isPaginationParamKey(entry.key);
            })
        };
    }

    function getContainer() {
        return document.getElementById('gstore-category-filter');
    }

    function getCheckedSlugs() {
        return Array.prototype.slice.call(document.querySelectorAll('.gstore-category-filter__checkbox:checked'))
            .map(function (checkbox) {
                return checkbox.value;
            });
    }

    function getCheckboxState() {
        return Array.prototype.slice.call(document.querySelectorAll('.gstore-category-filter__checkbox'))
            .filter(function (checkbox) {
                return checkbox.checked || checkbox.indeterminate;
            })
            .map(function (checkbox) {
                return {
                    slug: checkbox.value,
                    checked: checkbox.checked,
                    indeterminate: checkbox.indeterminate,
                    name: checkbox.dataset.name || ''
                };
            });
    }

    function buildThemeClearUrl() {
        var url = new URL(window.location.href);
        var otherParams = [];
        var container = getContainer();
        var targetBase = container && container.dataset.fullCatalogUrl
            ? container.dataset.fullCatalogUrl
            : (url.origin + url.pathname);

        url.searchParams.forEach(function (value, key) {
            if (key !== 'filter_cat' && key !== 'filter_cat[]' && key !== 'paged') {
                otherParams.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
            }
        });

        return targetBase + (otherParams.length ? (targetBase.indexOf('?') >= 0 ? '&' : '?') + otherParams.join('&') : '');
    }

    function buildExpectedClearUrl() {
        var url = new URL(window.location.href);
        var params = new URLSearchParams();
        var container = getContainer();
        var targetBase = container && container.dataset.fullCatalogUrl
            ? container.dataset.fullCatalogUrl
            : (url.origin + normalizePathname(url.pathname));

        url.searchParams.forEach(function (value, key) {
            if (isFilterParamKey(key) || isPaginationParamKey(key)) {
                return;
            }
            params.append(key, value);
        });

        var query = params.toString();
        return targetBase + (query ? (targetBase.indexOf('?') >= 0 ? '&' : '?') + query : '');
    }

    function buildExpectedApplyUrl() {
        var url = new URL(window.location.href);
        var params = new URLSearchParams();
        var checked = getCheckedSlugs();
        var targetBase = url.origin + normalizePathname(url.pathname);

        url.searchParams.forEach(function (value, key) {
            if (isFilterParamKey(key) || isPaginationParamKey(key)) {
                return;
            }
            params.append(key, value);
        });

        checked.forEach(function (slug) {
            params.append('filter_cat[]', slug);
        });

        var query = params.toString();
        return targetBase + (query ? '?' + query : '');
    }

    function snapshot(label) {
        var urlState = getUrlState(window.location.href);
        var checkboxState = getCheckboxState();
        var checkedSlugs = getCheckedSlugs();

        console.groupCollapsed('[GStore Filter Debug] ' + label);
        console.table({
            href: urlState.href,
            pathname: urlState.pathname,
            normalizedPathname: urlState.normalizedPathname,
            search: urlState.search || '(vazio)',
            checkedCount: checkedSlugs.length,
            hasPageSegment: urlState.hasPageSegment ? 'sim' : 'nao',
            numberedFilterKeys: urlState.numberedFilterParams.length ? 'sim' : 'nao'
        });

        if (urlState.filterParams.length) {
            console.log('Parametros de filtro na URL:', urlState.filterParams);
        } else {
            console.log('Parametros de filtro na URL: nenhum');
        }

        if (urlState.paginationParams.length) {
            console.log('Parametros de paginacao na URL:', urlState.paginationParams);
        }

        console.log('Checkboxes ativos/indeterminados:', checkboxState);
        console.log('URL do limpar no codigo atual:', buildThemeClearUrl());
        console.log('URL esperada ao limpar:', buildExpectedClearUrl());
        console.log('URL esperada ao aplicar:', buildExpectedApplyUrl());
        console.groupEnd();

        if (urlState.numberedFilterParams.length) {
            log('Detectado filter_cat[n] na URL. Esse formato costuma reaparecer depois da paginacao.', 'warn', urlState.numberedFilterParams);
        }

        if (urlState.hasPageSegment) {
            log('A URL esta paginada por caminho (/page/N/). Isso tambem precisa ser limpo ao remover filtros.', 'warn');
        }
    }

    function patchHistory() {
        history.pushState = function () {
            log('history.pushState disparado', 'info', Array.prototype.slice.call(arguments));
            return originalPushState.apply(this, arguments);
        };

        history.replaceState = function () {
            log('history.replaceState disparado', 'info', Array.prototype.slice.call(arguments));
            return originalReplaceState.apply(this, arguments);
        };
    }

    function restoreHistory() {
        history.pushState = originalPushState;
        history.replaceState = originalReplaceState;
    }

    function onDocumentClick(event) {
        var clearBtn = event.target.closest('#gstore-filter-clear');
        var applyBtn = event.target.closest('#gstore-filter-apply');
        var checkbox = event.target.closest('.gstore-category-filter__checkbox');
        var paginationLink = event.target.closest('a.page-numbers, .woocommerce-pagination a, a[href*="product-page="], a[href*="/page/"]');

        if (checkbox) {
            log('Clique em checkbox de filtro', 'info', {
                slug: checkbox.value,
                checked: checkbox.checked,
                indeterminate: checkbox.indeterminate
            });
            return;
        }

        if (applyBtn) {
            log('Clique em Aplicar', 'ok', {
                currentUrl: window.location.href,
                nextUrl: buildExpectedApplyUrl()
            });
            return;
        }

        if (clearBtn) {
            log('Clique em Limpar', 'warn', {
                currentUrl: window.location.href,
                clearUrlFromCurrentCode: buildThemeClearUrl(),
                expectedClearUrl: buildExpectedClearUrl()
            });
            return;
        }

        if (paginationLink) {
            log('Clique em paginacao', 'info', {
                href: paginationLink.href || paginationLink.getAttribute('href') || '',
                currentUrl: window.location.href
            });
        }
    }

    function onDocumentChange(event) {
        var checkbox = event.target.closest('.gstore-category-filter__checkbox');
        if (!checkbox) {
            return;
        }

        snapshot('change checkbox: ' + checkbox.value);
    }

    function stop() {
        while (listeners.length) {
            listeners.pop()();
        }

        restoreHistory();
        delete window[DEBUG_KEY];
        log('Diagnostico removido do contexto da pagina.', 'ok');
    }

    patchHistory();

    addListener(document, 'click', onDocumentClick, true);
    addListener(document, 'change', onDocumentChange, true);
    addListener(window, 'beforeunload', function () {
        snapshot('beforeunload');
    });
    addListener(window, 'pageshow', function () {
        snapshot('pageshow');
    });
    addListener(window, 'popstate', function () {
        snapshot('popstate');
    });

    window[DEBUG_KEY] = {
        stop: stop,
        snapshot: function (label) {
            snapshot(label || 'manual');
        },
        getUrlState: getUrlState,
        buildThemeClearUrl: buildThemeClearUrl,
        buildExpectedClearUrl: buildExpectedClearUrl,
        buildExpectedApplyUrl: buildExpectedApplyUrl
    };

    log('Diagnostico instalado. Use __gstoreCatalogFilterDebug.snapshot() para tirar um retrato manual.', 'ok');
    snapshot('init');
})();

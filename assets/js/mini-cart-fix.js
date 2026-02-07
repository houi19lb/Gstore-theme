/**
 * Mini Cart Sync - Versão Corrigida v3
 * 
 * PROBLEMA: O cookie de sessão WooCommerce não persiste entre páginas na Hostinger
 * (LiteSpeed Cache). Cada navegação cria uma nova sessão temporária com carrinho vazio.
 * 
 * SOLUÇÃO:
 * 1. Não fazer refresh automático da Store API no page load
 * 2. Usar fragments do WC (AJAX com sessão correta) para atualizar contagem
 * 3. Persistir contagem via localStorage entre páginas
 * 4. MutationObserver protege o badge contra re-renders do WC Blocks React
 */

(function($) {
    'use strict';

    // #region agent log - Interceptor de fetch para detectar chamadas WC
    // Monitora TODAS as requisições à Store API e WC AJAX
    (function() {
        var _origFetch = window.fetch;
        window.fetch = function(url, opts) {
            var urlStr = typeof url === 'string' ? url : (url && url.url ? url.url : '');
            if (urlStr.indexOf('wc/store') !== -1 || urlStr.indexOf('wc-ajax') !== -1) {
                var method = (opts && opts.method) || 'GET';
                _origFetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'FETCH-INTERCEPT',message:'Requisicao WC detectada',data:{page:window.location.pathname,url:urlStr.substring(0,120),method:method,caller:new Error().stack?.split('\n').slice(1,4).map(function(s){return s.trim()}).join(' | ')},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix-v3'})}).catch(function(){});
            }
            return _origFetch.apply(this, arguments);
        };
        // Interceptor XMLHttpRequest
        var _origOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function(method, url) {
            var urlStr = String(url || '');
            if (urlStr.indexOf('wc/store') !== -1 || urlStr.indexOf('wc-ajax') !== -1) {
                var _xhr = this;
                _xhr._gstoreDebugUrl = urlStr;
                _xhr._gstoreDebugMethod = method;
                fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'XHR-INTERCEPT',message:'Requisicao WC XHR detectada',data:{page:window.location.pathname,url:urlStr.substring(0,120),method:method,caller:new Error().stack?.split('\n').slice(1,4).map(function(s){return s.trim()}).join(' | ')},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix-v3'})}).catch(function(){});
            }
            return _origOpen.apply(this, arguments);
        };
    })();
    // #endregion

    // Configuração
    const CONFIG = {
        debounceDelay: 300,
        debug: window.gstoreMiniCart?.debug || false
    };

    // Estado
    let lastAddToCartAt = 0;
    let lastUserActionAt = 0;
    let badgeObserver = null;
    let observerPaused = false;
    
    // Chave de localStorage para persistir contagem entre páginas
    const CART_COUNT_KEY = 'gstore_cart_count';
    const CART_COUNT_TS_KEY = 'gstore_cart_count_ts';
    
    /**
     * Salva contagem do carrinho no localStorage
     */
    function saveCartCount(count) {
        try {
            localStorage.setItem(CART_COUNT_KEY, String(count));
            localStorage.setItem(CART_COUNT_TS_KEY, String(Date.now()));
        } catch(e) {}
    }
    
    /**
     * Recupera contagem salva do localStorage (válida por 30 minutos)
     */
    function getSavedCartCount() {
        try {
            var count = parseInt(localStorage.getItem(CART_COUNT_KEY) || '0', 10);
            var ts = parseInt(localStorage.getItem(CART_COUNT_TS_KEY) || '0', 10);
            if (Date.now() - ts > 30 * 60 * 1000) return null;
            return Number.isNaN(count) ? null : count;
        } catch(e) { return null; }
    }

    function debugLog(...args) {
        if (CONFIG.debug && window.console && console.log) {
            console.log('[MiniCart]', ...args);
        }
    }

    function getNonce() {
        return window.wc?.storeApiNonce || window.gstoreMiniCart?.storeApiNonce || null;
    }

    /**
     * Obtém o contador atual do DOM
     */
    function getCurrentCountFromDom() {
        const counterEl =
            document.querySelector('.Gstore-cart-count') ||
            document.querySelector('.wc-block-mini-cart__badge');
        if (!counterEl) return null;
        const value = parseInt(counterEl.textContent || '0', 10);
        return Number.isNaN(value) ? null : value;
    }

    /**
     * Extrai contagem de itens a partir dos fragments do WooCommerce
     */
    function getCountFromFragments(fragments) {
        if (!fragments) return null;
        const fragmentHtml =
            fragments['.Gstore-cart-count'] ||
            fragments['.wc-block-mini-cart__badge'] ||
            null;
        if (!fragmentHtml) return null;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = fragmentHtml;
        const text = wrapper.textContent || '';
        const value = parseInt(text, 10);
        return Number.isNaN(value) ? null : value;
    }

    /**
     * Sincroniza elementos do DOM com o contador do carrinho.
     * Pausa o MutationObserver durante a atualização para evitar loop.
     */
    function syncDOM(count) {
        // #region agent log
        var _currentDomCount = getCurrentCountFromDom();
        if(count !== _currentDomCount) { fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:syncDOM',message:'syncDOM ALTERANDO contador',data:{page:window.location.pathname,oldCount:_currentDomCount,newCount:count},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix-v3'})}).catch(()=>{}); }
        // #endregion

        // Pausa observer para evitar loop
        observerPaused = true;

        const badges = document.querySelectorAll('.wc-block-mini-cart__badge');
        badges.forEach(badge => {
            badge.textContent = count.toString();
            const ariaLabel = badge.getAttribute('aria-label');
            if (ariaLabel) {
                badge.setAttribute('aria-label', ariaLabel.replace(/\d+/, count.toString()));
            }
        });

        const customCounters = document.querySelectorAll('.Gstore-cart-count');
        customCounters.forEach(counter => {
            counter.textContent = count.toString();
            const ariaLabel = counter.getAttribute('aria-label');
            if (ariaLabel) {
                counter.setAttribute('aria-label', ariaLabel.replace(/\d+/, count.toString()));
            }
        });
        
        // Persiste no localStorage para manter entre navegações
        saveCartCount(count);

        // Re-ativa observer após pequeno delay (para que mutações do syncDOM não disparem)
        setTimeout(function() { observerPaused = false; }, 100);
    }

    /**
     * MutationObserver: protege badges contra re-renders externos.
     * 
     * O WC Blocks mini-cart é um componente React que re-renderiza o badge
     * baseado no data store (que tem a sessão errada → 0 itens).
     * O WC fragment system também pode substituir .Gstore-cart-count com HTML
     * do servidor (que tem a sessão errada → 0 itens).
     * 
     * Este observer detecta quando o badge é alterado para 0 por código externo
     * e imediatamente restaura o valor do localStorage.
     */
    function startBadgeProtection() {
        if (badgeObserver) return; // Já está observando

        var savedCount = getSavedCartCount();
        if (savedCount === null || savedCount <= 0) return; // Nada para proteger

        // Seleciona todos os containers que contêm badges
        var targets = [];
        
        // Observa o container do mini-cart block (para capturar re-renders do React)
        var miniCartWrapper = document.querySelector('.wc-block-mini-cart');
        if (miniCartWrapper) targets.push(miniCartWrapper);
        
        // Observa badges individuais
        document.querySelectorAll('.Gstore-cart-count, .wc-block-mini-cart__badge').forEach(function(el) {
            targets.push(el);
        });

        // Observa o header inteiro como fallback (para capturar substituições de fragmentos)
        var header = document.querySelector('header') || document.querySelector('.site-header') || document.querySelector('#masthead');
        if (header) targets.push(header);

        if (targets.length === 0) return;

        badgeObserver = new MutationObserver(function(mutations) {
            if (observerPaused) return;
            
            var currentSaved = getSavedCartCount();
            if (currentSaved === null || currentSaved <= 0) return;
            
            var currentDom = getCurrentCountFromDom();
            
            // Se o DOM foi alterado para 0 (ou null) mas localStorage tem itens, restaura
            if ((currentDom === 0 || currentDom === null) && currentSaved > 0) {
                // #region agent log
                fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:observer',message:'Badge foi zerado externamente - RESTAURANDO',data:{page:window.location.pathname,currentDom:currentDom,savedCount:currentSaved,mutationType:mutations[0]?.type,mutationTarget:mutations[0]?.target?.className||mutations[0]?.target?.nodeName},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix-v3'})}).catch(()=>{});
                // #endregion
                syncDOM(currentSaved);
            }
        });

        var observerConfig = { 
            childList: true, 
            subtree: true, 
            characterData: true 
        };

        targets.forEach(function(target) {
            try {
                badgeObserver.observe(target, observerConfig);
            } catch(e) {}
        });

        debugLog('Badge protection ativa para', targets.length, 'alvos');
    }

    /**
     * Handler para evento added_to_cart.
     * Usa SEMPRE os fragments do WC (sessão correta) para atualizar o contador.
     */
    function handleAddedToCart(event, fragments, cart_hash) {
        debugLog('Produto adicionado ao carrinho');
        lastAddToCartAt = Date.now();
        lastUserActionAt = Date.now();

        // #region agent log
        var _fragmentCount = getCountFromFragments(fragments);
        fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:handleAddedToCart',message:'added_to_cart disparado',data:{page:window.location.pathname,fragmentCount:_fragmentCount,domCount:getCurrentCountFromDom()},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix-v3'})}).catch(()=>{});
        // #endregion

        // Sempre usar fragments para o contador - são confiáveis (mesma sessão WC AJAX)
        const fragmentCount = getCountFromFragments(fragments);
        if (fragmentCount !== null) {
            syncDOM(fragmentCount);
        }

        // Reinicia proteção com o novo count
        if (badgeObserver) { badgeObserver.disconnect(); badgeObserver = null; }
        setTimeout(startBadgeProtection, 500);
    }

    /**
     * Handler para evento removed_from_cart
     */
    function handleRemovedFromCart(event, fragments, cart_hash) {
        debugLog('Produto removido do carrinho');
        lastUserActionAt = Date.now();

        var fragmentCount = getCountFromFragments(fragments);
        if (fragmentCount !== null) {
            syncDOM(fragmentCount);
        }

        // Reinicia proteção com o novo count
        if (badgeObserver) { badgeObserver.disconnect(); badgeObserver = null; }
        setTimeout(startBadgeProtection, 500);
    }

    /**
     * Handler para evento wc_fragments_refreshed.
     * Quando WC atualiza fragments, ele pode substituir .Gstore-cart-count
     * com HTML do servidor que tem sessão errada (0 itens).
     * Precisamos re-aplicar o localStorage count.
     */
    function handleFragmentsRefreshed() {
        debugLog('Fragmentos atualizados');

        var domCount = getCurrentCountFromDom();
        var savedCount = getSavedCartCount();

        // #region agent log
        fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:handleFragmentsRefreshed',message:'wc_fragments_refreshed - verificando se precisa restaurar',data:{page:window.location.pathname,domCount:domCount,savedCount:savedCount,willRestore:(domCount===0||domCount===null)&&savedCount>0},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix-v3'})}).catch(()=>{});
        // #endregion

        // Se fragments zeraram o badge mas localStorage tem itens, restaura
        if ((domCount === 0 || domCount === null) && savedCount !== null && savedCount > 0) {
            syncDOM(savedCount);
        }
    }

    /**
     * Inicializa os event listeners
     */
    function initEventListeners() {
        $(document.body).on('added_to_cart', handleAddedToCart);
        $(document.body).on('removed_from_cart', handleRemovedFromCart);
        $(document.body).on('wc_fragments_refreshed', handleFragmentsRefreshed);
        
        // NÃO usar wc_cart_button_updated - ele disparava refreshMiniCart()
        // que chamava a Store API com sessão errada e sobrescrevia o badge.
        
        $(document.body).on('updated_wc_div', function() {
            if (document.body.classList.contains('woocommerce-cart')) {
                lastUserActionAt = Date.now();
                // Na página do carrinho a sessão está correta, podemos ler fragments
                var domCount = getCurrentCountFromDom();
                if (domCount !== null) saveCartCount(domCount);
            }
        });

        $(document.body).on('wc_cart_emptied', function() {
            lastUserActionAt = Date.now();
            syncDOM(0);
            // Para a proteção (carrinho realmente está vazio)
            if (badgeObserver) { badgeObserver.disconnect(); badgeObserver = null; }
        });
    }

    /**
     * Inicialização
     */
    function init() {
        debugLog('Inicializando Mini Cart Sync v3...');

        var _phpSession = window.__gstoreDebugSession || {};

        // #region agent log
        fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:init',message:'MiniCart init v3 - com MutationObserver',data:{page:window.location.pathname,domCartCount:getCurrentCountFromDom(),phpCartCount:_phpSession.cart_count_php,phpSessionId:_phpSession.php_session_id||'N/A',sessionCookie:_phpSession.session_cookie,savedCount:getSavedCartCount(),cookies:document.cookie.split(';').map(c=>c.trim().split('=')[0]).filter(c=>c.includes('woocommerce')||c.includes('wp_woocommerce')||c.includes('cart')||c.includes('session')).join(',')},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix-v3'})}).catch(()=>{});
        // #endregion

        // Inicializa listeners
        initEventListeners();

        var domCount = getCurrentCountFromDom();
        var savedCount = getSavedCartCount();
        var phpCount = _phpSession.cart_count_php;
        
        // Se o PHP tem itens, confia no PHP (sessão correta)
        if (typeof phpCount === 'number' && phpCount > 0) {
            if (domCount !== phpCount) {
                syncDOM(phpCount);
            }
            // Atualiza localStorage com valor do PHP
            saveCartCount(phpCount);
        }
        // Se o PHP mostra 0 mas localStorage tem itens, usa localStorage
        else if (savedCount !== null && savedCount > 0 && (domCount === 0 || domCount === null)) {
            // #region agent log
            fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:init:restore',message:'Restaurando count de localStorage',data:{page:window.location.pathname,domCount:domCount,savedCount:savedCount,phpCount:phpCount},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix-v3'})}).catch(()=>{});
            // #endregion
            syncDOM(savedCount);
        }

        // Inicia proteção do badge contra re-renders externos (WC Blocks React, fragments)
        // Delay para garantir que nosso syncDOM já rodou
        setTimeout(startBadgeProtection, 300);

        debugLog('Mini Cart Sync v3 inicializado');
    }

    // Inicializa quando o DOM estiver pronto
    if (typeof jQuery !== 'undefined' && jQuery.ready) {
        jQuery(document).ready(init);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.gstoreRefreshMiniCart = function() { debugLog('refreshMiniCart desabilitado para proteger sessão'); };

})(jQuery);

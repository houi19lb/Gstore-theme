/**
 * Mini Cart Sync - Versão Simplificada
 * 
 * Sincroniza o Mini Cart Block do WooCommerce com eventos de adição/remoção de produtos.
 * Abordagem simplificada usando apenas a API REST do WooCommerce Blocks.
 */

(function($) {
    'use strict';

    // Configuração
    const CONFIG = {
        debounceDelay: 300,
        apiTimeout: 5000,
        debug: window.gstoreMiniCart?.debug || false
    };

    const STORAGE_KEY_LAST_COUNT = 'gstore_mini_cart_last_count';

    // Estado
    let refreshTimer = null;
    let isRefreshing = false;
    let lastAddToCartAt = 0;

    function getLastStoredCount() {
        try {
            var n = parseInt(sessionStorage.getItem(STORAGE_KEY_LAST_COUNT) || '0', 10);
            return (n >= 0 && Number.isFinite(n)) ? n : 0;
        } catch (e) {
            return 0;
        }
    }

    function setLastStoredCount(count) {
        try {
            sessionStorage.setItem(STORAGE_KEY_LAST_COUNT, String(count >= 0 ? count : 0));
        } catch (e) {}
    }

    /**
     * Log de debug
     */
    function debugLog(...args) {
        if (CONFIG.debug && window.console && console.log) {
            console.log('[MiniCart]', ...args);
        }
    }

    /**
     * Log de erro
     */
    function errorLog(...args) {
        if (window.console && console.error) {
            console.error('[MiniCart]', ...args);
        }
    }

    /**
     * Obtém o nonce da API do Store
     */
    function getNonce() {
        return window.wc?.storeApiNonce || window.gstoreMiniCart?.storeApiNonce || null;
    }

    /**
     * Obtém a URL do endpoint da API do carrinho
     */
    function getCartAPIUrl() {
        return window.gstoreMiniCart?.cartEndpoint || 
               window.location.origin + '/wp-json/wc/store/v1/cart';
    }

    /**
     * Verifica se o store do WordPress está disponível
     */
    function isStoreAvailable() {
        return !!(window.wp?.data?.dispatch?.('wc/store/cart'));
    }

    /**
     * Obtém o contador atual do DOM
     */
    function getCurrentCountFromDom() {
        const counterEl =
            document.querySelector('.Gstore-cart-count') ||
            document.querySelector('.wc-block-mini-cart__badge');
        if (!counterEl) {
            return null;
        }
        const value = parseInt(counterEl.textContent || '0', 10);
        return Number.isNaN(value) ? null : value;
    }

    /**
     * Extrai contagem de itens a partir dos fragments do WooCommerce
     */
    function getCountFromFragments(fragments) {
        if (!fragments) {
            return null;
        }

        const fragmentHtml =
            fragments['.Gstore-cart-count'] ||
            fragments['.wc-block-mini-cart__badge'] ||
            null;

        if (!fragmentHtml) {
            return null;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = fragmentHtml;
        const text = wrapper.textContent || '';
        const value = parseInt(text, 10);
        return Number.isNaN(value) ? null : value;
    }

    /**
     * Atualiza o carrinho via API REST e sincroniza o store
     */
    function refreshCart() {
        return new Promise((resolve, reject) => {
            const nonce = getNonce();
            const apiUrl = getCartAPIUrl();

            if (!nonce) {
                // #region agent log
                fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:refreshCart',message:'Nonce NAO disponivel - abortando refresh',data:{page:window.location.pathname,nonceWc:!!window.wc?.storeApiNonce,nonceGstore:!!window.gstoreMiniCart?.storeApiNonce},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'B'})}).catch(()=>{});
                // #endregion
                reject(new Error('Nonce não disponível'));
                return;
            }

            // #region agent log
            var _preRefreshDomCount = getCurrentCountFromDom();
            fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:refreshCart:before',message:'Iniciando chamada Store API',data:{page:window.location.pathname,apiUrl:apiUrl,noncePrefix:String(nonce).substring(0,8),domCountBefore:_preRefreshDomCount,trigger:new Error().stack?.split('\n').slice(1,4).map(s=>s.trim()).join(' | ')},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'B,D,E'})}).catch(()=>{});
            // #endregion

            debugLog('Atualizando carrinho via API...');

            fetch(apiUrl + '?_t=' + Date.now(), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WC-Store-API-Nonce': nonce,
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            })
            .then(response => {
                // #region agent log
                fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:refreshCart:response',message:'Resposta da Store API recebida',data:{page:window.location.pathname,status:response.status,ok:response.ok,nonceHeader:response.headers.get('X-WC-Store-API-Nonce')||'none',cacheHeader:response.headers.get('X-Cache')||response.headers.get('x-litespeed-cache')||'none'},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'B,D'})}).catch(()=>{});
                // #endregion
                if (!response.ok) {
                    throw new Error(`API retornou ${response.status}`);
                }
                return response.json();
            })
            .then(cartData => {
                debugLog('Dados do carrinho recebidos:', cartData);

                // #region agent log
                fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:refreshCart:data',message:'Dados do carrinho processados',data:{page:window.location.pathname,itemsCount:cartData.items_count,itemsTotal:cartData.totals?.total_items||'N/A',items:(cartData.items||[]).map(function(i){return{id:i.id,name:i.name,qty:i.quantity}}),domCountBefore:_preRefreshDomCount,willSyncTo:cartData.items_count||0},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'D,E'})}).catch(()=>{});
                // #endregion

                var apiCount = cartData.items_count || 0;
                // Mitigação cache: quando a API retorna 0 mas o DOM já mostra itens, a resposta pode ser cache (ex.: home em cache).
                // Não sobrescrever o badge com 0; fazer retry e só atualizar se o retry trouxer count >= atual (nunca reduzir).
                if (apiCount === 0 && _preRefreshDomCount > 0) {
                    debugLog('API retornou 0 com itens no DOM – possível cache; agendando retry.');
                    var _domCountWhenSuspicious = _preRefreshDomCount;
                    setTimeout(function () {
                        refreshCart().then(function (retryData) {
                            var retryCount = (retryData && retryData.items_count != null) ? retryData.items_count : 0;
                            var currentDom = getCurrentCountFromDom();
                            var safeCount = currentDom != null ? currentDom : _domCountWhenSuspicious;
                            // Não reduzir o badge quando suspeitamos de cache: só atualizar se o retry trouxer >= atual.
                            if (retryCount < safeCount) {
                                debugLog('Retry retornou ' + retryCount + ' < ' + safeCount + '; mantendo badge atual (possível cache).');
                                return;
                            }
                            if (isStoreAvailable()) {
                                try {
                                    window.wp.data.dispatch('wc/store/cart').receiveCart(retryData);
                                } catch (e) {}
                            }
                            syncDOM(retryCount);
                            setLastStoredCount(retryCount);
                        }).catch(function () {});
                    }, 600);
                    resolve(cartData);
                    return;
                }

                // Atualiza o store do WordPress se disponível
                if (isStoreAvailable()) {
                    try {
                        const cartStore = window.wp.data.dispatch('wc/store/cart');
                        cartStore.receiveCart(cartData);
                        debugLog('Store atualizado com sucesso');
                    } catch (e) {
                        debugLog('Erro ao atualizar store:', e);
                    }
                }

                // Sincroniza elementos do DOM e persiste para mitigação de cache
                var countToSync = cartData.items_count || 0;
                syncDOM(countToSync);
                setLastStoredCount(countToSync);
                
                resolve(cartData);
            })
            .catch(error => {
                // #region agent log
                fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:refreshCart:error',message:'ERRO na Store API',data:{page:window.location.pathname,error:String(error),domCountBefore:_preRefreshDomCount},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'B,D'})}).catch(()=>{});
                // #endregion
                errorLog('Erro ao atualizar carrinho:', error);
                reject(error);
            });
        });
    }

    /**
     * Sincroniza elementos do DOM com o contador do carrinho
     */
    function syncDOM(count) {
        // #region agent log
        var _currentDomCount = getCurrentCountFromDom();
        if(count !== _currentDomCount) { fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:syncDOM',message:'syncDOM ALTERANDO contador',data:{page:window.location.pathname,oldCount:_currentDomCount,newCount:count,caller:new Error().stack?.split('\n').slice(1,3).map(s=>s.trim()).join(' | ')},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'E'})}).catch(()=>{}); }
        // #endregion
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
    }

    /**
     * Função principal de refresh com debounce
     */
    function refreshMiniCart() {
        if (!getNonce()) {
            return Promise.resolve();
        }

        // Limpa timer anterior
        if (refreshTimer) {
            clearTimeout(refreshTimer);
        }

        // Se já está atualizando, agenda para depois
        if (isRefreshing) {
            refreshTimer = setTimeout(() => refreshMiniCart(), CONFIG.debounceDelay);
            return Promise.resolve();
        }

        // Debounce
        return new Promise((resolve) => {
            refreshTimer = setTimeout(() => {
                refreshTimer = null;
                isRefreshing = true;

                refreshCart()
                    .then(data => {
                        isRefreshing = false;
                        resolve(data);
                    })
                    .catch(error => {
                        isRefreshing = false;
                        errorLog('Falha ao atualizar mini cart:', error);
                        resolve(null);
                    });
            }, CONFIG.debounceDelay);
        });
    }

    /**
     * Handler para evento added_to_cart
     */
    function handleAddedToCart(event, fragments, cart_hash) {
        debugLog('Produto adicionado ao carrinho');
        lastAddToCartAt = Date.now();

        // #region agent log
        fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:handleAddedToCart',message:'added_to_cart disparado',data:{page:window.location.pathname,isSingleProduct:document.body.classList.contains('single-product'),fragmentCount:getCountFromFragments(fragments),cartHash:cart_hash||'none',domCount:getCurrentCountFromDom()},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'C'})}).catch(()=>{});
        // #endregion

        if (document.body.classList.contains('single-product')) {
            const fragmentCount = getCountFromFragments(fragments);
            if (fragmentCount !== null) {
                syncDOM(fragmentCount);
            }
            return;
        }

        refreshMiniCart();
    }

    /**
     * Handler para evento removed_from_cart
     */
    function handleRemovedFromCart(event, fragments, cart_hash) {
        debugLog('Produto removido do carrinho');
        refreshMiniCart();
    }

    /**
     * Handler para evento wc_fragments_refreshed
     */
    function handleFragmentsRefreshed() {
        debugLog('Fragmentos atualizados');
        var _isSingleProduct = document.body.classList.contains('single-product');
        var _elapsed = Date.now() - lastAddToCartAt;
        var _willSkip = _isSingleProduct && _elapsed < 1500;
        // #region agent log
        fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:handleFragmentsRefreshed',message:'wc_fragments_refreshed disparado',data:{page:window.location.pathname,isSingleProduct:_isSingleProduct,elapsed:_elapsed,willSkip:_willSkip,willRefresh:!_willSkip,domCartCount:getCurrentCountFromDom()},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'C'})}).catch(()=>{});
        // #endregion
        if (_isSingleProduct) {
            if (_elapsed < 1500) {
                return;
            }
        }
        refreshMiniCart();
    }

    /**
     * Inicializa os event listeners
     */
    function initEventListeners() {
        // Eventos principais do WooCommerce
        $(document.body).on('added_to_cart', handleAddedToCart);
        $(document.body).on('removed_from_cart', handleRemovedFromCart);
        $(document.body).on('wc_fragments_refreshed', handleFragmentsRefreshed);
        
        // Eventos adicionais
        $(document.body).on('wc_cart_button_updated', refreshMiniCart);
        $(document.body).on('updated_wc_div', refreshMiniCart);
        $(document.body).on('wc_cart_emptied', () => {
            syncDOM(0);
            setLastStoredCount(0);
            refreshMiniCart();
        });

        // Monitora mudanças de quantidade
        $(document.body).on('change', '.quantity input.qty', refreshMiniCart);
    }

    /**
     * Inicialização
     */
    function init() {
        debugLog('Inicializando Mini Cart Sync...');

        // #region agent log
        var _phpSession = window.__gstoreDebugSession || {};
        fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:init',message:'MiniCart init - page load',data:{page:window.location.pathname,bodyClasses:document.body.className.split(' ').filter(c=>c.includes('product')||c.includes('cart')||c.includes('checkout')||c.includes('shop')).join(' '),domCartCount:getCurrentCountFromDom(),storeAvailable:isStoreAvailable(),nonce:!!getNonce(),nonceSource:window.wc?.storeApiNonce?'wc.storeApiNonce':window.gstoreMiniCart?.storeApiNonce?'gstoreMiniCart':'none',cookies:document.cookie.split(';').map(c=>c.trim().split('=')[0]).filter(c=>c.includes('woocommerce')||c.includes('wp_woocommerce')||c.includes('cart')||c.includes('session')).join(','),phpSessionId:_phpSession.php_session_id||'N/A',phpCartCount:_phpSession.cart_count_php,phpCartItems:_phpSession.cart_items_php||[],buyNowActive:_phpSession.buy_now_active,savedCartExists:_phpSession.saved_cart_exists,savedCartCount:_phpSession.saved_cart_count,buyNowProduct:_phpSession.buy_now_product,sessionCookie:_phpSession.session_cookie,pageType:_phpSession.page_type,restoreWouldFire:_phpSession.restore_would_fire},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'A,D,F,G'})}).catch(()=>{});
        // #endregion

        // Inicializa listeners
        initEventListeners();

        // Na home/páginas em cache o HTML pode vir com badge 0; restaurar último count conhecido para a mitigação funcionar.
        function reapplyStoredCountIfNeeded() {
            var domCount = getCurrentCountFromDom();
            var storedCount = getLastStoredCount();
            if ((domCount === 0 || domCount === null) && storedCount > 0) {
                debugLog('Reaplicando badge de sessionStorage: ' + storedCount + ' (mitigação cache).');
                syncDOM(storedCount);
            }
        }
        reapplyStoredCountIfNeeded();
        // O Mini Cart Block (React) pode fazer seu próprio fetch e re-renderizar o badge com 0; reaplicar após delays.
        setTimeout(reapplyStoredCountIfNeeded, 500);
        setTimeout(reapplyStoredCountIfNeeded, 1500);
        setTimeout(reapplyStoredCountIfNeeded, 3000);

        // Aguarda o store estar disponível e faz refresh inicial (na home em cache, pular se já temos count salvo para evitar API 0).
        var isFront = (window.location.pathname === '/' || window.location.pathname === '') && !document.body.classList.contains('woocommerce-cart');
        const checkStore = setInterval(() => {
            if (isStoreAvailable() || document.querySelector('.wc-block-mini-cart')) {
                clearInterval(checkStore);
                if (isFront && getLastStoredCount() > 0) {
                    debugLog('Home com count em sessionStorage; pulando refresh inicial (evita API em cache).');
                } else {
                    debugLog('Store disponível, fazendo refresh inicial...');
                    setTimeout(() => refreshMiniCart(), 500);
                }
            }
        }, 100);

        // Timeout de segurança
        setTimeout(() => {
            clearInterval(checkStore);
        }, 10000);

        debugLog('Mini Cart Sync inicializado');
    }

    // Inicializa quando o DOM estiver pronto
    if (typeof jQuery !== 'undefined' && jQuery.ready) {
        jQuery(document).ready(init);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expõe função global para debug
    window.gstoreRefreshMiniCart = refreshMiniCart;

})(jQuery);

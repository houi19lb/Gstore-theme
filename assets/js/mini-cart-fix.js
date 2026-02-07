/**
 * Mini Cart Sync - Versão Corrigida
 * 
 * Sincroniza o Mini Cart Block do WooCommerce com eventos de adição/remoção de produtos.
 * 
 * CORREÇÃO: Não faz refresh automático via Store API no carregamento da página.
 * A Store API pode usar uma sessão diferente da sessão WC (especialmente em
 * ambientes com cache como LiteSpeed/Hostinger), causando a exibição de
 * carrinho vazio mesmo quando há itens. Agora confiamos nos fragments do WC
 * (que usam AJAX com a sessão correta) e só chamamos a Store API em resposta
 * a ações explícitas do usuário.
 */

(function($) {
    'use strict';

    // Configuração
    const CONFIG = {
        debounceDelay: 300,
        apiTimeout: 5000,
        debug: window.gstoreMiniCart?.debug || false
    };

    // Estado
    let refreshTimer = null;
    let isRefreshing = false;
    let lastAddToCartAt = 0;
    let lastUserActionAt = 0;
    
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
            // Válido por 30 minutos
            if (Date.now() - ts > 30 * 60 * 1000) return null;
            return Number.isNaN(count) ? null : count;
        } catch(e) { return null; }
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
     * Atualiza o carrinho via API REST e sincroniza o store.
     * SÓ deve ser chamado após ações explícitas do usuário (add/remove).
     */
    function refreshCart() {
        return new Promise((resolve, reject) => {
            const nonce = getNonce();
            const apiUrl = getCartAPIUrl();

            if (!nonce) {
                // #region agent log
                fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:refreshCart',message:'Nonce NAO disponivel - abortando refresh',data:{page:window.location.pathname},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix'})}).catch(()=>{});
                // #endregion
                reject(new Error('Nonce não disponível'));
                return;
            }

            // #region agent log
            var _preRefreshDomCount = getCurrentCountFromDom();
            fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:refreshCart:before',message:'Iniciando chamada Store API',data:{page:window.location.pathname,domCountBefore:_preRefreshDomCount,timeSinceUserAction:Date.now()-lastUserActionAt},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix'})}).catch(()=>{});
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
                if (!response.ok) {
                    throw new Error(`API retornou ${response.status}`);
                }
                return response.json();
            })
            .then(cartData => {
                debugLog('Dados do carrinho recebidos:', cartData);

                var newCount = cartData.items_count || 0;

                // #region agent log
                fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:refreshCart:data',message:'Dados do carrinho processados',data:{page:window.location.pathname,itemsCount:newCount,items:(cartData.items||[]).map(function(i){return{id:i.id,name:i.name,qty:i.quantity}}),domCountBefore:_preRefreshDomCount,willSyncTo:newCount},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix'})}).catch(()=>{});
                // #endregion

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

                // Sincroniza elementos do DOM
                syncDOM(newCount);
                
                resolve(cartData);
            })
            .catch(error => {
                // #region agent log
                fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:refreshCart:error',message:'ERRO na Store API',data:{page:window.location.pathname,error:String(error)},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix'})}).catch(()=>{});
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
        if(count !== _currentDomCount) { fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:syncDOM',message:'syncDOM ALTERANDO contador',data:{page:window.location.pathname,oldCount:_currentDomCount,newCount:count},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix'})}).catch(()=>{}); }
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
        
        // Persiste no localStorage para manter entre navegações
        saveCartCount(count);
    }

    /**
     * Função principal de refresh com debounce.
     * SÓ deve ser chamada após ações do usuário, NUNCA automaticamente no page load.
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
     * Handler para evento added_to_cart.
     * 
     * Usa SEMPRE os fragments do WooCommerce para atualizar o contador.
     * Os fragments são confiáveis porque vêm do AJAX add-to-cart que usa
     * a sessão WC correta. A Store API pode ter uma sessão diferente.
     */
    function handleAddedToCart(event, fragments, cart_hash) {
        debugLog('Produto adicionado ao carrinho');
        lastAddToCartAt = Date.now();
        lastUserActionAt = Date.now();

        // #region agent log
        var _fragmentCount = getCountFromFragments(fragments);
        fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:handleAddedToCart',message:'added_to_cart disparado',data:{page:window.location.pathname,isSingleProduct:document.body.classList.contains('single-product'),fragmentCount:_fragmentCount,cartHash:cart_hash||'none',domCount:getCurrentCountFromDom()},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix'})}).catch(()=>{});
        // #endregion

        // Sempre usar fragments para o contador - são confiáveis (mesma sessão WC)
        const fragmentCount = getCountFromFragments(fragments);
        if (fragmentCount !== null) {
            syncDOM(fragmentCount);
        }

        // NÃO chamar refreshMiniCart() aqui - a Store API pode ter sessão diferente
        // e sobrescrever o contador correto com dados vazios.
        // Os fragments já atualizaram o DOM corretamente.
    }

    /**
     * Handler para evento removed_from_cart
     */
    function handleRemovedFromCart(event, fragments, cart_hash) {
        debugLog('Produto removido do carrinho');
        lastUserActionAt = Date.now();

        // Usar fragments se disponíveis
        var fragmentCount = getCountFromFragments(fragments);
        if (fragmentCount !== null) {
            syncDOM(fragmentCount);
        }

        // No contexto de remoção (página do carrinho), refresh pode ser seguro
        // pois a sessão tende a estar correta nesse ponto
        refreshMiniCart();
    }

    /**
     * Handler para evento wc_fragments_refreshed.
     * 
     * CORREÇÃO: Não dispara mais refreshMiniCart() automaticamente.
     * O refresh dos fragments pelo WooCommerce já atualiza o DOM via HTML.
     * Chamar a Store API aqui causava sobrescrita com dados de sessão errada.
     */
    function handleFragmentsRefreshed() {
        debugLog('Fragmentos atualizados');

        // #region agent log
        fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:handleFragmentsRefreshed',message:'wc_fragments_refreshed disparado - NAO chamando refreshMiniCart',data:{page:window.location.pathname,domCartCount:getCurrentCountFromDom()},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix'})}).catch(()=>{});
        // #endregion

        // NÃO chamar refreshMiniCart() aqui.
        // Os fragments do WC já atualizaram os elementos do DOM.
        // A Store API pode ter sessão diferente e causar regressão.
    }

    /**
     * Inicializa os event listeners
     */
    function initEventListeners() {
        // Eventos principais do WooCommerce
        $(document.body).on('added_to_cart', handleAddedToCart);
        $(document.body).on('removed_from_cart', handleRemovedFromCart);
        $(document.body).on('wc_fragments_refreshed', handleFragmentsRefreshed);
        
        // Eventos adicionais - só dispara refresh em contextos de ação do usuário
        $(document.body).on('wc_cart_button_updated', function() {
            lastUserActionAt = Date.now();
            refreshMiniCart();
        });
        $(document.body).on('updated_wc_div', function() {
            // updated_wc_div acontece na página do carrinho após update - sessão correta
            if (document.body.classList.contains('woocommerce-cart')) {
                lastUserActionAt = Date.now();
                refreshMiniCart();
            }
        });
        $(document.body).on('wc_cart_emptied', () => {
            lastUserActionAt = Date.now();
            syncDOM(0);
        });
    }

    /**
     * Inicialização
     */
    function init() {
        debugLog('Inicializando Mini Cart Sync...');

        // #region agent log
        var _phpSession = window.__gstoreDebugSession || {};
        fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:init',message:'MiniCart init (POST-FIX) - SEM refresh automatico',data:{page:window.location.pathname,domCartCount:getCurrentCountFromDom(),phpCartCount:_phpSession.cart_count_php,phpSessionId:_phpSession.php_session_id||'N/A',sessionCookie:_phpSession.session_cookie,cookies:document.cookie.split(';').map(c=>c.trim().split('=')[0]).filter(c=>c.includes('woocommerce')||c.includes('wp_woocommerce')||c.includes('cart')||c.includes('session')).join(',')},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix'})}).catch(()=>{});
        // #endregion

        // Inicializa listeners
        initEventListeners();

        // CORREÇÃO: NÃO fazer refresh automático da Store API no carregamento.
        // O refresh automático via Store API causava o bug porque:
        // 1. A Store API usa uma sessão que pode ser diferente da sessão WC
        //    (especialmente com LiteSpeed Cache na Hostinger)
        // 2. Retornava carrinho vazio → syncDOM(0) → apagava itens do usuário
        // 
        // Em vez disso, usamos localStorage para manter o contador entre páginas.
        var domCount = getCurrentCountFromDom();
        var savedCount = getSavedCartCount();
        var phpCount = _phpSession.cart_count_php;
        
        // Se o PHP tem itens, confia no PHP (sessão correta)
        if (typeof phpCount === 'number' && phpCount > 0) {
            if (domCount !== phpCount) {
                syncDOM(phpCount);
            }
        }
        // Se o PHP mostra 0 mas localStorage tem itens, usa localStorage
        // (PHP provavelmente tem sessão errada por causa do cache)
        else if (savedCount !== null && savedCount > 0 && (domCount === 0 || domCount === null)) {
            // #region agent log
            fetch('http://127.0.0.1:7246/ingest/82530f36-f41c-4a9b-9141-c3c4bf366209',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'mini-cart-fix.js:init:restore',message:'Restaurando count de localStorage (PHP tem sessao errada)',data:{page:window.location.pathname,domCount:domCount,savedCount:savedCount,phpCount:phpCount},timestamp:Date.now(),sessionId:'debug-session',runId:'post-fix'})}).catch(()=>{});
            // #endregion
            syncDOM(savedCount);
        }

        debugLog('Mini Cart Sync inicializado (sem refresh automático)');
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

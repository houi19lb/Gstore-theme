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

    // Estado
    let refreshTimer = null;
    let isRefreshing = false;

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
     * Atualiza o carrinho via API REST e sincroniza o store
     */
    function refreshCart() {
        return new Promise((resolve, reject) => {
            const nonce = getNonce();
            const apiUrl = getCartAPIUrl();

            if (!nonce) {
                reject(new Error('Nonce não disponível'));
                return;
            }

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
                syncDOM(cartData.items_count || 0);
                
                resolve(cartData);
            })
            .catch(error => {
                errorLog('Erro ao atualizar carrinho:', error);
                reject(error);
            });
        });
    }

    /**
     * Sincroniza elementos do DOM com o contador do carrinho
     */
    function syncDOM(count) {
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

        // Inicializa listeners
        initEventListeners();

        // Aguarda o store estar disponível e faz refresh inicial
        const checkStore = setInterval(() => {
            if (isStoreAvailable() || document.querySelector('.wc-block-mini-cart')) {
                clearInterval(checkStore);
                debugLog('Store disponível, fazendo refresh inicial...');
                setTimeout(() => refreshMiniCart(), 500);
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

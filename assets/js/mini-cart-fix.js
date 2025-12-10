/**
 * Fix for Mini Cart Block synchronization with legacy Add to Cart buttons
 * 
 * Solves the issue where the Mini Cart Block doesn't update correctly
 * when adding products via standard AJAX buttons or after page reload.
 * 
 * Implements multiple fallback strategies for maximum reliability:
 * 1. Store invalidation (primary method)
 * 2. API REST direct call (fallback)
 * 3. Component force reload (fallback)
 * 4. Page reload (last resort)
 */
(function($) {
    'use strict';

    // Configuration
    const CONFIG = {
        maxRetries: 3,
        initialRetryDelay: 300,
        maxRetryDelay: 2000,
        debounceDelay: 150,
        storeCheckTimeout: 5000,
        debug: false // Set to true for debugging
    };

    // State management
    let isRefreshing = false;
    let refreshQueue = [];
    let retryCount = 0;
    let lastRefreshTime = 0;

    /**
     * Debug logging (only when enabled)
     */
    function debugLog(...args) {
        if (CONFIG.debug && window.console && console.log) {
            console.log('[MiniCartFix]', ...args);
        }
    }

    /**
     * Error logging
     */
    function errorLog(...args) {
        if (window.console && console.error) {
            console.error('[MiniCartFix]', ...args);
        }
    }

    /**
     * Check if WordPress data store is available
     */
    function isStoreAvailable() {
        try {
            return !!(
                window.wp &&
                window.wp.data &&
                window.wp.data.dispatch &&
                window.wp.data.dispatch('wc/store/cart')
            );
        } catch (e) {
            debugLog('Store check failed:', e);
            return false;
        }
    }

    /**
     * Check if WooCommerce API REST is available
     */
    function isAPIAvailable() {
        return !!(
            window.wc &&
            window.wc.storeApiNonce &&
            typeof fetch !== 'undefined'
        );
    }

    /**
     * Get WooCommerce API endpoint URL
     */
    function getCartAPIUrl() {
        if (!window.wc || !window.wc.storeApiNonce) {
            // Fallback: try to construct from known patterns
            const siteUrl = window.location.origin;
            return siteUrl + '/wp-json/wc/store/v1/cart';
        }
        
        // WooCommerce Blocks typically provides this
        const nonce = window.wc.storeApiNonce;
        const endpoint = '/wp-json/wc/store/v1/cart';
        return endpoint;
    }

    /**
     * Strategy 1: Invalidate store resolution (primary method)
     */
    function invalidateStore() {
        return new Promise((resolve, reject) => {
            try {
                if (!isStoreAvailable()) {
                    reject(new Error('Store not available'));
                    return;
                }

                const cartStore = window.wp.data.dispatch('wc/store/cart');
                
                if (!cartStore || typeof cartStore.invalidateResolutionForStoreSelector !== 'function') {
                    reject(new Error('Invalidate method not available'));
                    return;
                }

                cartStore.invalidateResolutionForStoreSelector('getCartData');
                
                debugLog('Store invalidated successfully');
                
                // Give it a moment to process
                setTimeout(() => {
                    resolve(true);
                }, 100);
            } catch (e) {
                debugLog('Store invalidation failed:', e);
                reject(e);
            }
        });
    }

    /**
     * Strategy 2: Direct API REST call (fallback)
     */
    function refreshViaAPI() {
        return new Promise((resolve, reject) => {
            try {
                if (!isAPIAvailable()) {
                    reject(new Error('API not available'));
                    return;
                }

                const apiUrl = getCartAPIUrl();
                const nonce = window.wc.storeApiNonce;

                fetch(apiUrl, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WC-Store-API-Nonce': nonce
                    },
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`API returned ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    debugLog('API refresh successful:', data);
                    // Trigger store update if possible
                    if (isStoreAvailable()) {
                        try {
                            window.wp.data.dispatch('wc/store/cart').receiveCart(data);
                        } catch (e) {
                            debugLog('Failed to update store with API data:', e);
                        }
                    }
                    resolve(data);
                })
                .catch(error => {
                    debugLog('API refresh failed:', error);
                    reject(error);
                });
            } catch (e) {
                debugLog('API refresh error:', e);
                reject(e);
            }
        });
    }

    /**
     * Strategy 3: Force component reload (fallback)
     */
    function forceComponentReload() {
        return new Promise((resolve, reject) => {
            try {
                // Try to find and trigger a re-render of the mini-cart component
                const miniCartButton = document.querySelector('.wc-block-mini-cart__button');
                const miniCartDrawer = document.querySelector('.wc-block-mini-cart__drawer');
                
                if (miniCartButton) {
                    // Trigger a click event to open/close and force refresh
                    const event = new MouseEvent('click', {
                        bubbles: true,
                        cancelable: true,
                        view: window
                    });
                    
                    // Only trigger if drawer is not already open
                    const isOpen = miniCartDrawer && miniCartDrawer.classList.contains('is-open');
                    
                    if (!isOpen) {
                        miniCartButton.dispatchEvent(event);
                        setTimeout(() => {
                            miniCartButton.dispatchEvent(event);
                            debugLog('Component reload triggered');
                            resolve(true);
                        }, 300);
                    } else {
                        debugLog('Component already open, skipping reload');
                        resolve(true);
                    }
                } else {
                    reject(new Error('Mini cart component not found'));
                }
            } catch (e) {
                debugLog('Component reload failed:', e);
                reject(e);
            }
        });
    }

    /**
     * Strategy 4: Page reload (last resort - only in extreme cases)
     */
    function reloadPage() {
        debugLog('Reloading page as last resort');
        window.location.reload();
    }

    /**
     * Calculate retry delay with exponential backoff
     */
    function getRetryDelay(attempt) {
        const delay = Math.min(
            CONFIG.initialRetryDelay * Math.pow(2, attempt),
            CONFIG.maxRetryDelay
        );
        return delay;
    }

    /**
     * Main refresh function with multiple strategies and retry logic
     */
    function refreshMiniCart(force = false) {
        // Prevent multiple simultaneous refreshes
        if (isRefreshing && !force) {
            debugLog('Refresh already in progress, queuing...');
            refreshQueue.push(() => refreshMiniCart(false));
            return Promise.resolve();
        }

        // Debounce rapid calls
        const now = Date.now();
        if (!force && (now - lastRefreshTime) < CONFIG.debounceDelay) {
            debugLog('Debouncing rapid refresh call');
            return Promise.resolve();
        }

        lastRefreshTime = now;
        isRefreshing = true;
        retryCount = 0;

        debugLog('Starting mini-cart refresh...');

        return executeRefreshStrategies()
            .then(() => {
                debugLog('Mini-cart refresh completed successfully');
                isRefreshing = false;
                retryCount = 0;
                
                // Process queued refreshes
                if (refreshQueue.length > 0) {
                    const nextRefresh = refreshQueue.shift();
                    setTimeout(() => nextRefresh(), CONFIG.debounceDelay);
                }
            })
            .catch(error => {
                errorLog('All refresh strategies failed:', error);
                isRefreshing = false;
                retryCount = 0;
                
                // Process queued refreshes even on failure
                if (refreshQueue.length > 0) {
                    const nextRefresh = refreshQueue.shift();
                    setTimeout(() => nextRefresh(), CONFIG.debounceDelay);
                }
            });
    }

    /**
     * Execute refresh strategies in cascade with retry logic
     */
    function executeRefreshStrategies(attempt = 0) {
        return new Promise((resolve, reject) => {
            // Strategy 1: Store invalidation
            invalidateStore()
                .then(() => {
                    debugLog('Strategy 1 (Store) succeeded');
                    resolve(true);
                })
                .catch(storeError => {
                    debugLog('Strategy 1 (Store) failed, trying Strategy 2...');
                    
                    // Strategy 2: API REST
                    return refreshViaAPI()
                        .then(() => {
                            debugLog('Strategy 2 (API) succeeded');
                            resolve(true);
                        })
                        .catch(apiError => {
                            debugLog('Strategy 2 (API) failed, trying Strategy 3...');
                            
                            // Strategy 3: Component reload
                            return forceComponentReload()
                                .then(() => {
                                    debugLog('Strategy 3 (Component) succeeded');
                                    resolve(true);
                                })
                                .catch(componentError => {
                                    debugLog('Strategy 3 (Component) failed');
                                    
                                    // Retry logic
                                    if (attempt < CONFIG.maxRetries) {
                                        const delay = getRetryDelay(attempt);
                                        debugLog(`Retrying in ${delay}ms (attempt ${attempt + 1}/${CONFIG.maxRetries})...`);
                                        
                                        setTimeout(() => {
                                            executeRefreshStrategies(attempt + 1)
                                                .then(resolve)
                                                .catch(reject);
                                        }, delay);
                                    } else {
                                        // All strategies failed, but don't reload page automatically
                                        // Just log the error - page reload is too disruptive
                                        errorLog('All strategies exhausted. Mini-cart may be out of sync.');
                                        reject(new Error('All refresh strategies failed'));
                                    }
                                });
                        });
                });
        });
    }

    /**
     * Verify that refresh was successful by checking cart count
     */
    function verifyRefreshSuccess(expectedCount = null) {
        return new Promise((resolve) => {
            setTimeout(() => {
                try {
                    if (isStoreAvailable()) {
                        const cartData = window.wp.data.select('wc/store/cart').getCartData();
                        if (cartData) {
                            const actualCount = cartData.items_count || 0;
                            debugLog(`Cart verification: ${actualCount} items`);
                            
                            if (expectedCount !== null && actualCount !== expectedCount) {
                                debugLog(`Cart count mismatch: expected ${expectedCount}, got ${actualCount}`);
                                // Don't reject, just log - might be a timing issue
                            }
                        }
                    }
                    resolve(true);
                } catch (e) {
                    debugLog('Verification failed:', e);
                    resolve(false);
                }
            }, 500);
        });
    }

    /**
     * Wait for store to be available
     */
    function waitForStore(timeout = CONFIG.storeCheckTimeout) {
        return new Promise((resolve, reject) => {
            const startTime = Date.now();
            
            const checkStore = setInterval(() => {
                if (isStoreAvailable()) {
                    clearInterval(checkStore);
                    resolve(true);
                } else if (Date.now() - startTime > timeout) {
                    clearInterval(checkStore);
                    reject(new Error('Store not available within timeout'));
                }
            }, 100);
        });
    }

    /**
     * Enhanced event handler for added_to_cart
     */
    function handleAddedToCart(event, fragments, cart_hash, $button) {
        debugLog('added_to_cart event received', {
            fragments: !!fragments,
            cart_hash: cart_hash,
            button: $button ? $button.length : 0
        });

        // Verify that fragments were received (indicates successful add)
        if (!fragments || !cart_hash) {
            debugLog('Warning: added_to_cart event missing fragments or cart_hash');
            // Still try to refresh, but log the issue
        }

        // Extract expected cart count from fragments if available
        let expectedCount = null;
        if (fragments && typeof fragments === 'object') {
            // Try to extract count from fragments
            Object.keys(fragments).forEach(selector => {
                const fragment = fragments[selector];
                if (typeof fragment === 'string') {
                    const match = fragment.match(/(\d+)/);
                    if (match) {
                        expectedCount = parseInt(match[1], 10);
                    }
                }
            });
        }

        // Wait a bit for the store to process the update
        setTimeout(() => {
            refreshMiniCart()
                .then(() => {
                    if (expectedCount !== null) {
                        verifyRefreshSuccess(expectedCount);
                    }
                })
                .catch(error => {
                    errorLog('Failed to refresh after added_to_cart:', error);
                });
        }, 200);
    }

    /**
     * Initialize event listeners
     */
    function initEventListeners() {
        // Primary event: added_to_cart
        $(document.body).on('added_to_cart', handleAddedToCart);

        // Fallback events
        $(document.body).on('wc_fragments_refreshed', function() {
            debugLog('wc_fragments_refreshed event received');
            setTimeout(() => refreshMiniCart(), 100);
        });

        $(document.body).on('wc_cart_button_updated', function() {
            debugLog('wc_cart_button_updated event received');
            setTimeout(() => refreshMiniCart(), 100);
        });

        $(document.body).on('updated_wc_div', function() {
            debugLog('updated_wc_div event received');
            setTimeout(() => refreshMiniCart(), 100);
        });

        // Listen for cart updates from other sources
        $(document.body).on('wc_cart_emptied', function() {
            debugLog('wc_cart_emptied event received');
            setTimeout(() => refreshMiniCart(true), 100);
        });

        // Listen for quantity changes
        $(document.body).on('change', '.quantity input.qty', function() {
            debugLog('Quantity changed');
            setTimeout(() => refreshMiniCart(), 300);
        });
    }

    /**
     * Initialize on page load
     */
    function init() {
        debugLog('Initializing mini-cart fix...');

        // Wait for store to be available
        waitForStore()
            .then(() => {
                debugLog('Store is available, initializing...');
                
                // Initial refresh after a short delay to ensure everything is ready
                setTimeout(() => {
                    refreshMiniCart(true);
                }, 500);

                // Second check after a longer delay (for slow-loading scenarios)
                setTimeout(() => {
                    refreshMiniCart(true);
                }, 2000);
            })
            .catch(error => {
                debugLog('Store not available, will retry on events:', error);
                // Still initialize listeners - they might work when store becomes available
            });

        // Initialize event listeners
        initEventListeners();

        debugLog('Mini-cart fix initialized');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already ready
        init();
    }

    // Also initialize when jQuery is ready (for compatibility)
    if (typeof jQuery !== 'undefined') {
        $(document).ready(init);
    }

    // Expose refresh function globally for manual triggers if needed
    window.gstoreRefreshMiniCart = refreshMiniCart;

})(jQuery);

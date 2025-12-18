/**
 * Gstore Add to Cart Toast
 * 
 * Modal/Toast de notificação quando produto é adicionado ao carrinho.
 * Substitui o link "Ver carrinho" padrão do WooCommerce por uma
 * notificação visual mais elegante.
 * 
 * @package Gstore
 */
(function() {
    'use strict';

    // Configurações
    const CONFIG = {
        duration: 5000,           // Duração do toast (ms)
        animationDuration: 350,   // Duração da animação (ms)
    };

    /**
     * Obtém a URL do carrinho de várias fontes possíveis
     */
    function getCartUrl() {
        // Tenta wc_add_to_cart_params (mais comum)
        if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.cart_url) {
            return wc_add_to_cart_params.cart_url;
        }
        // Tenta wc_cart_params
        if (typeof wc_cart_params !== 'undefined' && wc_cart_params.cart_url) {
            return wc_cart_params.cart_url;
        }
        // Tenta gstore_wc localizado pelo tema
        if (typeof gstore_wc !== 'undefined' && gstore_wc.cart_url) {
            return gstore_wc.cart_url;
        }
        // Fallback: tenta encontrar o link do carrinho no DOM
        const cartLink = document.querySelector('a[href*="/carrinho"], a[href*="/cart"]');
        if (cartLink) {
            return cartLink.href;
        }
        // Último fallback
        return window.location.origin + '/carrinho/';
    }

    // Estado
    let toastElement = null;
    let dismissTimeout = null;
    let isHovered = false;

    /**
     * Cria o elemento do toast se não existir
     */
    function createToastElement() {
        if (toastElement) return toastElement;

        const toast = document.createElement('div');
        toast.className = 'Gstore-cart-toast';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');
        toast.style.setProperty('--toast-duration', `${CONFIG.duration}ms`);

        toast.innerHTML = `
            <div class="Gstore-cart-toast__header">
                <div class="Gstore-cart-toast__status">
                    <span class="Gstore-cart-toast__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </span>
                    <h3 class="Gstore-cart-toast__title">Adicionado ao carrinho</h3>
                </div>
                <button type="button" class="Gstore-cart-toast__close" aria-label="Fechar notificação">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="Gstore-cart-toast__content">
                <div class="Gstore-cart-toast__image">
                    <img src="" alt="" />
                </div>
                <div class="Gstore-cart-toast__details">
                    <h4 class="Gstore-cart-toast__product-name"></h4>
                    <div class="Gstore-cart-toast__product-meta">
                        <span class="Gstore-cart-toast__product-price"></span>
                        <span class="Gstore-cart-toast__product-qty"></span>
                    </div>
                </div>
            </div>
            <div class="Gstore-cart-toast__footer">
                <button type="button" class="Gstore-cart-toast__btn Gstore-cart-toast__btn--continue">
                    Continuar comprando
                </button>
                <a href="#" class="Gstore-cart-toast__btn Gstore-cart-toast__btn--cart">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    Ver carrinho
                </a>
            </div>
            <div class="Gstore-cart-toast__progress">
                <div class="Gstore-cart-toast__progress-bar"></div>
            </div>
        `;

        // Event listeners
        const closeBtn = toast.querySelector('.Gstore-cart-toast__close');
        closeBtn.addEventListener('click', hideToast);

        const continueBtn = toast.querySelector('.Gstore-cart-toast__btn--continue');
        continueBtn.addEventListener('click', hideToast);

        // Atualiza o href do botão de carrinho
        const cartBtn = toast.querySelector('.Gstore-cart-toast__btn--cart');
        if (cartBtn) {
            cartBtn.href = getCartUrl();
        }

        // Pausar auto-dismiss no hover
        toast.addEventListener('mouseenter', () => {
            isHovered = true;
            clearTimeout(dismissTimeout);
        });

        toast.addEventListener('mouseleave', () => {
            isHovered = false;
            scheduleDismiss();
        });

        document.body.appendChild(toast);
        toastElement = toast;

        return toast;
    }

    /**
     * Atualiza o conteúdo do toast com informações do produto
     */
    function updateToastContent(productInfo) {
        if (!toastElement) return;

        const imageEl = toastElement.querySelector('.Gstore-cart-toast__image img');
        const nameEl = toastElement.querySelector('.Gstore-cart-toast__product-name');
        const priceEl = toastElement.querySelector('.Gstore-cart-toast__product-price');
        const qtyEl = toastElement.querySelector('.Gstore-cart-toast__product-qty');

        if (imageEl && productInfo.image) {
            imageEl.src = productInfo.image;
            imageEl.alt = productInfo.name || '';
        }

        if (nameEl) {
            nameEl.textContent = productInfo.name || 'Produto';
        }

        if (priceEl && productInfo.price) {
            priceEl.textContent = productInfo.price;
        }

        if (qtyEl) {
            const qty = productInfo.quantity || 1;
            qtyEl.textContent = `${qty} ${qty > 1 ? 'unidades' : 'unidade'}`;
        }
    }

    /**
     * Extrai informações do produto do card
     */
    function extractProductInfo(addedButton) {
        const productCard = addedButton.closest('.Gstore-product-card, .product, li.product');
        if (!productCard) {
            return { name: 'Produto adicionado', quantity: 1 };
        }

        // Nome do produto
        let name = '';
        const titleEl = productCard.querySelector(
            '.Gstore-product-card__title a, ' +
            '.woocommerce-loop-product__title, ' +
            '.product-title a, ' +
            'h2.woocommerce-loop-product__title'
        );
        if (titleEl) {
            name = titleEl.textContent.trim();
        }

        // Imagem do produto
        let image = '';
        const imgEl = productCard.querySelector(
            '.Gstore-product-card__image img, ' +
            '.woocommerce-product-gallery__image img, ' +
            '.attachment-woocommerce_thumbnail, ' +
            'img.wp-post-image'
        );
        if (imgEl) {
            image = imgEl.src;
        }

        // Preço do produto
        let price = '';
        const priceEl = productCard.querySelector(
            '.Gstore-product-card__price .woocommerce-Price-amount, ' +
            '.price ins .woocommerce-Price-amount, ' +
            '.price > .woocommerce-Price-amount, ' +
            '.price .amount'
        );
        if (priceEl) {
            price = priceEl.textContent.trim();
        }

        // Quantidade (do input se existir)
        let quantity = 1;
        const qtyInput = productCard.querySelector('input.qty');
        if (qtyInput) {
            quantity = parseInt(qtyInput.value, 10) || 1;
        }

        return { name, image, price, quantity };
    }

    /**
     * Mostra o toast
     */
    function showToast(productInfo) {
        createToastElement();
        
        // Limpa timeout anterior
        clearTimeout(dismissTimeout);
        
        // Remove classes de estado anterior
        toastElement.classList.remove('is-visible', 'is-exiting', 'is-error');
        
        // Força reflow para reiniciar animação
        void toastElement.offsetWidth;
        
        // Atualiza URL do carrinho (pode ter mudado)
        const cartBtn = toastElement.querySelector('.Gstore-cart-toast__btn--cart');
        if (cartBtn) {
            cartBtn.href = getCartUrl();
        }
        
        // Atualiza conteúdo
        updateToastContent(productInfo);
        
        // Reinicia a barra de progresso
        const progressBar = toastElement.querySelector('.Gstore-cart-toast__progress-bar');
        if (progressBar) {
            progressBar.style.animation = 'none';
            void progressBar.offsetWidth;
            progressBar.style.animation = '';
        }
        
        // Mostra o toast
        requestAnimationFrame(() => {
            toastElement.classList.add('is-visible');
            scheduleDismiss();
        });
    }

    /**
     * Esconde o toast
     */
    function hideToast() {
        if (!toastElement) return;
        
        clearTimeout(dismissTimeout);
        
        toastElement.classList.add('is-exiting');
        
        setTimeout(() => {
            toastElement.classList.remove('is-visible', 'is-exiting');
        }, CONFIG.animationDuration);
    }

    /**
     * Agenda o fechamento automático
     */
    function scheduleDismiss() {
        if (isHovered) return;
        
        clearTimeout(dismissTimeout);
        dismissTimeout = setTimeout(hideToast, CONFIG.duration);
    }

    /**
     * Handler para o evento added_to_cart do WooCommerce
     */
    function handleAddedToCart(event, fragments, cart_hash, $button) {
        // $button é um objeto jQuery, precisamos do elemento DOM
        const button = $button ? ($button[0] || $button.get(0)) : null;
        
        if (!button) {
            // Tenta encontrar o botão que foi clicado recentemente
            const recentlyClicked = document.querySelector('.add_to_cart_button.added');
            if (recentlyClicked) {
                const productInfo = extractProductInfo(recentlyClicked);
                showToast(productInfo);
            } else {
                showToast({ name: 'Produto adicionado ao carrinho', quantity: 1 });
            }
            return;
        }

        const productInfo = extractProductInfo(button);
        showToast(productInfo);
    }

    /**
     * Handler para erro ao adicionar ao carrinho
     */
    function handleAddToCartError() {
        createToastElement();
        
        toastElement.classList.add('is-error');
        
        const titleEl = toastElement.querySelector('.Gstore-cart-toast__title');
        if (titleEl) {
            titleEl.textContent = 'Erro ao adicionar';
        }
        
        showToast({ 
            name: 'Não foi possível adicionar o produto. Tente novamente.', 
            quantity: 1 
        });
    }

    /**
     * Inicializa o módulo
     */
    function init() {
        // Verifica se jQuery está disponível (WooCommerce usa jQuery)
        if (typeof jQuery === 'undefined') {
            console.warn('Gstore Cart Toast: jQuery não encontrado');
            return;
        }

        // Escuta o evento added_to_cart do WooCommerce
        jQuery(document.body).on('added_to_cart', handleAddedToCart);
        
        // Escuta erros (opcional)
        jQuery(document.body).on('wc_cart_button_error', handleAddToCartError);

        // Pré-cria o elemento do toast
        createToastElement();
    }

    // Inicializa quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Exporta funções para uso externo se necessário
    window.GstoreCartToast = {
        show: showToast,
        hide: hideToast
    };

})();


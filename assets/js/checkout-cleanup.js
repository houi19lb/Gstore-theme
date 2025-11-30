/**
 * Checkout Cleanup Script
 * 
 * Removes redundant elements from the WooCommerce Blocks Checkout that cannot be removed
 * via the Block Editor or CSS alone.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Function to remove the redundant order summary
    function removeRedundantSummary() {
        // The parent container described by the user
        const sidebar = document.querySelector('.wc-block-components-sidebar');
        
        if (!sidebar) return;

        // We want to keep the one inside our custom card
        const customCard = sidebar.querySelector('.Gstore-order-summary-card');
        
        if (!customCard) return;

        // Find all direct children that might be the duplicate summary
        // The user said it's a div containing .wc-block-components-checkout-order-summary__title
        const potentialDuplicates = Array.from(sidebar.children).filter(child => {
            // Skip our custom card
            if (child.contains(customCard) || child === customCard) return false;
            
            // Check if it looks like an order summary
            return child.querySelector('.wc-block-components-checkout-order-summary__title') !== null;
        });

        potentialDuplicates.forEach(duplicate => {
            // console.log('Gstore: Removing redundant order summary element', duplicate);
            duplicate.remove();
        });
    }

    // Run immediately in case it's already there
    removeRedundantSummary();

    // Observe for changes since Blocks render asynchronously
    const observer = new MutationObserver((mutations) => {
        removeRedundantSummary();
    });

    const checkoutWrapper = document.querySelector('.wp-block-woocommerce-checkout');
    if (checkoutWrapper) {
        observer.observe(checkoutWrapper, { childList: true, subtree: true });
    }
});

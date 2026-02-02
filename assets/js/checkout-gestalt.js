document.addEventListener('DOMContentLoaded', function () {
    // Função para injetar títulos de seção
    function injectCheckoutTitles() {
        const wrapper = document.querySelector('.woocommerce-billing-fields__field-wrapper');
        if (!wrapper) return;

        // Verificar se já foram injetados para evitar duplicidade
        if (document.querySelector('.gstore-checkout-section-title')) return;

        // Definição dos títulos e ícones
        const sections = [
            {
                targetId: 'billing_first_name_field',
                title: 'Dados Pessoais',
                className: 'gstore-title-personal',
                icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'
            },
            {
                targetId: 'billing_phone_field',
                title: 'Informações de Contato',
                className: 'gstore-title-contact',
                icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>'
            },
            {
                targetId: 'billing_postcode_field',
                title: 'Endereço',
                className: 'gstore-title-address',
                icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>'
            }
        ];

        sections.forEach(section => {
            const targetField = document.getElementById(section.targetId);
            if (targetField) {
                const titleEl = document.createElement('h3');
                titleEl.className = `gstore-checkout-section-title ${section.className || ''}`;
                titleEl.innerHTML = `<span class="gstore-icon">${section.icon}</span> ${section.title}`;

                // Inserir antes do campo alvo
                targetField.parentNode.insertBefore(titleEl, targetField);
            }
        });
    }

    // Executar na carga e também quando o checkout for atualizado (AJAX)
    injectCheckoutTitles();

    jQuery(document.body).on('updated_checkout', function () {
        injectCheckoutTitles();
    });
});

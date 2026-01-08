/**
 * GStore Category Filter JavaScript
 */

class CategoryFilterTree {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        if (!this.container) return;

        this.tree = this.container.querySelector('.gstore-category-filter__tree');
        this.searchInput = this.container.querySelector('.gstore-category-filter__search');
        this.chipsContainer = this.container.querySelector('#gstore-category-filter-chips');
        this.applyBtn = this.container.querySelector('#gstore-filter-apply');
        this.clearBtn = this.container.querySelector('#gstore-filter-clear');

        this.init();
    }

    init() {
        // Event Delegation para cliques na árvore
        this.tree.addEventListener('click', (e) => {
            const expandBtn = e.target.closest('.gstore-category-filter__expand');
            if (expandBtn) {
                e.preventDefault();
                e.stopPropagation();
                this.handleExpand(expandBtn);
                return;
            }

            const checkbox = e.target.closest('.gstore-category-filter__checkbox');
            if (checkbox) {
                this.handleCheckbox(checkbox);
                return;
            }

            // Permitir clicar no nó inteiro para expandir se tiver filhos
            const node = e.target.closest('.gstore-category-filter__node');
            if (node && !e.target.closest('.gstore-category-filter__checkbox')) {
                const item = node.closest('.gstore-category-filter__item');
                const btn = item.querySelector('.gstore-category-filter__expand');
                if (btn) {
                    this.handleExpand(btn);
                }
            }
        });

        // Evento de busca
        this.searchInput.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });

        // Botões de ação
        this.applyBtn.addEventListener('click', () => this.applyFilters());
        this.clearBtn.addEventListener('click', () => this.clearFilters());

        // Chips remover
        this.chipsContainer.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.gstore-category-filter__chip-remove');
            if (removeBtn) {
                const slug = removeBtn.dataset.slug;
                this.uncheckBySlug(slug);
            }
        });

        // Inicializar estados indeterminate e chips
        this.updateParentStates();
        this.updateChips();
        this.expandSelectedPath();
    }

    handleExpand(btn) {
        const item = btn.closest('.gstore-category-filter__item');
        item.classList.toggle('is-open');
    }

    handleCheckbox(checkbox) {
        const item = checkbox.closest('.gstore-category-filter__item');
        
        // REMOVIDO: Propagação automática para filhos para dar mais controle ao usuário
        /*
        const childrenCheckboxes = item.querySelectorAll('.gstore-category-filter__children .gstore-category-filter__checkbox');
        childrenCheckboxes.forEach(cb => {
            cb.checked = checkbox.checked;
            cb.indeterminate = false;
        });
        */

        this.updateParentStates();
        this.updateChips();
    }

    updateParentStates() {
        const allItems = Array.from(this.tree.querySelectorAll('.gstore-category-filter__item')).reverse();
        
        allItems.forEach(item => {
            const childrenContainer = item.querySelector('.gstore-category-filter__children');
            if (!childrenContainer) return;

            const parentCheckbox = item.querySelector(':scope > .gstore-category-filter__node .gstore-category-filter__checkbox');
            const childCheckboxes = Array.from(childrenContainer.querySelectorAll(':scope > .gstore-category-filter__item > .gstore-category-filter__node .gstore-category-filter__checkbox'));
            
            if (childCheckboxes.length === 0) return;

            const checkedCount = childCheckboxes.filter(cb => cb.checked).length;
            const indeterminateCount = childCheckboxes.filter(cb => cb.indeterminate).length;

            // ALTERADO: O pai agora fica APENAS no estado indeterminate se houver filhos selecionados.
            // Ele NUNCA é marcado como checked automaticamente para evitar que o filtro do pai 
            // (que inclui todos os outros filhos) seja aplicado involuntariamente.
            if (checkedCount > 0 || indeterminateCount > 0) {
                // Se o usuário já tiver marcado o pai manualmente, mantemos. 
                if (checkedCount === childCheckboxes.length && parentCheckbox.checked) {
                    parentCheckbox.indeterminate = false;
                } else {
                    // Se não estiver tudo selecionado OU se o pai não foi clicado, 
                    // usamos indeterminate para feedback visual sem incluir no filtro.
                    if (!parentCheckbox.checked) {
                        parentCheckbox.indeterminate = true;
                    } else {
                        parentCheckbox.indeterminate = false;
                    }
                }
            } else {
                parentCheckbox.indeterminate = false;
            }
        });
    }

    handleSearch(query) {
        query = query.toLowerCase().trim();
        const allItems = this.tree.querySelectorAll('.gstore-category-filter__item');

        if (!query) {
            allItems.forEach(item => {
                item.classList.remove('is-hidden');
            });
            return;
        }

        allItems.forEach(item => {
            const name = item.querySelector('.gstore-category-filter__name').textContent.toLowerCase();
            const matches = name.includes(query);
            item.setAttribute('data-matches', matches ? 'true' : 'false');
            item.classList.add('is-hidden');
        });

        // Mostrar itens que batem e seus pais
        const matchingItems = this.tree.querySelectorAll('.gstore-category-filter__item[data-matches="true"]');
        matchingItems.forEach(item => {
            item.classList.remove('is-hidden');
            
            // Subir na árvore para mostrar pais
            let parent = item.parentElement.closest('.gstore-category-filter__item');
            while (parent) {
                parent.classList.remove('is-hidden');
                parent.classList.add('is-open'); // Auto-expand ao buscar
                parent = parent.parentElement.closest('.gstore-category-filter__item');
            }

            // Mostrar todos os descendentes do item que bate
            const descendants = item.querySelectorAll('.gstore-category-filter__item');
            descendants.forEach(d => d.classList.remove('is-hidden'));
        });
    }

    updateChips() {
        // Pegar todos os checkboxes que estão realmente marcados (não apenas indeterminados)
        const selectedCheckboxes = Array.from(this.tree.querySelectorAll('.gstore-category-filter__checkbox:checked'));
        
        this.chipsContainer.innerHTML = '';
        
        const maxChips = 10;
        const toDisplay = selectedCheckboxes.slice(0, maxChips);
        const remaining = selectedCheckboxes.length - maxChips;

        toDisplay.forEach(cb => {
            const chip = document.createElement('div');
            chip.className = 'gstore-category-filter__chip';
            chip.innerHTML = `
                <span>${cb.dataset.name}</span>
                <button type="button" class="gstore-category-filter__chip-remove" data-slug="${cb.value}" aria-label="Remover">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            `;
            this.chipsContainer.appendChild(chip);
        });

        if (remaining > 0) {
            const more = document.createElement('span');
            more.className = 'gstore-category-filter__more';
            more.style.fontSize = '12px';
            more.style.color = '#718096';
            more.textContent = `+${remaining}...`;
            this.chipsContainer.appendChild(more);
        }
    }

    uncheckBySlug(slug) {
        const checkbox = this.tree.querySelector(`.gstore-category-filter__checkbox[value="${slug}"]`);
        if (checkbox) {
            checkbox.checked = false;
            this.handleCheckbox(checkbox);
        }
    }

    expandSelectedPath() {
        const selected = this.tree.querySelectorAll('.gstore-category-filter__checkbox:checked');
        selected.forEach(cb => {
            let parent = cb.closest('.gstore-category-filter__item').parentElement.closest('.gstore-category-filter__item');
            while (parent) {
                parent.classList.add('is-open');
                parent = parent.parentElement.closest('.gstore-category-filter__item');
            }
        });
    }

    applyFilters() {
        const checked = Array.from(this.tree.querySelectorAll('.gstore-category-filter__checkbox:checked'))
            .map(cb => cb.value);
        
        const url = new URL(window.location.href);
        url.searchParams.delete('filter_cat[]'); // Limpar antigos
        
        const otherParams = [];
        url.searchParams.forEach((value, key) => {
            if (key !== 'filter_cat' && key !== 'filter_cat[]') {
                otherParams.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
            }
        });

        checked.forEach(slug => {
            otherParams.push(`filter_cat[]=${encodeURIComponent(slug)}`);
        });

        // Resetar página ao filtrar
        const finalParams = otherParams.filter(p => !p.startsWith('paged='));

        const newUrl = url.origin + url.pathname + (finalParams.length ? '?' + finalParams.join('&') : '');
        window.location.href = newUrl;
    }

    clearFilters() {
        const url = new URL(window.location.href);
        const otherParams = [];
        
        url.searchParams.forEach((value, key) => {
            if (key !== 'filter_cat' && key !== 'filter_cat[]' && key !== 'paged') {
                otherParams.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
            }
        });

        const newUrl = url.origin + url.pathname + (otherParams.length ? '?' + otherParams.join('&') : '');
        window.location.href = newUrl;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new CategoryFilterTree('gstore-category-filter');
});

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
        this.clearBtn = this.container.querySelector('#gstore-filter-clear');
        this.fullCatalogUrl = this.container.dataset.fullCatalogUrl || '';

        this.init();
    }

    isFilterParamKey(key) {
        return /^filter_cat(?:\[\d*\]|\[\])?$/.test(String(key || '').trim());
    }

    isPaginationParamKey(key) {
        return ['paged', 'product-page', 'page'].includes(String(key || '').trim());
    }

    normalizePathname(pathname) {
        const normalized = String(pathname || '').replace(/\/page\/\d+\/?$/i, '/');
        return normalized || '/';
    }

    buildCatalogUrl(selectedSlugs, baseUrl) {
        const url = new URL(window.location.href);
        const params = new URLSearchParams();

        url.searchParams.forEach((value, key) => {
            if (this.isFilterParamKey(key) || this.isPaginationParamKey(key)) {
                return;
            }

            params.append(key, value);
        });

        selectedSlugs.forEach((slug) => {
            params.append('filter_cat[]', slug);
        });

        const targetBase = baseUrl || (url.origin + this.normalizePathname(url.pathname));
        const query = params.toString();

        return targetBase + (query ? (targetBase.includes('?') ? '&' : '?') + query : '');
    }

    init() {
        this.tree.addEventListener('click', (e) => {
            const expandBtn = e.target.closest('.gstore-category-filter__expand');
            if (expandBtn) {
                e.preventDefault();
                e.stopPropagation();
                this.handleExpand(expandBtn);
                return;
            }

            const node = e.target.closest('.gstore-category-filter__node');
            if (node && !e.target.closest('.gstore-category-filter__checkbox')) {
                const item = node.closest('.gstore-category-filter__item');
                const btn = item.querySelector('.gstore-category-filter__expand');
                if (btn) {
                    this.handleExpand(btn);
                }
            }
        });

        this.tree.addEventListener('change', (e) => {
            const checkbox = e.target.closest('.gstore-category-filter__checkbox');
            if (!checkbox) return;
            this.handleCheckbox(checkbox);
            this.applyFilters();
        });

        this.searchInput.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });

        this.clearBtn.addEventListener('click', () => this.clearFilters());

        this.chipsContainer.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.gstore-category-filter__chip-remove');
            if (removeBtn) {
                e.preventDefault();
                e.stopPropagation();
                const slug = removeBtn.dataset.slug;
                const hasChanged = this.uncheckBySlug(slug);
                if (hasChanged) {
                    this.applyFilters();
                }
            }
        });

        this.updateParentStates();
        this.updateChips();
        this.expandSelectedPath();
    }

    handleExpand(btn) {
        const item = btn.closest('.gstore-category-filter__item');
        item.classList.toggle('is-open');
    }

    handleCheckbox(checkbox) {
        this.updateParentStates();
        this.updateChips();
    }

    updateParentStates() {
        const allItems = Array.from(this.tree.querySelectorAll('.gstore-category-filter__item')).reverse();

        allItems.forEach((item) => {
            const childrenContainer = item.querySelector('.gstore-category-filter__children');
            if (!childrenContainer) return;

            const parentCheckbox = item.querySelector(':scope > .gstore-category-filter__node .gstore-category-filter__checkbox');
            const childCheckboxes = Array.from(
                childrenContainer.querySelectorAll(
                    ':scope > .gstore-category-filter__item > .gstore-category-filter__node .gstore-category-filter__checkbox'
                )
            );

            if (childCheckboxes.length === 0) return;

            const checkedCount = childCheckboxes.filter((cb) => cb.checked).length;
            const indeterminateCount = childCheckboxes.filter((cb) => cb.indeterminate).length;

            if (checkedCount > 0 || indeterminateCount > 0) {
                if (checkedCount === childCheckboxes.length && parentCheckbox.checked) {
                    parentCheckbox.indeterminate = false;
                } else if (!parentCheckbox.checked) {
                    parentCheckbox.indeterminate = true;
                } else {
                    parentCheckbox.indeterminate = false;
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
            allItems.forEach((item) => {
                item.classList.remove('is-hidden');
            });
            return;
        }

        allItems.forEach((item) => {
            const name = item.querySelector('.gstore-category-filter__name').textContent.toLowerCase();
            const matches = name.includes(query);
            item.setAttribute('data-matches', matches ? 'true' : 'false');
            item.classList.add('is-hidden');
        });

        const matchingItems = this.tree.querySelectorAll('.gstore-category-filter__item[data-matches="true"]');
        matchingItems.forEach((item) => {
            item.classList.remove('is-hidden');

            let parent = item.parentElement.closest('.gstore-category-filter__item');
            while (parent) {
                parent.classList.remove('is-hidden');
                parent.classList.add('is-open');
                parent = parent.parentElement.closest('.gstore-category-filter__item');
            }

            const descendants = item.querySelectorAll('.gstore-category-filter__item');
            descendants.forEach((descendant) => descendant.classList.remove('is-hidden'));
        });
    }

    updateChips() {
        const selectedCheckboxes = Array.from(this.tree.querySelectorAll('.gstore-category-filter__checkbox:checked'));

        this.chipsContainer.innerHTML = '';

        const maxChips = 10;
        const toDisplay = selectedCheckboxes.slice(0, maxChips);
        const remaining = selectedCheckboxes.length - maxChips;

        toDisplay.forEach((cb) => {
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
            if (!checkbox.checked) {
                return false;
            }
            checkbox.checked = false;
            this.handleCheckbox(checkbox);
            return true;
        }
        return false;
    }

    expandSelectedPath() {
        const selected = this.tree.querySelectorAll('.gstore-category-filter__checkbox:checked');
        selected.forEach((cb) => {
            let parent = cb.closest('.gstore-category-filter__item').parentElement.closest('.gstore-category-filter__item');
            while (parent) {
                parent.classList.add('is-open');
                parent = parent.parentElement.closest('.gstore-category-filter__item');
            }
        });
    }

    applyFilters() {
        const checked = Array.from(this.tree.querySelectorAll('.gstore-category-filter__checkbox:checked'))
            .map((cb) => cb.value);

        window.location.href = this.buildCatalogUrl(checked);
    }

    clearFilters() {
        window.location.href = this.buildCatalogUrl([]);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new CategoryFilterTree('gstore-category-filter');
});

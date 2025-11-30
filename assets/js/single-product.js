document.addEventListener('DOMContentLoaded', () => {
	const reviewTriggers = document.querySelectorAll('[data-gstore-tab-target="reviews"]');

	const focusReviewTab = () => {
		const reviewsTabLink =
			document.querySelector('.woocommerce-tabs .reviews_tab a') ||
			document.querySelector('#tab-title-reviews a');

		if (reviewsTabLink) {
			reviewsTabLink.click();
		}

		const reviewsPanel = document.querySelector('#tab-reviews');
		if (reviewsPanel) {
			const preferredOffset = Number(document.body.dataset.gstoreStickyOffset || 120);
			window.scrollTo({
				top: reviewsPanel.getBoundingClientRect().top + window.scrollY - preferredOffset,
				behavior: 'smooth',
			});
		}
	};

	reviewTriggers.forEach((trigger) => {
		trigger.addEventListener('click', focusReviewTab);
	});

	const enhanceQuantityField = (field) => {
		if (field.dataset.gstoreQtyEnhanced) {
			return;
		}

		const input = field.querySelector('input.qty');
		if (!input) {
			return;
		}

		field.dataset.gstoreQtyEnhanced = 'true';

		const wrapper = document.createElement('div');
		wrapper.className = 'Gstore-quantity-controls';

		const minus = document.createElement('button');
		minus.type = 'button';
		minus.className = 'Gstore-quantity-button';
		minus.setAttribute('aria-label', input.dataset.gstoreMinusLabel || 'Diminuir quantidade');
		minus.textContent = '-';

		const plus = document.createElement('button');
		plus.type = 'button';
		plus.className = 'Gstore-quantity-button';
		plus.setAttribute('aria-label', input.dataset.gstorePlusLabel || 'Aumentar quantidade');
		plus.textContent = '+';

		input.parentNode.insertBefore(wrapper, input);
		wrapper.appendChild(minus);
		wrapper.appendChild(input);
		wrapper.appendChild(plus);

		const getStep = () => parseFloat(input.step) || 1;

		minus.addEventListener('click', () => {
			const min = parseFloat(input.min) || 1;
			const currentValue = parseFloat(input.value) || min;
			const nextValue = Math.max(currentValue - getStep(), min);
			input.value = nextValue;
			input.dispatchEvent(new Event('change', { bubbles: true }));
		});

		plus.addEventListener('click', () => {
			const max = parseFloat(input.max) || Number.MAX_SAFE_INTEGER;
			const currentValue = parseFloat(input.value) || 0;
			const nextValue = Math.min(currentValue + getStep(), max);
			input.value = nextValue;
			input.dispatchEvent(new Event('change', { bubbles: true }));
		});
	};

	const quantityFields = document.querySelectorAll('.Gstore-single-product__add-to-cart .quantity');
	quantityFields.forEach(enhanceQuantityField);
});




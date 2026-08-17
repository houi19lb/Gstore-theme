/**
 * @jest-environment jsdom
 */
const fs = require('fs');
const path = require('path');

const script = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'flash-sale.js'), 'utf8');
const template = fs.readFileSync(path.join(__dirname, '..', 'woocommerce', 'content-single-product.php'), 'utf8');

describe('GStore single-product flash sale timer', () => {
	afterEach(() => {
		jest.clearAllTimers();
		jest.useRealTimers();
		document.body.innerHTML = '';
	});

	test('renders the campaign banner only from the product template integration', () => {
		expect(template).toContain("gstore_theme_get_product_flash_sale_campaign( $product_id )");
		expect(template).toContain('data-gstore-flash-sale-clock');
		expect(template).toContain('data-gstore-flash-sale-end');
	});

	test('updates all four responsive countdown units', () => {
		jest.useFakeTimers();
		jest.setSystemTime(new Date('2026-08-16T12:00:00Z'));
		document.body.innerHTML = `
			<section data-gstore-flash-sale-clock>
				<time data-gstore-flash-sale-end="2026-08-18T15:04:05Z">--:--:--</time>
				<strong data-gstore-flash-sale-mobile-countdown="days">00</strong>
				<strong data-gstore-flash-sale-mobile-countdown="hours">00</strong>
				<strong data-gstore-flash-sale-mobile-countdown="minutes">00</strong>
				<strong data-gstore-flash-sale-mobile-countdown="seconds">00</strong>
			</section>
		`;

		window.eval(script);

		expect(document.querySelector('[data-gstore-flash-sale-mobile-countdown="days"]').textContent).toBe('02');
		expect(document.querySelector('[data-gstore-flash-sale-mobile-countdown="hours"]').textContent).toBe('03');
		expect(document.querySelector('[data-gstore-flash-sale-mobile-countdown="minutes"]').textContent).toBe('04');
		expect(document.querySelector('[data-gstore-flash-sale-mobile-countdown="seconds"]').textContent).toBe('05');
	});
});

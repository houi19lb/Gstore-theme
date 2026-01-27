(function () {
	function safeText(s) {
		return String(s || "").trim();
	}

	function createNoticeEl(text) {
		var wrap = document.createElement("div");
		wrap.className = "gstore-product-notice";
		wrap.setAttribute("role", "note");
		wrap.setAttribute("aria-live", "polite");

		wrap.innerHTML =
			'<div class="gstore-product-notice__row">' +
			'  <div class="gstore-product-notice__icon">i</div>' +
			"  <div>" + escapeHtml(text) + "</div>" +
			"</div>";

		return wrap;
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/\"/g, "&quot;")
			.replace(/\'/g, "&#039;");
	}

	function findButtonsContainer() {
		var wcForm = document.querySelector("form.cart");
		if (wcForm) return wcForm;

		var btn =
			document.querySelector(".single_add_to_cart_button") ||
			document.querySelector("[data-buy-now]") ||
			document.querySelector(".buy-now") ||
			findButtonByText(/comprar agora/i);

		if (btn) return btn.closest("div, form, section") || btn.parentElement;

		return null;
	}

	function findAvailabilityBox() {
		return (
			document.querySelector(".gstore-availability") ||
			document.querySelector("[data-availability]") ||
			findElementByText(/dispon[ií]vel/i, ["div", "section", "article"])
		);
	}

	function findButtonByText(regex) {
		var buttons = Array.prototype.slice.call(document.querySelectorAll("button, a"));
		for (var i = 0; i < buttons.length; i++) {
			var t = safeText(buttons[i].textContent);
			if (t && regex.test(t)) return buttons[i];
		}
		return null;
	}

	function findElementByText(regex, tags) {
		var selector = (tags || ["div"]).join(",");
		var els = Array.prototype.slice.call(document.querySelectorAll(selector));
		for (var i = 0; i < els.length; i++) {
			var t = safeText(els[i].textContent);
			if (t && regex.test(t)) return els[i];
		}
		return null;
	}

	function alreadyInserted() {
		return !!document.querySelector(".gstore-product-notice");
	}

	function insertV1(text) {
		if (alreadyInserted()) return;

		var notice = createNoticeEl(text);

		var availability = findAvailabilityBox();
		var buttonsContainer = findButtonsContainer();

		if (availability && availability.parentNode) {
			availability.parentNode.insertBefore(notice, availability.nextSibling);
			return;
		}

		if (buttonsContainer && buttonsContainer.parentNode) {
			buttonsContainer.parentNode.insertBefore(notice, buttonsContainer);
			return;
		}

		var summary = document.querySelector(".summary");
		if (summary) summary.insertBefore(notice, summary.firstChild);
	}

	function run() {
		var data = window.gstoreProductNotice || {};
		if (!data.active) return;

		var text = safeText(data.text);
		if (!text) return;

		insertV1(text);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", run);
	} else {
		run();
	}
})();

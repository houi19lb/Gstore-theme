/**
 * GSTORE - Página Informativo (Pós-venda)
 * FAQ (details) um-aberto-por-vez; modal de aeroportos com busca.
 */
(function () {
	"use strict";

	// --- FAQ Armas: um details aberto por vez ---
	var faqContainer = document.querySelector(".Gstore-informativo-faq__items");
	if (faqContainer) {
		var details = faqContainer.querySelectorAll("details");
		details.forEach(function (d) {
			d.addEventListener("toggle", function () {
				if (!d.open) return;
				details.forEach(function (other) {
					if (other !== d) other.open = false;
				});
			});
		});
	}

	// --- Modal Aeroportos ---
	var modal = document.getElementById("Gstore-informativo-airports-modal");
	var openBtn = document.getElementById("Gstore-informativo-open-airports");
	if (!modal || !openBtn) return;

	var closeEls = modal.querySelectorAll("[data-close-modal]");
	var queryEl = document.getElementById("Gstore-informativo-airport-query");
	var tableBody = modal.querySelector("#Gstore-informativo-airports-table tbody");
	var resultsBadge = document.getElementById("Gstore-informativo-results-badge");
	var noResults = document.getElementById("Gstore-informativo-no-results");

	var airports = (typeof gstoreInformativoData !== "undefined" && Array.isArray(gstoreInformativoData.airports))
		? gstoreInformativoData.airports
		: [];

	function normalize(s) {
		return String(s || "").toLowerCase().trim();
	}

	function filterAirports() {
		var q = normalize(queryEl ? queryEl.value : "");
		return airports
			.filter(function (a) {
				if (!q) return true;
				return (
					normalize(a.city).indexOf(q) !== -1 ||
					normalize(a.name).indexOf(q) !== -1 ||
					normalize(a.iata).indexOf(q) !== -1
				);
			})
			.sort(function (a, b) {
				return (a.city + a.name).localeCompare(b.city + b.name);
			});
	}

	function renderTable() {
		var rows = filterAirports();
		if (resultsBadge) resultsBadge.textContent = rows.length + " resultados";
		if (!tableBody) return;
		tableBody.innerHTML = "";
		if (rows.length === 0) {
			if (noResults) noResults.style.display = "block";
			return;
		}
		if (noResults) noResults.style.display = "none";
		rows.forEach(function (a) {
			var tr = document.createElement("tr");
			var tdCity = document.createElement("td");
			tdCity.textContent = a.city;
			var tdName = document.createElement("td");
			tdName.textContent = a.name;
			var tdIata = document.createElement("td");
			tdIata.textContent = a.iata;
			tr.appendChild(tdCity);
			tr.appendChild(tdName);
			tr.appendChild(tdIata);
			tableBody.appendChild(tr);
		});
	}

	function openModal() {
		modal.classList.add("is-open");
		modal.setAttribute("aria-hidden", "false");
		renderTable();
		setTimeout(function () {
			if (queryEl) queryEl.focus();
		}, 0);
	}

	function closeModal() {
		modal.classList.remove("is-open");
		modal.setAttribute("aria-hidden", "true");
	}

	openBtn.addEventListener("click", openModal);
	closeEls.forEach(function (el) {
		el.addEventListener("click", closeModal);
	});
	window.addEventListener("keydown", function (e) {
		if (e.key === "Escape" && modal.classList.contains("is-open")) {
			closeModal();
		}
	});
	if (queryEl) queryEl.addEventListener("input", renderTable);
})();

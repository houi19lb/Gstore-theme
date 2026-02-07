/**
 * GSTORE - Página Informativo (Pós-venda)
 * Accordion e modal de aeroportos.
 */
(function () {
	"use strict";

	// --- Accordion ---
	document.querySelectorAll("[data-accordion]").forEach(function (acc) {
		var items = acc.querySelectorAll(".Gstore-informativo-acc-item");
		items.forEach(function (item) {
			var btn = item.querySelector("[data-acc-toggle]");
			var icon = item.querySelector(".Gstore-informativo-acc-icon");
			var panel = item.querySelector(".Gstore-informativo-acc-panel");
			var isOpen = item.classList.contains("is-open");

			if (!btn || !icon || !panel) return;

			if (!isOpen) {
				panel.hidden = true;
				icon.textContent = "+";
			} else {
				panel.hidden = false;
				icon.textContent = "−";
			}

			btn.addEventListener("click", function () {
				var openNow = item.classList.contains("is-open");
				items.forEach(function (it) {
					var p = it.querySelector(".Gstore-informativo-acc-panel");
					var i = it.querySelector(".Gstore-informativo-acc-icon");
					it.classList.remove("is-open");
					if (p) p.hidden = true;
					if (i) i.textContent = "+";
				});
				if (!openNow) {
					item.classList.add("is-open");
					panel.hidden = false;
					icon.textContent = "−";
				}
			});
		});
	});

	// --- Modal Aeroportos ---
	var modal = document.getElementById("Gstore-informativo-airports-modal");
	var openBtn = document.getElementById("Gstore-informativo-open-airports");
	if (!modal || !openBtn) return;

	var closeEls = modal.querySelectorAll("[data-close-modal]");
	var queryEl = document.getElementById("Gstore-informativo-airport-query");
	var ufEl = document.getElementById("Gstore-informativo-uf-select");
	var tableBody = modal.querySelector("#Gstore-informativo-airports-table tbody");
	var resultsBadge = document.getElementById("Gstore-informativo-results-badge");
	var noResults = document.getElementById("Gstore-informativo-no-results");

	var airports = (typeof gstoreInformativoData !== "undefined" && Array.isArray(gstoreInformativoData.airports))
		? gstoreInformativoData.airports
		: [];

	function normalize(s) {
		return String(s || "").toLowerCase().trim();
	}

	function getUniqueUFs() {
		var ufs = {};
		airports.forEach(function (a) {
			ufs[a.uf] = true;
		});
		return Object.keys(ufs).sort();
	}

	function filterAirports() {
		var q = normalize(queryEl ? queryEl.value : "");
		var uf = ufEl ? ufEl.value : "ALL";
		return airports
			.filter(function (a) {
				return uf === "ALL" ? true : a.uf === uf;
			})
			.filter(function (a) {
				if (!q) return true;
				return (
					normalize(a.uf).indexOf(q) !== -1 ||
					normalize(a.city).indexOf(q) !== -1 ||
					normalize(a.name).indexOf(q) !== -1 ||
					normalize(a.iata).indexOf(q) !== -1
				);
			})
			.sort(function (a, b) {
				return (a.uf + a.city).localeCompare(b.uf + b.city);
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

	function fillUfSelect() {
		if (!ufEl) return;
		var ufs = getUniqueUFs();
		ufEl.innerHTML = '<option value="ALL">Todos</option>';
		ufs.forEach(function (uf) {
			var opt = document.createElement("option");
			opt.value = uf;
			opt.textContent = uf;
			ufEl.appendChild(opt);
		});
	}

	function openModal() {
		modal.classList.add("is-open");
		modal.setAttribute("aria-hidden", "false");
		fillUfSelect();
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
	if (ufEl) ufEl.addEventListener("change", renderTable);
})();

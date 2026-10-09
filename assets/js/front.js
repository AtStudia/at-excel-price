(function () {
	"use strict";

	function textOf(row) {
		return (row.textContent || "").replace(/\s+/g, " ").trim().toLowerCase();
	}

	function cellText(row, index) {
		var cell = row.cells[index];
		return cell ? (cell.textContent || "").trim() : "";
	}

	function compareValues(a, b) {
		var na = a.replace(/\s/g, "").replace(",", ".");
		var nb = b.replace(/\s/g, "").replace(",", ".");
		var fa = parseFloat(na);
		var fb = parseFloat(nb);
		var aNum = na !== "" && !isNaN(fa) && /^-?\d+([.,]\d+)?$/.test(a.replace(/\s/g, ""));
		var bNum = nb !== "" && !isNaN(fb) && /^-?\d+([.,]\d+)?$/.test(b.replace(/\s/g, ""));
		if (aNum && bNum) {
			return fa - fb;
		}
		return a.localeCompare(b, undefined, { numeric: true, sensitivity: "base" });
	}

	function initSheet(sheet, perPage) {
		var tbody = sheet.querySelector("tbody");
		if (!tbody) {
			return;
		}

		var allRows = Array.prototype.slice.call(tbody.querySelectorAll("tr"));
		var filtered = allRows.slice();
		var page = 1;
		var search = sheet.querySelector("[data-atep-search]");
		var count = sheet.querySelector("[data-atep-count]");
		var pager = sheet.querySelector("[data-atep-pager]");
		var label = sheet.querySelector("[data-atep-page-label]");
		var prev = sheet.querySelector('[data-atep-page="prev"]');
		var next = sheet.querySelector('[data-atep-page="next"]');
		var sortCol = -1;
		var sortDir = 1;

		function render() {
			var total = filtered.length;
			var pages = Math.max(1, Math.ceil(total / perPage) || 1);
			if (page > pages) {
				page = pages;
			}
			if (page < 1) {
				page = 1;
			}
			var start = (page - 1) * perPage;
			var end = start + perPage;
			var onPage = filtered.slice(start, end);

			allRows.forEach(function (row) {
				row.classList.add("atep-page-hide");
			});
			onPage.forEach(function (row) {
				row.classList.remove("atep-page-hide");
			});

			if (count) {
				count.textContent = total
					? start + 1 + "–" + Math.min(end, total) + " / " + total
					: "0";
			}

			if (pager) {
				var showPager = total > perPage;
				pager.hidden = !showPager;
				if (label) {
					label.textContent = page + " / " + pages;
				}
				if (prev) {
					prev.disabled = page <= 1;
				}
				if (next) {
					next.disabled = page >= pages;
				}
			}
		}

		function applyFilter() {
			var q = search ? search.value.trim().toLowerCase() : "";
			filtered = allRows.filter(function (row) {
				return !q || textOf(row).indexOf(q) !== -1;
			});
			page = 1;
			render();
		}

		function applySort() {
			if (sortCol < 0) {
				return;
			}
			filtered.sort(function (a, b) {
				return sortDir * compareValues(cellText(a, sortCol), cellText(b, sortCol));
			});
			filtered.forEach(function (row) {
				tbody.appendChild(row);
			});
			render();
		}

		if (search) {
			search.addEventListener("input", applyFilter);
		}

		if (prev) {
			prev.addEventListener("click", function () {
				if (page > 1) {
					page -= 1;
					render();
				}
			});
		}

		if (next) {
			next.addEventListener("click", function () {
				page += 1;
				render();
			});
		}

		var headers = sheet.querySelectorAll("th[data-atep-sort]");
		Array.prototype.forEach.call(headers, function (th) {
			th.addEventListener("click", function () {
				var col = parseInt(th.getAttribute("data-atep-sort"), 10);
				if (sortCol === col) {
					sortDir = -sortDir;
				} else {
					sortCol = col;
					sortDir = 1;
				}
				Array.prototype.forEach.call(headers, function (other) {
					other.classList.remove("is-sorted", "is-desc");
				});
				th.classList.add("is-sorted");
				if (sortDir < 0) {
					th.classList.add("is-desc");
				}
				applySort();
			});
		});

		render();
	}

	function initRoot(root) {
		var perPage = parseInt(root.getAttribute("data-per-page"), 10) || 50;
		if (perPage < 1) {
			perPage = 50;
		}
		var tabs = root.querySelectorAll("[data-atep-tab]");
		var tabSelect = root.querySelector("[data-atep-tabs-select]");
		var sheets = root.querySelectorAll("[data-atep-sheet]");

		function showSheet(id) {
			var key = String(id);
			Array.prototype.forEach.call(tabs, function (other) {
				var on = other.getAttribute("data-atep-tab") === key;
				other.classList.toggle("is-active", on);
				other.setAttribute("aria-selected", on ? "true" : "false");
			});
			Array.prototype.forEach.call(sheets, function (sheet) {
				var on = sheet.getAttribute("data-atep-sheet") === key;
				sheet.classList.toggle("is-active", on);
				sheet.hidden = !on;
			});
			if (tabSelect && String(tabSelect.value) !== key) {
				tabSelect.value = key;
			}
		}

		Array.prototype.forEach.call(sheets, function (sheet) {
			initSheet(sheet, perPage);
		});

		// Mark ready only after rows were paged — otherwise a mid-init error can hide everything.
		root.classList.add("atep-ready");

		Array.prototype.forEach.call(tabs, function (tab) {
			tab.addEventListener("click", function () {
				showSheet(tab.getAttribute("data-atep-tab"));
			});
		});

		if (tabSelect) {
			tabSelect.addEventListener("change", function () {
				showSheet(tabSelect.value);
			});
		}
	}

	function boot() {
		var roots = document.querySelectorAll(".atep");
		Array.prototype.forEach.call(roots, initRoot);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}
})();

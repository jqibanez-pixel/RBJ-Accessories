"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
function ready(fn) {
    if (document.readyState !== "loading") {
        fn();
        return;
    }
    document.addEventListener("DOMContentLoaded", fn);
}
function q(selector, root = document) {
    return root.querySelector(selector);
}
function qa(selector, root = document) {
    return Array.from(root.querySelectorAll(selector));
}
function text(element) {
    return (element?.textContent ?? "").trim();
}
ready(() => {
    const content = q(".content");
    const sidebar = q(".sidebar");
    if (!content || !sidebar) {
        return;
    }
    injectTopbar(content, sidebar);
    enhanceSidebar(sidebar);
    enhanceTables(content);
    buildCommandPalette(sidebar);
    buildDetailDrawer();
    patchEmptyStates(content);
});
function injectTopbar(content, sidebar) {
    if (q(".admin-topbar")) {
        return;
    }
    const topbar = document.createElement("div");
    topbar.className = "admin-topbar";
    const left = document.createElement("div");
    left.className = "admin-topbar-left";
    const toggleBtn = document.createElement("button");
    toggleBtn.className = "admin-nav-toggle";
    toggleBtn.type = "button";
    toggleBtn.textContent = "Menu";
    toggleBtn.addEventListener("click", () => {
        if (window.matchMedia("(max-width: 980px)").matches) {
            document.body.classList.toggle("sidebar-open");
            return;
        }
        document.body.classList.toggle("sidebar-collapsed");
    });
    left.appendChild(toggleBtn);
    const search = document.createElement("input");
    search.className = "admin-search";
    search.type = "search";
    search.placeholder = "Search table rows... (Ctrl+K for quick jump)";
    search.addEventListener("input", () => {
        const table = q(".content table");
        if (!table) {
            return;
        }
        filterTableRows(table, search.value);
    });
    left.appendChild(search);
    const kbd = document.createElement("span");
    kbd.className = "admin-kbd";
    kbd.textContent = "Ctrl+K";
    left.appendChild(kbd);
    const right = document.createElement("div");
    right.className = "admin-topbar-right";
    qa("nav a", sidebar)
        .slice(0, 4)
        .forEach((link) => {
        const quick = document.createElement("a");
        quick.href = link.getAttribute("href") ?? "#";
        quick.className = "admin-quick-btn";
        quick.textContent = text(link);
        right.appendChild(quick);
    });
    topbar.appendChild(left);
    topbar.appendChild(right);
    content.insertBefore(topbar, content.firstChild);
}
function enhanceSidebar(sidebar) {
    qa("nav a", sidebar).forEach((link) => {
        const label = text(link);
        link.setAttribute("data-short", label.slice(0, 3).toUpperCase());
    });
    document.addEventListener("click", (event) => {
        if (!window.matchMedia("(max-width: 980px)").matches) {
            return;
        }
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (!sidebar.contains(target) && !target.closest(".admin-nav-toggle")) {
            document.body.classList.remove("sidebar-open");
        }
    });
}
function enhanceTables(content) {
    qa("table", content).forEach((table, index) => {
        const parent = table.parentElement;
        if (!parent) {
            return;
        }
        if (!parent.classList.contains("table-shell")) {
            const wrapper = document.createElement("div");
            wrapper.className = "table-shell";
            parent.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
        buildTableTools(table, index);
        buildStatusChips(table);
        makeRowsInspectable(table);
    });
}
function buildTableTools(table, _index) {
    const tbody = q("tbody", table);
    if (!tbody) {
        return;
    }
    const rows = qa("tr", tbody).filter((row) => !q("td[colspan]", row));
    if (!rows.length) {
        return;
    }
    const tools = document.createElement("div");
    tools.className = "table-tools";
    const left = document.createElement("div");
    const rowSearch = document.createElement("input");
    rowSearch.type = "search";
    rowSearch.placeholder = "Filter this table";
    left.appendChild(rowSearch);
    const right = document.createElement("div");
    right.className = "table-pagination";
    const size = document.createElement("select");
    [10, 20, 50].forEach((pageSize) => {
        const option = document.createElement("option");
        option.value = String(pageSize);
        option.textContent = `${pageSize}/page`;
        size.appendChild(option);
    });
    const prev = document.createElement("button");
    prev.type = "button";
    prev.textContent = "Prev";
    const info = document.createElement("span");
    info.textContent = "1/1";
    const next = document.createElement("button");
    next.type = "button";
    next.textContent = "Next";
    right.appendChild(size);
    right.appendChild(prev);
    right.appendChild(info);
    right.appendChild(next);
    tools.appendChild(left);
    tools.appendChild(right);
    const shell = table.closest(".table-shell");
    if (!shell?.parentElement) {
        return;
    }
    shell.insertAdjacentElement("beforebegin", tools);
    const state = {
        page: 1,
        query: "",
        size: Number.parseInt(size.value, 10)
    };
    const render = () => {
        const filtered = rows.filter((row) => text(row).toLowerCase().includes(state.query.toLowerCase()));
        const totalPages = Math.max(1, Math.ceil(filtered.length / state.size));
        if (state.page > totalPages) {
            state.page = totalPages;
        }
        const start = (state.page - 1) * state.size;
        const end = start + state.size;
        rows.forEach((row) => {
            row.style.display = "none";
        });
        filtered.slice(start, end).forEach((row) => {
            row.style.display = "";
        });
        info.textContent = `${state.page}/${totalPages}`;
        prev.disabled = state.page <= 1;
        next.disabled = state.page >= totalPages;
    };
    rowSearch.addEventListener("input", () => {
        state.query = rowSearch.value;
        state.page = 1;
        render();
    });
    size.addEventListener("change", () => {
        state.size = Number.parseInt(size.value, 10);
        state.page = 1;
        render();
    });
    prev.addEventListener("click", () => {
        state.page -= 1;
        render();
    });
    next.addEventListener("click", () => {
        state.page += 1;
        render();
    });
    render();
}
function buildStatusChips(table) {
    const statusElements = qa(".status, .status-badge, .message-status", table);
    if (!statusElements.length) {
        return;
    }
    const statusCounts = {};
    statusElements.forEach((element) => {
        const key = text(element).toLowerCase().replace(/\s+/g, "_");
        statusCounts[key] = (statusCounts[key] ?? 0) + 1;
    });
    const keys = Object.keys(statusCounts);
    if (!keys.length) {
        return;
    }
    const group = document.createElement("div");
    group.className = "status-chip-group";
    const allChip = createStatusChip("all", `All (${statusElements.length})`);
    allChip.classList.add("active");
    group.appendChild(allChip);
    keys.forEach((key) => {
        group.appendChild(createStatusChip(key, `${pretty(key)} (${statusCounts[key]})`));
    });
    const shell = table.closest(".table-shell");
    if (!shell?.parentElement) {
        return;
    }
    shell.insertAdjacentElement("beforebegin", group);
    function createStatusChip(value, label) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "status-chip";
        button.dataset.status = value;
        button.textContent = label;
        button.addEventListener("click", () => {
            qa(".status-chip", group).forEach((chip) => {
                chip.classList.remove("active");
            });
            button.classList.add("active");
            filterStatus(table, value);
        });
        return button;
    }
}
function filterStatus(table, value) {
    qa("tbody tr", table).forEach((row) => {
        const statusCell = q(".status, .status-badge, .message-status", row);
        if (!statusCell || value === "all") {
            row.style.display = "";
            return;
        }
        const key = text(statusCell).toLowerCase().replace(/\s+/g, "_");
        row.style.display = key === value ? "" : "none";
    });
}
function filterTableRows(table, query) {
    qa("tbody tr", table).forEach((row) => {
        if (q("td[colspan]", row)) {
            return;
        }
        const isVisible = text(row).toLowerCase().includes(query.toLowerCase());
        row.style.display = isVisible ? "" : "none";
    });
}
function buildCommandPalette(sidebar) {
    const links = qa("nav a", sidebar)
        .map((anchor) => {
        const href = anchor.getAttribute("href");
        if (!href) {
            return null;
        }
        return {
            href,
            label: text(anchor)
        };
    })
        .filter((link) => link !== null);
    if (!links.length) {
        return;
    }
    const overlay = document.createElement("div");
    overlay.className = "admin-modal-overlay";
    overlay.id = "adminCommandOverlay";
    overlay.innerHTML =
        '<div class="admin-modal admin-command">' +
            "<h3>Quick Navigation</h3>" +
            '<input type="search" id="adminCommandInput" placeholder="Type to find a page..." />' +
            '<div class="admin-command-list" id="adminCommandList"></div>' +
            "</div>";
    document.body.appendChild(overlay);
    const input = q("#adminCommandInput", overlay);
    const list = q("#adminCommandList", overlay);
    if (!input || !list) {
        return;
    }
    let cursor = 0;
    let filtered = links.slice();
    const render = () => {
        list.innerHTML = "";
        filtered.forEach((item, index) => {
            const anchor = document.createElement("a");
            anchor.className = `admin-command-item${index === cursor ? " active" : ""}`;
            anchor.href = item.href;
            anchor.textContent = item.label;
            list.appendChild(anchor);
        });
    };
    const open = () => {
        overlay.classList.add("show");
        input.value = "";
        filtered = links.slice();
        cursor = 0;
        render();
        input.focus();
    };
    const close = () => {
        overlay.classList.remove("show");
    };
    document.addEventListener("keydown", (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
            event.preventDefault();
            open();
            return;
        }
        if (event.key === "Escape" && overlay.classList.contains("show")) {
            close();
            return;
        }
        if (overlay.classList.contains("show") && event.key === "ArrowDown") {
            event.preventDefault();
            cursor = Math.min(filtered.length - 1, cursor + 1);
            render();
            return;
        }
        if (overlay.classList.contains("show") && event.key === "ArrowUp") {
            event.preventDefault();
            cursor = Math.max(0, cursor - 1);
            render();
            return;
        }
        if (overlay.classList.contains("show") && event.key === "Enter" && filtered[cursor]) {
            window.location.href = filtered[cursor].href;
        }
    });
    input.addEventListener("input", () => {
        const query = input.value.toLowerCase();
        filtered = links.filter((item) => item.label.toLowerCase().includes(query));
        cursor = 0;
        render();
    });
    overlay.addEventListener("click", (event) => {
        if (event.target === overlay) {
            close();
        }
    });
}
function buildDetailDrawer() {
    const drawer = document.createElement("aside");
    drawer.className = "admin-drawer";
    drawer.id = "adminDetailDrawer";
    drawer.innerHTML =
        '<button type="button" class="admin-nav-toggle" style="float:right" id="closeAdminDrawer">Close</button>' +
            "<h3>Record Details</h3>" +
            '<dl id="adminDrawerBody"></dl>';
    document.body.appendChild(drawer);
    q("#closeAdminDrawer", drawer)?.addEventListener("click", () => {
        drawer.classList.remove("open");
    });
}
function makeRowsInspectable(table) {
    const headers = qa("thead th", table).map((header, index) => text(header) || `Field ${index + 1}`);
    qa("tbody tr", table).forEach((row) => {
        if (q("td[colspan]", row)) {
            return;
        }
        row.style.cursor = "pointer";
        row.addEventListener("click", (event) => {
            const target = event.target;
            if (!(target instanceof Element) || target.closest("button, a, input, select, textarea, form")) {
                return;
            }
            const cells = qa("td", row).map((cell) => text(cell));
            const detailsList = q("#adminDrawerBody");
            const drawer = q("#adminDetailDrawer");
            if (!detailsList || !drawer) {
                return;
            }
            detailsList.innerHTML = "";
            cells.forEach((value, index) => {
                const term = document.createElement("dt");
                term.textContent = headers[index] ?? `Field ${index + 1}`;
                const description = document.createElement("dd");
                description.textContent = value || "-";
                detailsList.appendChild(term);
                detailsList.appendChild(description);
            });
            drawer.classList.add("open");
        });
    });
}
function patchEmptyStates(content) {
    qa("td[colspan], .empty-messages", content).forEach((element) => {
        if (q(".admin-empty-cta", element)) {
            return;
        }
        const anchor = document.createElement("a");
        anchor.href = "dashboard_admin.php";
        anchor.className = "admin-empty-cta";
        anchor.textContent = "Go To Dashboard";
        element.appendChild(anchor);
    });
}
function pretty(value) {
    return value
        .split("_")
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
}
window.adminConfirm = (message, onConfirm) => {
    const overlay = document.createElement("div");
    overlay.className = "admin-modal-overlay show";
    const modal = document.createElement("div");
    modal.className = "admin-modal";
    const title = document.createElement("h3");
    title.textContent = "Please Confirm";
    const body = document.createElement("p");
    body.textContent = String(message || "Are you sure?");
    const actions = document.createElement("div");
    actions.className = "admin-modal-actions";
    const cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.id = "cancelAdminConfirm";
    cancelBtn.textContent = "Cancel";
    const confirmBtn = document.createElement("button");
    confirmBtn.type = "button";
    confirmBtn.className = "danger";
    confirmBtn.id = "okAdminConfirm";
    confirmBtn.textContent = "Confirm";
    actions.appendChild(cancelBtn);
    actions.appendChild(confirmBtn);
    modal.appendChild(title);
    modal.appendChild(body);
    modal.appendChild(actions);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    q("#cancelAdminConfirm", overlay)?.addEventListener("click", () => {
        overlay.remove();
    });
    q("#okAdminConfirm", overlay)?.addEventListener("click", () => {
        overlay.remove();
        onConfirm?.();
    });
    overlay.addEventListener("click", (event) => {
        if (event.target === overlay) {
            overlay.remove();
        }
    });
};
//# sourceMappingURL=admin-enhancements.js.map
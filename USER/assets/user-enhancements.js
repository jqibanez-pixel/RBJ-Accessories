"use strict";
const STORAGE_KEY = "rbj_theme";
const CART_COUNT_SELECTOR = "[data-cart-count]";
const CART_COUNT_ENDPOINT = "cart_count.php";
const CART_COUNT_INTERVAL_MS = 30000;
const REVEAL_SELECTOR = "[data-reveal]";
const root = document.documentElement;
function onReady(fn) {
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", fn);
        return;
    }
    fn();
}
function normalizeTheme(mode) {
    return mode === "light" ? "light" : "dark";
}
function updateThemeToggleUi(btn, theme) {
    const icon = btn.querySelector("i");
    if (icon) {
        icon.className = theme === "light" ? "bx bx-sun" : "bx bx-moon";
    }
    btn.setAttribute("title", theme === "light" ? "Light mode" : "Dark mode");
    btn.setAttribute("aria-label", theme === "light" ? "Switch to dark mode" : "Switch to light mode");
}
function applyTheme(mode, btn) {
    const theme = normalizeTheme(mode);
    root.setAttribute("data-theme", theme);
    if (btn) {
        updateThemeToggleUi(btn, theme);
    }
    return theme;
}
function insertThemeToggle(navLinks) {
    const existingBtn = document.getElementById("navThemeToggleBtn");
    if (existingBtn instanceof HTMLButtonElement) {
        return existingBtn;
    }
    const btn = document.createElement("button");
    btn.type = "button";
    btn.id = "navThemeToggleBtn";
    btn.className = "nav-theme-toggle";
    btn.innerHTML = "<i class='bx bx-moon'></i>";
    const accountDropdown = navLinks.querySelector(".account-dropdown");
    if (accountDropdown && accountDropdown.nextSibling) {
        navLinks.insertBefore(btn, accountDropdown.nextSibling);
        return btn;
    }
    navLinks.appendChild(btn);
    return btn;
}
function initNavbarThemeToggle() {
    const navLinks = document.querySelector(".navbar .nav-links");
    if (!navLinks) {
        return;
    }
    const btn = insertThemeToggle(navLinks);
    if (btn.dataset.themeInit === "1") {
        return;
    }
    btn.dataset.themeInit = "1";
    const saved = localStorage.getItem(STORAGE_KEY);
    applyTheme(saved ?? root.getAttribute("data-theme"), btn);
    btn.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const current = normalizeTheme(root.getAttribute("data-theme"));
        const next = current === "light" ? "dark" : "light";
        localStorage.setItem(STORAGE_KEY, next);
        applyTheme(next, btn);
    });
}
async function refreshCartCount() {
    try {
        const response = await fetch(CART_COUNT_ENDPOINT, { credentials: "same-origin" });
        if (!response.ok) {
            return;
        }
        const data = (await response.json());
        if (typeof data.count === "undefined") {
            return;
        }
        const count = Number(data.count) || 0;
        document.querySelectorAll(CART_COUNT_SELECTOR).forEach((element) => {
            element.textContent = String(count);
            element.style.display = count > 0 ? "flex" : "none";
        });
    }
    catch (_error) {
        // Silent fail to avoid interrupting page interactions.
    }
}
function initCartCountSync() {
    const badges = document.querySelectorAll(CART_COUNT_SELECTOR);
    if (!badges.length) {
        return;
    }
    void refreshCartCount();
    window.setInterval(() => {
        void refreshCartCount();
    }, CART_COUNT_INTERVAL_MS);
}
function initRevealAnimations() {
    const elements = Array.from(document.querySelectorAll(REVEAL_SELECTOR));
    if (!elements.length) {
        return;
    }
    elements.forEach((element) => {
        element.classList.add("rbj-reveal");
    });
    if (!("IntersectionObserver" in window)) {
        elements.forEach((element) => element.classList.add("is-visible"));
        return;
    }
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }
            entry.target.classList.add("is-visible");
            obs.unobserve(entry.target);
        });
    }, { threshold: 0.15, rootMargin: "0px 0px -8% 0px" });
    elements.forEach((element) => observer.observe(element));
}
onReady(() => {
    initNavbarThemeToggle();
    initCartCountSync();
    initRevealAnimations();
});
//# sourceMappingURL=user-enhancements.js.map
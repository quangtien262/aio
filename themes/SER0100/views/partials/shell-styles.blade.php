.ser-shell-flash {
    background: linear-gradient(135deg, #fff3cd, #ffe5b4);
    border-bottom: 1px solid rgba(194, 65, 12, 0.18);
    color: #7c2d12;
}

.ser-shell-flash .wrap {
    padding: 10px 0;
    font-size: 13px;
    font-weight: 700;
}

.ser-shell-topbar {
    background: #081a2a;
    color: rgba(255, 255, 255, 0.82);
}

.ser-shell-topbar-inner,
.ser-shell-header-inner,
.ser-shell-nav-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.ser-shell-topbar-inner {
    padding: 9px 0;
    font-size: 13px;
}

.ser-shell-inline {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.ser-shell-inline button,
.ser-shell-inline a {
    border: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    cursor: pointer;
}

.ser-shell-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    color: #f8fafc;
    font-weight: 700;
}

.ser-shell-status::before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 0 6px rgba(34, 197, 94, 0.18);
}

.ser-shell-header {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(217, 226, 236, 0.85);
    box-shadow: 0 18px 40px rgba(8, 26, 42, 0.08);
}

.ser-shell-header-inner {
    padding: 16px 0;
}

.ser-shell-brand {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.ser-shell-brand img {
    width: 52px;
    height: 52px;
    border-radius: 18px;
    object-fit: cover;
    border: 1px solid rgba(217, 226, 236, 0.92);
    background: #fff;
    padding: 6px;
}

.ser-shell-brand-copy {
    display: grid;
    gap: 4px;
}

.ser-shell-brand-copy strong {
    color: #102a43;
    font-size: 18px;
    line-height: 1.2;
}

.ser-shell-brand-copy span {
    color: #627d98;
    font-size: 13px;
    line-height: 1.5;
}

.ser-shell-search {
    display: flex;
    align-items: center;
    flex: 1 1 320px;
    max-width: 480px;
    min-height: 50px;
    border: 1px solid rgba(217, 226, 236, 0.95);
    border-radius: 999px;
    padding: 6px;
    background: #f8fbfd;
}

.ser-shell-search input {
    flex: 1;
    min-width: 0;
    border: 0;
    outline: none;
    background: transparent;
    padding: 0 16px;
    font: inherit;
    color: #102a43;
}

.ser-shell-search button {
    border: 0;
    border-radius: 999px;
    min-height: 38px;
    padding: 0 18px;
    background: linear-gradient(135deg, #c2410c, #ea580c);
    color: #fff;
    font-weight: 800;
    cursor: pointer;
}

.ser-shell-nav {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(244, 247, 251, 0.98));
    border-top: 1px solid rgba(217, 226, 236, 0.7);
}

.ser-shell-nav-inner {
    padding: 12px 0 14px;
}

.ser-shell-menu,
.ser-shell-tools {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.ser-shell-menu-link,
.ser-shell-cart,
.ser-shell-cta,
.ser-shell-dropdown summary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 16px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 800;
}

.ser-shell-menu-link,
.ser-shell-dropdown summary {
    border: 1px solid rgba(217, 226, 236, 0.95);
    background: #fff;
    color: #102a43;
}

.ser-shell-menu-link:hover,
.ser-shell-dropdown[open] summary,
.ser-shell-dropdown:hover summary {
    border-color: rgba(31, 111, 120, 0.45);
    color: #1f6f78;
    box-shadow: 0 10px 24px rgba(31, 111, 120, 0.12);
}

.ser-shell-cart {
    border: 1px solid rgba(31, 111, 120, 0.2);
    background: rgba(31, 111, 120, 0.08);
    color: #155e75;
}

.ser-shell-cta {
    border: 0;
    background: linear-gradient(135deg, #c2410c, #ea580c);
    color: #fff;
    box-shadow: 0 14px 28px rgba(194, 65, 12, 0.2);
}

.ser-shell-dropdown {
    position: relative;
}

.ser-shell-dropdown summary {
    list-style: none;
    cursor: pointer;
    gap: 8px;
}

.ser-shell-dropdown summary::-webkit-details-marker {
    display: none;
}

.ser-shell-dropdown summary::after {
    content: '▾';
    font-size: 12px;
}

.ser-shell-dropdown-panel {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    min-width: 260px;
    padding: 14px;
    border: 1px solid rgba(217, 226, 236, 0.95);
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 24px 48px rgba(8, 26, 42, 0.16);
}

.ser-shell-dropdown--catalog .ser-shell-dropdown-panel {
    right: auto;
    left: 0;
    min-width: min(1040px, calc(100vw - 32px));
}

.ser-shell-mega-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.ser-shell-mega-card {
    padding: 16px;
    border: 1px solid rgba(217, 226, 236, 0.9);
    border-radius: 20px;
    background: linear-gradient(180deg, #ffffff, #f8fbfd);
}

.ser-shell-mega-summary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    list-style: none;
    margin-bottom: 14px;
    padding: 8px 12px 8px 14px;
    border: 1px solid rgba(31, 111, 120, 0.28);
    border-radius: 999px;
    background: linear-gradient(180deg, #ffffff, #f4f8fb);
    color: #1f6f78;
    cursor: pointer;
    box-shadow: 0 12px 24px rgba(8, 26, 42, 0.06);
}

.ser-shell-mega-summary::-webkit-details-marker {
    display: none;
}

.ser-shell-mega-summary::after {
    content: '▾';
    font-size: 11px;
    color: currentColor;
}

.ser-shell-mega-summary-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    padding: 6px 12px;
    border-radius: 999px;
    background: transparent;
    color: inherit;
    border: 0;
    transition: color 0.18s ease, transform 0.18s ease, text-shadow 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
}

.ser-shell-mega-summary-icon {
    opacity: 0;
    transform: translateX(-4px);
    transition: opacity 0.18s ease, transform 0.18s ease;
    font-size: 12px;
    line-height: 1;
}

.ser-shell-mega-summary-link strong {
    display: block;
    font-size: 15px;
    line-height: 1.4;
}

.ser-shell-mega-summary-link:hover {
    color: #0f766e;
    background: rgba(236, 253, 245, 0.72);
    transform: translateX(2px);
    text-shadow: 0 6px 16px rgba(15, 118, 110, 0.16);
    box-shadow: 0 8px 18px rgba(15, 118, 110, 0.1);
}

.ser-shell-mega-summary:hover .ser-shell-mega-summary-icon,
.ser-shell-mega-summary-link:hover .ser-shell-mega-summary-icon,
.ser-shell-mega-summary-link:focus-visible .ser-shell-mega-summary-icon {
    opacity: 1;
    transform: translateX(0);
}

.ser-shell-mega-summary-link:hover strong {
    text-decoration: underline;
    text-decoration-thickness: 2px;
    text-underline-offset: 4px;
}

.ser-shell-mega-summary-link:focus-visible {
    outline: 0;
    color: #0f766e;
    background: rgba(236, 253, 245, 0.72);
    box-shadow: 0 0 0 4px rgba(31, 111, 120, 0.12);
}

.ser-shell-mega-body {
    display: grid;
    gap: 0;
}

.ser-shell-mega-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #102a43;
    font-size: 15px;
    font-weight: 800;
}

.ser-shell-mega-card > p {
    margin: 8px 0 0;
    color: #627d98;
    font-size: 13px;
    line-height: 1.6;
}

.ser-shell-tree {
    margin-top: 12px;
    display: grid;
    gap: 10px;
    padding-left: 6px;
}

.ser-shell-tree-group {
    display: grid;
    gap: 8px;
    padding-left: 0;
}

.ser-shell-tree-item {
    display: grid;
    gap: 8px;
}

.ser-shell-tree-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #334e68;
    font-size: 13px;
    font-weight: 700;
}

.ser-shell-tree-link.is-root {
    color: #102a43;
}

.ser-shell-tree-link.is-child {
    gap: 10px;
    padding-left: 2px;
}

.ser-shell-tree-link-icon {
    flex: 0 0 auto;
    color: #1f6f78;
    font-size: 12px;
    line-height: 1;
    transform: translateY(-1px);
}

.ser-shell-tree-link-icon.is-root {
    font-size: 11px;
    opacity: 0.9;
}

.ser-shell-tree-link-icon.is-child {
    opacity: 0.8;
}

.ser-shell-tree-children {
    display: grid;
    gap: 8px;
    margin-left: 6px;
    padding-left: 18px;
    border-left: 1px dashed rgba(31, 111, 120, 0.24);
}

.ser-shell-preset-panel {
    display: grid;
    gap: 10px;
}

.ser-shell-preset-option {
    width: 100%;
    text-align: left;
    padding: 12px 14px;
    border: 1px solid rgba(217, 226, 236, 0.95);
    border-radius: 16px;
    background: #fff;
    color: #102a43;
    cursor: pointer;
}

.ser-shell-preset-option strong {
    display: block;
    font-size: 14px;
}

.ser-shell-preset-option span {
    display: block;
    margin-top: 4px;
    color: #627d98;
    font-size: 12px;
    line-height: 1.5;
}

.ser-shell-primary-panel {
    min-width: 280px;
}

.ser-shell-primary-option {
    display: block;
    text-decoration: none;
}

.ser-shell-primary-option--parent {
    border-color: rgba(31, 111, 120, 0.34);
    background: rgba(31, 111, 120, 0.08);
}

.ser-shell-primary-option:hover {
    border-color: rgba(31, 111, 120, 0.34);
    background: rgba(31, 111, 120, 0.06);
}

.ser-shell-preset-option.is-active {
    border-color: rgba(31, 111, 120, 0.34);
    background: rgba(31, 111, 120, 0.08);
}

.ser-shell-preset-option:disabled {
    cursor: default;
}

@media (max-width: 1080px) {
    .ser-shell-mega-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 780px) {
    .ser-shell-header {
        position: static;
    }

    .ser-shell-header-inner,
    .ser-shell-nav-inner,
    .ser-shell-topbar-inner {
        align-items: stretch;
    }

    .ser-shell-search,
    .ser-shell-header-cart,
    .ser-shell-menu,
    .ser-shell-tools {
        width: 100%;
    }

    .ser-shell-menu,
    .ser-shell-tools,
    .ser-shell-inline {
        gap: 10px;
    }

    .ser-shell-dropdown,
    .ser-shell-dropdown summary,
    .ser-shell-menu-link,
    .ser-shell-cart,
    .ser-shell-cta {
        width: 100%;
    }

    .ser-shell-dropdown-panel,
    .ser-shell-dropdown--catalog .ser-shell-dropdown-panel {
        position: static;
        min-width: 100%;
        margin-top: 10px;
        padding: 10px;
        border-radius: 20px;
        max-height: min(72vh, 560px);
        overflow: auto;
    }

    .ser-shell-mega-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .ser-shell-mega-card {
        padding: 0;
        overflow: hidden;
    }

    .ser-shell-mega-summary {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
        margin-bottom: 0;
        border: 0;
        border-radius: 0;
        background: linear-gradient(180deg, #ffffff, #f4f8fb);
        color: #102a43;
        box-shadow: none;
    }

    .ser-shell-mega-summary-link {
        width: 100%;
        padding: 0;
        border: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
    }

    .ser-shell-mega-summary-icon {
        display: none;
    }

    .ser-shell-mega-summary::after {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 999px;
        background: rgba(31, 111, 120, 0.1);
        color: #1f6f78;
        font-size: 18px;
        font-weight: 700;
        content: '+';
    }

    .ser-shell-mega-card[open] .ser-shell-mega-summary::after {
        content: '−';
    }

    .ser-shell-mega-body {
        padding: 0 18px 18px;
        gap: 10px;
    }

    .ser-shell-mega-card:not([open]) .ser-shell-mega-body {
        display: none;
    }

    .ser-shell-tree {
        margin-top: 4px;
        padding-left: 2px;
    }

    .ser-shell-tree-children {
        margin-left: 4px;
        padding-left: 14px;
    }
}

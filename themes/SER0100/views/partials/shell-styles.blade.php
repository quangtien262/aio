.ser-shell-flash {
    background: linear-gradient(135deg, color-mix(in srgb, var(--a) 18%, white), color-mix(in srgb, var(--o) 20%, white));
    border-bottom: 1px solid color-mix(in srgb, var(--o) 18%, transparent);
    color: var(--o-deep);
}

.ser-shell-flash .wrap {
    padding: 10px 0;
    font-size: 13px;
    font-weight: 700;
}

.ser-shell-topbar {
    background: var(--night);
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
    color: var(--n);
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
    color: var(--n);
}

.ser-shell-search button {
    border: 0;
    border-radius: 999px;
    min-height: 38px;
    padding: 0 18px;
    background: linear-gradient(135deg, var(--o), var(--o-deep));
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
    color: var(--n);
}

.ser-shell-menu-link:hover,
.ser-shell-dropdown[open] summary,
.ser-shell-dropdown:hover summary {
    border-color: var(--p);
    color: var(--p);
    box-shadow: 0 10px 24px color-mix(in srgb, var(--p) 18%, transparent);
}

.ser-shell-cart {
    border: 1px solid color-mix(in srgb, var(--p) 22%, transparent);
    background: color-mix(in srgb, var(--p) 8%, white);
    color: var(--p);
}

.ser-shell-cta {
    border: 0;
    background: linear-gradient(135deg, var(--o), var(--o-deep));
    color: #fff;
    box-shadow: 0 14px 28px color-mix(in srgb, var(--o) 20%, transparent);
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
    border: 1px solid color-mix(in srgb, var(--p) 28%, transparent);
    border-radius: 999px;
    background: linear-gradient(180deg, #ffffff, #f4f8fb);
    color: var(--p);
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
    color: var(--p);
    background: color-mix(in srgb, var(--p) 12%, white);
    transform: translateX(2px);
    text-shadow: 0 6px 16px color-mix(in srgb, var(--p) 16%, transparent);
    box-shadow: 0 8px 18px color-mix(in srgb, var(--p) 10%, transparent);
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
    color: var(--p);
    background: color-mix(in srgb, var(--p) 12%, white);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--p) 12%, transparent);
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
    color: var(--p);
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
    border-left: 1px dashed color-mix(in srgb, var(--p) 24%, transparent);
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
    border-color: color-mix(in srgb, var(--p) 34%, transparent);
    background: color-mix(in srgb, var(--p) 8%, white);
}

.ser-shell-primary-option:hover {
    border-color: color-mix(in srgb, var(--p) 34%, transparent);
    background: color-mix(in srgb, var(--p) 6%, white);
}

.ser-shell-preset-option.is-active {
    border-color: color-mix(in srgb, var(--p) 34%, transparent);
    background: color-mix(in srgb, var(--p) 8%, white);
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
        background: color-mix(in srgb, var(--p) 10%, white);
        color: var(--p);
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

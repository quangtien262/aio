.ser-shell-flash {
    background: linear-gradient(135deg, #f5fbf8, #f8efe0);
    border-bottom: 1px solid rgba(180, 83, 9, 0.16);
    color: #7c4a03;
}

.ser-shell-flash .wrap {
    padding: 10px 0;
    font-size: 13px;
    font-weight: 700;
}

.ser-shell-topbar {
    background: #0b1b26;
    color: rgba(248, 250, 252, 0.84);
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
    background: rgba(240, 180, 41, 0.12);
    color: #f8fafc;
    font-weight: 700;
}

.ser-shell-status::before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #f0b429;
    box-shadow: 0 0 0 6px rgba(240, 180, 41, 0.18);
}

.ser-shell-header {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(252, 252, 251, 0.96);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(217, 226, 236, 0.85);
    box-shadow: 0 18px 44px rgba(8, 26, 42, 0.06);
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
    border-radius: 20px;
    object-fit: cover;
    border: 1px solid rgba(214, 226, 222, 0.92);
    background: #fff;
    padding: 6px;
}

.ser-shell-brand-copy {
    display: grid;
    gap: 4px;
}

.ser-shell-brand-copy strong {
    color: #0f172f;
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
    border: 1px solid rgba(214, 226, 222, 0.95);
    border-radius: 999px;
    padding: 6px;
    background: #f6faf7;
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
    background: linear-gradient(135deg, #0f766e, #0f5d56);
    color: #fff;
    font-weight: 800;
    cursor: pointer;
}

.ser-shell-nav {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(246, 249, 247, 0.98));
    border-top: 1px solid rgba(214, 226, 222, 0.7);
}

.ser-shell-nav-inner {
    padding: 14px 0 16px;
    gap: 18px;
}

.ser-shell-menu,
.ser-shell-tools {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.ser-shell-cart,
.ser-shell-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 16px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 800;
}

.ser-shell-cart {
    border: 1px solid rgba(15, 118, 110, 0.18);
    background: rgba(15, 118, 110, 0.08);
    color: #0f766e;
}

.ser-shell-cta {
    border: 0;
    background: linear-gradient(135deg, #b45309, #d97706);
    color: #fff;
    box-shadow: 0 14px 28px rgba(180, 83, 9, 0.2);
}

.ser-shell-menu--editorial {
    flex: 1 1 auto;
    gap: 28px;
}

.ser-shell-nav-item {
    position: relative;
}

.ser-shell-nav-item::after {
    content: '';
    position: absolute;
    left: -12px;
    right: -12px;
    top: 100%;
    height: 18px;
}

.ser-shell-nav-link {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0 8px;
    color: #0f172f;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: -0.01em;
}

.ser-shell-nav-link span {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.ser-shell-nav-link span::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -8px;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, #0f766e, #f0b429);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.22s ease;
}

.ser-shell-nav-item:hover .ser-shell-nav-link span::after,
.ser-shell-nav-link:hover span::after,
.ser-shell-nav-link:focus-visible span::after {
    transform: scaleX(1);
}

.ser-shell-nav-item--catalog > .ser-shell-nav-link::before,
.ser-shell-nav-item > .ser-shell-nav-link::before {
    content: '▾';
    font-size: 11px;
    color: #627d98;
    order: 2;
}

.ser-shell-nav-link--solo::before {
    display: none;
}

.ser-shell-flyout {
    position: absolute;
    top: calc(100% + 2px);
    left: 0;
    min-width: 320px;
    padding: 18px;
    border: 1px solid rgba(214, 226, 222, 0.95);
    border-radius: 28px;
    background: rgba(255, 253, 250, 0.98);
    box-shadow: 0 30px 60px rgba(8, 26, 42, 0.14);
    opacity: 0;
    visibility: hidden;
    transform: translateY(4px);
    pointer-events: none;
    transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s ease;
}

.ser-shell-nav-item:hover > .ser-shell-flyout,
.ser-shell-nav-item:focus-within > .ser-shell-flyout {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    pointer-events: auto;
}

.ser-shell-flyout--catalog {
    min-width: min(1080px, calc(100vw - 32px));
}

.ser-shell-flyout-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.ser-shell-flyout-card {
    padding: 18px;
    border: 1px solid rgba(217, 226, 236, 0.9);
    border-radius: 22px;
    background: linear-gradient(180deg, #ffffff, #f8fbfd);
}

.ser-shell-flyout-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
    color: #0f172f;
}

.ser-shell-flyout-title strong {
    display: block;
    font-size: 16px;
    line-height: 1.4;
}

.ser-shell-flyout-title span {
    color: #0f766e;
    font-weight: 800;
}

.ser-shell-flyout-card > p {
    margin: 8px 0 0;
    color: #627d98;
    font-size: 13px;
    line-height: 1.6;
}

.ser-shell-flyout-list,
.ser-shell-primary-links {
    margin-top: 14px;
    display: grid;
    gap: 10px;
}

.ser-shell-flyout-list a,
.ser-shell-primary-links a {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 0;
    border-top: 1px solid rgba(214, 226, 222, 0.85);
    color: #334e68;
    font-size: 14px;
    font-weight: 700;
}

.ser-shell-flyout-list a:first-child,
.ser-shell-primary-links a:first-child {
    border-top: 0;
    padding-top: 0;
}

.ser-shell-primary-stack {
    display: grid;
    gap: 16px;
    min-width: 320px;
}

.ser-shell-primary-feature {
    display: grid;
    gap: 8px;
    padding: 18px;
    border-radius: 22px;
    background: linear-gradient(135deg, rgba(15, 118, 110, 0.08), rgba(240, 180, 41, 0.12));
}

.ser-shell-primary-feature strong {
    color: #0f172f;
    font-size: 18px;
}

.ser-shell-primary-feature span,
.ser-shell-primary-links a span {
    color: #627d98;
    line-height: 1.65;
}

.ser-shell-primary-links a {
    display: grid;
    gap: 4px;
    padding-right: 14px;
}

.ser-shell-primary-links a strong {
    color: #0f172f;
    font-size: 15px;
}

.ser-shell-primary-links a:hover,
.ser-shell-flyout-list a:hover,
.ser-shell-flyout-title:hover,
.ser-shell-nav-link:hover {
    color: #0f766e;
}

@media (max-width: 980px) {
    .ser-shell-nav-inner {
        align-items: flex-start;
        flex-direction: column;
    }

    .ser-shell-menu--editorial {
        width: 100%;
        gap: 18px;
    }

    .ser-shell-flyout--catalog,
    .ser-shell-flyout {
        position: static;
        min-width: 0;
        width: 100%;
        margin-top: 10px;
        opacity: 1;
        visibility: visible;
        transform: none;
        pointer-events: auto;
        display: none;
    }

    .ser-shell-nav-item:hover > .ser-shell-flyout,
    .ser-shell-nav-item:focus-within > .ser-shell-flyout {
        display: block;
    }

    .ser-shell-flyout-grid {
        grid-template-columns: 1fr;
    }
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

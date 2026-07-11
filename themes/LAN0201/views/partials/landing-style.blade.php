@include('theme-lan0201::partials.palette-tokens', ['branding' => $branding ?? []])

:root {
    --th-landing-bg: linear-gradient(180deg, #f8f0e7 0%, #f4ede6 24%, #f7f5f2 100%);
    --th-landing-surface: rgba(255, 252, 248, 0.82);
    --th-landing-surface-strong: #fffaf5;
    --th-landing-line: rgba(138, 91, 56, 0.16);
    --th-landing-ink: #241b17;
    --th-landing-muted: #6f6258;
    --th-landing-accent: var(--th-red);
    --th-landing-accent-deep: var(--th-red-deep);
    --th-landing-highlight: #f0c788;
    --th-landing-success: #335f53;
    --th-landing-shadow: 0 28px 60px rgba(68, 46, 31, 0.1);
    --th-landing-radius-xl: 28px;
    --th-landing-radius-lg: 22px;
    --th-landing-radius-md: 16px;
    --th-landing-container: min(1180px, calc(100% - 32px));
    --th-landing-space-xs: 10px;
    --th-landing-space-sm: 16px;
    --th-landing-space-md: 24px;
    --th-landing-space-lg: 34px;
    --th-landing-space-xl: 48px;
    --th-landing-heading-font: "Baskerville Old Face", "Palatino Linotype", "Book Antiqua", Georgia, serif;
    --th-landing-body-font: Aptos, "Trebuchet MS", Verdana, sans-serif;
}

* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    margin: 0;
    font-family: var(--th-landing-body-font);
    color: var(--th-landing-ink);
    background: var(--th-landing-bg);
    background-attachment: fixed;
}
a { color: inherit; text-decoration: none; }
img { display: block; max-width: 100%; }
button, input, textarea, select { font: inherit; }

.th-landing-page {
    min-height: 100vh;
    position: relative;
    overflow: hidden;
}

.th-landing-page::before,
.th-landing-page::after {
    content: '';
    position: fixed;
    inset: auto;
    border-radius: 999px;
    pointer-events: none;
    z-index: 0;
    filter: blur(0);
}

.th-landing-page::before {
    top: -120px;
    right: -160px;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(232, 188, 132, 0.28) 0%, rgba(232, 188, 132, 0) 72%);
}

.th-landing-page::after {
    left: -120px;
    bottom: 10%;
    width: 360px;
    height: 360px;
    background: radial-gradient(circle, rgba(164, 104, 64, 0.16) 0%, rgba(164, 104, 64, 0) 72%);
}

.th-landing-shell {
    position: relative;
    z-index: 1;
}

.th-landing-container {
    width: var(--th-landing-container);
    margin: 0 auto;
}

.th-landing-topbar {
    border-bottom: 1px solid rgba(92, 66, 46, 0.08);
    color: var(--th-landing-muted);
    font-size: 13px;
}

.th-landing-topbar-inner,
.th-landing-header-inner,
.th-landing-footer-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}

.th-landing-topbar-inner {
    min-height: 42px;
    flex-wrap: wrap;
}

.th-landing-inline {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.th-landing-inline-action {
    padding: 0;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
}

.th-landing-inline-form { margin: 0; }

.th-landing-header {
    position: sticky;
    top: 0;
    z-index: 20;
    backdrop-filter: blur(18px);
    background: rgba(248, 240, 231, 0.72);
    border-bottom: 1px solid rgba(92, 66, 46, 0.08);
}

.th-landing-header-inner {
    display: grid;
    grid-template-columns: minmax(220px, auto) minmax(0, 1fr) auto;
    align-items: center;
    gap: 16px 24px;
    min-height: 96px;
    padding: 18px 0;
}

.th-landing-brand {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 220px;
}

.th-landing-brand img {
    width: 148px;
    height: 48px;
    object-fit: contain;
}

.th-landing-brand-copy {
    display: grid;
    gap: 4px;
}

.th-landing-brand-copy strong {
    font-family: "Iowan Old Style", "Palatino Linotype", "Book Antiqua", Georgia, serif;
    font-size: 19px;
    letter-spacing: 0.03em;
}

.th-landing-brand-copy span {
    color: var(--th-landing-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.16em;
}

.th-landing-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 28px;
    flex-wrap: wrap;
    grid-column: 1 / -1;
    order: 3;
    padding-top: 2px;
}

.th-landing-nav a {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--th-landing-muted);
    transition: color .18s ease;
}

.th-landing-nav a.is-active,
.th-landing-nav a:hover {
    color: var(--th-landing-accent-deep);
}

.th-landing-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    min-width: 260px;
    flex-wrap: wrap;
    grid-column: 3;
}

.th-landing-link,
.th-landing-button,
.th-landing-outline,
.th-landing-ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 999px;
    font-weight: 700;
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
}

.th-landing-button,
.th-landing-link.is-primary {
    border: 0;
    color: #fff;
    background: linear-gradient(135deg, var(--th-landing-accent) 0%, var(--th-landing-accent-deep) 100%);
    box-shadow: 0 16px 32px rgba(159, 88, 34, 0.24);
}

.th-landing-outline {
    border: 1px solid rgba(160, 97, 48, 0.24);
    background: rgba(255, 250, 245, 0.72);
    color: var(--th-landing-ink);
}

.th-landing-ghost {
    border: 0;
    background: transparent;
    color: var(--th-landing-muted);
}

.th-landing-button:hover,
.th-landing-link.is-primary:hover,
.th-landing-outline:hover,
.th-landing-ghost:hover {
    transform: translateY(-1px);
}

.th-landing-main {
    padding: 42px 0 84px;
}

.th-landing-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 18px;
    color: var(--th-landing-muted);
    font-size: 13px;
}

.th-landing-kicker {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 0 14px;
    border-radius: 999px;
    background: rgba(201, 109, 36, 0.12);
    color: var(--th-landing-accent-deep);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.th-landing-panel,
.th-landing-card,
.th-landing-hero {
    border: 1px solid var(--th-landing-line);
    border-radius: var(--th-landing-radius-xl);
    background: var(--th-landing-surface);
    box-shadow: var(--th-landing-shadow);
}

.th-landing-panel {
    padding: 30px;
}

.th-landing-hero {
    position: relative;
    overflow: hidden;
    padding: 42px;
    background:
        radial-gradient(circle at top left, rgba(240, 199, 136, 0.34) 0%, rgba(240, 199, 136, 0) 30%),
        linear-gradient(135deg, rgba(255, 248, 241, 0.96) 0%, rgba(246, 236, 226, 0.96) 100%);
}

.th-landing-hero-grid,
.th-landing-two-col,
.th-landing-three-col,
.th-landing-four-col {
    display: grid;
    gap: 20px;
}

.th-landing-hero-grid { grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr); }
.th-landing-two-col { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.th-landing-three-col { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.th-landing-four-col { grid-template-columns: repeat(4, minmax(0, 1fr)); }

.th-landing-title,
.th-landing-section-title,
.th-landing-display {
    font-family: var(--th-landing-heading-font);
    letter-spacing: -0.03em;
}

.th-landing-display {
    margin: 16px 0 14px;
    font-size: clamp(42px, 7vw, 78px);
    line-height: .95;
}

.th-landing-title {
    margin: 0 0 14px;
    font-size: clamp(32px, 4vw, 52px);
    line-height: 1;
}

.th-landing-section-title {
    margin: 0 0 12px;
    font-size: clamp(28px, 3vw, 40px);
    line-height: 1.06;
}

.th-landing-summary,
.th-landing-copy,
.th-landing-meta,
.th-landing-list {
    color: var(--th-landing-muted);
    line-height: 1.8;
}

.th-landing-summary { font-size: 16px; }

.th-landing-copy p:first-child { margin-top: 0; }

@keyframes th-rise-fade {
    from {
        opacity: 0;
        transform: translate3d(0, 22px, 0);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

.th-landing-hero,
.th-landing-panel,
.th-landing-card,
.th-landing-listing {
    animation: th-rise-fade .72s cubic-bezier(.2, .75, .2, 1) both;
}

.th-landing-grid-listings > *:nth-child(2),
.th-landing-three-col > *:nth-child(2),
.th-landing-two-col > *:nth-child(2) {
    animation-delay: .08s;
}

.th-landing-grid-listings > *:nth-child(3),
.th-landing-three-col > *:nth-child(3) {
    animation-delay: .16s;
}

.th-landing-link,
.th-landing-button,
.th-landing-outline,
.th-landing-ghost,
.th-landing-listing,
.th-landing-card,
.th-landing-stat {
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease, background .22s ease, color .22s ease;
}

.th-landing-actions-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 22px;
}

.th-landing-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.th-landing-stat {
    padding: 18px;
    border-radius: var(--th-landing-radius-lg);
    border: 1px solid rgba(140, 103, 68, 0.16);
    background: rgba(255, 252, 247, 0.82);
}

.th-landing-stat strong {
    display: block;
    font-size: 30px;
    color: var(--th-landing-accent-deep);
}

.th-landing-stat span {
    display: block;
    margin-top: 8px;
    color: var(--th-landing-muted);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.th-landing-media {
    overflow: hidden;
    border-radius: var(--th-landing-radius-lg);
    border: 1px solid rgba(140, 103, 68, 0.16);
    background: #efe7de;
}

.th-landing-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.th-landing-chip-row,
.th-landing-meta-row,
.th-landing-tag-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.th-landing-chip,
.th-landing-tag,
.th-landing-pill {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 0 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.th-landing-chip {
    background: rgba(255, 250, 245, 0.9);
    border: 1px solid rgba(160, 97, 48, 0.16);
    color: var(--th-landing-accent-deep);
}

.th-landing-pill {
    background: rgba(51, 95, 83, 0.12);
    color: var(--th-landing-success);
}

.th-landing-tag {
    background: rgba(36, 27, 23, 0.08);
    color: var(--th-landing-ink);
}

.th-landing-grid-listings {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.th-landing-listing {
    overflow: hidden;
    border: 1px solid var(--th-landing-line);
    border-radius: var(--th-landing-radius-xl);
    background: linear-gradient(180deg, rgba(255, 251, 246, 0.98) 0%, rgba(255, 255, 255, 0.98) 100%);
    box-shadow: 0 18px 36px rgba(72, 49, 34, 0.08);
    transition: transform .18s ease, box-shadow .18s ease;
}

.th-landing-listing:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 44px rgba(72, 49, 34, 0.12);
}

.th-landing-listing-media {
    position: relative;
    aspect-ratio: 4 / 5;
    overflow: hidden;
    background: #e7ddd4;
}

.th-landing-listing-badge {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: 1;
}

.th-landing-listing-body {
    display: grid;
    gap: 12px;
    padding: 20px;
}

.th-landing-listing-title {
    margin: 0;
    font-family: "Iowan Old Style", "Palatino Linotype", "Book Antiqua", Georgia, serif;
    font-size: 26px;
    line-height: 1.04;
}

.th-landing-price {
    display: flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
}

.th-landing-price strong {
    font-size: 30px;
    color: var(--th-landing-accent-deep);
    letter-spacing: -0.04em;
}

.th-landing-price span {
    color: #96877c;
    text-decoration: line-through;
}

.th-landing-divider {
    height: 1px;
    background: rgba(140, 103, 68, 0.12);
}

.th-landing-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.th-landing-field {
    display: grid;
    gap: 8px;
}

.th-landing-field.is-full {
    grid-column: 1 / -1;
}

.th-landing-field label {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--th-landing-muted);
}

.th-landing-field input,
.th-landing-field textarea,
.th-landing-field select {
    width: 100%;
    min-height: 52px;
    padding: 0 16px;
    border: 1px solid rgba(140, 103, 68, 0.16);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.92);
    color: var(--th-landing-ink);
}

.th-landing-field textarea {
    min-height: 136px;
    padding: 14px 16px;
    resize: vertical;
}

.th-landing-empty {
    padding: 34px;
    text-align: center;
    border: 1px dashed rgba(140, 103, 68, 0.3);
    border-radius: var(--th-landing-radius-lg);
    color: var(--th-landing-muted);
}

.th-landing-footer {
    padding: 20px 0 44px;
}

.th-landing-footer-inner {
    border: 1px solid var(--th-landing-line);
    border-radius: var(--th-landing-radius-xl);
    background: rgba(255, 250, 245, 0.82);
    box-shadow: var(--th-landing-shadow);
    padding: 28px 30px;
    align-items: flex-start;
}

.th-landing-footer-grid {
    display: grid;
    grid-template-columns: 1.15fr repeat(3, minmax(0, 1fr));
    gap: 18px;
    width: 100%;
}

.th-landing-footer-card h4 {
    margin: 0 0 12px;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--th-landing-muted);
}

.th-landing-footer-links {
    display: grid;
    gap: 8px;
    color: var(--th-landing-muted);
    font-size: 14px;
}

.th-landing-company strong {
    display: block;
    font-family: "Iowan Old Style", "Palatino Linotype", "Book Antiqua", Georgia, serif;
    font-size: 22px;
    margin-bottom: 8px;
}

.th-landing-boc-status {
    display: inline-flex;
    margin-top: 14px;
    color: var(--th-landing-muted);
    font-size: 13px;
    line-height: 1.5;
}

.th-landing-alert {
    margin-bottom: 18px;
    padding: 14px 16px;
    border-radius: 16px;
    border: 1px solid rgba(160, 97, 48, 0.18);
    background: rgba(255, 248, 236, 0.92);
    color: #8a5a24;
}

.th-landing-alert.is-error {
    border-color: rgba(160, 48, 48, 0.18);
    background: rgba(255, 241, 241, 0.92);
    color: #9f2f2f;
}

@media (max-width: 1100px) {
    .th-landing-hero-grid,
    .th-landing-two-col,
    .th-landing-three-col,
    .th-landing-four-col,
    .th-landing-grid-listings,
    .th-landing-footer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .th-landing-header-inner {
        grid-template-columns: minmax(180px, 1fr) auto;
        min-height: auto;
    }

    .th-landing-actions {
        grid-column: 2;
    }

    .th-landing-nav {
        justify-content: flex-start;
        gap: 18px;
    }
}

@media (max-width: 760px) {
    .th-landing-container {
        width: min(100% - 20px, 1180px);
    }

    .th-landing-topbar-inner,
    .th-landing-header-inner,
    .th-landing-footer-inner {
        flex-direction: column;
        align-items: stretch;
    }

    .th-landing-header-inner {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .th-landing-nav,
    .th-landing-actions {
        justify-content: flex-start;
        min-width: 0;
    }

    .th-landing-actions,
    .th-landing-nav {
        grid-column: 1;
    }

    .th-landing-main {
        padding-top: 22px;
    }

    .th-landing-hero,
    .th-landing-panel {
        padding: 22px;
    }

    .th-landing-hero-grid,
    .th-landing-two-col,
    .th-landing-three-col,
    .th-landing-four-col,
    .th-landing-grid-listings,
    .th-landing-footer-grid,
    .th-landing-form-grid {
        grid-template-columns: 1fr;
    }

    .th-landing-field.is-full {
        grid-column: auto;
    }

    .th-landing-display {
        font-size: clamp(36px, 13vw, 58px);
    }

    .th-landing-title {
        font-size: clamp(28px, 10vw, 42px);
    }
}

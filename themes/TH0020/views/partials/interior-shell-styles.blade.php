            :root {
                --th20-ink: #21312b;
                --th20-muted: #6f7a72;
                --th20-line: #e2ddd2;
                --th20-bg: #f6f2ea;
                --th20-surface: #fffdf8;
                --th20-cream: #fbf7ef;
                --th20-olive: #60715c;
                --th20-olive-dark: #33483c;
                --th20-wood: #b98455;
                --th20-clay: #d8b799;
                --th20-shadow: 0 22px 60px rgba(57, 48, 38, .09);
            }
            .th-container { width: min(1240px, calc(100% - 32px)); margin: 0 auto; }
            .th-interior-page,
            body.th-interior-page { background: var(--th20-bg); color: var(--th20-ink); }
            .th-interior-page a,
            body.th-interior-page a { color: inherit; text-decoration: none; }
            .th-interior-page img,
            body.th-interior-page img { display: block; max-width: 100%; }
            .th-topbar { background: #fbf7ef; border-top: 3px solid var(--th20-olive-dark); color: var(--th20-muted); font-size: 13px; }
            .th-topbar-inner,
            .th-header-inner,
            .th-main-nav-inner,
            .th-footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 18px; }
            .th-topbar-inner { min-height: 34px; padding: 7px 0; }
            .th-inline { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
            .th-inline-action,
            .th-inline-form button { padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; }
            .th-inline-form { margin: 0; }
            .th-accent { color: var(--th20-wood); font-weight: 700; }
            .th-header { background: var(--th20-surface); border-bottom: 1px solid var(--th20-line); }
            .th-header-inner { padding: 18px 0; }
            .th-logo { min-width: 220px; display: flex; align-items: center; }
            .th-logo img { width: 166px; height: 54px; object-fit: contain; }
            .th-search { flex: 1; max-width: 650px; display: grid; grid-template-columns: minmax(0, 1fr) 92px; overflow: hidden; background: #fff; border: 1px solid var(--th20-line); border-radius: 999px; box-shadow: 0 10px 28px rgba(57,48,38,.05); }
            .th-search input,
            .th-search button { height: 46px; border: 0; font: inherit; }
            .th-search input { padding: 0 20px; color: var(--th20-ink); background: transparent; }
            .th-search button { background: var(--th20-olive-dark); color: #fff; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; cursor: pointer; }
            .th-cart { min-width: 130px; display: flex; justify-content: flex-end; color: var(--th20-ink); font-weight: 900; letter-spacing: .06em; text-transform: uppercase; }
            .th-main-nav { position: relative; z-index: 40; background: #fffdf8; color: var(--th20-ink); border-top: 1px solid rgba(51,72,60,.08); border-bottom: 1px solid #d8d0c2; box-shadow: 0 14px 32px rgba(57,48,38,.08); }
            .th-main-nav-inner { min-height: 54px; justify-content: flex-start; }
            .th-home-link { width: 58px; min-height: 54px; display: grid; place-items: center; border-left: 1px solid #d8d0c2; border-right: 1px solid #d8d0c2; background: #ece6dc; color: var(--th20-olive-dark); font-size: 21px; font-weight: 900; text-shadow: 0 1px 0 rgba(255,255,255,.65); box-shadow: inset 0 -2px 0 rgba(51,72,60,.12); transition: background .18s ease, color .18s ease, box-shadow .18s ease, transform .18s ease; }
            .th-home-link:hover { background: var(--th20-olive-dark); color: #fff; box-shadow: inset 0 -3px 0 var(--th20-wood); transform: translateY(-1px); text-shadow: none; }
            .th-main-nav-menu { display: flex; align-items: stretch; justify-content: flex-start; gap: 0; font-size: 13px; font-weight: 900; }
            .th-nav-item { position: relative; }
            .th-main-nav-menu > .th-nav-item > .th-nav-link { min-height: 54px; padding: 0 24px; display: inline-flex; align-items: center; gap: 9px; white-space: nowrap; line-height: 1; text-align: left; text-transform: uppercase; letter-spacing: .105em; color: #17251f; font-weight: 950; text-shadow: 0 1px 0 rgba(255,255,255,.68); border-right: 1px solid rgba(51,72,60,.08); transition: color .18s ease, background .18s ease, box-shadow .18s ease, transform .18s ease; cursor: pointer; }
            .th-nav-link:hover,
            .th-nav-item:hover > .th-nav-link { color: #fff; background: #33483c; box-shadow: inset 0 -3px 0 var(--th20-wood); transform: translateY(-1px); text-shadow: none; }
            .th-nav-caret { font-size: 11px; color: var(--th20-wood); transform: translateY(-1px); transition: color .18s ease, transform .18s ease; }
            .th-nav-item:hover > .th-nav-link .th-nav-caret { color: #f4d06f; transform: translateY(-1px) rotate(180deg); }
            .th-nav-products { position: static; }
            .th-nav-products > .th-nav-link { background: var(--th20-olive-dark); color: #fff; box-shadow: inset 0 -3px 0 rgba(244,208,111,.55); text-shadow: none; }
            .th-nav-products > .th-nav-link .th-nav-caret { color: #f4d06f; }
            .th-nav-products-panel { position: absolute; top: 100%; left: 0; right: 0; background: #fffdf8; color: var(--th20-ink); border-top: 1px solid rgba(51,72,60,.12); border-bottom: 1px solid #d8d0c2; box-shadow: 0 32px 80px rgba(33,49,43,.2); opacity: 0; visibility: hidden; transform: translateY(8px); pointer-events: none; transition: opacity .14s ease, transform .18s ease, visibility .18s ease; }
            .th-nav-item:hover .th-nav-products-panel,
            .th-nav-item:focus-within .th-nav-products-panel { opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto; }
            .th-nav-products-inner { width: min(1240px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 32px; display: grid; grid-template-columns: minmax(250px, .3fr) minmax(0, 1fr); gap: 28px; }
            .th-nav-products-intro { min-height: 240px; padding: 28px; display: grid; gap: 14px; align-content: end; color: #fff; background: linear-gradient(145deg, rgba(51,72,60,.92), rgba(99,113,92,.72)), url('https://picsum.photos/seed/th0020-menu-room/720/720') center/cover; }
            .th-nav-products-intro span { color: #ead9ba; font-size: 12px; font-weight: 900; letter-spacing: .16em; text-transform: uppercase; }
            .th-nav-products-intro strong { max-width: 320px; font-family: Georgia, 'Times New Roman', serif; font-size: 38px; line-height: 1.02; font-weight: 500; }
            .th-nav-products-intro small { color: rgba(255,255,255,.82); line-height: 1.75; }
            .th-nav-products-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
            .th-nav-category-card { min-width: 0; min-height: 178px; display: grid; gap: 14px; align-content: start; padding: 18px; background: #fff; border: 1px solid var(--th20-line); box-shadow: 0 12px 30px rgba(57,48,38,.05); transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
            .th-nav-category-card:hover { transform: translateY(-4px); border-color: var(--th20-olive-dark); box-shadow: var(--th20-shadow); }
            .th-nav-category-head { display: flex; align-items: center; gap: 11px; }
            .th-nav-category-icon { width: 38px; height: 38px; display: grid; place-items: center; background: #f1eadf; color: var(--th20-wood); font-size: 17px; }
            .th-nav-category-head strong { font-size: 14px; line-height: 1.25; text-transform: uppercase; letter-spacing: .08em; }
            .th-nav-category-links { display: grid; gap: 8px; }
            .th-nav-category-links a { color: var(--th20-muted); font-size: 13px; line-height: 1.45; }
            .th-nav-category-links a:hover { color: var(--th20-olive-dark); }
            .th-nav-simple-panel { position: absolute; top: 100%; left: 0; min-width: 240px; padding: 10px; background: #fffdf8; color: var(--th20-ink); border: 1px solid #d8d0c2; box-shadow: 0 22px 54px rgba(33,49,43,.18); opacity: 0; visibility: hidden; transform: translateY(8px); pointer-events: none; transition: opacity .14s ease, transform .18s ease, visibility .18s ease; }
            .th-nav-item:hover .th-nav-simple-panel,
            .th-nav-item:focus-within .th-nav-simple-panel { opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto; }
            .th-nav-simple-panel a { display: block; padding: 12px 14px; color: #24362f; font-size: 13px; font-weight: 800; line-height: 1.35; text-transform: none; letter-spacing: .03em; border-radius: 12px; }
            .th-nav-simple-panel a:hover { background: #33483c; color: #fff; }
            .th-footer { background: #27382f !important; color: #fff; border: 0 !important; }
            .th-footer-card h4,
            .th-company strong { color: #ead9ba !important; letter-spacing: .12em; }
            .th-footer-links { color: rgba(255,255,255,.72) !important; }
            .th-company { background: rgba(255,255,255,.06) !important; border: 1px solid rgba(255,255,255,.12) !important; border-radius: 20px !important; }
            .th-interior-page .th-cms-listing-head,
            .th-interior-page .search-hero { border: 0; background: transparent; box-shadow: none; padding: 24px 0 14px; }
            .th-interior-page .th-cms-listing-head h1,
            .th-interior-page .search-hero h1,
            .th-interior-page .cart-title,
            .th-interior-page .checkout-title,
            .th-interior-page .success-card h1 { font-family: Georgia, 'Times New Roman', serif; font-size: clamp(38px, 5vw, 62px); line-height: 1; font-weight: 500; color: var(--th20-ink); text-transform: none; }
            .th-interior-page .th-cms-listing-head p,
            .th-interior-page .search-hero p { color: var(--th20-muted); line-height: 1.75; }
            .th-interior-page .product-card,
            .th-interior-page .th-cms-card-grid > .th-cms-card { display: flex; flex-direction: column; border: 1px solid var(--th20-line); border-radius: 22px; background: #fffdf8; box-shadow: 0 14px 36px rgba(57,48,38,.06); overflow: hidden; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .th-interior-page .product-card:hover,
            .th-interior-page .th-cms-card-grid > .th-cms-card:hover { transform: translateY(-5px); border-color: var(--th20-olive-dark); box-shadow: var(--th20-shadow); }
            .th-interior-page .product-media,
            .th-interior-page .th-cms-card-grid > .th-cms-card > a:first-child { display: block; aspect-ratio: 4 / 3; overflow: hidden; background: #e3ddd2; }
            .th-interior-page .product-media img,
            .th-interior-page .th-cms-card-grid > .th-cms-card > a:first-child .th-cms-card-media { width: 100%; height: 100%; object-fit: cover; transition: transform .34s ease; }
            .th-interior-page .product-card:hover img,
            .th-interior-page .th-cms-card-grid > .th-cms-card:hover img { transform: scale(1.04); }
            .th-interior-page .product-body,
            .th-interior-page .th-cms-card-grid .th-cms-card-body { flex: 1; display: flex; flex-direction: column; padding: 18px 18px 20px; background: #fffdf8; }
            .th-interior-page .product-title,
            .th-interior-page .th-cms-card-title { margin: 0 0 10px; color: var(--th20-ink); font-size: 20px; line-height: 1.3; }
            .th-interior-page .th-cms-card-summary { margin: 0; color: var(--th20-muted); line-height: 1.7; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
            .th-interior-page .th-cms-meta-row,
            .th-interior-page .interior-news-date { color: var(--th20-wood); font-size: 11px; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; }
            .th-interior-page .th-cms-sidebar-card,
            .th-interior-page .cart-panel,
            .th-interior-page .summary-panel,
            .th-interior-page .checkout-panel,
            .th-interior-page .success-card,
            .th-interior-page .empty-state,
            .th-interior-page .filter-card,
            .th-interior-page .section-panel,
            .th-interior-page .sidebar-card { border: 1px solid var(--th20-line); border-radius: 24px; background: #fffdf8; box-shadow: 0 14px 38px rgba(57,48,38,.06); }
            .th-interior-page input,
            .th-interior-page select,
            .th-interior-page textarea,
            .th-interior-page .search-field input,
            .th-interior-page .search-field select,
            .th-interior-page .th-news-field input,
            .th-interior-page .th-news-field select { border-radius: 999px !important; border-color: var(--th20-line) !important; background: #fff !important; }
            .th-interior-page textarea { border-radius: 20px !important; }
            .th-interior-page .th-cms-button,
            .th-interior-page .search-button,
            .th-interior-page .primary-button,
            .th-interior-page .submit-button,
            .th-interior-page .cta-link,
            .th-interior-page .th-cms-page-link,
            .th-interior-page .search-page-link { border-radius: 999px !important; }
            .th-interior-page .th-cms-button.primary,
            .th-interior-page .search-button,
            .th-interior-page .primary-button,
            .th-interior-page .submit-button,
            .th-interior-page .cta-link.primary { background: var(--th20-olive-dark) !important; color: #fff !important; }
            .th-interior-page .search-reset,
            .th-interior-page .ghost-button,
            .th-interior-page .cta-link.secondary { border-color: var(--th20-olive-dark) !important; color: var(--th20-olive-dark) !important; background: transparent !important; }
            @media (max-width: 1100px) {
                .th-nav-products-inner { grid-template-columns: 1fr; }
                .th-nav-products-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (max-width: 760px) {
                .th-topbar-inner,
                .th-header-inner,
                .th-main-nav-inner,
                .th-footer-inner { flex-direction: column; align-items: stretch; }
                .th-logo { min-width: 0; justify-content: center; }
                .th-search { max-width: none; grid-template-columns: 1fr; border-radius: 22px; }
                .th-main-nav-menu { overflow-x: auto; }
                .th-main-nav-menu > .th-nav-item > .th-nav-link { padding: 0 16px; }
                .th-nav-products-panel,
                .th-nav-simple-panel { display: none; }
                .th-nav-products-grid,
                .th-interior-page .th-cms-card-grid { grid-template-columns: 1fr; }
            }

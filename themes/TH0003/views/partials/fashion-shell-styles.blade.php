            .th-container { width: min(1200px, calc(100% - 24px)); margin: 0 auto; }
            .th-fashion-page, body.th-fashion-page { background: #f7f5f2; color: #1f1a1d; }
            .th-fashion-page .th-topbar, body.th-fashion-page .th-topbar { border-top: 3px solid #1f1a1d; background: #fdfbf8; color: #756a70; }
            .th-fashion-page .th-header, body.th-fashion-page .th-header { background: #fffaf6; border-bottom: 1px solid #eadfda; }
            .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
            .th-topbar-inner { padding: 6px 0; }
            .th-inline { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
            .th-inline-action { padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; }
            .th-inline-form { margin: 0; }
            .th-accent { color: #b20f3a; }
            .th-header-inner { padding: 12px 0; }
            .th-logo { display: flex; align-items: center; gap: 12px; min-width: 220px; }
            .th-logo img { width: 150px; height: 52px; object-fit: contain; }
            .th-search { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr) 52px; border: 1px solid #1f1a1d; border-radius: 0; overflow: hidden; background: #fff; max-width: 620px; }
            .th-search input, .th-search button { border: 0; height: 44px; font-size: 14px; }
            .th-search input { padding: 0 14px; background: transparent; }
            .th-search button { background: #1f1a1d; color: #fff; font-weight: 800; cursor: pointer; letter-spacing: .08em; text-transform: uppercase; }
            .th-cart { min-width: 120px; display: flex; justify-content: flex-end; font-weight: 800; color: #1f1a1d; text-transform: uppercase; letter-spacing: .08em; }
            .th-main-nav { position: relative; background: #1f1a1d; color: #fff; z-index: 40; }
            .th-main-nav-inner { min-height: 42px; justify-content: flex-start; }
            .th-home-link { width: 48px; min-height: 42px; display: grid; place-items: center; background: rgba(255,255,255,.08); font-size: 17px; transition: background .18s ease, color .18s ease; }
            .th-home-link:hover { background: rgba(255,255,255,.14); color: #f2c94c; }
            .th-main-nav-menu { display: flex; justify-content: flex-start; gap: 0; font-size: 14px; font-weight: 800; }
            .th-nav-item { position: relative; }
            .th-nav-link { min-height: 42px; padding: 0 18px; display: inline-flex; align-items: center; gap: 8px; text-align: left; text-transform: uppercase; letter-spacing: .08em; font-size: 12px; transition: color .18s ease, background .18s ease; cursor: pointer; }
            .th-nav-link:hover, .th-nav-item:hover > .th-nav-link { color: #f2c94c; background: rgba(255,255,255,.06); }
            .th-nav-caret { font-size: 11px; opacity: .72; transform: translateY(-1px); }
            .th-nav-products { position: static; background: #b20f3a; }
            .th-nav-products-panel { position: absolute; top: 100%; left: 0; right: 0; background: #fffaf6; color: #1f1a1d; border-bottom: 1px solid #eadfda; box-shadow: 0 26px 60px rgba(31,26,29,.16); opacity: 0; visibility: hidden; transform: translateY(12px); pointer-events: none; transition: opacity .18s ease, transform .22s ease, visibility .22s ease; }
            .th-nav-item:hover .th-nav-products-panel, .th-nav-item:focus-within .th-nav-products-panel { opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto; }
            .th-nav-products-inner { width: min(1200px, calc(100% - 24px)); margin: 0 auto; padding: 26px 0 30px; display: grid; grid-template-columns: minmax(230px, .28fr) minmax(0, 1fr); gap: 26px; }
            .th-nav-products-intro { background: #1f1a1d; color: #fff; padding: 24px; display: grid; gap: 12px; align-content: start; }
            .th-nav-products-intro span { color: #f2c94c; font-size: 12px; font-weight: 900; letter-spacing: .16em; text-transform: uppercase; }
            .th-nav-products-intro strong { font-family: Georgia, 'Times New Roman', serif; font-size: 34px; line-height: 1.05; font-weight: 500; }
            .th-nav-products-intro small { color: rgba(255,255,255,.72); line-height: 1.65; }
            .th-nav-products-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
            .th-nav-category-card { min-width: 0; display: grid; gap: 12px; align-content: start; padding: 18px; background: #fff; border: 1px solid #eadfda; transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease; }
            .th-nav-category-card:hover { transform: translateY(-3px); border-color: #1f1a1d; box-shadow: 0 18px 40px rgba(31,26,29,.1); }
            .th-nav-category-head { display: flex; align-items: center; gap: 10px; color: #1f1a1d; }
            .th-nav-category-icon { width: 34px; height: 34px; display: grid; place-items: center; background: #f7f0eb; color: #b20f3a; font-size: 16px; }
            .th-nav-category-head strong { font-size: 15px; line-height: 1.25; text-transform: uppercase; letter-spacing: .06em; }
            .th-nav-category-links { display: grid; gap: 8px; }
            .th-nav-category-links a { color: #756a70; font-size: 13px; line-height: 1.45; }
            .th-nav-category-links a:hover { color: #b20f3a; }
            .th-nav-simple-panel { position: absolute; top: 100%; left: 0; min-width: 220px; background: #fff; color: #1f1a1d; border: 1px solid #eadfda; box-shadow: 0 18px 42px rgba(31,26,29,.14); padding: 8px; opacity: 0; visibility: hidden; transform: translateY(10px); pointer-events: none; transition: opacity .18s ease, transform .22s ease, visibility .22s ease; }
            .th-nav-item:hover .th-nav-simple-panel, .th-nav-item:focus-within .th-nav-simple-panel { opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto; }
            .th-nav-simple-panel a { display: block; padding: 10px 12px; color: #4f454a; font-size: 13px; line-height: 1.35; text-transform: none; letter-spacing: 0; }
            .th-nav-simple-panel a:hover { background: #fff4ee; color: #b20f3a; }
            .th-footer { background: #1f1a1d !important; color: #fff; border: 0 !important; }
            .th-footer-card h4, .th-company strong { color: #f2c94c !important; letter-spacing: .12em; }
            .th-footer-links { color: rgba(255,255,255,.68) !important; }
            .th-company { background: rgba(255,255,255,.06) !important; border: 1px solid rgba(255,255,255,.12) !important; border-radius: 0 !important; }
            .th-fashion-page .breadcrumb, .th-fashion-page .th-breadcrumb { color: #756a70; }
            .th-fashion-page .breadcrumb span:last-child, .th-fashion-page .th-breadcrumb span:last-child { color: #1f1a1d; font-weight: 800; }
            .th-fashion-page .th-cms-listing-head,
            .th-fashion-page .search-hero {
                border: 0;
                background: transparent;
                box-shadow: none;
                padding: 22px 0 14px;
            }
            .th-fashion-page .th-cms-listing-head h1,
            .th-fashion-page .search-hero h1,
            .th-fashion-page .cart-title,
            .th-fashion-page .checkout-title,
            .th-fashion-page .success-card h1 {
                font-family: Georgia, 'Times New Roman', serif;
                font-size: clamp(36px, 5vw, 58px);
                line-height: 1;
                font-weight: 500;
                color: #1f1a1d;
                text-transform: none;
            }
            .th-fashion-page .th-cms-listing-head p,
            .th-fashion-page .search-hero p {
                color: #756a70;
                line-height: 1.75;
            }
            .th-fashion-page .th-cms-card,
            .th-fashion-page .product-card {
                border: 0;
                border-radius: 0;
                background: transparent;
                box-shadow: none;
                overflow: visible;
            }
            .th-fashion-page .th-cms-card:hover,
            .th-fashion-page .product-card:hover {
                transform: translateY(-4px);
                box-shadow: none;
            }
            .th-fashion-page .th-cms-card-media,
            .th-fashion-page .product-media {
                border: 0;
                border-radius: 0;
                background: #ded4cf;
                aspect-ratio: 3 / 4;
                overflow: hidden;
            }
            .th-fashion-page .th-cms-card-media,
            .th-fashion-page .th-cms-card-media img,
            .th-fashion-page .product-media img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .th-fashion-page .th-cms-card img,
            .th-fashion-page .product-card img {
                transition: transform .32s ease;
            }
            .th-fashion-page .th-cms-card:hover img,
            .th-fashion-page .product-card:hover img {
                transform: scale(1.045);
            }
            .th-fashion-page .th-cms-card-body,
            .th-fashion-page .product-body {
                padding: 14px 0 0;
            }
            .th-fashion-page .th-cms-card-title,
            .th-fashion-page .product-title {
                color: #1f1a1d;
                font-size: 18px;
                line-height: 1.35;
            }
            .th-fashion-page .th-cms-meta-row,
            .th-fashion-page .fashion-news-date {
                color: #b20f3a;
                font-size: 11px;
                font-weight: 900;
                letter-spacing: .14em;
                text-transform: uppercase;
            }
            .th-fashion-page .th-cms-sidebar-card,
            .th-fashion-page .cart-panel,
            .th-fashion-page .summary-panel,
            .th-fashion-page .checkout-panel,
            .th-fashion-page .success-card,
            .th-fashion-page .empty-state,
            .th-fashion-page .filter-card,
            .th-fashion-page .section-panel,
            .th-fashion-page .sidebar-card {
                border: 0;
                border-radius: 0;
                background: #fffaf6;
                box-shadow: 0 14px 38px rgba(31,26,29,.07);
            }
            .th-fashion-page input,
            .th-fashion-page select,
            .th-fashion-page textarea,
            .th-fashion-page .search-field input,
            .th-fashion-page .search-field select,
            .th-fashion-page .th-news-field input,
            .th-fashion-page .th-news-field select {
                border-radius: 0 !important;
                border-color: #eadfda !important;
            }
            .th-fashion-page .th-cms-button,
            .th-fashion-page .search-button,
            .th-fashion-page .primary-button,
            .th-fashion-page .submit-button,
            .th-fashion-page .cta-link,
            .th-fashion-page .th-cms-page-link,
            .th-fashion-page .search-page-link {
                border-radius: 0 !important;
            }
            .th-fashion-page .th-cms-button.primary,
            .th-fashion-page .search-button,
            .th-fashion-page .primary-button,
            .th-fashion-page .submit-button,
            .th-fashion-page .cta-link.primary {
                background: #1f1a1d !important;
                color: #fff !important;
            }
            .th-fashion-page .search-reset,
            .th-fashion-page .ghost-button,
            .th-fashion-page .cta-link.secondary {
                border-color: #1f1a1d !important;
                color: #1f1a1d !important;
                background: transparent !important;
            }
            @media (max-width: 1100px) {
                .th-nav-products-inner { grid-template-columns: 1fr; }
                .th-nav-products-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (max-width: 760px) {
                .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { flex-direction: column; align-items: stretch; }
                .th-search { max-width: none; grid-template-columns: 1fr; }
                .th-main-nav-menu { overflow-x: auto; }
                .th-nav-link { padding: 0 14px; white-space: nowrap; }
                .th-nav-products-panel, .th-nav-simple-panel { display: none; }
                .th-nav-products-grid { grid-template-columns: 1fr; }
            }

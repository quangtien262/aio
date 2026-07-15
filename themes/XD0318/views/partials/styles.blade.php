<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { margin: 0; background: #fff; color: #142233; font-family: Arial, Helvetica, sans-serif; }
    a { color: inherit; text-decoration: none; }
    button, input, textarea, select { font: inherit; }

    .fg18-page { --fg18-orange: #f4512a; --fg18-navy: #142638; --fg18-deep: #102232; --fg18-muted: #5a6675; --fg18-line: #e6ebef; overflow: hidden; }
    .fg18-container { width: min(1440px, calc(100% - 48px)); margin: 0 auto; }
    .fg18-section { position: relative; padding: 82px 0; }
    .fg18-edit-block { position: absolute; z-index: 25; top: 14px; right: 14px; border: 0; border-radius: 4px; background: var(--fg18-orange); color: #fff; padding: 8px 12px; font-weight: 800; cursor: pointer; }
    .fg18-button { display: inline-flex; min-height: 50px; align-items: center; justify-content: center; gap: 10px; padding: 0 34px; border: 2px solid var(--fg18-orange); background: var(--fg18-orange); color: #fff; font-weight: 800; text-transform: uppercase; }
    .fg18-button--ghost { background: transparent; border-color: #fff; color: #fff; }
    .fg18-kicker { margin: 0 0 12px; color: var(--fg18-orange); font-size: 20px; font-weight: 800; }
    .fg18-title { margin: 0; color: #142233; font-size: clamp(36px, 4vw, 58px); font-weight: 900; line-height: 1.16; letter-spacing: .04em; text-transform: uppercase; }
    .fg18-center { text-align: center; }

    .fg18-header { position: relative; z-index: 40; background: #fff; }
    .fg18-header__inner { min-height: 100px; display: flex; align-items: center; justify-content: space-between; gap: 28px; }
    .fg18-brand, .fg18-footer-brand { display: inline-flex; align-items: center; gap: 12px; color: #102232; line-height: 1; }
    .fg18-brand img, .fg18-footer-brand img { max-width: 220px; max-height: 68px; object-fit: contain; }
    .fg18-brand strong, .fg18-footer-brand strong { display: block; font-size: 30px; font-weight: 900; letter-spacing: -.03em; }
    .fg18-brand small, .fg18-footer-brand small { display: block; margin-top: 4px; color: #b3b9c0; font-size: 11px; letter-spacing: .02em; }
    .fg18-brand__mark { position: relative; width: 35px; height: 48px; display: inline-block; border-radius: 26px 26px 26px 6px; background: var(--fg18-orange); transform: skew(-18deg); }
    .fg18-brand__mark::after { position: absolute; left: 12px; top: 11px; width: 12px; height: 25px; border-radius: 18px 18px 18px 4px; background: #fff; content: ""; }
    .fg18-nav { display: flex; align-items: center; gap: clamp(24px, 3vw, 46px); color: #00152d; font-size: 18px; font-weight: 650; white-space: nowrap; }
    .fg18-nav a { display: inline-flex; align-items: center; gap: 6px; }
    .fg18-nav a:hover { color: var(--fg18-orange); }
    .fg18-actions { display: flex; align-items: center; gap: 12px; }
    .fg18-actions button, .fg18-actions > a:not(.fg18-search) { border: 1px solid var(--fg18-line); background: #fff; color: #102232; padding: 9px 12px; font-size: 13px; font-weight: 800; cursor: pointer; text-transform: uppercase; }
    .fg18-actions button:hover, .fg18-actions > a:not(.fg18-search):hover { border-color: var(--fg18-orange); color: var(--fg18-orange); }
    .fg18-search { position: relative; width: 30px; height: 30px; display: inline-block; }
    .fg18-search::before { position: absolute; left: 4px; top: 4px; width: 14px; height: 14px; border: 2px solid #00152d; border-radius: 50%; content: ""; }
    .fg18-search::after { position: absolute; left: 19px; top: 19px; width: 10px; height: 2px; background: #00152d; content: ""; transform: rotate(45deg); transform-origin: left center; }
    .fg18-flag { display: grid; width: 30px; height: 20px; place-items: center; background: #e60012; color: #fff; font-size: 10px; font-weight: 900; }
    .fg18-flag--en { background: #305493; }
    .fg18-menu { display: none; }

    .fg18-hero { position: relative; background: #111; }
    .fg18-hero__viewport { position: relative; min-height: 570px; overflow: hidden; }
    .fg18-hero__slide { position: absolute; inset: 0; opacity: 0; transition: opacity .5s ease; }
    .fg18-hero__slide.is-active { opacity: 1; }
    .fg18-hero__slide img { width: 100%; height: 100%; object-fit: cover; }
    .fg18-hero__slide::after { position: absolute; inset: 0; background: rgba(13, 25, 38, .62); content: ""; }
    .fg18-hero__content { position: absolute; z-index: 2; left: max(24px, calc((100vw - 1440px) / 2)); top: 50%; width: min(560px, calc(100% - 72px)); transform: translateY(-50%); color: #fff; }
    .fg18-hero__content h1 { margin: 0 0 22px; font-size: clamp(38px, 4.2vw, 58px); font-weight: 900; line-height: 1.35; letter-spacing: .08em; text-transform: uppercase; }
    .fg18-hero__content p { margin: 0 0 28px; font-size: 20px; font-weight: 650; line-height: 1.55; }
    .fg18-hero__actions { display: flex; gap: 14px; flex-wrap: wrap; }
    .fg18-hero__nav { position: absolute; z-index: 4; top: 50%; width: 52px; height: 52px; border: 1px solid #fff; border-radius: 50%; background: transparent; color: #fff; font-size: 46px; line-height: 1; cursor: pointer; transform: translateY(-50%); }
    .fg18-hero__nav.prev { left: 18px; }
    .fg18-hero__nav.next { right: 18px; }

    .fg18-about__grid { display: grid; grid-template-columns: .95fr 1fr; gap: 42px; align-items: center; }
    .fg18-about__image img { width: 100%; min-height: 470px; object-fit: cover; }
    .fg18-about__copy p { margin: 26px 0 0; color: #273647; font-size: 17px; line-height: 1.75; }
    .fg18-about__copy .fg18-button { margin-top: 28px; }

    .fg18-video { position: relative; min-height: 315px; display: grid; place-items: center; background: var(--fg18-bg) center/cover; color: #fff; text-align: center; }
    .fg18-video::before { position: absolute; inset: 0; background: rgba(17, 40, 59, .82); content: ""; }
    .fg18-video__content { position: relative; z-index: 1; width: min(680px, calc(100% - 48px)); }
    .fg18-video h2 { margin: 10px 0 22px; color: #fff; font-size: clamp(34px, 4vw, 50px); font-weight: 900; line-height: 1.25; }
    .fg18-play { display: inline-grid; width: 72px; height: 72px; place-items: center; border-radius: 50%; background: var(--fg18-orange); color: #fff; box-shadow: 0 0 0 18px rgba(255,255,255,.08); font-weight: 900; }

    .fg18-services__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 42px 34px; margin-top: 44px; }
    .fg18-service-card img { width: 100%; aspect-ratio: 1.55; object-fit: cover; }
    .fg18-service-card h3 { margin: 24px 0 14px; color: #172637; font-size: 29px; font-weight: 800; line-height: 1.2; }
    .fg18-service-card p { margin: 0; color: #526071; font-size: 18px; line-height: 1.62; }
    .fg18-more { display: inline-flex; align-items: center; gap: 8px; margin-top: 20px; color: var(--fg18-orange); font-size: 17px; font-weight: 700; }
    .fg18-quote-card { min-height: 100%; display: grid; align-content: center; padding: 48px; background: linear-gradient(rgba(16,34,50,.9), rgba(16,34,50,.9)), var(--fg18-bg) center/cover; color: #fff; border-radius: 4px; }
    .fg18-quote-card h3 { margin: 0 0 34px; color: #fff; font-size: 29px; font-weight: 900; line-height: 1.28; text-transform: uppercase; }
    .fg18-quote-card input { width: 100%; height: 58px; border: 0; margin-bottom: 18px; padding: 0 18px; color: #172637; }

    .fg18-faq__grid { display: grid; grid-template-columns: .95fr 1fr; gap: 58px; align-items: start; }
    .fg18-faq__lead { margin: 28px 0 40px; color: #526071; font-size: 19px; line-height: 1.65; }
    .fg18-faq-list { border: 1px solid #d8dde2; }
    .fg18-faq-list details + details { border-top: 1px solid #d8dde2; }
    .fg18-faq-list summary { list-style: none; cursor: pointer; padding: 26px 54px 26px 28px; color: #2b3948; font-size: 24px; line-height: 1.35; position: relative; }
    .fg18-faq-list summary::-webkit-details-marker { display: none; }
    .fg18-faq-list summary::after { position: absolute; right: 26px; top: 50%; width: 22px; height: 22px; display: grid; place-items: center; border-radius: 50%; background: var(--fg18-orange); color: #fff; font-size: 16px; content: "+"; transform: translateY(-50%); }
    .fg18-faq-list details[open] summary::after { content: "-"; }
    .fg18-faq-list p { margin: 0; padding: 0 28px 24px; color: #526071; line-height: 1.65; }
    .fg18-faq-images { position: relative; min-height: 560px; }
    .fg18-faq-images img { position: absolute; object-fit: cover; box-shadow: 0 0 0 12px #fff; }
    .fg18-faq-images img:first-child { right: 0; top: 0; width: 74%; height: 330px; }
    .fg18-faq-images img:last-child { left: 0; bottom: 0; width: 72%; height: 310px; }

    .fg18-contact { position: relative; color: #fff; background: var(--fg18-bg) center/cover; }
    .fg18-contact::before { position: absolute; inset: 0; background: rgba(15, 36, 54, .86); content: ""; }
    .fg18-contact .fg18-container { position: relative; z-index: 1; }
    .fg18-contact__grid { display: grid; grid-template-columns: .9fr 1.1fr; gap: 58px; align-items: center; }
    .fg18-contact h2 { margin: 0 0 30px; color: #fff; font-size: clamp(48px, 5vw, 72px); font-weight: 900; line-height: 1.15; }
    .fg18-contact p { font-size: 20px; line-height: 1.6; }
    .fg18-form { display: grid; grid-template-columns: repeat(2, 1fr); gap: 22px; }
    .fg18-form input, .fg18-form textarea { width: 100%; border: 0; background: #fff; color: #172637; padding: 0 18px; font-size: 18px; }
    .fg18-form input { height: 58px; }
    .fg18-form textarea, .fg18-form .is-wide { grid-column: 1 / -1; }
    .fg18-form textarea { min-height: 148px; padding-top: 18px; resize: vertical; }
    .fg18-form button { grid-column: 1 / -1; min-height: 64px; border: 0; background: var(--fg18-orange); color: #fff; font-size: 22px; font-weight: 800; cursor: pointer; }

    .fg18-posts { background: #fff; }
    .fg18-posts__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 36px; margin-top: 42px; }
    .fg18-post img { width: 100%; aspect-ratio: 1.5; object-fit: cover; }
    .fg18-post__meta { margin: 24px 0 14px; color: #5b6572; font-size: 18px; }
    .fg18-post__meta span { color: var(--fg18-orange); }
    .fg18-post h3 { margin: 0 0 18px; color: #2b2f34; font-size: 29px; line-height: 1.24; }
    .fg18-post p { margin: 0; color: #526071; font-size: 18px; line-height: 1.65; }

    .fg18-footer { background: #102232; color: #fff; padding: 62px 0; }
    .fg18-footer__grid { display: grid; grid-template-columns: 1.1fr 1fr 1.35fr; gap: 72px; }
    .fg18-footer-brand { margin-bottom: 28px; color: #fff; }
    .fg18-footer-brand small { color: rgba(255,255,255,.65); }
    .fg18-footer p { color: rgba(255,255,255,.82); font-size: 18px; line-height: 1.65; }
    .fg18-footer h3 { margin: 0 0 28px; color: #fff; font-size: 29px; font-weight: 900; }
    .fg18-footer-list, .fg18-contact-list { margin: 0; padding: 0; list-style: none; }
    .fg18-footer-list li + li { margin-top: 16px; }
    .fg18-footer-list a { color: rgba(255,255,255,.82); font-size: 18px; }
    .fg18-footer-list a:hover { color: var(--fg18-orange); }
    .fg18-contact-list { margin-top: 22px; display: grid; gap: 18px; }
    .fg18-contact-list li { display: grid; grid-template-columns: 28px 1fr; gap: 12px; color: rgba(255,255,255,.9); font-size: 18px; line-height: 1.55; }
    .fg18-contact-list span { color: var(--fg18-orange); font-weight: 900; }
    .fg18-socials { display: flex; gap: 14px; margin-top: 28px; }
    .fg18-socials a { display: grid; width: 58px; height: 58px; place-items: center; border-radius: 50%; background: rgba(255,255,255,.08); color: #fff; font-weight: 900; }

    @media (max-width: 1180px) {
        .fg18-header__inner { min-height: 86px; }
        .fg18-nav { display: none; position: absolute; left: 24px; right: 24px; top: 86px; flex-direction: column; align-items: flex-start; padding: 22px; background: #fff; box-shadow: 0 18px 34px rgba(20,34,51,.16); }
        .fg18-nav.is-open { display: flex; }
        .fg18-menu { display: inline-grid; width: 38px; height: 38px; place-items: center; }
        .fg18-actions button:not(.fg18-menu) { display: none; }
        .fg18-about__grid, .fg18-faq__grid, .fg18-contact__grid { grid-template-columns: 1fr; }
        .fg18-services__grid, .fg18-posts__grid, .fg18-footer__grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 760px) {
        .fg18-container { width: min(100% - 32px, 1440px); }
        .fg18-section { padding: 58px 0; }
        .fg18-brand strong { font-size: 23px; }
        .fg18-actions { gap: 8px; }
        .fg18-flag { display: none; }
        .fg18-hero__viewport { min-height: 540px; }
        .fg18-hero__content h1 { font-size: 34px; }
        .fg18-hero__content p { font-size: 17px; }
        .fg18-title { font-size: 34px; }
        .fg18-services__grid, .fg18-posts__grid, .fg18-footer__grid, .fg18-form { grid-template-columns: 1fr; }
        .fg18-faq-images { min-height: 420px; }
        .fg18-faq-images img:first-child, .fg18-faq-images img:last-child { width: 86%; height: 240px; }
        .fg18-contact h2 { font-size: 42px; }
    }
</style>

<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { margin: 0; background: #fff; color: #0a1730; font-family: "Arial Narrow", Arial, Helvetica, sans-serif; }
    a { color: inherit; text-decoration: none; }
    button, input, textarea, select { font: inherit; }

    .bb14-page { --bb14-navy: #07142c; --bb14-ink: #111b31; --bb14-muted: #596173; --bb14-yellow: #ffb51b; --bb14-line: #e7e7e7; --bb14-soft: #f6f6f6; overflow: hidden; }
    .bb14-container { width: min(1480px, calc(100% - 48px)); margin: 0 auto; }
    .bb14-section { padding: 76px 0; }
    .bb14-section-title { max-width: 960px; margin: 0 auto 42px; text-align: center; }
    .bb14-section-title h2 { margin: 0; color: var(--bb14-ink); font-size: clamp(34px, 3.5vw, 52px); font-weight: 900; line-height: 1.05; text-transform: uppercase; }
    .bb14-section-title h2::after { display: block; width: 160px; height: 8px; margin: 14px auto 0; border-top: 5px solid var(--bb14-yellow); border-bottom: 3px solid transparent; content: ""; }
    .bb14-section-title p { margin: 18px auto 0; color: #26334b; font-size: 20px; font-weight: 700; line-height: 1.65; }
    .bb14-button { display: inline-flex; min-height: 54px; align-items: center; justify-content: center; gap: 12px; padding: 0 34px; border: 2px solid var(--bb14-yellow); background: var(--bb14-yellow); color: #07142c; font-weight: 900; text-transform: uppercase; }
    .bb14-button--ghost { background: transparent; color: #fff; border-color: #fff; }
    .bb14-edit-block { position: absolute; z-index: 20; top: 14px; right: 14px; border: 0; border-radius: 4px; background: var(--bb14-yellow); color: #07142c; padding: 8px 12px; font-weight: 900; cursor: pointer; }

    .bb14-topbar { background: var(--bb14-navy); color: #f7f8fb; font-size: 16px; font-weight: 800; }
    .bb14-topbar__inner { display: flex; min-height: 58px; align-items: center; justify-content: space-between; gap: 28px; }
    .bb14-topbar a, .bb14-topbar button { color: inherit; }
    .bb14-topbar button { border: 0; background: transparent; cursor: pointer; font-weight: 900; }
    .bb14-topbar__contact, .bb14-topbar__right { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bb14-topbar__contact span span, .bb14-topbar__contact a span { color: var(--bb14-yellow); margin-right: 6px; }
    .bb14-connect { color: var(--bb14-yellow); margin-left: 16px; }
    .bb14-flag { display: inline-grid; min-width: 32px; height: 22px; place-items: center; background: #e43; color: #fff; font-size: 11px; }
    .bb14-flag + .bb14-flag { background: #315f9f; }

    .bb14-navband { position: relative; min-height: 104px; background: #fff; z-index: 30; }
    .bb14-logo-panel { position: absolute; left: 0; top: 0; width: 34vw; min-width: 420px; height: 136px; background: var(--bb14-yellow); clip-path: polygon(0 0, 100% 0, 80% 100%, 0 100%); display: flex; justify-content: flex-end; align-items: center; padding-right: 150px; }
    .bb14-brand, .bb14-footer-brand { display: inline-flex; align-items: center; gap: 14px; color: #fff; line-height: 1; }
    .bb14-brand img, .bb14-footer-brand img { max-width: 230px; max-height: 72px; object-fit: contain; }
    .bb14-brand strong, .bb14-footer-brand strong { display: block; font-size: 38px; letter-spacing: 1px; }
    .bb14-brand em, .bb14-footer-brand em { display: block; font-style: normal; font-size: 31px; letter-spacing: 1px; }
    .bb14-brand__icon { display: grid; grid-template-columns: repeat(3, 22px); gap: 6px; }
    .bb14-brand__icon i { display: block; width: 22px; height: 16px; background: currentColor; }
    .bb14-navband__inner { min-height: 104px; display: flex; justify-content: flex-end; align-items: center; gap: 28px; padding-left: 360px; }
    .bb14-nav { display: flex; align-items: center; gap: clamp(18px, 1.8vw, 34px); color: #30394c; font-weight: 900; text-transform: uppercase; white-space: nowrap; }
    .bb14-nav a:first-child { color: var(--bb14-yellow); }
    .bb14-nav-actions { display: flex; align-items: center; gap: 22px; }
    .bb14-search { color: var(--bb14-yellow); font-size: 42px; line-height: 1; }
    .bb14-quote { display: inline-flex; align-items: center; gap: 14px; min-height: 54px; padding: 0 26px; border-radius: 4px; background: var(--bb14-yellow); color: #07142c; font-size: 18px; font-weight: 900; }
    .bb14-menu-toggle { display: none; }

    .bb14-hero { position: relative; background: var(--bb14-navy); }
    .bb14-hero__viewport { position: relative; min-height: 620px; overflow: hidden; }
    .bb14-hero__slide { position: absolute; inset: 0; opacity: 0; transition: opacity .45s ease; }
    .bb14-hero__slide.is-active { opacity: 1; }
    .bb14-hero__slide img { width: 100%; height: 100%; object-fit: cover; }
    .bb14-hero__overlay { position: absolute; inset: 0; background: rgba(7, 20, 44, .78); }
    .bb14-hero__content { position: absolute; inset: 0; display: grid; place-items: center; text-align: center; color: #fff; }
    .bb14-hero__content h1 { width: min(920px, 100%); margin: 0; font-size: clamp(38px, 4vw, 64px); font-weight: 900; line-height: 1.1; text-transform: uppercase; }
    .bb14-hero__content p { width: min(980px, 100%); margin: 24px auto 34px; font-size: 20px; font-weight: 800; line-height: 1.7; }
    .bb14-hero__actions { display: flex; justify-content: center; gap: 16px; flex-wrap: wrap; }
    .bb14-hero__nav { position: absolute; top: 50%; z-index: 5; width: 56px; height: 56px; border: 1px solid rgba(255,255,255,.75); background: rgba(255,255,255,.45); color: #07142c; font-size: 46px; line-height: 1; cursor: pointer; transform: translateY(-50%); }
    .bb14-hero__nav.prev { left: 16px; }
    .bb14-hero__nav.next { right: 16px; }

    .bb14-service-strip { position: relative; padding: 54px 0 66px; background: #fff; }
    .bb14-horizontal, .bb14-service-carousel { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(250px, 320px); gap: 22px; overflow-x: auto; overscroll-behavior-inline: contain; scroll-snap-type: x proximity; padding: 4px 0 18px; scrollbar-width: thin; }
    .bb14-category-card { min-height: 170px; display: grid; align-content: center; justify-items: center; gap: 12px; padding: 24px; border: 1px solid var(--bb14-line); background: #f5f5f5; text-align: center; scroll-snap-align: start; }
    .bb14-category-card img { width: 54px; height: 54px; object-fit: contain; }
    .bb14-category-card span { color: var(--bb14-yellow); font-size: 44px; }
    .bb14-category-card strong { color: var(--bb14-ink); font-size: 22px; text-transform: uppercase; }
    .bb14-category-card small { color: var(--bb14-muted); font-size: 15px; line-height: 1.4; }

    .bb14-about__grid { display: grid; grid-template-columns: 1.05fr .95fr; gap: 26px; align-items: stretch; }
    .bb14-about__cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 22px; }
    .bb14-about__cards article { display: grid; grid-template-columns: 76px 1fr; align-items: center; gap: 18px; min-height: 158px; padding: 28px; background: #f1f1f1; border: 2px solid transparent; }
    .bb14-about__cards article:nth-child(2n) { border-color: var(--bb14-yellow); background: #fff; }
    .bb14-about__cards span { color: var(--bb14-yellow); font-size: 52px; }
    .bb14-about__cards h3 { margin: 0 0 10px; font-size: 26px; text-transform: uppercase; }
    .bb14-about__cards p { margin: 0; color: #26334b; font-size: 18px; font-weight: 700; line-height: 1.55; }
    .bb14-about__stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 24px; }
    .bb14-about__stats article { min-height: 190px; display: grid; place-items: center; align-content: center; border: 1px solid var(--bb14-yellow); text-align: center; }
    .bb14-about__stats span { color: #07142c; font-size: 38px; }
    .bb14-about__stats strong { color: var(--bb14-yellow); font-size: 42px; line-height: 1.3; }
    .bb14-about__stats p { margin: 0; color: #111b31; font-size: 18px; font-weight: 900; text-transform: uppercase; }
    .bb14-about__image { width: 100%; height: 100%; min-height: 520px; object-fit: cover; }

    .bb14-projects { position: relative; background: var(--bb14-navy); color: #fff; }
    .bb14-projects::before { position: absolute; inset: 0 0 auto; height: 260px; background: linear-gradient(rgba(7,20,44,.78), rgba(7,20,44,.78)), var(--bb14-project-bg) center/cover; content: ""; }
    .bb14-projects__head { position: relative; z-index: 1; min-height: 260px; display: grid; align-content: center; justify-items: center; gap: 34px; text-align: center; }
    .bb14-projects__head h2 { margin: 0; font-size: clamp(34px, 3.4vw, 52px); text-transform: uppercase; }
    .bb14-project-tabs { display: flex; flex-wrap: wrap; justify-content: center; gap: 18px; font-size: 18px; font-weight: 900; text-transform: uppercase; }
    .bb14-project-tabs span { padding: 16px 30px; }
    .bb14-project-tabs .is-active { background: var(--bb14-yellow); color: #fff; }
    .bb14-project-grid { display: grid; grid-template-columns: repeat(4, 1fr); }
    .bb14-project-card { position: relative; min-height: 330px; overflow: hidden; background: #26334b; }
    .bb14-project-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .25s ease; }
    .bb14-project-card:hover img { transform: scale(1.04); }
    .bb14-project-card span { position: absolute; left: 0; right: 0; bottom: 0; padding: 18px; background: linear-gradient(transparent, rgba(7,20,44,.9)); color: #fff; font-size: 20px; font-weight: 900; text-transform: uppercase; }

    .bb14-why { position: relative; background: #fff; }
    .bb14-why::after { position: absolute; inset: 0; background: var(--bb14-why-bg) right center/60% auto no-repeat; opacity: .08; content: ""; pointer-events: none; }
    .bb14-why .bb14-container { position: relative; z-index: 1; }
    .bb14-why__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
    .bb14-why__grid article { display: grid; grid-template-columns: 72px 1fr; gap: 22px; min-height: 190px; padding: 34px; border: 1px solid var(--bb14-line); background: rgba(255,255,255,.76); }
    .bb14-why__grid span { color: var(--bb14-yellow); font-size: 42px; }
    .bb14-why__grid h3 { margin: 0 0 14px; font-size: 25px; text-transform: uppercase; }
    .bb14-why__grid p { margin: 0; color: #26334b; font-size: 18px; font-weight: 700; line-height: 1.65; }

    .bb14-service-carousel { grid-auto-columns: minmax(320px, 430px); }
    .bb14-service-card { scroll-snap-align: start; background: #fff; box-shadow: 0 14px 28px rgba(7,20,44,.08); }
    .bb14-service-card img { width: 100%; aspect-ratio: 1.34; object-fit: cover; }
    .bb14-service-card span { display: block; padding: 24px 28px 32px; }
    .bb14-service-card strong { display: block; min-height: 68px; color: var(--bb14-ink); font-size: 25px; line-height: 1.35; text-transform: uppercase; }
    .bb14-service-card small { display: -webkit-box; margin-top: 18px; overflow: hidden; color: #26334b; font-size: 18px; font-weight: 700; line-height: 1.65; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }

    .bb14-team__row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 28px; align-items: end; }
    .bb14-team-card { position: relative; min-height: 430px; display: grid; align-items: end; justify-items: center; }
    .bb14-team-card img { width: 100%; height: 390px; object-fit: contain; object-position: bottom center; }
    .bb14-team-card div { width: min(290px, 92%); margin-top: -42px; position: relative; z-index: 2; text-align: center; }
    .bb14-team-card span, .bb14-team-card strong { display: block; padding: 18px 12px; color: #fff; font-size: 20px; font-weight: 900; }
    .bb14-team-card span { background: var(--bb14-yellow); }
    .bb14-team-card strong { background: #000; }

    .bb14-footer { background: var(--bb14-navy); color: #fff; padding: 72px 0 28px; border-bottom: 2px solid var(--bb14-yellow); }
    .bb14-footer__top { display: grid; grid-template-columns: 1.15fr 1fr 1fr 1.1fr; gap: 52px; }
    .bb14-footer h3 { margin: 0 0 28px; font-size: 26px; text-transform: uppercase; }
    .bb14-footer h3::after { display: block; width: 92px; height: 4px; margin-top: 16px; background: var(--bb14-yellow); content: ""; }
    .bb14-footer p { color: #e6ebf5; font-size: 18px; font-weight: 700; line-height: 1.7; }
    .bb14-footer-gallery { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .bb14-footer-gallery img { width: 100%; aspect-ratio: 1.25; object-fit: cover; }
    .bb14-footer-list { margin: 0; padding: 0; list-style: none; }
    .bb14-footer-list li + li { margin-top: 22px; }
    .bb14-footer-list a { color: #fff; font-size: 18px; font-weight: 900; }
    .bb14-newsletter { display: flex; height: 52px; }
    .bb14-newsletter input { min-width: 0; flex: 1; border: 0; padding: 0 14px; }
    .bb14-newsletter button { width: 80px; border: 0; background: var(--bb14-yellow); color: #fff; font-size: 28px; cursor: pointer; }
    .bb14-share-title { margin-top: 34px !important; }
    .bb14-socials { display: flex; gap: 16px; }
    .bb14-socials a { display: grid; width: 42px; height: 42px; place-items: center; border: 1px solid rgba(255,255,255,.5); font-weight: 900; }
    .bb14-contact-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 48px; margin-top: 62px; }
    .bb14-contact-cards article { display: grid; grid-template-columns: 56px 1fr; column-gap: 20px; min-height: 136px; align-content: center; padding: 26px; border: 1px solid rgba(255,255,255,.35); }
    .bb14-contact-cards span { grid-row: span 2; color: var(--bb14-yellow); font-size: 36px; }
    .bb14-contact-cards strong { font-size: 22px; text-transform: uppercase; }
    .bb14-contact-cards p { margin: 12px 0 0; font-size: 17px; }
    .bb14-copyright { margin-top: 60px; text-align: center; color: #fff; font-size: 19px; font-weight: 900; }

    @media (max-width: 1180px) {
        .bb14-topbar__inner { align-items: flex-start; flex-direction: column; padding: 12px 0; }
        .bb14-logo-panel { position: relative; width: 100%; min-width: 0; height: auto; clip-path: none; justify-content: center; padding: 20px; }
        .bb14-navband__inner { min-height: auto; padding: 18px 0; justify-content: space-between; }
        .bb14-menu-toggle { display: inline-flex; border: 0; background: var(--bb14-yellow); color: #07142c; padding: 12px 18px; font-weight: 900; }
        .bb14-nav { display: none; position: absolute; left: 24px; right: 24px; top: calc(100% - 4px); flex-direction: column; align-items: flex-start; padding: 18px; background: #fff; box-shadow: 0 18px 30px rgba(7,20,44,.12); }
        .bb14-nav.is-open { display: flex; }
        .bb14-project-grid, .bb14-footer__top, .bb14-contact-cards { grid-template-columns: repeat(2, 1fr); }
        .bb14-about__grid, .bb14-why__grid { grid-template-columns: 1fr; }
        .bb14-team__row { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 760px) {
        .bb14-container { width: min(100% - 32px, 1480px); }
        .bb14-section { padding: 52px 0; }
        .bb14-nav-actions { gap: 12px; }
        .bb14-quote { display: none; }
        .bb14-hero__viewport { min-height: 560px; }
        .bb14-hero__content h1 { font-size: 36px; }
        .bb14-hero__content p { font-size: 17px; }
        .bb14-hero__nav { width: 44px; height: 44px; font-size: 34px; }
        .bb14-about__cards, .bb14-about__stats, .bb14-project-grid, .bb14-why__grid, .bb14-team__row, .bb14-footer__top, .bb14-contact-cards { grid-template-columns: 1fr; }
        .bb14-project-card { min-height: 250px; }
        .bb14-team-card { min-height: 360px; }
        .bb14-team-card img { height: 320px; }
    }
</style>


<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { margin: 0; background: #f2f7ec; color: #064c38; font-family: Arial, Helvetica, sans-serif; }
    a { color: inherit; text-decoration: none; }
    button, input, textarea, select { font: inherit; }

    .rx13-page { --rx13-deep: #005138; --rx13-green: #7bd615; --rx13-soft: #f2f7ec; --rx13-line: #dce8d3; --rx13-text: #064c38; --rx13-muted: #6c766e; overflow: hidden; background: var(--rx13-soft); }
    .rx13-container { width: min(1480px, calc(100% - 48px)); margin: 0 auto; }
    .rx13-section { position: relative; padding: 88px 0; }
    .rx13-edit-block { position: absolute; z-index: 25; top: 14px; right: 14px; border: 0; border-radius: 999px; background: var(--rx13-green); color: var(--rx13-deep); padding: 8px 14px; font-weight: 900; cursor: pointer; }
    .rx13-button { display: inline-flex; min-height: 58px; align-items: center; justify-content: center; gap: 12px; padding: 0 32px; border: 1px solid var(--rx13-green); border-radius: 999px; background: transparent; color: var(--rx13-deep); font-weight: 900; }
    .rx13-button--solid { background: var(--rx13-green); color: #fff; }
    .rx13-eyebrow { display: inline-flex; align-items: center; gap: 10px; margin: 0 0 18px; color: var(--rx13-deep); font-size: 17px; font-weight: 900; text-transform: uppercase; letter-spacing: .03em; }
    .rx13-eyebrow::before { width: 30px; height: 30px; border: 1px solid rgba(0,81,56,.5); border-radius: 50%; content: ""; }
    .rx13-title { margin: 0; color: var(--rx13-deep); font-size: clamp(42px, 4.4vw, 70px); font-weight: 900; line-height: 1.14; letter-spacing: -.04em; }
    .rx13-dot { width: 12px; height: 12px; border-radius: 50%; background: var(--rx13-green); }

    .rx13-header { background: var(--rx13-soft); }
    .rx13-header__inner { min-height: 116px; display: flex; align-items: center; justify-content: space-between; gap: 28px; padding: 0 24px; }
    .rx13-brand, .rx13-footer-brand { display: inline-flex; align-items: center; gap: 12px; color: var(--rx13-deep); }
    .rx13-brand img, .rx13-footer-brand img { max-width: 220px; max-height: 68px; object-fit: contain; }
    .rx13-brand strong, .rx13-footer-brand strong { font-size: 36px; font-weight: 900; letter-spacing: -.05em; }
    .rx13-brand__mark { position: relative; display: inline-block; width: 40px; height: 40px; border: 2px solid var(--rx13-deep); border-radius: 50%; }
    .rx13-brand__mark::before { position: absolute; left: 6px; top: 17px; width: 29px; height: 6px; border-radius: 99px; background: currentColor; content: ""; transform: rotate(-12deg); }
    .rx13-brand__mark::after { position: absolute; left: 9px; top: 9px; width: 20px; height: 12px; border-left: 2px solid currentColor; border-bottom: 2px solid currentColor; content: ""; transform: skew(-22deg) rotate(-12deg); }
    .rx13-nav { display: flex; align-items: center; gap: clamp(24px, 3vw, 52px); color: var(--rx13-deep); font-size: 18px; font-weight: 900; text-transform: uppercase; white-space: nowrap; }
    .rx13-nav a:first-child { color: var(--rx13-green); }
    .rx13-actions { display: flex; align-items: center; gap: 12px; }
    .rx13-search { position: relative; width: 58px; height: 58px; border: 0; border-radius: 50%; background: var(--rx13-deep); cursor: pointer; }
    .rx13-search::before { position: absolute; left: 18px; top: 17px; width: 14px; height: 14px; border: 3px solid #fff; border-radius: 50%; content: ""; }
    .rx13-search::after { position: absolute; left: 32px; top: 33px; width: 12px; height: 3px; background: #fff; content: ""; transform: rotate(45deg); }
    .rx13-auth { display: flex; gap: 8px; }
    .rx13-auth button, .rx13-auth a { border: 1px solid var(--rx13-line); border-radius: 999px; background: transparent; color: var(--rx13-deep); padding: 10px 14px; font-size: 13px; font-weight: 900; text-transform: uppercase; cursor: pointer; }
    .rx13-appointment { display: inline-flex; min-height: 58px; align-items: center; gap: 12px; padding: 0 30px; border-radius: 999px; background: var(--rx13-green); color: #fff; font-size: 18px; font-weight: 900; }
    .rx13-flag { display: grid; width: 26px; height: 26px; place-items: center; border-radius: 50%; background: #e60012; color: #fff; font-size: 10px; font-weight: 900; }
    .rx13-flag--en { background: #315b9b; }
    .rx13-menu { display: none; }

    .rx13-hero { padding: 18px 24px 42px; }
    .rx13-hero__viewport { position: relative; min-height: 780px; overflow: hidden; border-radius: 58px; background: var(--rx13-deep); }
    .rx13-hero__slide { position: absolute; inset: 0; opacity: 0; transition: opacity .45s ease; }
    .rx13-hero__slide.is-active { opacity: 1; }
    .rx13-hero__image { position: absolute; right: 7%; bottom: 0; z-index: 2; width: min(42vw, 660px); height: 92%; object-fit: contain; object-position: bottom center; }
    .rx13-hero__orb { position: absolute; right: 15%; bottom: 0; width: 500px; height: 500px; border-radius: 50%; background: var(--rx13-green); }
    .rx13-hero__content { position: absolute; z-index: 3; left: 4.3%; top: 50%; width: min(760px, 48%); transform: translateY(-50%); color: #fff; }
    .rx13-hero__content h1 { margin: 0; color: #fff; font-size: clamp(54px, 5vw, 86px); font-weight: 900; line-height: 1.18; letter-spacing: -.04em; }
    .rx13-hero__content p { margin: 32px 0 38px; color: rgba(255,255,255,.88); font-size: 21px; font-weight: 700; line-height: 1.6; }
    .rx13-hero__actions { display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
    .rx13-play { display: inline-grid; width: 58px; height: 58px; place-items: center; border-radius: 50%; background: var(--rx13-green); color: #fff; font-weight: 900; }
    .rx13-hero__watch { display: inline-flex; align-items: center; gap: 12px; color: #fff; font-size: 20px; font-weight: 900; }
    .rx13-hero__dots { position: absolute; z-index: 4; left: 50%; bottom: 36px; display: flex; gap: 10px; transform: translateX(-50%); }
    .rx13-hero__dots button { width: 14px; height: 14px; border: 0; border-radius: 50%; background: rgba(123,214,21,.35); cursor: pointer; }
    .rx13-hero__dots button.is-active { background: var(--rx13-green); box-shadow: 0 0 0 3px rgba(255,255,255,.65); }

    .rx13-benefits__grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 28px; }
    .rx13-benefit { min-height: 300px; padding: 32px; border: 1px solid var(--rx13-line); border-radius: 22px; background: rgba(255,255,255,.18); }
    .rx13-benefit__icon { display: grid; width: 118px; height: 118px; place-items: center; margin-bottom: 28px; border-radius: 50%; background: var(--rx13-green); color: #fff; font-size: 42px; font-weight: 900; }
    .rx13-benefit h3 { margin: 0 0 18px; color: var(--rx13-deep); font-size: 25px; line-height: 1.25; }
    .rx13-benefit p { margin: 0; color: #28382e; font-size: 18px; line-height: 1.55; }

    .rx13-about__grid { display: grid; grid-template-columns: .95fr 1fr; gap: 70px; align-items: center; }
    .rx13-about__media { position: relative; min-height: 730px; }
    .rx13-about__media img { position: absolute; object-fit: cover; border-radius: 18px; }
    .rx13-about__media img:first-child { left: 0; top: 0; width: 52%; height: 560px; box-shadow: 24px 24px 0 transparent, 0 0 0 1px var(--rx13-green); }
    .rx13-about__media img:nth-child(2) { right: 0; bottom: 0; width: 52%; height: 430px; }
    .rx13-years { position: absolute; right: 0; top: 0; width: 360px; min-height: 140px; display: flex; align-items: center; gap: 20px; padding: 28px 42px; border-radius: 18px; background: var(--rx13-green); color: #fff; }
    .rx13-years strong { font-size: 70px; line-height: 1; }
    .rx13-years span { font-size: 26px; font-weight: 800; line-height: 1.25; }
    .rx13-about__copy > p { color: #788178; font-size: 20px; line-height: 1.65; }
    .rx13-about-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 26px; margin: 44px 0; }
    .rx13-about-card { min-height: 170px; display: grid; grid-template-columns: 70px 1fr; gap: 20px; align-items: center; padding: 28px; border: 1px solid var(--rx13-line); border-radius: 18px; }
    .rx13-about-card span { display: grid; width: 62px; height: 62px; place-items: center; border-radius: 50%; background: var(--rx13-green); color: #fff; font-weight: 900; }
    .rx13-about-card h3 { margin: 0 0 10px; color: var(--rx13-deep); font-size: 25px; }
    .rx13-about-card ul { margin: 0; padding: 0; list-style: none; color: #566359; line-height: 1.8; }
    .rx13-hotline { display: inline-flex; align-items: center; gap: 16px; margin-left: 28px; color: var(--rx13-deep); font-weight: 900; }
    .rx13-hotline span { display: grid; width: 58px; height: 58px; place-items: center; border-radius: 50%; background: var(--rx13-green); color: #fff; }

    .rx13-featured { background: var(--rx13-deep); color: #fff; }
    .rx13-featured .rx13-title, .rx13-featured .rx13-eyebrow { color: #fff; }
    .rx13-featured-track { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(320px, 31%); gap: 30px; overflow-x: auto; padding: 42px 0 10px; scroll-snap-type: x proximity; scrollbar-width: thin; }
    .rx13-featured-card { position: relative; min-height: 520px; overflow: hidden; border-radius: 22px; background: #0b6248; scroll-snap-align: start; }
    .rx13-featured-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .25s ease; }
    .rx13-featured-card:hover img { transform: scale(1.04); }
    .rx13-featured-card strong { position: absolute; left: 24px; bottom: 24px; right: 24px; color: #fff; font-size: 32px; text-shadow: 0 3px 12px rgba(0,0,0,.35); }

    .rx13-services .rx13-title { text-align: center; }
    .rx13-services .rx13-eyebrow { display: flex; justify-content: center; }
    .rx13-services__grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px; margin-top: 52px; }
    .rx13-service-card { display: grid; grid-template-columns: 280px 1fr; gap: 30px; align-items: center; min-height: 300px; padding: 28px; border: 1px solid var(--rx13-line); border-radius: 22px; background: rgba(255,255,255,.22); }
    .rx13-service-card img { width: 100%; height: 230px; object-fit: cover; border-radius: 18px; }
    .rx13-service-card h3 { margin: 0 0 18px; color: var(--rx13-deep); font-size: 27px; line-height: 1.35; }
    .rx13-service-card p { margin: 0; color: #59645c; font-size: 18px; line-height: 1.55; }
    .rx13-open { display: grid; width: 58px; height: 58px; place-items: center; margin-top: 28px; border: 1px solid var(--rx13-green); border-radius: 14px; color: var(--rx13-green); font-size: 24px; font-weight: 900; }

    .rx13-promo__grid { display: grid; grid-template-columns: .8fr 1.2fr; gap: 56px; align-items: stretch; }
    .rx13-promo__image img { width: 100%; height: 100%; min-height: 620px; object-fit: cover; border-radius: 22px; }
    .rx13-promo-card { min-height: 300px; display: grid; grid-template-columns: 1fr .75fr; align-items: center; padding: 54px; border-radius: 22px; background: #fff; }
    .rx13-promo-card h3 { margin: 0 0 18px; color: var(--rx13-deep); font-size: 32px; line-height: 1.35; }
    .rx13-promo-card p { color: #59645c; font-size: 19px; line-height: 1.55; }
    .rx13-promo-card img { width: 100%; max-height: 260px; object-fit: contain; }
    .rx13-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 36px; padding: 54px; border-radius: 22px; background: var(--rx13-green); color: #fff; }
    .rx13-stats strong { display: block; font-size: 56px; line-height: 1; }
    .rx13-stats span { display: block; margin-top: 12px; font-size: 21px; font-weight: 800; }

    .rx13-process { background: #fff; }
    .rx13-process__grid { display: grid; grid-template-columns: 1fr .48fr; gap: 60px; align-items: center; }
    .rx13-step-list { display: grid; gap: 28px; margin-top: 46px; }
    .rx13-step { display: grid; grid-template-columns: 86px 1fr; gap: 28px; align-items: center; padding: 24px 28px; border: 1px solid var(--rx13-line); border-radius: 20px; background: #fff; }
    .rx13-step strong { display: grid; width: 72px; height: 72px; place-items: center; border-radius: 50%; background: var(--rx13-green); color: #fff; font-size: 26px; }
    .rx13-step h3 { margin: 0 0 8px; color: var(--rx13-deep); font-size: 26px; }
    .rx13-step p { margin: 0; color: #59645c; font-size: 18px; }
    .rx13-process__image img { width: 100%; min-height: 640px; object-fit: cover; border-radius: 22px; }

    .rx13-testimonials__grid { display: grid; grid-template-columns: .9fr 1fr; gap: 58px; align-items: center; }
    .rx13-testimonials__image img { width: 100%; height: 540px; object-fit: cover; border-radius: 22px; }
    .rx13-quote { min-height: 540px; display: grid; align-content: center; padding: 70px; border-radius: 22px; background: var(--rx13-green); color: #fff; }
    .rx13-quote p { margin: 0 0 46px; font-size: 28px; line-height: 1.65; }
    .rx13-person { display: flex; align-items: center; gap: 20px; }
    .rx13-person img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; }
    .rx13-person strong { display: block; font-size: 26px; }
    .rx13-person span { display: block; margin-top: 8px; font-size: 18px; }

    .rx13-posts__grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px; margin-top: 52px; }
    .rx13-post { overflow: hidden; border: 1px solid var(--rx13-line); border-radius: 20px; background: rgba(255,255,255,.35); }
    .rx13-post img { width: 100%; aspect-ratio: 1.28; object-fit: cover; }
    .rx13-post__body { padding: 24px; }
    .rx13-post h3 { margin: 0 0 16px; color: var(--rx13-deep); font-size: 23px; line-height: 1.35; }
    .rx13-post p { color: #7c837d; font-size: 17px; line-height: 1.55; }
    .rx13-meta { display: flex; gap: 20px; margin-bottom: 18px; color: #697269; font-size: 16px; }

    .rx13-footer { position: relative; overflow: hidden; background: var(--rx13-deep); color: #fff; padding: 92px 0; }
    .rx13-footer__map { position: absolute; inset: 0; opacity: .06; background: radial-gradient(circle at 40% 45%, #fff 0 1px, transparent 2px), linear-gradient(110deg, transparent 20%, rgba(255,255,255,.4) 21%, transparent 22%); background-size: 80px 80px, 180px 180px; }
    .rx13-footer__grid { position: relative; z-index: 1; display: grid; grid-template-columns: 1.25fr 1fr 1.1fr 1.1fr; gap: 56px; }
    .rx13-footer-brand { color: #fff; margin-bottom: 34px; }
    .rx13-footer p { color: rgba(255,255,255,.82); font-size: 19px; line-height: 1.65; }
    .rx13-footer h3 { margin: 0 0 28px; color: #fff; font-size: 28px; }
    .rx13-footer h3::after { display: block; width: 76px; height: 2px; margin-top: 14px; background: linear-gradient(90deg, var(--rx13-green), #fff); content: ""; }
    .rx13-footer-list { margin: 0; padding: 0; list-style: none; }
    .rx13-footer-list li { padding: 14px 0; border-bottom: 1px dashed rgba(255,255,255,.22); }
    .rx13-footer-videos { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
    .rx13-footer-videos a { position: relative; overflow: hidden; border-radius: 10px; }
    .rx13-footer-videos img { width: 100%; aspect-ratio: 1.45; object-fit: cover; }
    .rx13-footer-videos span { position: absolute; left: 50%; top: 50%; display: grid; width: 38px; height: 38px; place-items: center; border-radius: 50%; background: var(--rx13-green); transform: translate(-50%, -50%); }
    .rx13-newsletter input { width: 100%; height: 58px; border: 0; border-radius: 10px; padding: 0 18px; margin: 22px 0; }
    .rx13-newsletter button { min-height: 56px; border: 0; border-radius: 10px; padding: 0 32px; background: var(--rx13-green); color: #fff; font-size: 19px; font-weight: 900; cursor: pointer; }
    .rx13-socials { display: flex; gap: 16px; margin-top: 42px; }
    .rx13-socials a { display: grid; width: 48px; height: 48px; place-items: center; border: 1px dashed rgba(255,255,255,.55); border-radius: 50%; font-weight: 900; }

    @media (max-width: 1180px) {
        .rx13-header__inner { min-height: 90px; }
        .rx13-nav { display: none; position: absolute; left: 24px; right: 24px; top: 90px; z-index: 40; flex-direction: column; align-items: flex-start; padding: 22px; border-radius: 18px; background: #fff; box-shadow: 0 20px 40px rgba(0,81,56,.12); }
        .rx13-nav.is-open { display: flex; }
        .rx13-menu { display: inline-grid; width: 42px; height: 42px; place-items: center; border: 1px solid var(--rx13-line); border-radius: 50%; background: transparent; color: var(--rx13-deep); }
        .rx13-auth, .rx13-appointment { display: none; }
        .rx13-hero__content { width: min(700px, 58%); }
        .rx13-benefits__grid, .rx13-posts__grid, .rx13-footer__grid { grid-template-columns: repeat(2, 1fr); }
        .rx13-about__grid, .rx13-promo__grid, .rx13-process__grid, .rx13-testimonials__grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 760px) {
        .rx13-container { width: min(100% - 32px, 1480px); }
        .rx13-section { padding: 58px 0; }
        .rx13-brand strong { font-size: 28px; }
        .rx13-search, .rx13-flag { display: none; }
        .rx13-hero { padding: 12px 16px 28px; }
        .rx13-hero__viewport { min-height: 680px; border-radius: 28px; }
        .rx13-hero__content { left: 24px; right: 24px; top: 28px; width: auto; transform: none; }
        .rx13-hero__content h1 { font-size: 42px; }
        .rx13-hero__image { width: 84%; right: 0; height: 52%; }
        .rx13-hero__orb { width: 330px; height: 330px; right: -40px; }
        .rx13-benefits__grid, .rx13-about-cards, .rx13-services__grid, .rx13-stats, .rx13-posts__grid, .rx13-footer__grid { grid-template-columns: 1fr; }
        .rx13-about__media { min-height: 600px; }
        .rx13-about__media img:first-child, .rx13-about__media img:nth-child(2) { width: 72%; }
        .rx13-years { width: 250px; padding: 22px; }
        .rx13-service-card { grid-template-columns: 1fr; }
        .rx13-promo-card { grid-template-columns: 1fr; }
        .rx13-step { grid-template-columns: 1fr; }
        .rx13-quote { padding: 34px; }
    }
</style>

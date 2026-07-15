<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { margin: 0; background: #f2f6ec; color: #173d33; font-family: Arial, Helvetica, sans-serif; }
    a { color: inherit; text-decoration: none; }
    button, input, textarea { font: inherit; }

    .rx13-page { --rx-green: #00533d; --rx-deep: #003d2e; --rx-lime: #82d612; --rx-soft: #f2f6ec; --rx-ink: #064b39; --rx-text: #354940; --rx-line: #dbe7d6; --rx-white: #ffffff; overflow: hidden; }
    .rx13-container { width: min(1320px, calc(100% - 48px)); margin: 0 auto; }
    .rx13-section { position: relative; padding: 72px 0; }
    .rx13-kicker { display: flex; align-items: center; gap: 8px; margin: 0 0 12px; color: var(--rx-ink); font-size: 14px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .rx13-kicker::before { width: 26px; height: 2px; background: var(--rx-lime); content: ''; }
    .rx13-title { max-width: 760px; margin: 0; color: var(--rx-ink); font-size: clamp(32px, 4vw, 54px); line-height: 1.08; }
    .rx13-copy { color: #61736c; font-size: 16px; line-height: 1.7; }
    .rx13-button { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; gap: 10px; padding: 0 22px; border: 1px solid var(--rx-lime); border-radius: 999px; background: var(--rx-lime); color: #083e2e; font-weight: 700; transition: transform .2s ease, background .2s ease; }
    .rx13-button:hover { background: #9cea24; transform: translateY(-2px); }
    .rx13-button--ghost { background: transparent; color: inherit; }

    .rx13-header { position: relative; z-index: 20; background: var(--rx-soft); padding: 20px 0; }
    .rx13-header__inner { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 30px; }
    .rx13-brand { display: inline-flex; align-items: center; gap: 10px; color: var(--rx-green); font-size: 30px; line-height: 1; }
    .rx13-brand img { display: block; width: auto; max-width: 200px; max-height: 72px; object-fit: contain; }
    .rx13-brand__mark { display: grid; width: 38px; height: 38px; place-items: center; border: 2px solid currentColor; border-radius: 50%; font-size: 20px; font-weight: 800; }
    .rx13-nav { display: flex; align-items: center; justify-content: center; gap: clamp(18px, 2vw, 38px); }
    .rx13-nav a { color: var(--rx-ink); font-size: 14px; font-weight: 700; white-space: nowrap; }
    .rx13-nav a:hover { color: var(--rx-lime); }
    .rx13-header__actions { display: flex; align-items: center; gap: 10px; }
    .rx13-auth-link { border: 0; background: transparent; color: var(--rx-ink); cursor: pointer; font-size: 13px; font-weight: 700; white-space: nowrap; }
    .rx13-search { display: grid; width: 44px; height: 44px; place-items: center; border-radius: 50%; background: var(--rx-green); color: var(--rx-white); font-size: 24px; }
    .rx13-cta { display: inline-flex; align-items: center; gap: 10px; min-height: 46px; padding: 0 20px; border-radius: 999px; background: var(--rx-lime); color: var(--rx-white); font-size: 14px; font-weight: 700; white-space: nowrap; }
    .rx13-menu-toggle { display: none; }

    .rx13-hero-block { padding-top: 18px; }
    .rx13-hero { position: relative; width: min(2000px, calc(100% - 48px)); min-height: 600px; margin: 0 auto; overflow: hidden; border-radius: 42px; background: var(--rx-green); }
    .rx13-hero__slide { position: absolute; inset: 0; display: grid; grid-template-columns: minmax(0, 1fr) minmax(400px, .85fr); align-items: center; gap: 42px; padding: clamp(42px, 7vw, 90px); opacity: 0; pointer-events: none; transition: opacity .45s ease; }
    .rx13-hero__slide.is-active { opacity: 1; pointer-events: auto; }
    .rx13-hero__content { position: relative; z-index: 1; max-width: 700px; color: var(--rx-white); }
    .rx13-hero__eyebrow { margin: 0 0 18px; color: var(--rx-lime); font-size: 14px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .rx13-hero__title { max-width: 660px; margin: 0; font-size: clamp(42px, 5vw, 78px); line-height: 1.05; }
    .rx13-hero__description { max-width: 660px; margin: 28px 0 34px; color: #e0eee7; font-size: 17px; line-height: 1.65; }
    .rx13-hero__media { position: relative; align-self: stretch; min-height: 440px; }
    .rx13-hero__media::after { position: absolute; right: 8%; bottom: 0; width: 70%; height: 62%; border-radius: 50% 50% 0 0; background: var(--rx-lime); content: ''; }
    .rx13-hero__media img { position: absolute; right: 0; bottom: 0; z-index: 1; width: 100%; height: 100%; object-fit: cover; object-position: center top; mix-blend-mode: normal; }
    .rx13-hero__fallback { position: relative; z-index: 1; display: grid; height: 100%; min-height: 400px; place-items: center; color: rgba(255, 255, 255, .18); font-size: 120px; font-weight: 800; }
    .rx13-hero__dots { position: absolute; bottom: 28px; left: 50%; z-index: 4; display: flex; gap: 8px; transform: translateX(-50%); }
    .rx13-hero__dot { width: 9px; height: 9px; border: 0; border-radius: 50%; background: rgba(255,255,255,.35); cursor: pointer; }
    .rx13-hero__dot.is-active { background: var(--rx-lime); }

    .rx13-benefits-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
    .rx13-benefit { min-height: 280px; padding: 30px; border: 1px solid var(--rx-line); border-radius: 24px; background: transparent; }
    .rx13-benefit__icon { display: grid; width: 86px; height: 86px; margin-bottom: 30px; place-items: center; border-radius: 50%; background: var(--rx-lime); color: var(--rx-white); font-size: 22px; font-weight: 800; }
    .rx13-benefit h3 { margin: 0 0 16px; color: var(--rx-ink); font-size: 22px; }
    .rx13-benefit p { margin: 0; color: var(--rx-text); line-height: 1.65; }

    .rx13-about { display: grid; grid-template-columns: .92fr 1.08fr; gap: clamp(40px, 7vw, 110px); align-items: center; }
    .rx13-about__media { display: grid; grid-template-columns: 1fr .92fr; gap: 20px; align-items: start; }
    .rx13-about__media img { display: block; width: 100%; height: 420px; border-radius: 28px; object-fit: cover; }
    .rx13-about__media img:last-child { height: 300px; margin-top: 115px; }
    .rx13-about__years { position: absolute; padding: 28px; border-radius: 26px; background: var(--rx-lime); color: var(--rx-white); font-size: 16px; }
    .rx13-about__years strong { display: block; font-size: 54px; line-height: 1; }
    .rx13-about__content { max-width: 640px; }
    .rx13-about__cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 28px; }
    .rx13-about__card { padding: 22px; border: 1px solid var(--rx-line); border-radius: 18px; }
    .rx13-about__card h3 { margin: 0 0 12px; font-size: 18px; }
    .rx13-about__card ul { margin: 0; padding-left: 18px; color: #587169; line-height: 1.8; }

    .rx13-featured { background: var(--rx-green); }
    .rx13-featured .rx13-kicker, .rx13-featured .rx13-title { color: var(--rx-white); }
    .rx13-featured__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; margin-top: 40px; }
    .rx13-featured__card { overflow: hidden; border-radius: 24px; background: rgba(255,255,255,.06); }
    .rx13-featured__card img { display: block; width: 100%; aspect-ratio: 1.12; object-fit: cover; }
    .rx13-featured__card h3 { margin: 0; padding: 19px 20px; color: var(--rx-white); font-size: 22px; }

    .rx13-common { padding-top: 30px; }
    .rx13-common__head { max-width: 760px; margin: 0 auto 40px; text-align: center; }
    .rx13-common__head .rx13-kicker { justify-content: center; }
    .rx13-common__grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 22px; }
    .rx13-common__card { display: grid; grid-template-columns: 230px 1fr; gap: 26px; padding: 20px; border: 1px solid var(--rx-line); border-radius: 22px; background: rgba(255,255,255,.36); }
    .rx13-common__card img { width: 230px; height: 190px; border-radius: 16px; object-fit: cover; }
    .rx13-common__card h3 { margin: 9px 0 12px; color: var(--rx-ink); font-size: 22px; }
    .rx13-common__card p { display: -webkit-box; margin: 0; overflow: hidden; color: #5b6e67; line-height: 1.55; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
    .rx13-common__link { display: inline-flex; width: 44px; height: 44px; margin-top: 20px; align-items: center; justify-content: center; border: 1px solid #a7dd59; border-radius: 14px; color: var(--rx-lime); }

    .rx13-promo { display: grid; grid-template-columns: .7fr 1.3fr; gap: 32px; align-items: stretch; }
    .rx13-promo__image { min-height: 360px; overflow: hidden; border-radius: 26px; background: #dce8d2; }
    .rx13-promo__image img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .rx13-promo__content { display: grid; gap: 28px; }
    .rx13-promo__box { min-height: 190px; padding: 40px; border-radius: 26px; background: var(--rx-white); }
    .rx13-promo__box h2 { max-width: 600px; margin: 0 0 16px; color: var(--rx-ink); font-size: 30px; }
    .rx13-promo__stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; padding: 28px; border-radius: 26px; background: var(--rx-lime); color: var(--rx-white); }
    .rx13-promo__stats strong { display: block; font-size: clamp(30px, 3vw, 48px); }
    .rx13-promo__stats span { font-size: 14px; }

    .rx13-process { display: grid; grid-template-columns: 1.35fr .65fr; gap: 56px; align-items: center; }
    .rx13-process__steps { margin-top: 30px; }
    .rx13-process__step { display: grid; grid-template-columns: 76px 1fr; gap: 22px; align-items: center; padding: 20px 24px; border: 1px solid var(--rx-line); border-radius: 20px; background: rgba(255,255,255,.48); }
    .rx13-process__step + .rx13-process__step { margin-top: 18px; }
    .rx13-process__number { display: grid; width: 58px; height: 58px; place-items: center; border-radius: 50%; background: var(--rx-lime); color: var(--rx-white); font-size: 22px; font-weight: 800; }
    .rx13-process__step h3 { margin: 0 0 8px; color: var(--rx-ink); font-size: 20px; }
    .rx13-process__step p { margin: 0; color: #61736c; line-height: 1.55; }
    .rx13-process__image { min-height: 600px; overflow: hidden; border-radius: 26px; }
    .rx13-process__image img { width: 100%; height: 100%; object-fit: cover; }

    .rx13-testimonial { display: grid; grid-template-columns: 1fr 1fr; gap: 42px; align-items: center; }
    .rx13-testimonial__image { min-height: 390px; overflow: hidden; border-radius: 26px; }
    .rx13-testimonial__image img { width: 100%; height: 100%; object-fit: cover; }
    .rx13-quote { padding: clamp(32px, 5vw, 60px); border-radius: 26px; background: var(--rx-lime); color: var(--rx-white); }
    .rx13-quote p { margin: 0; font-size: clamp(22px, 2vw, 31px); line-height: 1.5; }
    .rx13-quote__person { display: flex; align-items: center; gap: 14px; margin-top: 32px; }
    .rx13-quote__person img { width: 58px; height: 58px; border-radius: 50%; object-fit: cover; }
    .rx13-quote__person strong, .rx13-quote__person span { display: block; }
    .rx13-quote__person span { margin-top: 4px; color: #e4f9c6; }

    .rx13-posts__head { display: flex; align-items: end; justify-content: space-between; gap: 30px; margin-bottom: 38px; }
    .rx13-posts__grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 22px; }
    .rx13-post { overflow: hidden; border: 1px solid var(--rx-line); border-radius: 20px; background: var(--rx-white); }
    .rx13-post img { display: block; width: 100%; aspect-ratio: 1.2; object-fit: cover; }
    .rx13-post__body { padding: 20px; }
    .rx13-post h3 { display: -webkit-box; min-height: 52px; margin: 0 0 14px; overflow: hidden; color: var(--rx-ink); font-size: 19px; line-height: 1.35; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
    .rx13-post p { display: -webkit-box; min-height: 66px; margin: 0 0 20px; overflow: hidden; color: #60716a; line-height: 1.55; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
    .rx13-post__meta { display: flex; gap: 14px; margin-bottom: 14px; color: #86be2c; font-size: 13px; }

    .rx13-footer { background: var(--rx-green); color: var(--rx-white); padding: 70px 0; }
    .rx13-footer__grid { display: grid; grid-template-columns: 1.25fr .9fr .9fr 1fr; gap: 48px; }
    .rx13-footer h3 { margin: 0 0 20px; color: var(--rx-white); font-size: 19px; }
    .rx13-footer p { color: #d3e6dc; line-height: 1.65; }
    .rx13-footer ul { margin: 0; padding: 0; list-style: none; }
    .rx13-footer li + li { margin-top: 13px; }
    .rx13-footer li a { color: #e1efe7; }
    .rx13-footer__brand { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 12px; color: var(--rx-white); font-size: 30px; }
    .rx13-footer__brand img { width: auto; max-width: 200px; max-height: 72px; object-fit: contain; }
    .rx13-footer__social { display: flex; gap: 10px; margin-top: 24px; }
    .rx13-footer__social a { display: grid; width: 38px; height: 38px; place-items: center; border: 1px solid rgba(255,255,255,.55); border-radius: 50%; font-size: 13px; font-weight: 700; }
    .rx13-footer__contact { color: #e3f1e9; line-height: 1.8; }
    .rx13-footer__form { display: flex; overflow: hidden; border: 1px solid rgba(255,255,255,.45); border-radius: 12px; }
    .rx13-footer__form input { min-width: 0; flex: 1; padding: 14px; border: 0; outline: 0; background: var(--rx-white); color: var(--rx-ink); }
    .rx13-footer__form button { border: 0; padding: 0 18px; background: var(--rx-lime); color: var(--rx-white); font-weight: 700; cursor: pointer; }

    @media (max-width: 1180px) {
        .rx13-header__inner { grid-template-columns: auto 1fr; }
        .rx13-menu-toggle { display: inline-flex; justify-self: end; border: 1px solid var(--rx-line); border-radius: 10px; background: var(--rx-white); color: var(--rx-ink); padding: 10px 14px; font-weight: 700; }
        .rx13-nav { display: none; grid-column: 1 / -1; flex-direction: column; align-items: flex-start; padding: 16px 0; }
        .rx13-nav.is-open { display: flex; }
        .rx13-header__actions { display: none; }
        .rx13-benefits-grid { grid-template-columns: repeat(2, 1fr); }
        .rx13-posts__grid { grid-template-columns: repeat(2, 1fr); }
        .rx13-footer__grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 760px) {
        .rx13-container { width: min(100% - 32px, 1320px); }
        .rx13-section { padding: 48px 0; }
        .rx13-hero { width: calc(100% - 32px); min-height: 720px; border-radius: 28px; }
        .rx13-hero__slide { grid-template-columns: 1fr; padding: 42px 28px; }
        .rx13-hero__media { min-height: 280px; }
        .rx13-hero__title { font-size: 42px; }
        .rx13-benefits-grid, .rx13-featured__grid, .rx13-common__grid, .rx13-posts__grid, .rx13-footer__grid { grid-template-columns: 1fr; }
        .rx13-about, .rx13-promo, .rx13-process, .rx13-testimonial { grid-template-columns: 1fr; }
        .rx13-about__media img { height: 310px; }
        .rx13-about__media img:last-child { height: 230px; margin-top: 70px; }
        .rx13-common__card { grid-template-columns: 1fr; }
        .rx13-common__card img { width: 100%; height: 220px; }
        .rx13-promo__stats { grid-template-columns: repeat(2, 1fr); }
        .rx13-process__image { min-height: 420px; order: -1; }
        .rx13-posts__head { display: block; }
        .rx13-posts__head .rx13-copy { margin-top: 16px; }
    }
</style>

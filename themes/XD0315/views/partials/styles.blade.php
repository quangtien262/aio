<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { margin: 0; background: #fff; color: #161616; font-family: "Arial Narrow", Impact, "Roboto Condensed", Arial, sans-serif; }
    a { color: inherit; text-decoration: none; }
    button, input, textarea, select { font: inherit; }

    .af15-page { --af15-orange: #f47c00; --af15-black: #111; --af15-text: #242424; --af15-muted: #686868; --af15-line: #e7e7e7; overflow: hidden; }
    .af15-container { width: min(1420px, calc(100% - 48px)); margin: 0 auto; }
    .af15-section { padding: 88px 0; }
    .af15-button { display: inline-flex; min-height: 58px; align-items: center; justify-content: center; gap: 10px; padding: 0 34px; border: 2px solid var(--af15-orange); background: var(--af15-orange); color: #fff; font-weight: 900; text-transform: uppercase; }
    .af15-button--ghost { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.65); color: #fff; }
    .af15-edit-block { position: absolute; z-index: 30; top: 14px; right: 14px; border: 0; border-radius: 2px; background: var(--af15-orange); color: #fff; padding: 8px 12px; font-weight: 900; cursor: pointer; }

    .af15-site-header { position: absolute; z-index: 50; left: 0; top: 0; width: 100%; padding: 26px 56px 0; color: #fff; }
    .af15-site-header__inner { display: grid; grid-template-columns: 150px 1fr; align-items: start; gap: 38px; }
    .af15-brand { display: inline-grid; place-items: center; width: 112px; height: 112px; border-radius: 50%; background: var(--af15-orange); color: #fff; text-align: center; font-weight: 900; line-height: 1; text-transform: uppercase; }
    .af15-brand img { width: 112px; height: 112px; object-fit: contain; border-radius: 50%; }
    .af15-brand__fallback strong { display: block; font-size: 18px; letter-spacing: 2px; }
    .af15-brand__fallback span { display: block; margin-top: 7px; font-size: 11px; letter-spacing: 1px; }
    .af15-nav-shell { min-height: 82px; display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 0 22px 0 34px; background: #030303; }
    .af15-nav { display: flex; align-items: center; gap: clamp(20px, 2vw, 38px); color: #fff; font-size: 17px; font-weight: 900; text-transform: uppercase; white-space: nowrap; }
    .af15-nav a:first-child { color: var(--af15-orange); }
    .af15-nav a { transition: color .2s ease; }
    .af15-nav a:hover { color: var(--af15-orange); }
    .af15-actions { display: flex; align-items: center; gap: 14px; color: #fff; }
    .af15-auth { display: flex; align-items: center; gap: 8px; color: #fff; font-size: 14px; font-weight: 900; text-transform: uppercase; }
    .af15-auth button { border: 1px solid rgba(244,124,0,.7); background: transparent; color: inherit; padding: 9px 12px; cursor: pointer; }
    .af15-auth button:hover { background: var(--af15-orange); }
    .af15-flag { display: grid; width: 26px; height: 18px; place-items: center; background: #e60012; font-size: 10px; line-height: 1; }
    .af15-flag--en { background: #183a75; }
    .af15-search, .af15-menu-toggle { display: grid; width: 48px; height: 48px; place-items: center; border: 2px solid var(--af15-orange); background: transparent; color: var(--af15-orange); font-size: 28px; line-height: 1; cursor: pointer; }
    .af15-menu-toggle { font-size: 34px; }

    .af15-hero { position: relative; min-height: 100vh; background: #111; }
    .af15-hero__viewport { position: relative; min-height: 100vh; overflow: hidden; }
    .af15-hero__slide { position: absolute; inset: 0; opacity: 0; transition: opacity .5s ease; }
    .af15-hero__slide.is-active { opacity: 1; }
    .af15-hero__slide img { width: 100%; height: 100%; object-fit: cover; }
    .af15-hero__slide::before { position: absolute; inset: 0; background: rgba(0,0,0,.42); content: ""; }
    .af15-hero__slide::after { position: absolute; inset: 0; background: linear-gradient(118deg, rgba(0,0,0,.22) 0 38%, rgba(255,255,255,.18) 38.2% 56%, rgba(0,0,0,.08) 56.2%); content: ""; }
    .af15-hero__content { position: absolute; z-index: 3; left: 50%; bottom: 22%; width: min(820px, calc(100% - 48px)); transform: translateX(-50%); text-align: center; color: #fff; }
    .af15-hero__content p { margin: 0 0 16px; font-size: clamp(26px, 3vw, 44px); font-weight: 900; line-height: 1.15; }
    .af15-hero__content h1 { margin: 0 0 30px; color: var(--af15-orange); font-size: clamp(48px, 5vw, 72px); font-weight: 900; line-height: 1; text-transform: uppercase; }
    .af15-hero__nav { position: absolute; z-index: 5; top: 50%; width: 54px; height: 54px; border: 0; border-radius: 50%; background: rgba(0,0,0,.42); color: #fff; font-size: 46px; line-height: 1; cursor: pointer; transform: translateY(-50%); }
    .af15-hero__nav.prev { left: 36px; }
    .af15-hero__nav.next { right: 36px; }
    .af15-hero__dots { position: absolute; z-index: 5; left: 0; right: 0; bottom: 42px; display: flex; justify-content: center; gap: 12px; }
    .af15-hero__dot { width: 14px; height: 14px; border: 2px solid #fff; border-radius: 50%; background: transparent; opacity: .55; }
    .af15-hero__dot.is-active { background: var(--af15-orange); opacity: 1; }

    .af15-title-row { display: flex; align-items: center; justify-content: center; gap: 32px; padding: 0 24px 54px; text-align: left; }
    .af15-title-row h2, .af15-center-title { margin: 0; color: var(--af15-orange); font-size: clamp(42px, 4vw, 62px); font-weight: 900; line-height: 1; text-transform: uppercase; }
    .af15-title-row p { max-width: 560px; margin: 0; padding-left: 30px; border-left: 4px solid var(--af15-orange); color: #666; font-size: 22px; font-style: italic; font-weight: 900; line-height: 1.6; }
    .af15-title-row--dark { padding-top: 82px; background: #151515; color: #fff; }
    .af15-title-row--dark p { color: #fff; }
    .af15-center-title { margin-bottom: 48px; text-align: center; }
    .af15-split-title { display: flex; align-items: center; justify-content: center; gap: 32px; padding: 70px 24px 54px; background: #fff; text-align: left; }
    .af15-split-title h2 { margin: 0; color: var(--af15-orange); font-size: clamp(42px, 4vw, 62px); font-weight: 900; line-height: 1; text-transform: uppercase; }
    .af15-split-title p { max-width: 560px; margin: 0; padding-left: 30px; border-left: 4px solid var(--af15-orange); color: #666; font-size: 22px; font-style: italic; font-weight: 900; line-height: 1.6; }

    .af15-classes { position: relative; background: #fff; }
    .af15-class-grid { display: grid; grid-template-columns: repeat(4, 1fr); }
    .af15-class-card { position: relative; min-height: 390px; overflow: hidden; background: #222; }
    .af15-class-card.is-wide { grid-column: span 2; }
    .af15-class-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .25s ease; }
    .af15-class-card:hover img { transform: scale(1.04); }
    .af15-class-card::after { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.08), rgba(0,0,0,.9)); content: ""; }
    .af15-class-card span { position: absolute; z-index: 2; left: 36px; right: 28px; bottom: 32px; color: #fff; }
    .af15-class-card strong { display: block; margin: 0 0 10px; color: var(--af15-orange); font-size: 34px; font-weight: 900; line-height: 1; text-transform: uppercase; }
    .af15-class-card small { display: block; max-width: 760px; margin: 0; font-size: 18px; font-weight: 900; line-height: 1.75; }

    .af15-about { background: #151515; color: #fff; }
    .af15-about__grid { display: grid; grid-template-columns: .95fr 1.05fr; gap: 54px; align-items: center; }
    .af15-about__photo { display: grid; gap: 24px; justify-items: center; }
    .af15-about__photo img { width: 100%; min-height: 620px; object-fit: cover; }
    .af15-about__copy h2 { margin: 0; color: var(--af15-orange); font-size: clamp(44px, 4vw, 66px); font-weight: 900; line-height: 1.1; text-transform: uppercase; }
    .af15-about__copy em { display: block; margin: 14px 0 28px; padding-left: 28px; border-left: 4px solid var(--af15-orange); color: #fff; font-size: 22px; font-weight: 900; line-height: 1.6; }
    .af15-about__copy p { margin: 0 0 30px; color: #e8e8e8; font-size: 20px; font-weight: 900; line-height: 1.7; }
    .af15-video { position: relative; display: block; min-height: 430px; overflow: hidden; background: #333; }
    .af15-video img { width: 100%; height: 100%; min-height: 430px; object-fit: cover; }
    .af15-video span { position: absolute; left: 50%; top: 50%; display: grid; width: 94px; height: 94px; place-items: center; border: 7px solid #fff; border-radius: 50%; color: #fff; font-size: 48px; transform: translate(-50%, -50%); }

    .af15-team { background: #fff; }
    .af15-team__row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px; }
    .af15-team-card { position: relative; min-height: 500px; overflow: hidden; background: #222; }
    .af15-team-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .25s ease; }
    .af15-team-card:hover img { transform: scale(1.04); }
    .af15-team-card::after { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.05), rgba(0,0,0,.72)); content: ""; }
    .af15-team-card div { position: absolute; z-index: 2; left: 0; right: 0; bottom: 86px; color: #fff; text-align: center; }
    .af15-team-card strong { display: block; font-size: 32px; font-weight: 900; line-height: 1.1; text-transform: uppercase; }
    .af15-team-card span { display: block; margin-top: 22px; font-size: 18px; font-weight: 900; }

    .af15-clubs { position: relative; background: #151515; color: #fff; }
    .af15-club-grid { display: grid; grid-template-columns: repeat(6, 1fr); }
    .af15-club-card { position: relative; min-height: 365px; overflow: hidden; background: #222; }
    .af15-club-card.is-wide { grid-column: span 3; min-height: 440px; }
    .af15-club-card:not(.is-wide) { grid-column: span 2; }
    .af15-club-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .25s ease; }
    .af15-club-card:hover img { transform: scale(1.04); }
    .af15-club-card strong { position: absolute; left: 36px; bottom: 36px; max-width: 520px; padding: 18px 28px; background: var(--af15-orange); color: #fff; font-size: 30px; font-weight: 900; line-height: 1.25; text-transform: uppercase; box-shadow: -6px 0 0 #050505; }

    .af15-news { background: #fff; }
    .af15-news-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 34px; }
    .af15-news-card img { width: 100%; aspect-ratio: 1.45; object-fit: cover; }
    .af15-news-card h3 { margin: 28px 0 18px; color: #303030; font-size: 28px; font-weight: 900; line-height: 1.25; }
    .af15-news-card p { min-height: 92px; margin: 0 0 34px; color: #777; font-size: 20px; font-weight: 900; line-height: 1.65; }
    .af15-news-card a { display: inline-flex; min-width: 142px; min-height: 54px; align-items: center; justify-content: center; border-left: 1px solid #111; border-bottom: 1px solid #111; color: #111; font-size: 18px; font-weight: 900; text-transform: uppercase; }

    .af15-stories { position: relative; margin: 30px auto 0; background: #111; color: #fff; }
    .af15-stories::before { position: absolute; inset: 0; background: linear-gradient(rgba(0,0,0,.78), rgba(0,0,0,.84)), var(--af15-story-bg) center/cover; content: ""; }
    .af15-stories > * { position: relative; z-index: 1; }
    .af15-story-track { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(520px, 1fr); gap: 34px; overflow-x: auto; padding: 0 0 72px; scroll-snap-type: x proximity; scrollbar-width: thin; }
    .af15-story-card { display: grid; grid-template-columns: 1fr 1fr; gap: 34px; align-items: center; min-height: 390px; padding: 26px; background: #fff; color: #333; scroll-snap-align: start; }
    .af15-story-card img { width: 100%; height: 310px; object-fit: cover; }
    .af15-story-card h3 { margin: 0 0 18px; color: var(--af15-orange); font-size: 32px; font-weight: 900; text-transform: uppercase; }
    .af15-story-tabs { display: flex; gap: 12px; margin-bottom: 18px; }
    .af15-story-tabs span { padding: 12px 20px; background: #333; color: #fff; font-weight: 900; }
    .af15-story-tabs span:last-child { background: var(--af15-orange); }
    .af15-story-stats { display: grid; gap: 14px; color: #555; font-size: 18px; font-weight: 900; }
    .af15-story-stats div { display: grid; grid-template-columns: 1fr auto auto; gap: 22px; padding-bottom: 12px; border-bottom: 1px solid #eee; }

    .af15-footer { background: #151515; color: #fff; padding: 86px 0 64px; }
    .af15-footer__grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 48px; align-items: start; }
    .af15-footer-logo, .af15-footer-brand { display: grid; width: 142px; height: 142px; place-items: center; margin-bottom: 34px; border-radius: 50%; background: var(--af15-orange); color: #fff; font-weight: 900; text-align: center; text-transform: uppercase; }
    .af15-footer-logo img, .af15-footer-brand img { width: 142px; height: 142px; object-fit: contain; border-radius: 50%; }
    .af15-logo-mark strong, .af15-logo-mark em { display: block; font-style: normal; line-height: 1.1; }
    .af15-logo-mark i { display: block; margin: 8px 0; font-style: normal; font-size: 28px; }
    .af15-footer h3 { margin: 0 0 28px; color: var(--af15-orange); font-size: 34px; font-weight: 900; text-transform: uppercase; }
    .af15-footer p, .af15-footer li { color: #eee; font-size: 20px; font-weight: 900; line-height: 1.6; }
    .af15-footer-gallery { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
    .af15-footer-gallery img { width: 100%; aspect-ratio: 1; object-fit: cover; }
    .af15-footer-map, .af15-map { min-height: 260px; background: linear-gradient(135deg, #8dd7df, #ccefd7); display: grid; place-items: center; color: #286; font-weight: 900; }
    .af15-outline { display: inline-flex; min-height: 54px; align-items: center; justify-content: center; margin-top: 28px; padding: 0 28px; border-left: 1px solid #fff; border-bottom: 1px solid #fff; color: #fff; font-size: 18px; font-weight: 900; text-transform: uppercase; }
    .af15-socials { display: flex; gap: 18px; margin: 34px 0; }
    .af15-socials a { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 50%; background: #fff; color: var(--af15-orange); font-weight: 900; }

    @media (max-width: 1220px) {
        .af15-site-header { position: relative; padding: 18px 24px; background: #050505; }
        .af15-site-header__inner { grid-template-columns: 96px 1fr; align-items: center; }
        .af15-brand, .af15-brand img { width: 84px; height: 84px; }
        .af15-nav-shell { min-height: 70px; padding: 0 14px; }
        .af15-nav { display: none; position: absolute; left: 24px; right: 24px; top: 118px; flex-direction: column; align-items: flex-start; gap: 18px; padding: 22px; background: #030303; }
        .af15-nav.is-open { display: flex; }
        .af15-auth { display: none; }
        .af15-class-grid, .af15-team__row, .af15-footer__grid { grid-template-columns: repeat(2, 1fr); }
        .af15-class-card, .af15-class-card.is-wide { grid-column: span 1; }
        .af15-about__grid, .af15-news-grid { grid-template-columns: 1fr; }
        .af15-club-card.is-wide, .af15-club-card:not(.is-wide) { grid-column: span 3; }
    }

    @media (max-width: 760px) {
        .af15-container { width: min(100% - 32px, 1420px); }
        .af15-section { padding: 58px 0; }
        .af15-site-header { padding: 14px 16px; }
        .af15-site-header__inner { grid-template-columns: 72px 1fr; gap: 14px; }
        .af15-brand, .af15-brand img { width: 68px; height: 68px; }
        .af15-nav-shell { justify-content: flex-end; gap: 10px; }
        .af15-actions { gap: 8px; }
        .af15-flag, .af15-search { display: none; }
        .af15-menu-toggle { width: 44px; height: 44px; }
        .af15-nav { top: 92px; left: 16px; right: 16px; }
        .af15-hero, .af15-hero__viewport { min-height: 620px; }
        .af15-hero__content { bottom: 15%; }
        .af15-hero__content h1 { font-size: 42px; }
        .af15-hero__content p { font-size: 24px; }
        .af15-hero__nav { width: 44px; height: 44px; font-size: 34px; }
        .af15-hero__nav.prev { left: 14px; }
        .af15-hero__nav.next { right: 14px; }
        .af15-title-row, .af15-split-title { display: block; padding-bottom: 34px; text-align: center; }
        .af15-title-row p, .af15-split-title p { margin-top: 20px; padding-left: 0; border-left: 0; }
        .af15-class-grid, .af15-team__row, .af15-footer__grid { grid-template-columns: 1fr; }
        .af15-class-card { min-height: 330px; }
        .af15-about__photo img { min-height: 420px; }
        .af15-video, .af15-video img { min-height: 300px; }
        .af15-club-grid { grid-template-columns: 1fr; }
        .af15-club-card.is-wide, .af15-club-card:not(.is-wide) { grid-column: span 1; min-height: 320px; }
        .af15-story-track { grid-auto-columns: minmax(300px, 88vw); }
        .af15-story-card { grid-template-columns: 1fr; }
    }
</style>

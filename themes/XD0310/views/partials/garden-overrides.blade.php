<style>
    :root {
        --xd10-green: #2f6b3b;
        --xd10-green-dark: #173d25;
        --xd10-green-soft: #dfead8;
        --xd10-lime: #a8c66c;
        --xd10-cream: #f6f4ea;
        --xd10-sand: #e8dfcb;
        --xd10-ink: #213127;
        --xd10-muted: #667269;
        --xd10-white: #fff;
        --wide: min(1220px, calc(100% - 48px));
    }

    html { scroll-behavior: smooth; }
    body,
    .xd5-page,
    .xd5-page button,
    .xd5-page input,
    .xd5-page textarea {
        font-family: "Segoe UI", "Noto Sans", Arial, sans-serif;
    }
    body {
        overflow-x: hidden;
        background: var(--xd10-cream);
        color: var(--xd10-ink);
    }
    .xd5-page { color: var(--xd10-ink); }
    .xd5-container,
    .xd-container { width: var(--wide); margin-inline: auto; }

    .xd5-header {
        position: absolute;
        z-index: 20;
        width: 100%;
        color: var(--xd10-white);
        background: linear-gradient(180deg, rgba(13, 39, 23, .94), rgba(13, 39, 23, .72));
        backdrop-filter: blur(10px);
    }
    .xd5-utility { border-color: rgba(255, 255, 255, .16); }
    .xd5-utility > div { padding-block: 10px; opacity: .88; }
    .xd5-nav-wrap { min-height: 84px; }
    .xd5-brand {
        min-width: max-content;
        color: var(--xd10-white);
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -.04em;
    }
    .xd5-brand > span { color: inherit; font-size: 24px; }
    .xd5-brand::before {
        display: grid;
        width: 36px;
        height: 36px;
        border-radius: 50% 50% 50% 8px;
        place-items: center;
        background: var(--xd10-lime);
        color: var(--xd10-green-dark);
        content: "✦";
        transform: rotate(-8deg);
    }
    .xd5-brand img + span,
    .xd5-brand img ~ span { display: none; }
    .xd5-brand img {
        max-width: 210px;
        max-height: 58px;
        filter: brightness(0) invert(1);
    }
    .xd5-nav-wrap nav { gap: 27px; font-size: 13px; letter-spacing: .04em; }
    .xd5-nav-wrap nav a {
        position: relative;
        padding-block: 12px;
    }
    .xd5-nav-wrap nav a::after {
        position: absolute;
        right: 50%;
        bottom: 3px;
        left: 50%;
        height: 2px;
        background: var(--xd10-lime);
        content: "";
        transition: .25s ease;
    }
    .xd5-nav-wrap nav a:hover::after { right: 0; left: 0; }
    .xd5-hotline {
        padding: 11px 17px;
        border: 1px solid rgba(255, 255, 255, .3);
        border-radius: 999px;
        font-size: 15px;
    }

    .xd5-hero { min-height: 760px; background: var(--xd10-green-dark); }
    .xd5-hero__slide { isolation: isolate; transition: opacity .8s ease; }
    .xd5-hero__slide > img {
        position: absolute;
        inset: 0;
        z-index: -2;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transform: scale(1.02);
        transition: transform 7s ease;
    }
    .xd5-hero__slide.is-active > img { transform: scale(1.08); }
    .xd5-veil {
        z-index: -1;
        background:
            linear-gradient(90deg, rgba(12, 41, 23, .89) 0%, rgba(12, 41, 23, .63) 46%, rgba(12, 41, 23, .14) 78%),
            linear-gradient(0deg, rgba(12, 31, 19, .4), transparent 45%);
    }
    .xd5-hero-copy { min-height: 760px; }
    .xd5-hero-copy > div {
        max-width: 680px;
        margin-top: 105px;
        padding-left: 28px;
        border-left: 3px solid var(--xd10-lime);
    }
    .xd5-kicker,
    .xd5-eyebrow,
    .xd-kicker {
        color: var(--xd10-lime);
        font-size: 13px;
        font-weight: 800;
        letter-spacing: .15em;
        text-transform: uppercase;
    }
    .xd5-hero h1 {
        max-width: 660px;
        margin-block: 18px;
        color: var(--xd10-white);
        font-size: clamp(46px, 6vw, 78px);
        font-weight: 700;
        letter-spacing: -.055em;
        line-height: .98;
    }
    .xd5-hero p:not(.xd5-kicker) {
        max-width: 590px;
        color: rgba(255, 255, 255, .82);
        font-size: 18px;
        line-height: 1.75;
    }
    .xd5-btn {
        align-items: center;
        min-height: 52px;
        padding: 13px 25px;
        border: 0;
        border-radius: 999px;
        background: var(--xd10-lime);
        color: var(--xd10-green-dark);
        font-weight: 800;
        transition: transform .25s ease, background .25s ease;
    }
    .xd5-btn:hover { background: #badb78; transform: translateY(-2px); }

    .xd5-section { padding-block: 100px; }
    .xd5-title,
    .xd-section-title h2 {
        max-width: 760px;
        margin-block: 12px 18px;
        color: var(--xd10-ink);
        font-size: clamp(36px, 4.2vw, 58px);
        font-weight: 700;
        letter-spacing: -.045em;
        line-height: 1.08;
    }
    .xd5-section p { color: var(--xd10-muted); }

    .xd5-services {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        margin-top: 46px;
    }
    .xd5-service {
        min-height: 430px;
        grid-template-rows: auto auto 1fr;
        overflow: hidden;
        border: 0;
        border-radius: 26px 26px 8px 26px;
        padding: 0;
        background: var(--xd10-white);
        box-shadow: 0 18px 50px rgba(33, 49, 39, .09);
        transition: transform .3s ease, box-shadow .3s ease;
    }
    .xd5-service:hover {
        box-shadow: 0 24px 65px rgba(33, 49, 39, .15);
        transform: translateY(-8px);
    }
    .xd5-service h3 { margin: 25px 24px 0; color: var(--xd10-green-dark); font-size: 21px; }
    .xd5-service p { margin: 12px 24px 22px; }
    .xd5-service img {
        order: -1;
        width: 100%;
        height: 220px;
        border-radius: 0 0 55px;
        object-fit: cover;
    }

    .xd5-about { grid-template-columns: 1.08fr .92fr; gap: 90px; }
    .xd5-about-media { min-height: 560px; }
    .xd5-about-media::before {
        position: absolute;
        inset: 42px 12% 0 0;
        border-radius: 120px 12px 12px;
        background: var(--xd10-green-soft);
        content: "";
    }
    .xd5-about-media img {
        position: relative;
        width: 88%;
        height: 520px;
        border-radius: 120px 12px 12px;
        object-fit: cover;
    }
    .xd5-about-badge {
        right: 0;
        bottom: 0;
        width: 220px;
        border: 9px solid var(--xd10-cream);
        border-radius: 50%;
        padding: 37px 20px;
        background: var(--xd10-green-dark);
        text-align: center;
    }
    .xd5-about-badge b { color: var(--xd10-lime); font-size: 52px; }
    .xd5-progress { overflow: hidden; height: 9px; border-radius: 999px; background: var(--xd10-green-soft); }
    .xd5-progress i { border-radius: inherit; background: var(--xd10-green); }

    .xd5-benefits {
        position: relative;
        overflow: hidden;
        background: var(--xd10-green-dark);
        color: var(--xd10-white);
    }
    .xd5-benefits::before {
        position: absolute;
        top: -180px;
        right: -160px;
        width: 480px;
        height: 480px;
        border: 1px solid rgba(168, 198, 108, .22);
        border-radius: 50%;
        content: "";
        box-shadow: 0 0 0 80px rgba(168, 198, 108, .05), 0 0 0 160px rgba(168, 198, 108, .035);
    }
    .xd5-benefits .xd5-title,
    .xd5-benefits p { color: var(--xd10-white); }
    .xd5-benefit-grid { position: relative; grid-template-columns: .95fr 1.05fr; gap: 80px; }
    .xd5-benefit-grid > img {
        width: 100%;
        height: 550px;
        border-radius: 10px 120px 10px 10px;
        object-fit: cover;
    }
    .xd5-benefit-list { gap: 16px; margin-top: 45px; }
    .xd5-benefit-list b {
        min-height: 72px;
        border: 1px solid rgba(255, 255, 255, .13);
        border-radius: 14px;
        padding: 12px;
    }
    .xd5-benefit-list b::before {
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        border-radius: 50% 50% 50% 9px;
        background: var(--xd10-lime);
        color: var(--xd10-green-dark);
        content: "✓";
    }

    .xd-section { padding-block: 100px; background: var(--xd10-cream); }
    .xd-section-title { margin-bottom: 42px; }
    .xd-projects {
        display: grid;
        width: var(--wide);
        margin-inline: auto;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .xd-project-card {
        position: relative;
        min-width: 0;
        height: 440px;
        overflow: hidden;
        border-radius: 8px 50px 8px 8px;
    }
    .xd-project-card:nth-child(even) { margin-top: 38px; }
    .xd-project-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s ease; }
    .xd-project-card:hover img { transform: scale(1.07); }
    .xd-project-caption {
        position: absolute;
        inset: auto 0 0;
        padding: 60px 24px 24px;
        color: var(--xd10-white);
        background: linear-gradient(transparent, rgba(10, 35, 19, .92));
    }
    .xd-project-caption small { color: var(--xd10-lime); }
    .xd-project-caption h3 { margin: 8px 0 0; font-size: 22px; }
    .xd-row-nav,
    .xd-row-dots { display: none; }

    .xd5-testimonial { background: var(--xd10-sand); }
    .xd5-testimonial-grid { grid-template-columns: 1.1fr repeat(3, minmax(0, 1fr)); gap: 18px; }
    .xd5-quote {
        position: relative;
        border-radius: 26px 26px 8px 26px;
        padding: 34px;
        box-shadow: none;
    }
    .xd5-quote::before {
        display: block;
        margin-bottom: 18px;
        color: var(--xd10-green);
        content: "“";
        font-family: Georgia, serif;
        font-size: 64px;
        line-height: .5;
    }
    .xd5-quote b { display: block; margin-top: 22px; color: var(--xd10-green-dark); }

    .xd5-team-head { max-width: 760px; margin-inline: auto; text-align: center; }
    .xd5-team { gap: 22px; }
    .xd5-team article {
        border-radius: 120px 120px 12px 12px;
        background: var(--xd10-green-dark);
    }
    .xd5-team img { height: 430px; border-radius: inherit; }
    .xd5-team h3 { color: var(--xd10-white); }
    .xd5-team p { color: var(--xd10-lime); }

    .xd5-contact {
        position: relative;
        overflow: hidden;
        background:
            linear-gradient(90deg, rgba(16, 55, 30, .97), rgba(16, 55, 30, .86)),
            url("https://images.unsplash.com/photo-1416879595882-3373a0480b5b?auto=format&fit=crop&w=1800&q=85") center/cover;
    }
    .xd5-contact .xd5-title,
    .xd5-contact p { color: var(--xd10-white); }
    .xd5-contact-grid { grid-template-columns: .9fr 1.1fr; gap: 90px; }
    .xd5-contact-info b { color: var(--xd10-lime); }
    .xd5-contact-card {
        border-radius: 40px 8px 40px 8px;
        padding: 48px;
        background: var(--xd10-cream);
    }
    .xd5-contact-card h2 { color: var(--xd10-green-dark); }
    .xd5-contact-card input,
    .xd5-contact-card textarea {
        border: 1px solid #dce4d9;
        border-radius: 10px;
        background: var(--xd10-white);
        outline: none;
    }
    .xd5-contact-card input:focus,
    .xd5-contact-card textarea:focus { border-color: var(--xd10-green); }

    .xd5-posts { gap: 22px; }
    .xd5-post {
        overflow: hidden;
        border-radius: 24px 24px 8px 24px;
        background: var(--xd10-white);
        box-shadow: 0 18px 50px rgba(33, 49, 39, .09);
    }
    .xd5-post img { height: 260px; transition: transform .5s ease; }
    .xd5-post:hover img { transform: scale(1.05); }
    .xd5-post h3 { color: var(--xd10-green-dark); font-size: 22px; }

    .xd5-partners { background: var(--xd10-white); }
    .xd5-partner {
        overflow: hidden;
        border: 1px solid #e7ebe3;
        border-radius: 12px;
        padding: 8px;
        color: var(--xd10-green-dark);
        filter: grayscale(1);
        transition: filter .25s ease, border-color .25s ease;
    }
    .xd5-partner:hover { border-color: var(--xd10-lime); filter: grayscale(0); }

    .xd5-footer { padding-top: 62px; background: #102c1b; color: var(--xd10-white); }
    .xd5-footer .xd5-brand { color: var(--xd10-white); }
    .xd5-footer-top { border-color: rgba(255, 255, 255, .13); }
    .xd5-footer-top small { color: var(--xd10-lime); }
    .xd5-footer p { color: rgba(255, 255, 255, .68); }
    .xd5-footer h3 { color: var(--xd10-lime); }
    .xd5-footer input { border-radius: 999px 0 0 999px; padding-inline: 18px; }
    .xd5-footer button {
        border-radius: 0 999px 999px 0;
        background: var(--xd10-lime);
        color: var(--xd10-green-dark);
        font-weight: 800;
    }

    .xd10-motion-ready .xd10-reveal {
        opacity: 0;
        transform: translateY(42px);
        transition:
            opacity .75s cubic-bezier(.2, .65, .3, 1),
            transform .75s cubic-bezier(.2, .65, .3, 1);
        transition-delay: var(--xd10-delay, 0ms);
    }
    .xd10-motion-ready .xd10-reveal.is-visible {
        opacity: 1;
        transform: translateY(0);
    }
    .xd5-hero__slide.is-active .xd5-hero-copy > div {
        animation: xd10HeroIn .9s .12s both cubic-bezier(.2, .65, .3, 1);
    }
    @keyframes xd10HeroIn {
        from { opacity: 0; transform: translateY(32px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 1020px) {
        .xd5-nav-wrap nav { gap: 15px; }
        .xd5-services { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .xd5-about,
        .xd5-benefit-grid,
        .xd5-contact-grid { grid-template-columns: 1fr; }
        .xd5-testimonial-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .xd-projects { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 720px) {
        :root { --wide: min(100% - 30px, 1220px); }
        .xd5-header { position: relative; background: var(--xd10-green-dark); }
        .xd5-nav-wrap { min-height: 76px; flex-wrap: wrap; }
        .xd5-nav-wrap > button {
            display: block;
            margin-left: auto;
            border: 1px solid rgba(255, 255, 255, .4);
            border-radius: 999px;
            color: var(--xd10-white);
        }
        .xd5-nav-wrap nav {
            width: 100%;
            flex-basis: 100%;
            padding: 0 0 20px;
        }
        .xd5-hotline { display: none; }
        .xd5-hero,
        .xd5-hero-copy { min-height: 620px; }
        .xd5-hero-copy > div { margin-top: 0; padding-left: 20px; }
        .xd5-hero h1 { font-size: clamp(40px, 12vw, 56px); }
        .xd5-section,
        .xd-section { padding-block: 72px; }
        .xd5-title,
        .xd-section-title h2 { font-size: 38px; }
        .xd5-services,
        .xd5-testimonial-grid,
        .xd5-team,
        .xd5-benefit-list,
        .xd5-footer-grid,
        .xd-projects { grid-template-columns: 1fr; }
        .xd5-about-media { min-height: 430px; }
        .xd5-about-media img { width: 100%; height: 400px; border-radius: 70px 10px 10px; }
        .xd5-about-badge { width: 165px; padding: 25px 14px; }
        .xd5-benefit-grid > img { height: 390px; border-radius: 8px 70px 8px 8px; }
        .xd-project-card,
        .xd-project-card:nth-child(even) { height: 390px; margin-top: 0; }
        .xd5-contact-card { padding: 30px 22px; }
        .xd5-footer-top { align-items: start; }
    }

    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        .xd10-motion-ready .xd10-reveal {
            opacity: 1;
            transform: none;
            transition: none;
        }
        .xd5-hero__slide.is-active .xd5-hero-copy > div { animation: none; }
    }
</style>

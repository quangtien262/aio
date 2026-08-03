<style>
    :root {
        --xd11-lime: #b6d43c;
        --xd11-lime-light: #d9e98f;
        --xd11-ink: #122626;
        --xd11-teal: #18443e;
        --xd11-soft: #eef2e9;
        --xd11-cream: #f8f8f3;
        --xd11-white: #fff;
        --xd11-muted: #647170;
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
        margin: 0;
        overflow-x: hidden;
        background: var(--xd11-cream);
        color: var(--xd11-ink);
    }
    .xd5-page { color: var(--xd11-ink); }
    .xd5-container,
    .xd3-container { width: var(--wide); margin-inline: auto; }

    .xd5-header {
        position: relative;
        z-index: 20;
        background: var(--xd11-ink);
        color: var(--xd11-white);
    }
    .xd5-utility { border-color: rgba(255, 255, 255, .1); background: #0b1c1c; }
    .xd5-utility > div { padding-block: 9px; opacity: .82; }
    .xd5-nav-wrap { min-height: 82px; gap: 22px; }
    .xd5-brand {
        min-width: max-content;
        color: var(--xd11-white);
        font-size: 27px;
        font-weight: 850;
        letter-spacing: -.05em;
    }
    .xd5-brand::before {
        display: grid;
        width: 36px;
        height: 36px;
        border-radius: 6px 18px 6px 18px;
        place-items: center;
        background: var(--xd11-lime);
        color: var(--xd11-ink);
        content: "↗";
        font-size: 20px;
        font-weight: 900;
    }
    .xd5-brand > span { color: inherit; font-size: 27px; }
    .xd5-brand img { max-width: 190px; max-height: 56px; filter: brightness(0) invert(1); }
    .xd5-nav-wrap nav { gap: 24px; font-size: 12px; letter-spacing: .055em; }
    .xd5-nav-wrap nav a {
        position: relative;
        padding-block: 12px;
    }
    .xd5-nav-wrap nav a::after {
        position: absolute;
        right: 50%;
        bottom: 4px;
        left: 50%;
        height: 2px;
        background: var(--xd11-lime);
        content: "";
        transition: .25s ease;
    }
    .xd5-nav-wrap nav a:hover::after { right: 0; left: 0; }
    .xd5-hotline {
        padding: 9px 13px;
        border-left: 2px solid var(--xd11-lime);
        color: var(--xd11-lime-light);
        font-size: 15px;
    }
    .xd11-auth-actions {
        display: flex;
        align-items: center;
        gap: 7px;
        white-space: nowrap;
    }
    .xd11-auth-actions button,
    .xd11-account-link {
        border: 1px solid rgba(255, 255, 255, .4);
        border-radius: 999px;
        padding: 8px 12px;
        background: transparent;
        color: var(--xd11-white);
        font: inherit;
        font-size: 12px;
        font-weight: 750;
        cursor: pointer;
    }
    .xd11-auth-actions button:last-child {
        border-color: var(--xd11-lime);
        background: var(--xd11-lime);
        color: var(--xd11-ink);
    }

    .xd5-hero { min-height: 690px; isolation: isolate; background: var(--xd11-ink); }
    .xd5-hero__slide { transition: opacity .75s ease; }
    .xd5-hero__slide > img {
        position: absolute;
        inset: 0;
        z-index: -2;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transform: scale(1.01);
        transition: transform 7s ease;
    }
    .xd5-hero__slide.is-active > img { transform: scale(1.07); }
    .xd5-veil {
        z-index: -1;
        background:
            linear-gradient(90deg, rgba(11, 31, 31, .94) 0%, rgba(11, 31, 31, .76) 48%, rgba(11, 31, 31, .2) 82%),
            linear-gradient(0deg, rgba(11, 31, 31, .42), transparent 45%);
    }
    .xd5-hero-copy { min-height: 690px; }
    .xd5-hero-copy > div { max-width: 720px; margin-top: 0; }
    .xd5-kicker,
    .xd5-eyebrow {
        color: var(--xd11-lime);
        font-size: 13px;
        font-weight: 850;
        letter-spacing: .16em;
        text-transform: uppercase;
    }
    .xd5-hero h1 {
        max-width: 720px;
        margin-block: 18px;
        color: var(--xd11-white);
        font-size: clamp(48px, 6vw, 78px);
        font-weight: 750;
        letter-spacing: -.06em;
        line-height: .98;
    }
    .xd5-hero p:not(.xd5-kicker) {
        max-width: 610px;
        color: rgba(255, 255, 255, .78);
        font-size: 18px;
        line-height: 1.72;
    }
    .xd5-btn {
        align-items: center;
        min-height: 50px;
        border: 0;
        border-radius: 999px;
        padding: 12px 24px;
        background: var(--xd11-lime);
        color: var(--xd11-ink);
        font-weight: 850;
        transition: transform .25s ease, background .25s ease;
    }
    .xd5-btn:hover { background: var(--xd11-lime-light); transform: translateY(-2px); }

    .xd5-section { padding-block: 100px; }
    .xd5-title {
        max-width: 790px;
        margin-block: 12px 18px;
        color: var(--xd11-ink);
        font-size: clamp(36px, 4.3vw, 58px);
        font-weight: 750;
        letter-spacing: -.045em;
        line-height: 1.08;
    }
    .xd5-section p { color: var(--xd11-muted); }

    .xd5-services {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        margin-top: 48px;
    }
    .xd5-service {
        min-height: 430px;
        grid-template-rows: auto auto 1fr;
        overflow: hidden;
        border: 1px solid #e1e7dd;
        border-radius: 8px;
        padding: 0;
        background: var(--xd11-white);
        box-shadow: 0 15px 45px rgba(18, 38, 38, .07);
        transition: transform .3s ease, border-color .3s ease, box-shadow .3s ease;
    }
    .xd5-service:hover {
        border-color: var(--xd11-lime);
        box-shadow: 0 22px 58px rgba(18, 38, 38, .13);
        transform: translateY(-7px);
    }
    .xd5-service h3 { margin: 25px 23px 0; color: var(--xd11-ink); font-size: 21px; }
    .xd5-service p { margin: 12px 23px 23px; }
    .xd5-service img { order: -1; width: 100%; height: 215px; object-fit: cover; }

    .xd5-about { grid-template-columns: 1.05fr .95fr; gap: 90px; }
    .xd5-about-media { min-height: 535px; }
    .xd5-about-media::before {
        position: absolute;
        top: 30px;
        right: 6%;
        bottom: 0;
        left: 8%;
        border: 2px solid var(--xd11-lime);
        content: "";
    }
    .xd5-about-media img {
        position: relative;
        width: 86%;
        height: 500px;
        object-fit: cover;
    }
    .xd5-about-badge {
        right: 0;
        bottom: 0;
        width: 215px;
        border: 8px solid var(--xd11-cream);
        border-radius: 8px;
        padding: 32px 20px;
        background: var(--xd11-teal);
    }
    .xd5-about-badge b { color: var(--xd11-lime); font-size: 50px; }
    .xd5-progress { overflow: hidden; border-radius: 999px; background: #dfe6dd; }
    .xd5-progress i { border-radius: inherit; background: var(--xd11-lime); }

    .xd5-benefits {
        position: relative;
        overflow: hidden;
        background: var(--xd11-ink);
        color: var(--xd11-white);
    }
    .xd5-benefits::before {
        position: absolute;
        right: -180px;
        bottom: -260px;
        width: 590px;
        height: 590px;
        border: 80px solid rgba(182, 212, 60, .06);
        border-radius: 50%;
        content: "";
    }
    .xd5-benefits .xd5-title,
    .xd5-benefits p { color: var(--xd11-white); }
    .xd5-benefit-grid { position: relative; grid-template-columns: .9fr 1.1fr; gap: 80px; }
    .xd5-benefit-grid > img { width: 100%; height: 540px; object-fit: cover; }
    .xd5-benefit-list { gap: 14px; margin-top: 42px; }
    .xd5-benefit-list b {
        min-height: 72px;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 8px;
        padding: 12px;
    }
    .xd5-benefit-list b::before {
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        border-radius: 7px;
        background: var(--xd11-lime);
        color: var(--xd11-ink);
        content: "✓";
    }

    /* The process block uses the xd3 namespace, so XD0311 supplies its full layout here. */
    .xd3-process {
        position: relative;
        overflow: hidden;
        padding-block: 110px;
        background: var(--xd11-soft);
    }
    .xd3-process::before {
        position: absolute;
        top: -140px;
        left: -110px;
        width: 360px;
        height: 360px;
        border: 1px solid rgba(24, 68, 62, .12);
        border-radius: 50%;
        content: "";
        box-shadow: 0 0 0 55px rgba(24, 68, 62, .035), 0 0 0 110px rgba(24, 68, 62, .025);
    }
    .xd3-process .xd3-container { position: relative; }
    .xd3-process__title {
        display: grid;
        grid-template-columns: .34fr .66fr;
        gap: 50px;
        align-items: end;
    }
    .xd3-process__title > p {
        grid-column: 1;
        grid-row: 1;
        margin: 0 0 10px;
        color: var(--xd11-teal);
        font-size: 13px;
        font-weight: 850;
        letter-spacing: .16em;
        text-transform: uppercase;
    }
    .xd3-process__title > h2 {
        grid-column: 2;
        grid-row: 1;
        margin: 0;
        color: var(--xd11-ink);
        font-size: clamp(40px, 5vw, 66px);
        letter-spacing: -.055em;
        line-height: 1;
    }
    .xd3-process__intro {
        max-width: 690px;
        margin: 24px 0 56px auto;
        color: var(--xd11-muted);
        font-size: 17px;
        line-height: 1.7;
    }
    .xd3-steps {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0;
        counter-reset: process;
    }
    .xd3-step {
        position: relative;
        min-height: 270px;
        border-top: 2px solid #cad5c4;
        padding: 42px 30px 28px 0;
    }
    .xd3-step:not(:last-child)::after {
        position: absolute;
        top: -7px;
        right: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--xd11-lime);
        content: "";
    }
    .xd3-step__number {
        display: grid;
        width: 58px;
        height: 58px;
        margin-bottom: 30px;
        border-radius: 50%;
        place-items: center;
        background: var(--xd11-ink);
        color: var(--xd11-lime);
        font-size: 17px;
        font-weight: 850;
    }
    .xd3-step h3 {
        margin: 0 0 13px;
        color: var(--xd11-ink);
        font-size: 21px;
        line-height: 1.25;
    }
    .xd3-step p { margin: 0; color: var(--xd11-muted); line-height: 1.65; }

    .xd5-testimonial { background: var(--xd11-teal); }
    .xd5-testimonial .xd5-title,
    .xd5-testimonial p { color: var(--xd11-white); }
    .xd5-testimonial-grid { grid-template-columns: 1.05fr repeat(3, minmax(0, 1fr)); gap: 16px; }
    .xd5-quote {
        border-radius: 8px;
        padding: 32px;
        box-shadow: none;
    }
    .xd5-quote::before {
        display: block;
        margin-bottom: 18px;
        color: var(--xd11-lime);
        content: "“";
        font-family: Georgia, serif;
        font-size: 62px;
        line-height: .5;
    }
    .xd5-quote p { color: var(--xd11-muted); }
    .xd5-quote b { display: block; margin-top: 20px; color: var(--xd11-ink); }

    .xd5-team-head { max-width: 760px; margin-inline: auto; text-align: center; }
    .xd5-team { gap: 20px; }
    .xd5-team article { border-radius: 8px; background: var(--xd11-ink); }
    .xd5-team img { height: 420px; }
    .xd5-team h3 { color: var(--xd11-white); }
    .xd5-team p { color: var(--xd11-lime-light); }

    .xd5-contact {
        background:
            linear-gradient(90deg, rgba(12, 35, 34, .97), rgba(12, 35, 34, .86)),
            url("https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1800&q=85") center/cover;
    }
    .xd5-contact .xd5-title,
    .xd5-contact p { color: var(--xd11-white); }
    .xd5-contact-grid { grid-template-columns: .9fr 1.1fr; gap: 90px; }
    .xd5-contact-info b { color: var(--xd11-lime); }
    .xd5-contact-card { border-radius: 10px; padding: 46px; background: var(--xd11-cream); }
    .xd5-contact-card h2 { color: var(--xd11-ink); }
    .xd5-contact-card input,
    .xd5-contact-card textarea {
        border: 1px solid #dce4d9;
        border-radius: 7px;
        background: var(--xd11-white);
        outline: none;
    }
    .xd5-contact-card input:focus,
    .xd5-contact-card textarea:focus { border-color: var(--xd11-lime); }

    .xd5-posts { gap: 20px; }
    .xd5-post {
        overflow: hidden;
        border: 1px solid #e1e7dd;
        border-radius: 8px;
        background: var(--xd11-white);
        box-shadow: 0 16px 45px rgba(18, 38, 38, .07);
    }
    .xd5-post img { height: 255px; transition: transform .5s ease; }
    .xd5-post:hover img { transform: scale(1.05); }
    .xd5-post h3 { color: var(--xd11-ink); font-size: 22px; }

    .xd5-partners { background: var(--xd11-white); }
    .xd5-partner {
        overflow: hidden;
        border: 1px solid #e2e8df;
        border-radius: 7px;
        padding: 9px;
        color: var(--xd11-teal);
        filter: grayscale(1);
        transition: filter .25s ease, border-color .25s ease;
    }
    .xd5-partner:hover { border-color: var(--xd11-lime); filter: grayscale(0); }

    .xd5-footer { padding-top: 60px; background: #0d2020; color: var(--xd11-white); }
    .xd5-footer .xd5-brand { color: var(--xd11-white); }
    .xd5-footer-top { border-color: rgba(255, 255, 255, .11); }
    .xd5-footer-top small,
    .xd5-footer h3 { color: var(--xd11-lime); }
    .xd5-footer p { color: rgba(255, 255, 255, .65); }
    .xd5-footer input { border-radius: 999px 0 0 999px; padding-inline: 18px; }
    .xd5-footer button {
        border-radius: 0 999px 999px 0;
        background: var(--xd11-lime);
        color: var(--xd11-ink);
        font-weight: 850;
    }

    .xd11-motion-ready .xd11-reveal {
        opacity: 0;
        transform: translateY(40px);
        transition:
            opacity .74s cubic-bezier(.2, .65, .3, 1),
            transform .74s cubic-bezier(.2, .65, .3, 1);
        transition-delay: var(--xd11-delay, 0ms);
    }
    .xd11-motion-ready .xd11-reveal.is-visible {
        opacity: 1;
        transform: translateY(0);
    }
    .xd5-hero__slide.is-active .xd5-hero-copy > div {
        animation: xd11HeroIn .9s .12s both cubic-bezier(.2, .65, .3, 1);
    }
    @keyframes xd11HeroIn {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 1100px) {
        .xd5-nav-wrap nav { gap: 13px; }
        .xd5-hotline { display: none; }
        .xd5-services { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .xd5-about,
        .xd5-benefit-grid,
        .xd5-contact-grid { grid-template-columns: 1fr; }
        .xd5-testimonial-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 720px) {
        :root { --wide: min(100% - 30px, 1220px); }
        .xd5-utility { display: none; }
        .xd5-nav-wrap { min-height: 76px; flex-wrap: wrap; }
        .xd5-nav-wrap > button {
            display: block;
            order: 2;
            margin-left: auto;
            border: 1px solid rgba(255, 255, 255, .4);
            border-radius: 999px;
            color: var(--xd11-white);
        }
        .xd11-auth-actions { order: 3; width: 100%; padding-bottom: 12px; }
        .xd5-nav-wrap nav {
            order: 4;
            width: 100%;
            flex-basis: 100%;
            padding-bottom: 18px;
        }
        .xd5-hero,
        .xd5-hero-copy { min-height: 620px; }
        .xd5-hero h1 { font-size: clamp(40px, 12vw, 56px); }
        .xd5-section,
        .xd3-process { padding-block: 72px; }
        .xd5-title { font-size: 38px; }
        .xd5-services,
        .xd5-testimonial-grid,
        .xd5-team,
        .xd5-benefit-list,
        .xd5-footer-grid { grid-template-columns: 1fr; }
        .xd5-about-media { min-height: 430px; }
        .xd5-about-media img { width: 94%; height: 400px; }
        .xd5-about-badge { width: 165px; padding: 24px 14px; }
        .xd5-benefit-grid > img { height: 390px; }
        .xd3-process__title { display: block; }
        .xd3-process__title > p { margin-bottom: 15px; }
        .xd3-process__title > h2 { font-size: 45px; }
        .xd3-process__intro { margin: 20px 0 45px; }
        .xd3-steps { grid-template-columns: 1fr; }
        .xd3-step {
            min-height: 0;
            border-top: 0;
            border-left: 2px solid #cad5c4;
            padding: 0 0 38px 78px;
        }
        .xd3-step:not(:last-child)::after {
            top: 58px;
            right: auto;
            bottom: 0;
            left: -7px;
        }
        .xd3-step__number { position: absolute; top: 0; left: 17px; width: 45px; height: 45px; margin: 0; }
        .xd5-contact-card { padding: 30px 22px; }
        .xd5-footer-top { align-items: start; }
    }

    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        .xd11-motion-ready .xd11-reveal {
            opacity: 1;
            transform: none;
            transition: none;
        }
        .xd5-hero__slide.is-active .xd5-hero-copy > div { animation: none; }
    }
</style>

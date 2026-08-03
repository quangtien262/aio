<style>
    :root {
        --xd12-orange: #ffb51b;
        --xd12-red: #ef3347;
        --xd12-navy: #0e1b2b;
        --xd12-blue: #1a3046;
        --xd12-soft: #f3f6f8;
        --xd12-white: #fff;
        --xd12-ink: #172536;
        --xd12-muted: #667585;
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
        background: var(--xd12-white);
        color: var(--xd12-ink);
    }
    .xd5-container,
    .xd3-container { width: var(--wide); margin-inline: auto; }

    .xd12-header {
        position: absolute;
        z-index: 20;
        width: 100%;
        color: var(--xd12-white);
    }
    .xd12-utility { background: rgba(8, 18, 31, .96); font-size: 12px; }
    .xd12-utility > div {
        display: flex;
        min-height: 42px;
        align-items: center;
        gap: 22px;
    }
    .xd12-utility a { color: var(--xd12-white); }
    .xd12-utility-spacer { flex: 1; }
    .xd12-utility button {
        border: 1px solid rgba(255, 255, 255, .35);
        border-radius: 999px;
        padding: 5px 10px;
        background: transparent;
        color: var(--xd12-white);
        cursor: pointer;
    }
    .xd12-quote-small {
        align-self: stretch;
        display: flex;
        align-items: center;
        padding-inline: 17px;
        background: var(--xd12-orange);
        color: var(--xd12-navy) !important;
        font-weight: 800;
    }
    .xd12-navigation {
        display: flex;
        min-height: 84px;
        align-items: center;
        gap: 25px;
        padding-inline: 28px;
        background: rgba(255, 255, 255, .97);
        box-shadow: 0 12px 36px rgba(10, 25, 42, .12);
        color: var(--xd12-ink);
    }
    .xd12-navigation .xd5-brand {
        min-width: max-content;
        color: var(--xd12-navy);
        font-size: 26px;
        font-weight: 850;
        letter-spacing: -.05em;
    }
    .xd12-navigation .xd5-brand::before {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        background: linear-gradient(145deg, var(--xd12-red) 0 49%, var(--xd12-orange) 50%);
        color: var(--xd12-white);
        content: "↗";
        font-size: 21px;
        font-weight: 900;
    }
    .xd12-navigation .xd5-brand > span { color: inherit; font-size: 26px; }
    .xd12-navigation .xd5-brand img { max-width: 190px; max-height: 58px; }
    .xd12-navigation nav {
        display: flex;
        flex: 1;
        justify-content: center;
        gap: 27px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .xd12-navigation nav a {
        position: relative;
        padding-block: 12px;
    }
    .xd12-navigation nav a::after {
        position: absolute;
        right: 50%;
        bottom: 3px;
        left: 50%;
        height: 2px;
        background: var(--xd12-red);
        content: "";
        transition: .25s ease;
    }
    .xd12-navigation nav a:hover::after { right: 0; left: 0; }
    .xd12-navigation > button { display: none; }
    .xd12-quote {
        border-radius: 4px;
        padding: 13px 17px;
        background: var(--xd12-orange);
        color: var(--xd12-navy);
        font-weight: 850;
        white-space: nowrap;
    }

    .xd5-hero {
        min-height: 780px;
        isolation: isolate;
        background: var(--xd12-navy);
    }
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
            linear-gradient(90deg, rgba(7, 18, 32, .92), rgba(7, 18, 32, .6) 52%, rgba(7, 18, 32, .22)),
            linear-gradient(0deg, rgba(7, 18, 32, .5), transparent 50%);
    }
    .xd5-hero-copy {
        min-height: 780px;
        justify-content: flex-start;
        text-align: left;
    }
    .xd5-hero-copy > div { max-width: 750px; margin-top: 105px; }
    .xd5-kicker,
    .xd5-eyebrow {
        color: var(--xd12-orange);
        font-size: 13px;
        font-weight: 850;
        letter-spacing: .16em;
        text-transform: uppercase;
    }
    .xd5-hero h1 {
        max-width: 740px;
        margin-block: 18px;
        color: var(--xd12-white);
        font-size: clamp(48px, 6vw, 78px);
        font-weight: 800;
        letter-spacing: -.055em;
        line-height: .98;
        text-transform: none;
    }
    .xd5-hero p:not(.xd5-kicker) {
        max-width: 610px;
        color: rgba(255, 255, 255, .8);
        font-size: 18px;
        line-height: 1.7;
    }
    .xd5-btn {
        align-items: center;
        min-height: 50px;
        border: 0;
        border-radius: 4px;
        padding: 12px 24px;
        background: var(--xd12-orange);
        color: var(--xd12-navy);
        font-weight: 850;
        transition: transform .25s ease, background .25s ease;
    }
    .xd5-btn:hover { background: #ffc447; transform: translateY(-2px); }

    .xd5-section { padding-block: 100px; }
    .xd5-title {
        max-width: 820px;
        margin-block: 12px 18px;
        color: var(--xd12-ink);
        font-size: clamp(36px, 4.3vw, 58px);
        font-weight: 800;
        letter-spacing: -.045em;
        line-height: 1.08;
    }
    .xd5-section p { color: var(--xd12-muted); }

    .xd5-services {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 17px;
        margin-top: 48px;
    }
    .xd5-service {
        min-height: 430px;
        grid-template-rows: auto auto 1fr;
        overflow: hidden;
        border: 1px solid #e2e8ed;
        border-radius: 5px;
        padding: 0;
        background: var(--xd12-white);
        box-shadow: 0 16px 45px rgba(14, 27, 43, .07);
        transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease;
    }
    .xd5-service:hover {
        border-color: var(--xd12-orange);
        box-shadow: 0 23px 58px rgba(14, 27, 43, .14);
        transform: translateY(-7px);
    }
    .xd5-service h3 { margin: 24px 22px 0; color: var(--xd12-ink); font-size: 21px; }
    .xd5-service p { margin: 12px 22px 23px; }
    .xd5-service img { order: -1; width: 100%; height: 215px; object-fit: cover; }

    .xd5-about { grid-template-columns: .95fr 1.05fr; gap: 90px; }
    .xd5-about-media { min-height: 540px; }
    .xd5-about-media::before {
        position: absolute;
        top: 28px;
        right: 5%;
        bottom: 0;
        left: 8%;
        border: 3px solid var(--xd12-orange);
        content: "";
    }
    .xd5-about-media img { position: relative; width: 88%; height: 505px; object-fit: cover; }
    .xd5-about-badge {
        right: 0;
        bottom: 0;
        width: 215px;
        border: 8px solid var(--xd12-white);
        border-radius: 5px;
        padding: 32px 20px;
        background: var(--xd12-red);
        color: var(--xd12-white);
    }
    .xd5-about-badge b { color: var(--xd12-white); font-size: 50px; }
    .xd5-progress { overflow: hidden; border-radius: 999px; background: #e2e8ed; }
    .xd5-progress i { border-radius: inherit; background: var(--xd12-orange); }

    .xd5-benefits {
        position: relative;
        overflow: hidden;
        background: var(--xd12-red);
        color: var(--xd12-white);
    }
    .xd5-benefits::after {
        position: absolute;
        top: -170px;
        right: -140px;
        width: 450px;
        height: 450px;
        border: 70px solid rgba(255, 255, 255, .06);
        border-radius: 50%;
        content: "";
    }
    .xd5-benefits .xd5-title,
    .xd5-benefits p { color: var(--xd12-white); }
    .xd5-benefit-grid { position: relative; z-index: 1; grid-template-columns: .9fr 1.1fr; gap: 70px; }
    .xd5-benefit-grid > img { width: 100%; height: 515px; object-fit: cover; }
    .xd5-benefit-list { gap: 12px; margin-top: 42px; }
    .xd5-benefit-list b {
        min-height: 80px;
        border: 1px solid rgba(255, 255, 255, .2);
        border-radius: 5px;
        padding: 12px;
        background: rgba(255, 255, 255, .06);
    }
    .xd5-benefit-list b::before {
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        border-radius: 4px;
        background: var(--xd12-orange);
        color: var(--xd12-navy);
        content: "✓";
    }

    /* XD0312 owns the full xd3 process layout used by this block. */
    .xd3-process {
        position: relative;
        overflow: hidden;
        padding-block: 110px;
        background: var(--xd12-navy);
        color: var(--xd12-white);
    }
    .xd3-process::before {
        position: absolute;
        top: -90px;
        right: -120px;
        width: 470px;
        height: 470px;
        border: 1px solid rgba(255, 181, 27, .18);
        border-radius: 50%;
        content: "";
        box-shadow: 0 0 0 65px rgba(255, 181, 27, .035), 0 0 0 130px rgba(255, 181, 27, .025);
    }
    .xd3-process .xd3-container { position: relative; }
    .xd3-process__title {
        display: grid;
        grid-template-columns: .32fr .68fr;
        gap: 48px;
        align-items: end;
    }
    .xd3-process__title > p {
        grid-column: 1;
        grid-row: 1;
        margin: 0 0 10px;
        color: var(--xd12-orange);
        font-size: 13px;
        font-weight: 850;
        letter-spacing: .16em;
        text-transform: uppercase;
    }
    .xd3-process__title > h2 {
        grid-column: 2;
        grid-row: 1;
        margin: 0;
        color: var(--xd12-white);
        font-size: clamp(42px, 5.2vw, 68px);
        letter-spacing: -.055em;
        line-height: 1;
    }
    .xd3-process__intro {
        max-width: 700px;
        margin: 24px 0 58px auto;
        color: rgba(255, 255, 255, .67);
        font-size: 17px;
        line-height: 1.7;
    }
    .xd3-steps {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .xd3-step {
        position: relative;
        min-height: 300px;
        border: 1px solid rgba(255, 255, 255, .11);
        border-radius: 6px;
        padding: 32px 27px;
        background: rgba(255, 255, 255, .045);
        transition: background .3s ease, border-color .3s ease, transform .3s ease;
    }
    .xd3-step:hover {
        border-color: var(--xd12-orange);
        background: rgba(255, 255, 255, .075);
        transform: translateY(-6px);
    }
    .xd3-step:not(:last-child)::after {
        position: absolute;
        z-index: 2;
        top: 62px;
        right: -17px;
        width: 18px;
        border-top: 2px dashed var(--xd12-orange);
        content: "";
    }
    .xd3-step__number {
        display: grid;
        width: 62px;
        height: 62px;
        margin-bottom: 34px;
        border-radius: 50%;
        place-items: center;
        background: var(--xd12-orange);
        color: var(--xd12-navy);
        font-size: 17px;
        font-weight: 900;
    }
    .xd3-step h3 {
        margin: 0 0 14px;
        color: var(--xd12-white);
        font-size: 21px;
        line-height: 1.25;
    }
    .xd3-step p { margin: 0; color: rgba(255, 255, 255, .62); line-height: 1.65; }

    .xd5-team-head { max-width: 780px; margin-inline: auto; text-align: center; }
    .xd5-team { gap: 20px; }
    .xd5-team article { border-radius: 5px; background: var(--xd12-blue); }
    .xd5-team img { height: 405px; }
    .xd5-team h3 { color: var(--xd12-white); }
    .xd5-team p { color: var(--xd12-orange); }

    .xd5-posts { gap: 20px; }
    .xd5-post {
        overflow: hidden;
        border: 1px solid #e2e8ed;
        border-radius: 5px;
        background: var(--xd12-white);
        box-shadow: 0 16px 45px rgba(14, 27, 43, .07);
    }
    .xd5-post img { height: 255px; transition: transform .5s ease; }
    .xd5-post:hover img { transform: scale(1.05); }
    .xd5-post h3 { color: var(--xd12-ink); font-size: 22px; }

    .xd5-partners { background: var(--xd12-blue); color: var(--xd12-white); }
    .xd5-partner {
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .16);
        border-radius: 5px;
        padding: 10px;
        filter: grayscale(1);
        transition: filter .25s ease, border-color .25s ease;
    }
    .xd5-partner:hover { border-color: var(--xd12-orange); filter: grayscale(0); }

    .xd12-footer { padding-top: 62px; background: var(--xd12-navy); }
    .xd12-footer-grid { display: grid; grid-template-columns: 1.25fr 1fr 1fr 1fr; gap: 45px; }
    .xd12-footer .xd5-brand { color: var(--xd12-white); }
    .xd12-footer .xd5-brand > span { color: inherit; font-size: 26px; }
    .xd12-footer h3 { margin-top: 0; color: var(--xd12-orange); }
    .xd12-footer p,
    .xd12-footer li { color: rgba(255, 255, 255, .7); }
    .xd12-footer ul { padding: 0; list-style: none; }
    .xd12-footer li { margin: 11px 0; }
    .xd12-contact-list li::before { margin-right: 9px; color: var(--xd12-orange); content: "•"; }
    .xd12-footer form { display: grid; grid-template-columns: 1fr auto; }
    .xd12-footer input { min-width: 0; border: 0; padding: 13px; }
    .xd12-footer button {
        border: 0;
        padding-inline: 15px;
        background: var(--xd12-orange);
        color: var(--xd12-navy);
        font-weight: 800;
    }

    .xd12-motion-ready .xd12-reveal {
        opacity: 0;
        transform: translateY(40px);
        transition:
            opacity .74s cubic-bezier(.2, .65, .3, 1),
            transform .74s cubic-bezier(.2, .65, .3, 1);
        transition-delay: var(--xd12-delay, 0ms);
    }
    .xd12-motion-ready .xd12-reveal.is-visible {
        opacity: 1;
        transform: translateY(0);
    }
    .xd5-hero__slide.is-active .xd5-hero-copy > div {
        animation: xd12HeroIn .9s .12s both cubic-bezier(.2, .65, .3, 1);
    }
    @keyframes xd12HeroIn {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 1100px) {
        .xd12-navigation nav { gap: 13px; }
        .xd5-services { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .xd5-about,
        .xd5-benefit-grid { grid-template-columns: 1fr; }
        .xd12-footer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 720px) {
        :root { --wide: min(100% - 30px, 1220px); }
        .xd12-header { position: relative; background: var(--xd12-navy); }
        .sf-language-switcher {
            top: 7px !important;
            right: 8px !important;
        }
        .xd12-utility > div { gap: 8px; overflow: auto; }
        .xd12-utility > div > a:first-child,
        .xd12-utility-spacer,
        .xd12-quote-small { display: none; }
        .xd12-navigation { min-height: 74px; flex-wrap: wrap; padding-inline: 15px; }
        .xd12-navigation > button {
            display: block;
            margin-left: auto;
            border: 1px solid rgba(23, 37, 54, .3);
            border-radius: 999px;
            padding: 8px 12px;
            background: transparent;
            color: var(--xd12-ink);
        }
        .xd12-navigation nav {
            display: none;
            order: 3;
            flex-basis: 100%;
            flex-direction: column;
            align-items: flex-start;
            padding: 8px 0 18px;
        }
        .xd12-navigation nav.is-open { display: flex; }
        .xd12-quote { display: none; }
        .xd5-hero,
        .xd5-hero-copy { min-height: 620px; }
        .xd5-hero-copy > div { margin-top: 0; }
        .xd5-hero h1 { font-size: clamp(40px, 12vw, 56px); }
        .xd5-section,
        .xd3-process { padding-block: 72px; }
        .xd5-title { font-size: 38px; }
        .xd5-services,
        .xd5-team,
        .xd5-benefit-list,
        .xd12-footer-grid { grid-template-columns: 1fr; }
        .xd5-about-media { min-height: 430px; }
        .xd5-about-media img { width: 94%; height: 400px; }
        .xd5-about-badge { width: 165px; padding: 24px 14px; }
        .xd5-benefit-grid > img { height: 390px; }
        .xd3-process__title {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .xd3-process__title > p {
            order: -1;
            margin-bottom: 15px;
        }
        .xd3-process__title > h2 {
            max-width: 330px;
            font-size: clamp(38px, 11vw, 45px);
        }
        .xd3-process__intro { margin: 20px 0 44px; }
        .xd3-steps { grid-template-columns: 1fr; }
        .xd3-step { min-height: 0; padding: 28px 24px 28px 92px; }
        .xd3-step:not(:last-child)::after {
            top: auto;
            right: auto;
            bottom: -17px;
            left: 49px;
            width: 0;
            height: 18px;
            border-top: 0;
            border-left: 2px dashed var(--xd12-orange);
        }
        .xd3-step__number {
            position: absolute;
            top: 25px;
            left: 20px;
            width: 52px;
            height: 52px;
            margin: 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        .xd12-motion-ready .xd12-reveal {
            opacity: 1;
            transform: none;
            transition: none;
        }
        .xd5-hero__slide.is-active .xd5-hero-copy > div { animation: none; }
    }
</style>

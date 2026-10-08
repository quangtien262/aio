<style>
    .rx13-service-page { padding: 28px 0 0; color: var(--rx13-text); }
    .rx13-service-page a:focus-visible { outline: 2px solid var(--rx13-deep); outline-offset: 4px; }
    .rx13-service-breadcrumb { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 26px; font-size: 13px; line-height: 1.6; color: #64746a; }
    .rx13-service-breadcrumb a:hover { color: var(--rx13-deep); text-decoration: underline; }
    .rx13-service-breadcrumb [aria-current] { color: var(--rx13-deep); }
    .rx13-service-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 32px; align-items: start; }
    .rx13-service-article { min-width: 0; overflow: hidden; border: 1px solid var(--rx13-line); border-radius: 24px; background: #fff; }
    .rx13-service-heading { padding: clamp(24px, 3vw, 44px); }
    .rx13-service-eyebrow { display: flex; align-items: center; gap: 9px; margin: 0 0 14px; font-size: 12px; letter-spacing: .12em; font-weight: 800; text-transform: uppercase; }
    .rx13-service-eyebrow::before { content: ''; width: 7px; height: 7px; background: var(--rx13-green); border-radius: 50%; }
    .rx13-service-heading h1 { margin: 0; max-width: 850px; font-size: clamp(30px, 3.2vw, 46px); line-height: 1.18; letter-spacing: -.035em; font-weight: 800; overflow-wrap: anywhere; }
    .rx13-service-summary { max-width: 780px; margin: 18px 0 0; font-size: 17px; line-height: 1.7; color: #526158; }
    .rx13-service-cta { display: inline-flex; justify-content: center; align-items: center; gap: 24px; min-height: 46px; padding: 11px 22px; border-radius: 999px; background: var(--rx13-green); color: var(--rx13-deep); font-size: 14px; font-weight: 800; }
    .rx13-service-heading .rx13-service-cta { margin-top: 24px; }
    .rx13-service-cta:hover { background: #a0e653; }
    .rx13-service-cover { margin: 0 24px; overflow: hidden; border-radius: 16px; background: var(--rx13-soft); }
    .rx13-service-cover img { display: block; width: 100%; aspect-ratio: 2.4; max-height: 400px; object-fit: cover; }
    .rx13-service-content { padding: clamp(24px, 3vw, 44px); }
    .rx13-service-rich { color: #33493e; font-size: 16px; line-height: 1.85; overflow-wrap: anywhere; }
    .rx13-service-rich > :first-child { margin-top: 0; }
    .rx13-service-rich > :last-child { margin-bottom: 0; }
    .rx13-service-rich h2, .rx13-service-rich h3, .rx13-service-rich h4 { margin: 32px 0 12px; color: var(--rx13-deep); line-height: 1.35; }
    .rx13-service-rich h2 { font-size: 25px; letter-spacing: -.02em; }
    .rx13-service-rich h3 { font-size: 20px; }
    .rx13-service-rich p { margin: 0 0 18px; }
    .rx13-service-rich ul, .rx13-service-rich ol { padding-left: 24px; }
    .rx13-service-rich li { padding-left: 4px; margin-bottom: 8px; }
    .rx13-service-rich li::marker { color: var(--rx13-deep); }
    .rx13-service-rich a { color: var(--rx13-deep); text-decoration: underline; text-underline-offset: 3px; }
    .rx13-service-rich img, .rx13-service-rich iframe, .rx13-service-rich video { max-width: 100%; }
    .rx13-service-rich img { height: auto; border-radius: 12px; }
    .rx13-service-rich table { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; }
    .rx13-service-rich th, .rx13-service-rich td { padding: 12px; border: 1px solid var(--rx13-line); }
    .rx13-service-rich blockquote { margin: 24px 0; padding: 18px 24px; border-left: 3px solid var(--rx13-green); background: var(--rx13-soft); }
    .rx13-service-gallery { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-top: 30px; }
    .rx13-service-gallery figure { margin: 0; }
    .rx13-service-gallery img { display: block; width: 100%; aspect-ratio: 1.6; object-fit: cover; border-radius: 12px; }
    .rx13-service-gallery figcaption { margin-top: 8px; font-size: 13px; color: #64746a; }
    .rx13-service-next { display: flex; gap: 20px; align-items: center; justify-content: space-between; margin-top: 36px; padding: 24px; border: 1px solid var(--rx13-line); border-radius: 14px; background: var(--rx13-soft); }
    .rx13-service-next h2 { margin: 0 0 8px; font-size: 19px; line-height: 1.4; }
    .rx13-service-next p { margin: 0; max-width: 480px; font-size: 14px; line-height: 1.65; color: #526158; }
    .rx13-service-next a { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 12px; font-size: 14px; font-weight: 800; }
    .rx13-service-sidebar { display: grid; gap: 22px; min-width: 0; }
    .rx13-service-panel { padding: 24px; border: 1px solid var(--rx13-line); border-radius: 20px; background: #fff; }
    .rx13-service-panel-heading { display: flex; align-items: center; gap: 12px; justify-content: space-between; margin-bottom: 10px; }
    .rx13-service-panel-heading h2 { margin: 0; font-size: 20px; line-height: 1.4; letter-spacing: -.02em; }
    .rx13-service-panel-heading > span { display: grid; place-items: center; min-width: 28px; height: 28px; border-radius: 50%; background: var(--rx13-soft); font-size: 12px; font-weight: 800; }
    .rx13-service-list, .rx13-service-news { list-style: none; margin: 0; padding: 0; }
    .rx13-service-list li + li { border-top: 1px solid var(--rx13-line); }
    .rx13-service-list a { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 10px; margin: 0 -10px; font-size: 14px; font-weight: 600; line-height: 1.5; border-radius: 8px; }
    .rx13-service-list a[aria-current], .rx13-service-list a:hover { background: var(--rx13-soft); color: var(--rx13-deep); }
    .rx13-service-list a[aria-current] { font-weight: 800; box-shadow: inset 3px 0 var(--rx13-green); }
    .rx13-service-list a > span:first-child { overflow-wrap: anywhere; }
    .rx13-service-list a > span:last-child { flex-shrink: 0; }
    .rx13-service-view-all { display: flex; justify-content: space-between; gap: 16px; padding-top: 16px; margin-top: 10px; border-top: 1px solid var(--rx13-line); font-size: 13px; font-weight: 800; }
    .rx13-service-news li + li { border-top: 1px solid var(--rx13-line); }
    .rx13-service-news a { display: flex; gap: 12px; padding: 16px 0; align-items: start; }
    .rx13-service-news img { width: 68px; height: 60px; flex: 0 0 68px; object-fit: cover; border-radius: 8px; background: var(--rx13-soft); }
    .rx13-service-news h3 { margin: 0 0 8px; font-size: 14px; line-height: 1.5; font-weight: 700; overflow-wrap: anywhere; }
    .rx13-service-news time { font-size: 12px; color: #64746a; }
    .rx13-service-news a:hover h3 { text-decoration: underline; text-underline-offset: 3px; }
    .rx13-service-help { padding: 26px; border-radius: 20px; background: var(--rx13-deep); color: #fff; }
    .rx13-service-help-mark { display: grid; place-items: center; width: 36px; height: 36px; margin-bottom: 18px; border: 1px solid #619e80; border-radius: 50%; color: var(--rx13-green); font-size: 20px; }
    .rx13-service-help h2 { margin: 0 0 10px; font-size: 23px; letter-spacing: -.025em; }
    .rx13-service-help p { margin: 0 0 20px; font-size: 14px; line-height: 1.7; color: #d4e6da; }
    .rx13-service-phone { display: block; margin-bottom: 18px; font-size: 25px; font-weight: 800; letter-spacing: -.02em; overflow-wrap: anywhere; }
    .rx13-service-help .rx13-service-cta { display: flex; justify-content: space-between; }
    .rx13-service-help a:focus-visible { outline-color: #fff; }
    .rx13-service-page .rx13-contact { padding: 64px 0 80px; scroll-margin-top: 24px; }
    @media(max-width: 1100px) {
        .rx13-service-grid { grid-template-columns: minmax(0, 1fr) 300px; gap: 24px; }
        .rx13-service-panel { padding: 20px; }
        .rx13-service-next { flex-direction: column; align-items: start; }
    }
    @media(max-width: 800px) {
        .rx13-service-grid { grid-template-columns: minmax(0, 1fr); }
        .rx13-service-sidebar { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
        .rx13-service-sidebar > [aria-labelledby="rx13-service-news-title"] { grid-column: 1 / -1; }
        .rx13-service-cover img { aspect-ratio: 1.8; }
    }
    @media(max-width: 560px) {
        .rx13-service-page { padding-top: 18px; }
        .rx13-service-page > .rx13-container { width: calc(100% - 32px); }
        .rx13-service-breadcrumb { margin-bottom: 20px; font-size: 12px; gap: 7px; }
        .rx13-service-article { border-radius: 18px; }
        .rx13-service-heading, .rx13-service-content { padding: 24px 20px; }
        .rx13-service-heading h1 { font-size: 31px; }
        .rx13-service-summary { font-size: 15px; }
        .rx13-service-cover { margin: 0 12px; border-radius: 10px; }
        .rx13-service-rich { font-size: 15px; }
        .rx13-service-rich h2 { font-size: 22px; }
        .rx13-service-gallery, .rx13-service-sidebar { grid-template-columns: minmax(0, 1fr); }
        .rx13-service-next { padding: 20px; }
        .rx13-service-page .rx13-contact { padding: 40px 0 48px; }
    }
</style>

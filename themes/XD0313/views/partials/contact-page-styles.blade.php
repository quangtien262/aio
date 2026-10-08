<style>
    .rx13-page .tc-contact-page { --tc-accent: var(--rx13-deep); padding: 8px 0 64px; background: var(--rx13-soft); color: var(--rx13-deep); }
    .rx13-page .tc-contact-container { width: min(1480px, calc(100% - 48px)); }
    .rx13-page .tc-contact-breadcrumb { padding: 16px 0 22px; color: #637468; }
    .rx13-page .tc-contact-heading { max-width: none; padding: 0 0 28px; }
    .rx13-page .tc-contact-heading > span { font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .rx13-page .tc-contact-heading h1 { margin: 10px 0 12px; font-size: clamp(30px, 3vw, 42px); line-height: 1.15; letter-spacing: -.035em; }
    .rx13-page .tc-contact-heading p { max-width: 780px; font-size: 15px; line-height: 1.7; color: #596d60; }
    .rx13-page .tc-contact-layout { grid-template-columns: minmax(0, .85fr) minmax(0, 1.15fr); gap: 28px; }
    .rx13-page .tc-contact-info { padding: 32px; border: 0; border-radius: 24px; background: var(--rx13-deep); color: #fff; }
    .rx13-page .tc-contact-info > p { color: #d4e6da; margin-bottom: 22px; }
    .rx13-page .tc-contact-info h2 { color: #fff; }
    .rx13-page .tc-contact-item { border-top-color: #ffffff26; padding: 20px 0; }
    .rx13-page .tc-contact-item > .tc-contact-icon { width: 42px; height: 42px; border-radius: 50%; background: #ffffff12; color: var(--rx13-green); }
    .rx13-page .tc-contact-item h3 { color: #bdd7c8; font-size: 12px; }
    .rx13-page .tc-contact-item p, .rx13-page .tc-contact-item a { color: #fff; font-size: 16px; font-weight: 600; line-height: 1.65; }
    .rx13-page .tc-contact-item a[href^="tel:"] { font-size: 26px; letter-spacing: -.02em; }
    .rx13-page .tc-contact-item .tc-contact-directions { color: var(--rx13-green); font-size: 13px; font-weight: 700; }
    .rx13-page .tc-contact-item a:hover { color: var(--rx13-green); }
    .rx13-page .tc-contact-form-card { padding: 32px; border: 1px solid var(--rx13-line); border-radius: 24px; box-shadow: none; }
    .rx13-page .tc-contact-layout h2 { margin-bottom: 10px; font-size: 23px; letter-spacing: -.02em; }
    .rx13-page .tc-contact-form-card > p { color: #637468; margin-bottom: 22px; }
    .rx13-page .tc-contact-fields { gap: 18px 16px; }
    .rx13-page .tc-contact-field-wide:has(input) { grid-column: auto; }
    .rx13-page .tc-contact-fields label { margin-bottom: 7px; color: #24493a; }
    .rx13-page .tc-contact-fields input, .rx13-page .tc-contact-fields textarea { padding: 11px 13px; border: 1px solid #d8e1db; border-radius: 8px; background: #fafcf9; color: #203c2e; font: inherit; font-size: 15px; line-height: 1.5; }
    .rx13-page .tc-contact-fields textarea { min-height: 118px; }
    .rx13-page .tc-contact-fields small { color: #637468; }
    .rx13-page .tc-contact-fields input:focus, .rx13-page .tc-contact-fields textarea:focus { border-color: var(--rx13-deep); background: #fff; }
    .rx13-page .tc-contact-fields [aria-invalid="true"] { border-color: #b63730; }
    .rx13-page .tc-contact-form-card button { display: flex; width: 100%; min-height: 48px; margin-top: 22px; padding: 12px 20px; border-radius: 999px; background: var(--rx13-green); color: var(--rx13-deep); font-size: 14px; font-weight: 800; }
    .rx13-page .tc-contact-form-card button:hover { background: #91e336; }
    .rx13-page .tc-contact-page :focus-visible { outline: 2px solid var(--rx13-deep); outline-offset: 3px; }
    .rx13-page .tc-contact-info :focus-visible { outline-color: var(--rx13-green); }
    .rx13-page .tc-contact-copy { max-width: 100%; color: #385345; }
    @media(max-width: 1000px) {
        .rx13-page .tc-contact-info, .rx13-page .tc-contact-form-card { padding: 24px; }
        .rx13-page .tc-contact-layout { gap: 24px; }
    }
    @media(max-width: 760px) {
        .rx13-page .tc-contact-container { width: calc(100% - 32px); }
        .rx13-page .tc-contact-layout { grid-template-columns: minmax(0, 1fr); }
        .rx13-page .tc-contact-page { padding-bottom: 48px; }
        .rx13-page .tc-contact-heading { padding-bottom: 24px; }
        .rx13-page .tc-contact-heading h1 { font-size: 32px; }
        .rx13-page .tc-contact-info, .rx13-page .tc-contact-form-card { border-radius: 18px; }
    }
    @media(max-width: 480px) {
        .rx13-page .tc-contact-fields { grid-template-columns: minmax(0, 1fr); }
        .rx13-page .tc-contact-fields input, .rx13-page .tc-contact-fields textarea { font-size: 16px; }
    }
</style>

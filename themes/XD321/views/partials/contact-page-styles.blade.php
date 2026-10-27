<style>
html body .tc-contact-page[data-contact-theme="XD321"] {
    --tc-accent: var(--r, #ed1c24);
    --tc-ink: var(--b, #173d78);
    --tc-muted: #68798e;
    padding-bottom: 64px;
    background: #f4f7fb;
    font: 400 16px/1.65 Barlow, 'Segoe UI', Arial, sans-serif;
}
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-breadcrumb { padding: 24px 0; font-size: 13px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-breadcrumb a:hover { color: var(--tc-accent); }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-layout { grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 28px; align-items: stretch; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-overview {
    display: flex;
    flex-direction: column;
    padding: 36px;
    border: 1px solid #244b7c;
    border-radius: 18px;
    background: radial-gradient(ellipse at top right, #2b568b 0, transparent 58%), linear-gradient(150deg, #173d78, #0b2f61);
    color: #fff;
}
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-heading { padding-bottom: 28px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-heading > span { color: #a9c3e5; font-size: 12px; letter-spacing: .14em; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-heading h1 { max-width: none; margin: 14px 0 20px; color: #fff; font: 700 clamp(38px, 3.4vw, 52px)/1.12 'Barlow Condensed', 'Segoe UI', sans-serif; letter-spacing: 0; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-heading h1:after { width: 44px; margin-top: 20px; background: var(--tc-accent); border-radius: 2px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-heading p { color: #d2deee; font-size: 16px; line-height: 1.75; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-copy { color: #d2deee; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-copy a { color: #fff; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-info { margin-top: auto; color: #fff; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-info > h2 { color: #fff; font: 700 25px/1.3 'Barlow Condensed', 'Segoe UI', sans-serif; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-info > p { margin: 6px 0 20px; color: #b7cbe4; font-size: 14px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item { gap: 16px; margin-top: 12px; padding: 17px; border: 1px solid #ffffff21; border-radius: 12px; background: #ffffff06; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item:last-child { padding-bottom: 17px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item > .tc-contact-icon { flex-basis: 42px; width: 42px; height: 42px; border: 0; border-radius: 10px; background: #ffffff12; color: #fff; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item h3 { color: #b7cbe4; font-size: 13px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item p,
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item a { color: #fff; font-size: 16px; line-height: 1.6; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item a[href^="tel:"] { font-size: 25px; font-weight: 700; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item a:hover { color: #ffbfc2; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item .tc-contact-directions { margin-top: 9px; color: #fff; font-size: 13px; text-decoration: none; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-form-card { padding: 36px; border: 1px solid #dfe6f0; border-radius: 18px; background: #fff; box-shadow: 0 8px 30px #173d7806; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-form-card > h2 { color: var(--tc-ink); font: 700 32px/1.2 'Barlow Condensed', 'Segoe UI', sans-serif; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-form-card > p { margin: 8px 0 26px; font-size: 14px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields { gap: 20px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields label { margin-bottom: 8px; color: #314861; font-size: 14px; font-weight: 600; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields input,
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields textarea { padding: 13px 15px; border: 1px solid #d8e1ed; border-radius: 8px; background: #fff; color: #20334b; font: 400 16px/1.5 Barlow, 'Segoe UI', Arial, sans-serif; transition: border-color .2s, box-shadow .2s; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields textarea { min-height: 152px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields input:focus,
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields textarea:focus { border-color: var(--tc-ink); box-shadow: 0 0 0 3px #173d7810; outline: 0; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields [aria-invalid="true"] { border-color: #c43939; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields small { font-size: 12px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-form-card button { justify-content: space-between; gap: 20px; width: 100%; min-height: 50px; margin-top: 24px; padding: 14px 20px; border-radius: 8px; background: var(--tc-accent); font: 600 16px/1.4 Barlow, 'Segoe UI', Arial, sans-serif; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-form-card button:hover { background: #d91620; box-shadow: 0 6px 18px #ed1c2420; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-form-card button:disabled { opacity: .65; cursor: wait; }
html body .tc-contact-page[data-contact-theme="XD321"] :focus-visible { outline: 3px solid var(--tc-accent); outline-offset: 4px; }
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-notice,
html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-error { border-radius: 8px; font-size: 14px; }
@media (max-width: 1000px) {
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-overview,
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-form-card { padding: 28px; }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-layout { gap: 22px; }
}
@media (max-width: 800px) {
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-layout { grid-template-columns: minmax(0, 1fr); }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-heading { padding-bottom: 24px; }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-heading h1 { font-size: 42px; }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item { padding: 16px; }
}
@media (max-width: 480px) {
    html body .tc-contact-page[data-contact-theme="XD321"] { padding-bottom: 40px; }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-breadcrumb { padding: 20px 0; }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-overview,
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-form-card { padding: 24px 20px; border-radius: 14px; }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-heading h1 { font-size: 38px; }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item { gap: 12px; padding: 14px; }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item:last-child { padding-bottom: 14px; }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item p,
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-item a { font-size: 15px; }
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields { grid-template-columns: minmax(0, 1fr); gap: 18px; }
}
@media (prefers-reduced-motion: reduce) {
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields input,
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-fields textarea,
    html body .tc-contact-page[data-contact-theme="XD321"] .tc-contact-form-card button { transition: none; }
}
</style>

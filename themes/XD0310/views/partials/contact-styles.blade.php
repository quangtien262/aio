<style>
html body main.tc-contact-page[data-contact-theme="XD0310"] {
    --tc-accent: var(--xd10-green);
    --tc-ink: var(--xd10-ink);
    --tc-muted: var(--xd10-muted);
    padding-bottom: 64px;
    background: radial-gradient(ellipse at top right, #dfead870, transparent 55%), var(--xd10-cream);
}
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-breadcrumb { padding-block: 24px 28px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-layout {
    grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
    gap: 28px;
    align-items: start;
}
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-overview {
    overflow: hidden;
    padding: clamp(26px, 3vw, 40px);
    border-radius: 24px;
    color: #fff;
    background: linear-gradient(145deg, #173d25ef 20%, #173d25e6 65%, #173d25cc), url('/theme-demo/xd-shared/garden-2.jpg') center / cover;
}
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-heading { padding-bottom: 28px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-heading > span { color: var(--xd10-lime); }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-heading h1 {
    margin-block: 14px 18px;
    max-width: 440px;
    font-size: clamp(30px, 3vw, 42px);
    line-height: 1.2;
    color: #fff;
}
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-heading h1::after { display: none; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-heading p,
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-copy { color: #e1eadf; font-size: 14px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-info { color: #fff; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-info > h2 { color: #fff; font-size: 18px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-info > p { color: #c9d9c8; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-item { padding-block: 18px; border-color: #ffffff24; gap: 14px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-item > .tc-contact-icon { background: #ffffff0f; color: var(--xd10-lime); border-color: #ffffff26; border-radius: 12px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-item h3 { color: #c9d9c8; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-item p,
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-item a { color: #fff; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-item a[href^="tel:"] { font-size: 24px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-item .tc-contact-directions { color: var(--xd10-lime); }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-form-card {
    padding: clamp(26px, 3vw, 40px);
    border: 1px solid #dce4d8;
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 12px 40px #173d2508;
}
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-form-card h2 { font-size: 26px; color: var(--xd10-green-dark); }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-form-card > p { margin-bottom: 24px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-fields { gap: 18px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-fields input,
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-fields textarea { border-color: #dce4d8; border-radius: 10px; background: #fafbf8; padding: 12px 14px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-fields input:focus,
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-fields textarea:focus { background: #fff; border-color: var(--xd10-green); outline-color: #2f6b3b24; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-fields [aria-invalid=true] { border-color: #c43939; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-fields textarea { min-height: 132px; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-form-card button { width: 100%; margin-top: 22px; border-radius: 12px; background: var(--xd10-green-dark); color: #fff; }
html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-form-card button:hover { background: var(--xd10-green); }
html body main.tc-contact-page[data-contact-theme="XD0310"] :focus-visible { outline-color: var(--xd10-lime); }
@media (max-width: 800px) {
    html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-layout { grid-template-columns: 1fr; gap: 22px; }
    html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-heading h1 { max-width: none; }
}
@media (max-width: 480px) {
    html body main.tc-contact-page[data-contact-theme="XD0310"] { padding-bottom: 40px; }
    html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-overview,
    html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-form-card { padding: 24px 20px; border-radius: 18px; }
    html body main.tc-contact-page[data-contact-theme="XD0310"] .tc-contact-heading h1 { font-size: 30px; }
}
</style>

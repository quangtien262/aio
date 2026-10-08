<style>
.xd4-contact{background:#f4f6f2;scroll-margin-top:24px}
.xd4-contact__grid{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:64px;align-items:center}
.xd4-contact__copy{min-width:0}.xd4-contact__copy h2{font-size:clamp(28px,3vw,42px);font-weight:800;line-height:1.25;margin:0 0 20px;color:var(--xd4-ink)}
.xd4-contact__copy>p:not(.xd4-eyebrow){font-size:16px;line-height:1.85;color:var(--xd4-muted);max-width:520px}
.xd4-contact__info{display:grid;gap:20px;margin:28px 0 0;padding-top:24px;border-top:1px solid #dce3d5}.xd4-contact__info dt{font-size:12px;color:var(--xd4-muted);margin-bottom:6px}.xd4-contact__info dd{margin:0;font-size:15px;font-weight:600;overflow-wrap:anywhere}
.xd4-contact__card{padding:32px;background:#fff;border:1px solid #e1e7db;border-top:4px solid var(--xd4-green);box-shadow:0 16px 40px #22272908;border-radius:10px;min-width:0}
.xd4-contact__card h3{font-size:24px;font-weight:750;line-height:1.4;margin:0 0 8px}.xd4-contact__hint{font-size:13px;color:var(--xd4-muted);margin:0 0 24px}
.xd4-contact__fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.xd4-contact__fields>div{min-width:0}.xd4-contact__wide{grid-column:1/-1}
.xd4-contact__fields label{display:block;font-size:13px;font-weight:600;margin-bottom:8px}.xd4-contact__fields label span{color:#b42318}
.xd4-contact__fields input,.xd4-contact__fields textarea{display:block;width:100%;padding:12px 14px;border:1px solid #d7dfd1;background:#fbfcfa;border-radius:6px;color:var(--xd4-ink);font:inherit;font-size:15px;min-height:46px}
.xd4-contact__fields textarea{resize:vertical;min-height:120px}.xd4-contact__fields input:focus,.xd4-contact__fields textarea:focus{outline:2px solid var(--xd4-green);outline-offset:2px;border-color:var(--xd4-green)}
.xd4-contact__fields small{display:block;font-size:12px;color:var(--xd4-muted);margin-top:6px}.xd4-contact__fields .xd4-contact__error{color:#b42318}
.xd4-contact__form>.xd4-button{display:flex;justify-content:center;gap:16px;width:100%;border:0;border-radius:6px;margin-top:24px;color:var(--xd4-ink);cursor:pointer;font-size:14px;min-height:48px}.xd4-contact__form>.xd4-button:disabled{opacity:.7;cursor:wait}.xd4-contact__notice{padding:14px;border-radius:6px;background:#eef6e6;font-size:14px}
@media(max-width:900px){.xd4-contact__grid{grid-template-columns:minmax(0,1fr);gap:32px}.xd4-contact__copy>p:not(.xd4-eyebrow){max-width:none}}
@media(max-width:600px){.xd4-contact__card{padding:24px 20px}.xd4-contact__fields{grid-template-columns:minmax(0,1fr);gap:16px}.xd4-contact__copy h2{font-size:28px}}
.xd4-consultation{width:min(600px,calc(100% - 32px));max-height:calc(100dvh - 40px);margin:auto;overflow-y:auto;color:var(--xd4-ink);font-family:var(--theme-font-body)}
.xd4-consultation::backdrop{background:rgba(15,24,26,.7);backdrop-filter:blur(5px)}
.xd4-consultation__close{position:absolute;top:12px;right:12px;display:grid;place-items:center;width:32px;height:32px;border:0;border-radius:50%;background:#f1f4ee;color:var(--xd4-ink);font-size:24px;cursor:pointer}
.xd4-consultation h3{padding-right:26px}.xd4-consultation-open{overflow:hidden}
</style>

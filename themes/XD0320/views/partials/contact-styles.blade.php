<style>
.xd20-contact-section{padding:80px 0;background:#f5f5f3;color:var(--xd20-ink)}
.xd20-contact-shell{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:64px;align-items:center}
.xd20-contact-kicker{color:var(--xd20-red);text-transform:uppercase;font-size:12px;font-weight:700;letter-spacing:1.7px}
.xd20-contact-copy h2{font-size:clamp(28px,3vw,42px);line-height:1.25;margin:16px 0 22px;max-width:550px}
.xd20-contact-copy>p{color:#626970;line-height:1.85;font-size:15px;max-width:540px}
.xd20-contact-details{margin:32px 0 0}.xd20-contact-details>div{display:flex;gap:18px;padding:18px 0;border-top:1px solid #dfe2e4}
.xd20-contact-details i{color:var(--xd20-red);font-size:18px;margin-top:5px}.xd20-contact-details dt{font-size:12px;color:#697079;margin-bottom:6px}.xd20-contact-details dd{margin:0;font-size:15px;line-height:1.6;overflow-wrap:anywhere}.xd20-contact-details a{color:inherit;text-decoration:none}
.xd20-contact-card{background:#fff;padding:36px;border:1px solid #e5e7e9;border-top:3px solid var(--xd20-red);box-shadow:0 16px 48px #20232608;border-radius:4px}
.xd20-contact-card h3{font-size:23px;line-height:1.4;margin:0 0 8px}
.xd20-contact-hint{font-size:12px;color:#697079;margin:0 0 24px}
.xd20-contact-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px 18px}.xd20-contact-fields>div{min-width:0}.xd20-contact-wide{grid-column:1/-1}
.xd20-contact-form label{display:block;font-size:13px;font-weight:600;margin-bottom:8px;color:#30363a}.xd20-contact-form label span{color:var(--xd20-red)}
.xd20-contact-form input:not([type="hidden"]),.xd20-contact-form textarea{display:block;width:100%;padding:12px 13px;border:1px solid #dce0e3;border-radius:4px;background:#fafbfb;color:#202326;font:inherit;font-size:15px;line-height:1.5;transition:border-color .15s,box-shadow .15s}
.xd20-contact-form textarea{resize:vertical;min-height:110px}.xd20-contact-form input:focus,.xd20-contact-form textarea:focus{outline:0;border-color:var(--xd20-red);box-shadow:0 0 0 3px #e3291814;background:white}
.xd20-contact-form small{display:block;font-size:11px;line-height:1.6;color:#697079;margin-top:7px}.xd20-contact-form .xd20-contact-server-error{color:#b42318}
.xd20-contact-form .xd20-button{margin-top:24px;display:flex;width:100%;justify-content:center;gap:14px;border:0;cursor:pointer;border-radius:4px;min-height:48px;color:#fff}
.xd20-contact-form .xd20-button:disabled{opacity:.7;cursor:wait}.xd20-contact-success{background:#eaf7ef;color:#17653a;padding:12px;font-size:13px}
.xd20-contact-form[aria-busy="true"] .xd20-button::before{content:"";width:16px;height:16px;border:2px solid #ffffff70;border-top-color:#fff;border-radius:50%;animation:xd20-contact-spin .7s linear infinite}
@keyframes xd20-contact-spin{to{transform:rotate(360deg)}}
.xd20-consultation{width:min(600px,calc(100% - 32px));max-height:calc(100dvh - 40px);overflow-y:auto;padding:30px 32px;border:0;border-top:4px solid var(--xd20-red);border-radius:8px;color:var(--xd20-ink);box-shadow:0 24px 100px #0004}
.xd20-consultation::backdrop{background:#111920a6;backdrop-filter:blur(4px)}.xd20-consultation-open{overflow:hidden}
.xd20-consultation-top{display:flex;justify-content:space-between;align-items:center;gap:20px}.xd20-consultation-top button{border:0;background:#f0f2f3;border-radius:50%;width:36px;height:36px;font-size:25px;cursor:pointer;color:#30363a;flex-shrink:0}
.xd20-consultation h2{font-size:28px;line-height:1.3;margin:14px 0 12px}.xd20-consultation-intro{font-size:13px;line-height:1.8;color:#697079;margin:0 0 20px}
.xd20-consultation button:focus-visible,.xd20-contact-section a:focus-visible,.xd20-contact-form button:focus-visible{outline:3px solid var(--xd20-red);outline-offset:4px}
@media(max-width:900px){.xd20-contact-shell{grid-template-columns:1fr;gap:32px}.xd20-contact-section{padding:56px 0}}
@media(max-width:540px){.xd20-contact-card{padding:24px 20px}.xd20-contact-fields{grid-template-columns:1fr;gap:16px}.xd20-consultation{padding:22px 20px;max-height:calc(100dvh - 24px)}.xd20-consultation h2{font-size:24px}.xd20-contact-form input:not([type="hidden"]),.xd20-contact-form textarea{font-size:16px}}
@media(prefers-reduced-motion:reduce){.xd20-contact-form input,.xd20-contact-form textarea{transition:none}.xd20-contact-form[aria-busy="true"] .xd20-button::before{animation:none}}
</style>

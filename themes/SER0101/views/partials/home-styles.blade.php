.ser-home{padding-bottom:64px}
body:has(.ser-home) .ser-shared-footer{padding:48px 0}
body:has(.ser-home) .ser-shared-footer h4{font-size:18px;font-weight:750}
body:has(.ser-home) .ser-shared-footer p,body:has(.ser-home) .ser-shared-footer a{font-size:14px}
.ser-home .aio-landing-stack{gap:32px;padding-top:28px;--aio-deep:var(--ser-navy);--aio-accent:var(--ser-primary)}
.ser-home .aio-landing-block{border-radius:18px;border-color:var(--ser-line);background:var(--ser-surface);box-shadow:0 8px 28px #102a4308;scroll-margin-top:230px}
.ser-home .aio-landing-block-inner{padding:40px}
.ser-home .aio-landing-heading{max-width:800px;margin-bottom:28px}
.ser-home .aio-landing-kicker{font-size:11px;font-weight:700;letter-spacing:.12em;margin-bottom:12px}
.ser-home .aio-landing-title{font-size:clamp(28px,3vw,38px);font-weight:800;line-height:1.25;letter-spacing:-.025em;color:var(--ser-navy);overflow-wrap:anywhere}
.ser-home .aio-landing-summary{font-size:15px;line-height:1.85;color:var(--ser-muted);max-width:720px}
.ser-home .aio-landing-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
.ser-home .aio-landing-card{display:flex;flex-direction:column;min-width:0;border-radius:12px;border-color:var(--ser-line);transition:box-shadow .2s,border-color .2s}
.ser-home .aio-landing-card:hover{border-color:var(--ser-primary);box-shadow:0 10px 24px #102a4310}
.ser-home .aio-landing-card-media{aspect-ratio:16/10}
.ser-home .aio-landing-card-body{display:flex;flex-direction:column;flex:1;padding:22px}
.ser-home .aio-landing-card h3{font-size:20px;font-weight:750;line-height:1.45;color:var(--ser-navy);overflow-wrap:anywhere}
.ser-home .aio-landing-card p{font-size:14px;line-height:1.8;margin-top:10px;overflow-wrap:anywhere}
.ser-home-card-link{display:flex;justify-content:space-between;align-items:center;gap:16px;width:100%;margin-top:auto;padding-top:18px;border-top:1px solid var(--ser-line);color:var(--ser-primary);font-size:13px;font-weight:700;text-align:left}
.ser-home .aio-landing-card p+.ser-home-card-link{margin-top:20px}
.ser-home .aio-landing-price{color:var(--ser-navy);font-size:26px;font-weight:800;margin:20px 0}
.ser-home .aio-landing-hero{min-height:500px;align-items:center;background-color:var(--ser-night)}
.ser-home .aio-landing-hero::before{background:linear-gradient(90deg,#0b1b26ed,#0b1b2680 65%,#0b1b261a)}
.ser-home .aio-landing-hero .aio-landing-block-inner{max-width:800px;padding:56px 48px}
.ser-home .aio-landing-hero .aio-landing-title{font-size:clamp(34px,4vw,52px);color:#fff;line-height:1.15}
.ser-home .aio-landing-hero .aio-landing-kicker{color:var(--ser-accent)}
.ser-home .aio-landing-hero .aio-landing-summary{color:#e2e8f0;font-size:16px}
.ser-home-actions{display:flex;align-items:center;flex-wrap:wrap;gap:24px;margin-top:28px}
.ser-home .aio-landing-action{display:inline-flex;align-items:center;justify-content:center;gap:18px;margin-top:0;border-radius:10px;background:var(--ser-navy);font-size:14px;font-weight:700;min-height:48px;padding:12px 20px}
.ser-home .aio-landing-hero .aio-landing-action{background:var(--ser-accent);color:var(--ser-night)}
.ser-home-secondary{color:#fff;font-size:14px;font-weight:700;display:inline-flex;gap:18px;align-items:center}
.ser-home [data-block-type="process_steps"]{background:var(--ser-mist)}
.ser-home .aio-landing-step{position:relative;padding:0}
.ser-home .aio-landing-step::before{content:none;display:none}
.ser-home .aio-landing-step h3{display:flex;align-items:center;gap:12px}
.ser-home .aio-landing-step h3::before{counter-increment:aio-step;content:counter(aio-step,decimal-leading-zero);flex:0 0 42px;font-size:18px;font-weight:900;line-height:42px;border-radius:10px;background:var(--ser-navy);color:#fff;text-align:center}
.ser-home [data-block-type="collection_gallery"] .aio-landing-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
.ser-home [data-block-type="service_pricing"] .aio-landing-card-body{padding:28px}
.ser-home [data-block-type="service_pricing"] .aio-landing-grid,.ser-home [data-block-type="latest_posts"] .aio-landing-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
.ser-home-features{display:grid;gap:12px;margin:0 0 24px;padding:0;list-style:none;color:var(--ser-muted);font-size:14px;line-height:1.7}
.ser-home-features li{display:flex;gap:10px}.ser-home-features li::before{content:'✓';color:var(--ser-primary);font-weight:700}
.ser-home .ser-home-card-featured{border:2px solid var(--ser-accent);background:var(--ser-mist)}
.ser-home [data-block-type="latest_posts"] .aio-landing-card{border:0;border-radius:0}
.ser-home [data-block-type="latest_posts"] .aio-landing-card-media{border-radius:12px}
.ser-home [data-block-type="latest_posts"] .aio-landing-card-body{padding:20px 0 0}
.ser-home [data-block-type="landing_contact"]{background:var(--ser-navy);color:#fff;border:0}
.ser-home [data-block-type="landing_contact"] .aio-landing-title{color:#fff}.ser-home [data-block-type="landing_contact"] .aio-landing-summary{color:#d9e2ec}.ser-home [data-block-type="landing_contact"] .aio-landing-kicker{color:var(--ser-accent)}
.ser-home-contact-card{display:flex;align-items:center;justify-content:space-between;gap:28px;padding-top:24px;border-top:1px solid #ffffff26}
.ser-home-contact-card h3{font-size:20px;font-weight:700;margin-bottom:8px}.ser-home-contact-card p{color:#d9e2ec;font-size:14px;line-height:1.8;max-width:640px}
.ser-home .ser-home-contact-card .aio-landing-action{background:var(--ser-accent);color:var(--ser-night);flex-shrink:0}
.ser-home a:focus-visible,.ser-home button:focus-visible{outline:3px solid var(--ser-accent);outline-offset:4px}
.ser-home .section-head h2{font-weight:800;font-size:clamp(28px,3vw,38px)}.ser-home .category-grid,.ser-home .service-grid,.ser-home .post-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
@media(max-width:960px){.ser-home .aio-landing-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ser-home .aio-landing-block-inner{padding:32px}.ser-home-contact-card{align-items:flex-start;flex-direction:column}.ser-home .category-grid,.ser-home .service-grid,.ser-home .post-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:620px){.ser-home .aio-landing-grid,.ser-home [data-block-type="collection_gallery"] .aio-landing-grid,.ser-home [data-block-type="service_pricing"] .aio-landing-grid,.ser-home [data-block-type="latest_posts"] .aio-landing-grid,.ser-home .category-grid,.ser-home .service-grid,.ser-home .post-grid{grid-template-columns:minmax(0,1fr)}.ser-home .aio-landing-stack{gap:24px;padding-top:20px}.ser-home .aio-landing-block-inner,.ser-home .aio-landing-hero .aio-landing-block-inner{padding:28px 20px}.ser-home .aio-landing-hero{min-height:440px}.ser-home .aio-landing-title{font-size:28px}.ser-home .aio-landing-hero .aio-landing-title{font-size:34px}.ser-home .aio-landing-block{scroll-margin-top:20px}.ser-home-actions{gap:18px}.ser-home .ser-home-contact-card .aio-landing-action{width:100%}.ser-home .aio-landing-card-body{padding:20px}}
@media(prefers-reduced-motion:reduce){.ser-home .aio-landing-card,.ser-home .aio-landing-card-media img{transition:none}.ser-home .aio-landing-card:hover img{transform:none}}

<style>
.theme-news-listing{--news-accent:#244b60;--news-tint:color-mix(in srgb,var(--news-accent) 7%,white);color:#202326;background:#fff;font-family:var(--theme-font-body,"Segoe UI"),Arial,sans-serif;line-height:1.6;padding:36px 0 64px;margin:0;width:100%;max-width:none}
.theme-news-listing *{box-sizing:border-box}
.theme-news-listing .tnl-container{width:min(1240px,calc(100% - 48px));margin-inline:auto;min-width:0}
.theme-news-listing a,.theme-news-listing a:hover,.theme-news-listing a:visited{color:inherit;text-decoration:none}
.theme-news-listing a:focus-visible{outline:3px solid var(--news-accent);outline-offset:4px}
.theme-news-listing .tnl-breadcrumb{display:flex;flex-wrap:wrap;gap:12px;font-size:13px;color:#667079;margin-bottom:24px}
.theme-news-listing .tnl-heading{display:flex;justify-content:space-between;align-items:end;gap:28px;padding:0 0 26px;border-bottom:2px solid #202326;margin-bottom:32px;background:none;border-radius:0}
.theme-news-listing .tnl-heading h1{font-family:inherit;color:inherit;font-size:clamp(30px,3vw,46px);font-weight:750;line-height:1.2;letter-spacing:-1px;margin:8px 0 12px;overflow-wrap:anywhere;text-transform:none}
.theme-news-listing .tnl-heading p{font-size:15px;line-height:1.7;margin:0;color:#667079;max-width:760px}
.theme-news-listing .tnl-kicker{font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700;color:color-mix(in srgb,var(--news-accent) 65%,black)}
.theme-news-listing .tnl-count{display:flex;align-items:baseline;gap:6px;color:#667079;white-space:nowrap;padding:0;background:none;border-radius:0;font-size:12px}
.theme-news-listing .tnl-count strong{font-size:20px;font-weight:700;color:#202326}
.theme-news-listing .tnl-lead-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:28px 36px;align-items:start}
.theme-news-listing .tnl-lead-grid:has(>article:nth-child(3))>.tnl-card--lead{grid-row:span 2}
.theme-news-listing .tnl-lead-grid--single{grid-template-columns:minmax(0,1fr)}
.theme-news-listing .tnl-card{display:grid;grid-template-columns:minmax(120px,.75fr) minmax(0,1.25fr);gap:22px;min-width:0;background:none;border:0;border-bottom:1px solid #e1e5e8;border-radius:0;padding:0 0 24px;overflow:visible}
.theme-news-listing .tnl-card--lead{display:block;padding:0 36px 0 0;border-bottom:0;border-right:1px solid #e1e5e8}
.theme-news-listing .tnl-lead-grid--single .tnl-card--lead{padding:0;border-right:0;display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);align-items:center;gap:36px}
.theme-news-listing .tnl-image{display:grid;place-items:center;aspect-ratio:4/3;overflow:hidden;background:var(--news-tint);align-self:start;border-radius:3px}
.theme-news-listing .tnl-card--lead .tnl-image{aspect-ratio:16/9;margin-bottom:22px}
.theme-news-listing .tnl-lead-grid--single .tnl-image{margin-bottom:0}
.theme-news-listing .tnl-image img{display:block;width:100%;height:100%;max-width:100%;object-fit:cover;transition:transform .2s}
.theme-news-listing .tnl-image:hover img{transform:scale(1.025)}
.theme-news-listing .tnl-image>span{font-size:38px;color:var(--news-accent);opacity:.55}
.theme-news-listing .tnl-body{min-width:0;padding:0}
.theme-news-listing .tnl-meta{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;font-size:11px;color:#667079}
.theme-news-listing .tnl-meta>span{font-weight:650;color:color-mix(in srgb,var(--news-accent) 65%,black)}
.theme-news-listing h2{font-family:inherit;color:inherit;font-size:20px;font-weight:700;line-height:1.45;letter-spacing:-.3px;text-transform:none;margin:8px 0 12px;overflow-wrap:anywhere}
.theme-news-listing .tnl-card--lead h2{font-family:Georgia,"Times New Roman",serif;font-size:clamp(26px,2.3vw,34px);font-weight:700;line-height:1.3;letter-spacing:-.5px}
.theme-news-listing .tnl-body p{margin:0 0 16px;font-size:14px;line-height:1.8;color:#667079;overflow-wrap:anywhere}
.theme-news-listing .tnl-more{display:inline-flex;align-items:center;gap:14px;padding:0;margin:0;border:0;font-size:12px;font-weight:650}
.theme-news-listing .tnl-more:hover,.theme-news-listing h2 a:hover{color:color-mix(in srgb,var(--news-accent) 65%,black)}
.theme-news-listing .tnl-feed{margin-top:40px;padding-top:24px;border-top:2px solid #202326}
.theme-news-listing .tnl-section-heading{font-family:inherit;font-size:22px;margin:0 0 24px}
.theme-news-listing .tnl-feed-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:26px 36px}
.theme-news-listing .tnl-card--list{grid-template-columns:144px minmax(0,1fr)}
.theme-news-listing .tnl-pagination{display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:20px;margin-top:36px;padding-top:24px;border-top:1px solid #e1e5e8;font-size:14px}
.theme-news-listing .tnl-pagination a,.theme-news-listing .tnl-empty>a{display:inline-block;padding:10px 18px;border:1px solid #d6dce0;border-radius:3px;background:white}
.theme-news-listing .tnl-empty{padding:56px 24px;text-align:center;background:#f7f8f9;border:1px dashed #d6dce0}
@media(max-width:1050px){.theme-news-listing .tnl-lead-grid{grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);gap:26px}.theme-news-listing .tnl-card--lead{padding-right:26px}.theme-news-listing .tnl-card--secondary{grid-template-columns:112px minmax(0,1fr);gap:16px}.theme-news-listing .tnl-feed-grid{grid-template-columns:minmax(0,1fr)}}
@media(max-width:760px){.theme-news-listing .tnl-lead-grid,.theme-news-listing .tnl-lead-grid--single,.theme-news-listing .tnl-lead-grid--single .tnl-card--lead{grid-template-columns:minmax(0,1fr)}.theme-news-listing .tnl-card--lead{padding:0 0 26px;border-right:0;border-bottom:1px solid #e1e5e8}.theme-news-listing .tnl-lead-grid:has(>article:nth-child(3))>.tnl-card--lead{grid-row:auto}.theme-news-listing .tnl-lead-grid--single .tnl-image{margin-bottom:22px}.theme-news-listing .tnl-card--secondary{grid-template-columns:144px minmax(0,1fr)}.theme-news-listing .tnl-heading{align-items:start;flex-direction:column;gap:14px}.theme-news-listing .tnl-feed{margin-top:28px}}
@media(max-width:540px){.theme-news-listing{padding:24px 0 44px}.theme-news-listing .tnl-container{width:calc(100% - 28px)}.theme-news-listing .tnl-heading{margin-bottom:26px}.theme-news-listing .tnl-card--secondary,.theme-news-listing .tnl-card--list{grid-template-columns:104px minmax(0,1fr);gap:16px}.theme-news-listing .tnl-card--lead h2{font-size:27px}.theme-news-listing h2{font-size:17px}.theme-news-listing .tnl-card--secondary .tnl-body p,.theme-news-listing .tnl-card--list .tnl-body p{font-size:12px;line-height:1.7}.theme-news-listing .tnl-meta{font-size:10px}.theme-news-listing .tnl-feed-grid{gap:24px}}
@media(prefers-reduced-motion:reduce){.theme-news-listing .tnl-image img{transition:none}}
.theme-news-listing[data-news-theme="AUTO850"]{--news-accent:var(--a850-red,#e10600)}
.theme-news-listing[data-news-theme="AUTO851"]{--news-accent:var(--a851-navy,#1c3268)}
.theme-news-listing[data-news-theme="AUTO852"]{--news-accent:var(--a852-orange,#ff5a00)}
.theme-news-listing[data-news-theme="AUTO853"]{--news-accent:var(--a853-red,#ed2f3b)}
.theme-news-listing[data-news-theme="BDS701"]{--news-accent:var(--bds-green,#84c441)}
.theme-news-listing[data-news-theme="BOOK920"]{--news-accent:var(--book20-teal,#08728c)}
.theme-news-listing[data-news-theme="BZ501"]{--news-accent:var(--bz-red,#ff3216)}
.theme-news-listing[data-news-theme="CA0050"]{--news-accent:var(--ca-blue,#0887df)}
.theme-news-listing[data-news-theme="DL750"]{--news-accent:var(--dl-olive,#3f5f2c)}
.theme-news-listing[data-news-theme="DN202"]{--news-accent:var(--d202-blue,#3799ee)}
.theme-news-listing[data-news-theme="DN302"]{--news-accent:var(--dn-navy,#465474)}
.theme-news-listing[data-news-theme="DN350"]{--news-accent:var(--dn350-orange,#ff9d10)}
.theme-news-listing[data-news-theme="DN351"]{--news-accent:var(--dn351-red,#c8212a)}
.theme-news-listing[data-news-theme="E800"]{--news-accent:var(--e800-orange,#ff773d)}
.theme-news-listing[data-news-theme="E801"]{--news-accent:var(--pink,#ed0060)}
.theme-news-listing[data-news-theme="E802"]{--news-accent:var(--orange,#ffae25)}
.theme-news-listing[data-news-theme="E803"]{--news-accent:var(--green,#72c800)}
.theme-news-listing[data-news-theme="E804"]{--news-accent:var(--e804-red,#f02f49)}
.theme-news-listing[data-news-theme="E805"]{--news-accent:var(--e805-red,#ef3e35)}
.theme-news-listing[data-news-theme="E806"]{--news-accent:var(--e806-red,#df3035)}
.theme-news-listing[data-news-theme="E807"]{--news-accent:var(--e807-orange,#f45b0b)}
.theme-news-listing[data-news-theme="EC900"]{--news-accent:var(--ec9-blue,#177be6)}
.theme-news-listing[data-news-theme="EC901"]{--news-accent:var(--ec91-red,#bd1015)}
.theme-news-listing[data-news-theme="EC902"]{--news-accent:var(--ec92-blue,#071b9b)}
.theme-news-listing[data-news-theme="EC903"]{--news-accent:var(--ec93-red,#ed1c24)}
.theme-news-listing[data-news-theme="EC904"]{--news-accent:var(--ec94-red,#ef3c33)}
.theme-news-listing[data-news-theme="EC905"]{--news-accent:var(--ec95-blue,#315d8c)}
.theme-news-listing[data-news-theme="EC906"]{--news-accent:var(--ec96-green,#00763c)}
.theme-news-listing[data-news-theme="EC907"]{--news-accent:var(--r,#f3182b)}
.theme-news-listing[data-news-theme="EC908"]{--news-accent:var(--orange,#ff5037)}
.theme-news-listing[data-news-theme="EC909"]{--news-accent:var(--ink,#080808)}
.theme-news-listing[data-news-theme="EC910"]{--news-accent:var(--ec10-gold,#dba621)}
.theme-news-listing[data-news-theme="EC911"]{--news-accent:var(--ec11-blue,#2f5be7)}
.theme-news-listing[data-news-theme="EC912"]{--news-accent:var(--ec12-red,#c9142a)}
.theme-news-listing[data-news-theme="EC913"]{--news-accent:var(--ec13-red,#c9142a)}
.theme-news-listing[data-news-theme="EC914"]{--news-accent:var(--ec14-orange,#f47a26)}
.theme-news-listing[data-news-theme="EC915"]{--news-accent:var(--ec15-copper,#b97c58)}
.theme-news-listing[data-news-theme="EC916"]{--news-accent:var(--ec16-teal,#16b6a3)}
.theme-news-listing[data-news-theme="EC917"]{--news-accent:var(--ec17-orange,#f57408)}
.theme-news-listing[data-news-theme="FOOT401"]{--news-accent:var(--foot-gold,#d6a62d)}
.theme-news-listing[data-news-theme="FOOT403"]{--news-accent:var(--dr-green,#0d3b35)}
.theme-news-listing[data-news-theme="FOOT404"]{--news-accent:var(--f404-primary,#4c50b9)}
.theme-news-listing[data-news-theme="FOOT405"]{--news-accent:var(--f405-green,#38ba82)}
.theme-news-listing[data-news-theme="FOOT406"]{--news-accent:var(--f406-green,#9bc24b)}
.theme-news-listing[data-news-theme="FOOT407"]{--news-accent:var(--f407-green,#164c3f)}
.theme-news-listing[data-news-theme="FOOT408"]{--news-accent:var(--f408-red,#e90025)}
.theme-news-listing[data-news-theme="FOOT409"]{--news-accent:var(--f409-red,#ed002b)}
.theme-news-listing[data-news-theme="NEWS88"]{--news-accent:var(--n88-red,#e4002b)}
.theme-news-listing[data-news-theme="NT501"]{--news-accent:var(--nt-gold,#dbae56)}
.theme-news-listing[data-news-theme="NT502"]{--news-accent:var(--n502-orange,#ff9f16)}
.theme-news-listing[data-news-theme="NT503"]{--news-accent:var(--n503-green,#064f38)}
.theme-news-listing[data-news-theme="NT504"]{--news-accent:var(--n504-green,#174f40)}
.theme-news-listing[data-news-theme="SER0101"]{--news-accent:var(--ser-primary,#0f766e)}
.theme-news-listing[data-news-theme="SER102"]{--news-accent:var(--ser-orange,#ff5a00)}
.theme-news-listing[data-news-theme="SER103"]{--news-accent:var(--ser103-accent,#ad8d89)}
.theme-news-listing[data-news-theme="SHOP601"]{--news-accent:var(--s601-red,#ef3f24)}
.theme-news-listing[data-news-theme="SHOP602"]{--news-accent:var(--s602-green,#174f3f)}
.theme-news-listing[data-news-theme="SHOP603"]{--news-accent:var(--s603-green,#176044)}
.theme-news-listing[data-news-theme="SHOP604"]{--news-accent:var(--s604-rose,#be5a5d)}
.theme-news-listing[data-news-theme="SHOP605"]{--news-accent:var(--p,#ff6387)}
.theme-news-listing[data-news-theme="SPA111"]{--news-accent:var(--sp11-teal,#176c6c)}
.theme-news-listing[data-news-theme="SPA502"]{--news-accent:var(--spa-gold,#ffb400)}
.theme-news-listing[data-news-theme="TH0050"]{--news-accent:var(--th5-green,#064638)}
.theme-news-listing[data-news-theme="TOOL750"]{--news-accent:var(--t750-red,#e42838)}
.theme-news-listing[data-news-theme="TOOL751"]{--news-accent:var(--t751-yellow,#ffbf00)}
.theme-news-listing[data-news-theme="XD0301"]{--news-accent:var(--lime-dark,#9fb500)}
.theme-news-listing[data-news-theme="XD0302"]{--news-accent:var(--xd2-red,#ef392a)}
.theme-news-listing[data-news-theme="XD0303"]{--news-accent:var(--xd3-orange,#ef4c23)}
.theme-news-listing[data-news-theme="XD0304"]{--news-accent:var(--xd4-green,#91bf3d)}
.theme-news-listing[data-news-theme="XD0305"]{--news-accent:var(--gold,#f0a629)}
.theme-news-listing[data-news-theme="XD0306"]{--news-accent:var(--gold,#e90025)}
.theme-news-listing[data-news-theme="XD0307"]{--news-accent:var(--gold,#f0a629)}
.theme-news-listing[data-news-theme="XD0308"]{--news-accent:var(--xd4-green,#91bf3d)}
.theme-news-listing[data-news-theme="XD0309"]{--news-accent:var(--gold,#f0a629)}
.theme-news-listing[data-news-theme="XD0310"]{--news-accent:var(--gold,#f0a629)}
.theme-news-listing[data-news-theme="XD0311"]{--news-accent:var(--gold,#f0a629)}
.theme-news-listing[data-news-theme="XD0312"]{--news-accent:var(--gold,#f0a629)}
.theme-news-listing[data-news-theme="XD0313"]{--news-accent:var(--rx13-green,#7bd615)}
.theme-news-listing[data-news-theme="XD0314"]{--news-accent:var(--bb14-navy,#07142c)}
.theme-news-listing[data-news-theme="XD0315"]{--news-accent:var(--af15-orange,#f47c00)}
.theme-news-listing[data-news-theme="XD0318"]{--news-accent:var(--fg18-orange,#f4512a)}
.theme-news-listing[data-news-theme="XD0320"]{--news-accent:var(--xd20-red,#e32918)}
.theme-news-listing[data-news-theme="XD0322"]{--news-accent:var(--o,#ff5b17)}
.theme-news-listing[data-news-theme="XD0323"]{--news-accent:var(--xd323-green,#07823f)}
.theme-news-listing[data-news-theme="XD0324"]{--news-accent:var(--wolf-gold,#c9a57d)}
.theme-news-listing[data-news-theme="XD0325"]{--news-accent:var(--x325-orange,#ff5b0b)}
.theme-news-listing[data-news-theme="XD321"]{--news-accent:var(--b,#153d78)}
/* Overlay headers must occupy space on news pages, without changing homepages. */
body:has(.theme-news-listing[data-news-theme="BDS701"]) .bds-header{position:relative;top:auto;background:#1f2d40}
body:has(.theme-news-listing[data-news-theme="BDS702"]) .bds-header{position:relative;top:auto;background:#1f2d40}
body:has(.theme-news-listing[data-news-theme="DN302"]) .dn-header{position:relative;top:auto;background:#fff}
body:has(.theme-news-listing[data-news-theme="EC915"]) .ec15-header{position:relative;top:auto;background:#24201d}
body:has(.theme-news-listing[data-news-theme="FOOT403"]) .dr-header{position:relative;top:auto;background:#173d37}
body:has(.theme-news-listing[data-news-theme="FOOT408"]) .f408-mainbar{position:relative;top:auto;background:#fff}
body:has(.theme-news-listing[data-news-theme="XD0304"]) .xd4-header{position:relative;top:auto;background:var(--xd4-ink,#202326)}
body:has(.theme-news-listing[data-news-theme="XD0305"]) .xd5-header{position:relative;top:auto;background:#202326}
body:has(.theme-news-listing[data-news-theme="XD0306"]) .xd5-header{position:relative;top:auto;background:#202326}
body:has(.theme-news-listing[data-news-theme="XD0308"]) .xd4-header{position:relative;top:auto;background:var(--xd4-ink,#202326)}
body:has(.theme-news-listing[data-news-theme="XD0310"]) .xd5-header{position:relative;top:auto;background:#202326}
body:has(.theme-news-listing[data-news-theme="XD0312"]) .xd12-header{position:relative;top:auto;background:#fff}
body:has(.theme-news-listing[data-news-theme="XD0315"]) .af15-site-header{position:relative;top:auto;background:#202326}
body:has(.theme-news-listing[data-news-theme="XD0323"]) .xd323-header{position:relative;top:auto;background:#fff}
body:has(.theme-news-listing[data-news-theme="XD0324"]) .xd324-header{position:relative;top:auto;background:#202326}
body:has(.theme-news-listing[data-news-theme="XD0325"]) .x325-nav{position:relative;top:auto;background:#fff}
body:has(.theme-news-listing) .f406-header__shell{margin-bottom:0}
.theme-news-listing .tnl-topic-image{display:block;width:100%;max-height:360px;object-fit:cover;border-radius:10px;margin-bottom:28px}
.theme-news-listing .tnl-query{margin-top:10px;font-weight:600;overflow-wrap:anywhere}
body:has(.theme-news-listing[data-news-theme="FOOT401"]) .foot-navigation-wrap{height:auto}
body:has(.theme-news-listing[data-news-theme="FOOT401"]) .foot-navigation{position:relative;left:auto;transform:none;margin-inline:auto}
</style>

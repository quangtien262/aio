<span hidden class="contact-theme-marker" data-contact-theme="{{ data_get($activeTheme ?? [], 'key') }}"></span>
<style>
.tc-contact-icon svg{width:22px;height:22px}
.tc-contact-page,.tc-contact-page *{box-sizing:border-box}.tc-contact-page a{text-decoration:none}.tc-contact-copy{margin-bottom:28px;line-height:1.8;overflow-wrap:anywhere}.tc-contact-copy img,.tc-contact-copy iframe{max-width:100%}

/* Shared page layout has priority over legacy theme card styling. Theme colors remain configurable. */
html body .tc-contact-page[data-contact-theme]{--tc-accent:var(--f404-primary,var(--primary,#315b65));--tc-ink:#172832;--tc-muted:#657079;padding:0 0 80px;background:linear-gradient(120deg,#fafbfc 55%,#f1f4f5);color:var(--tc-ink);font:400 15px/1.7 "Be Vietnam Pro","Segoe UI",Arial,sans-serif;text-align:left}
.tc-contact-container{width:min(1160px,calc(100% - 48px));margin:auto}
body:has(.tc-contact-page[data-contact-theme=""]) .site-header{padding-inline:max(24px,calc((100% - 1160px)/2))}
@media(max-width:480px){body:has(.tc-contact-page[data-contact-theme=""]) .site-header{padding-inline:16px}}
html body .tc-contact-page .tc-contact-breadcrumb{display:flex;gap:12px;flex-wrap:wrap;padding:26px 0 36px;font-size:12px;color:var(--tc-muted)}
html body .tc-contact-page .tc-contact-breadcrumb a{color:inherit}
html body .tc-contact-page .tc-contact-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.08fr);gap:clamp(32px,6vw,88px);align-items:start}
html body .tc-contact-page .tc-contact-overview{min-width:0;padding-top:12px}
html body .tc-contact-page .tc-contact-heading{max-width:560px;padding:0 0 32px}
html body .tc-contact-page .tc-contact-heading>span{display:block;color:var(--tc-muted);font-size:11px;font-weight:700;line-height:1.5;letter-spacing:.12em;text-transform:uppercase;overflow-wrap:anywhere}
html body .tc-contact-page .tc-contact-heading h1{font:700 clamp(32px,3.2vw,48px)/1.18 "Be Vietnam Pro","Segoe UI",Arial,sans-serif;color:var(--tc-ink);text-transform:none;letter-spacing:-.04em;margin:16px 0 22px;max-width:460px}
html body .tc-contact-page .tc-contact-heading h1:after{content:"";display:block;width:52px;height:4px;background:var(--tc-accent);margin-top:24px}
html body .tc-contact-page .tc-contact-heading p{display:block;max-width:490px;font-size:15px;line-height:1.8;color:var(--tc-muted);margin:0}
html body .tc-contact-page .tc-contact-copy{background:transparent;border:0;border-radius:0;box-shadow:none;padding:0;margin:0 0 28px;color:var(--tc-muted);font-size:14px;line-height:1.8}
html body .tc-contact-page .tc-contact-info{padding:0;background:transparent;border:0;border-radius:0;box-shadow:none;color:var(--tc-ink)}
html body .tc-contact-page .tc-contact-layout h2{font:700 23px/1.35 "Be Vietnam Pro","Segoe UI",Arial,sans-serif;letter-spacing:-.02em;text-transform:none;margin:0 0 10px;color:var(--tc-ink)}
html body .tc-contact-page .tc-contact-info>h2{font-size:17px;margin:0 0 6px}
html body .tc-contact-page .tc-contact-info>p{font-size:13px;color:var(--tc-muted);margin:0 0 16px;line-height:1.7}
html body .tc-contact-page .tc-contact-item{display:flex;align-items:start;gap:16px;padding:20px 0;border-top:1px solid #dde3e6}
html body .tc-contact-page .tc-contact-item:last-child{padding-bottom:0}
html body .tc-contact-page .tc-contact-item>.tc-contact-icon{display:grid;place-items:center;flex:0 0 44px;width:44px;height:44px;background:#fff;color:var(--tc-ink);border:1px solid #e0e5e8;border-radius:50%}
html body .tc-contact-page .tc-contact-item h3{font-size:12px;font-weight:500;line-height:1.5;color:var(--tc-muted);margin:0 0 5px;letter-spacing:normal;text-transform:none}
html body .tc-contact-page .tc-contact-item p,html body .tc-contact-page .tc-contact-item a{font-size:15px;font-weight:500;line-height:1.7;color:var(--tc-ink);margin:0;overflow-wrap:anywhere}
html body .tc-contact-page .tc-contact-item a[href^="tel:"]{font-size:24px;line-height:1.3;font-weight:700;letter-spacing:-.02em}
html body .tc-contact-page .tc-contact-item>div{min-width:0}
html body .tc-contact-page .tc-contact-item .tc-contact-directions{display:inline-block;margin-top:8px;font-size:12px;font-weight:700;text-decoration:underline;text-underline-offset:4px;color:var(--tc-ink)}
html body .tc-contact-page .tc-contact-form-card{padding:clamp(24px,3vw,40px);border:1px solid #e1e6e8;border-top:4px solid var(--tc-accent);border-radius:12px;background:#fff;box-shadow:0 18px 60px #192e3b0b;min-width:0}
html body .tc-contact-page .tc-contact-form-card>p{font-size:12px;line-height:1.7;color:var(--tc-muted);margin:0 0 28px}
html body .tc-contact-page .tc-contact-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px 18px}
html body .tc-contact-page .tc-contact-fields>div{min-width:0;grid-column:auto}
html body .tc-contact-page .tc-contact-fields>.tc-contact-field-wide:has(textarea){grid-column:1/-1}
html body .tc-contact-page .tc-contact-fields label{display:block;font-size:12px;font-weight:600;color:var(--tc-ink);margin:0 0 8px}
html body .tc-contact-page .tc-contact-fields input,html body .tc-contact-page .tc-contact-fields textarea{display:block;width:100%;min-width:0;height:auto;max-width:100%;border:1px solid #d7dfe3;background:#fafbfc;border-radius:6px;padding:12px 14px;color:var(--tc-ink);font:14px/1.6 "Be Vietnam Pro","Segoe UI",Arial,sans-serif;box-shadow:none}
html body .tc-contact-page .tc-contact-fields textarea{resize:vertical;float:none;min-height:154px}
html body .tc-contact-page .tc-contact-fields input:focus,html body .tc-contact-page .tc-contact-fields textarea:focus{background:#fff;border-color:var(--tc-ink);outline:2px solid #17283218;outline-offset:2px}
html body .tc-contact-page .tc-contact-fields small{display:block;margin-top:8px;font-size:11px;line-height:1.6;color:var(--tc-muted)}
html body .tc-contact-page .tc-contact-fields [aria-invalid=true]{border-color:#c43939}
html body .tc-contact-page .tc-contact-form-card button{display:inline-flex;align-items:center;justify-content:center;gap:26px;min-height:48px;height:auto;max-width:100%;padding:14px 26px;margin:24px 0 0;background:var(--tc-ink);color:#fff;border:0;border-radius:6px;font:600 14px "Be Vietnam Pro","Segoe UI",Arial,sans-serif;cursor:pointer;transition:background .2s,box-shadow .2s}
html body .tc-contact-page .tc-contact-form-card button:hover{background:#304954;box-shadow:0 6px 18px #1728321a}
html body .tc-contact-page :focus-visible{outline:3px solid var(--tc-ink);outline-offset:3px}
.tc-contact-notice,.tc-contact-error{padding:16px;margin-bottom:22px;border-radius:6px;font-size:13px;line-height:1.8}.tc-contact-notice{background:#eaf8ee;color:#216a39}.tc-contact-error{background:#fff1ef;color:#a72b28}.tc-contact-error ul{margin:8px 0 0;padding-left:20px}
@media(max-width:800px){html body .tc-contact-page .tc-contact-layout{grid-template-columns:1fr;gap:32px}html body .tc-contact-page .tc-contact-overview{padding-top:0}html body .tc-contact-page .tc-contact-heading{max-width:none}html body .tc-contact-page .tc-contact-heading h1{max-width:none;font-size:36px}html body .tc-contact-page .tc-contact-info{max-width:none}html body .tc-contact-page .tc-contact-breadcrumb{padding-bottom:24px}}
@media(max-width:480px){.tc-contact-container{width:calc(100% - 32px)}html body .tc-contact-page[data-contact-theme]{padding-bottom:40px}html body .tc-contact-page .tc-contact-heading h1{font-size:32px}html body .tc-contact-page .tc-contact-form-card{padding:24px 20px}html body .tc-contact-page .tc-contact-fields{grid-template-columns:1fr;gap:18px}html body .tc-contact-page .tc-contact-form-card button{width:100%}html body .tc-contact-page .tc-contact-breadcrumb{padding-top:20px}}
html body .tc-contact-page[data-contact-theme="AUTO850"]{--tc-accent:var(--a850-red,#e10600)}
html body .tc-contact-page[data-contact-theme="AUTO851"]{--tc-accent:var(--a851-navy,#1c3268)}
html body .tc-contact-page[data-contact-theme="AUTO852"]{--tc-accent:var(--a852-orange,#ff5a00)}
html body .tc-contact-page[data-contact-theme="AUTO853"]{--tc-accent:var(--a853-red,#ed2f3b)}
html body .tc-contact-page[data-contact-theme="BDS701"]{--tc-accent:var(--bds-green,#84c441)}
html body .tc-contact-page[data-contact-theme="BOOK920"]{--tc-accent:var(--book20-teal,#08728c)}
html body .tc-contact-page[data-contact-theme="BZ501"]{--tc-accent:var(--bz-red,#ff3216)}
html body .tc-contact-page[data-contact-theme="CA0050"]{--tc-accent:var(--ca-blue,#0887df)}
html body .tc-contact-page[data-contact-theme="DL750"]{--tc-accent:var(--dl-olive,#3f5f2c)}
html body .tc-contact-page[data-contact-theme="DN202"]{--tc-accent:var(--d202-blue,#3799ee)}
html body .tc-contact-page[data-contact-theme="DN302"]{--tc-accent:var(--dn-navy,#465474)}
html body .tc-contact-page[data-contact-theme="DN350"]{--tc-accent:var(--dn350-orange,#ff9d10)}
html body .tc-contact-page[data-contact-theme="DN351"]{--tc-accent:var(--dn351-red,#c8212a)}
html body .tc-contact-page[data-contact-theme="E800"]{--tc-accent:var(--e800-orange,#ff773d)}
html body .tc-contact-page[data-contact-theme="E801"]{--tc-accent:var(--pink,#ed0060)}
html body .tc-contact-page[data-contact-theme="E802"]{--tc-accent:var(--orange,#ffae25)}
html body .tc-contact-page[data-contact-theme="E803"]{--tc-accent:var(--green,#72c800)}
html body .tc-contact-page[data-contact-theme="E804"]{--tc-accent:var(--e804-red,#f02f49)}
html body .tc-contact-page[data-contact-theme="E805"]{--tc-accent:var(--e805-red,#ef3e35)}
html body .tc-contact-page[data-contact-theme="E806"]{--tc-accent:var(--e806-red,#df3035)}
html body .tc-contact-page[data-contact-theme="E807"]{--tc-accent:var(--e807-orange,#f45b0b)}
html body .tc-contact-page[data-contact-theme="EC900"]{--tc-accent:var(--ec9-blue,#177be6)}
html body .tc-contact-page[data-contact-theme="EC901"]{--tc-accent:var(--ec91-red,#bd1015)}
html body .tc-contact-page[data-contact-theme="EC902"]{--tc-accent:var(--ec92-blue,#071b9b)}
html body .tc-contact-page[data-contact-theme="EC903"]{--tc-accent:var(--ec93-red,#ed1c24)}
html body .tc-contact-page[data-contact-theme="EC904"]{--tc-accent:var(--ec94-red,#ef3c33)}
html body .tc-contact-page[data-contact-theme="EC905"]{--tc-accent:var(--ec95-blue,#315d8c)}
html body .tc-contact-page[data-contact-theme="EC906"]{--tc-accent:var(--ec96-green,#00763c)}
html body .tc-contact-page[data-contact-theme="EC907"]{--tc-accent:var(--r,#f3182b)}
html body .tc-contact-page[data-contact-theme="EC908"]{--tc-accent:var(--orange,#ff5037)}
html body .tc-contact-page[data-contact-theme="EC909"]{--tc-accent:var(--ink,#080808)}
html body .tc-contact-page[data-contact-theme="EC910"]{--tc-accent:var(--ec10-gold,#dba621)}
html body .tc-contact-page[data-contact-theme="EC911"]{--tc-accent:var(--ec11-blue,#2f5be7)}
html body .tc-contact-page[data-contact-theme="EC912"]{--tc-accent:var(--ec12-red,#c9142a)}
html body .tc-contact-page[data-contact-theme="EC913"]{--tc-accent:var(--ec13-red,#c9142a)}
html body .tc-contact-page[data-contact-theme="EC914"]{--tc-accent:var(--ec14-orange,#f47a26)}
html body .tc-contact-page[data-contact-theme="EC915"]{--tc-accent:var(--ec15-copper,#b97c58)}
html body .tc-contact-page[data-contact-theme="EC916"]{--tc-accent:var(--ec16-teal,#16b6a3)}
html body .tc-contact-page[data-contact-theme="EC917"]{--tc-accent:var(--ec17-orange,#f57408)}
html body .tc-contact-page[data-contact-theme="FOOT401"]{--tc-accent:var(--foot-gold,#d6a62d)}
html body .tc-contact-page[data-contact-theme="FOOT403"]{--tc-accent:var(--dr-green,#0d3b35)}
html body .tc-contact-page[data-contact-theme="FOOT404"]{--tc-accent:var(--f404-primary,#4c50b9)}
html body .tc-contact-page[data-contact-theme="FOOT405"]{--tc-accent:var(--f405-green,#38ba82)}
html body .tc-contact-page[data-contact-theme="FOOT406"]{--tc-accent:var(--f406-green,#9bc24b)}
html body .tc-contact-page[data-contact-theme="FOOT407"]{--tc-accent:var(--f407-green,#164c3f)}
html body .tc-contact-page[data-contact-theme="FOOT408"]{--tc-accent:var(--f408-red,#e90025)}
html body .tc-contact-page[data-contact-theme="FOOT409"]{--tc-accent:var(--f409-red,#ed002b)}
html body .tc-contact-page[data-contact-theme="NEWS88"]{--tc-accent:var(--n88-red,#e4002b)}
html body .tc-contact-page[data-contact-theme="NT501"]{--tc-accent:var(--nt-gold,#dbae56)}
html body .tc-contact-page[data-contact-theme="NT502"]{--tc-accent:var(--n502-orange,#ff9f16)}
html body .tc-contact-page[data-contact-theme="NT503"]{--tc-accent:var(--n503-green,#064f38)}
html body .tc-contact-page[data-contact-theme="NT504"]{--tc-accent:var(--n504-green,#174f40)}
html body .tc-contact-page[data-contact-theme="SER0101"]{--tc-accent:var(--ser-primary,#0f766e)}
html body .tc-contact-page[data-contact-theme="SER102"]{--tc-accent:var(--ser-orange,#ff5a00)}
html body .tc-contact-page[data-contact-theme="SER103"]{--tc-accent:var(--ser103-accent,#ad8d89)}
html body .tc-contact-page[data-contact-theme="SHOP601"]{--tc-accent:var(--s601-red,#ef3f24)}
html body .tc-contact-page[data-contact-theme="SHOP602"]{--tc-accent:var(--s602-green,#174f3f)}
html body .tc-contact-page[data-contact-theme="SHOP603"]{--tc-accent:var(--s603-green,#176044)}
html body .tc-contact-page[data-contact-theme="SHOP604"]{--tc-accent:var(--s604-rose,#be5a5d)}
html body .tc-contact-page[data-contact-theme="SHOP605"]{--tc-accent:var(--p,#ff6387)}
html body .tc-contact-page[data-contact-theme="SPA111"]{--tc-accent:var(--sp11-teal,#176c6c)}
html body .tc-contact-page[data-contact-theme="SPA502"]{--tc-accent:var(--spa-gold,#ffb400)}
html body .tc-contact-page[data-contact-theme="TH0050"]{--tc-accent:var(--th5-green,#064638)}
html body .tc-contact-page[data-contact-theme="TOOL750"]{--tc-accent:var(--t750-red,#e42838)}
html body .tc-contact-page[data-contact-theme="TOOL751"]{--tc-accent:var(--t751-yellow,#ffbf00)}
html body .tc-contact-page[data-contact-theme="XD0301"]{--tc-accent:var(--lime-dark,#9fb500)}
html body .tc-contact-page[data-contact-theme="XD0302"]{--tc-accent:var(--xd2-red,#ef392a)}
html body .tc-contact-page[data-contact-theme="XD0303"]{--tc-accent:var(--xd3-orange,#ef4c23)}
html body .tc-contact-page[data-contact-theme="XD0304"]{--tc-accent:var(--xd4-green,#91bf3d)}
html body .tc-contact-page[data-contact-theme="XD0305"]{--tc-accent:var(--gold,#f0a629)}
html body .tc-contact-page[data-contact-theme="XD0306"]{--tc-accent:var(--gold,#e90025)}
html body .tc-contact-page[data-contact-theme="XD0307"]{--tc-accent:var(--gold,#f0a629)}
html body .tc-contact-page[data-contact-theme="XD0308"]{--tc-accent:var(--xd4-green,#91bf3d)}
html body .tc-contact-page[data-contact-theme="XD0309"]{--tc-accent:var(--gold,#f0a629)}
html body .tc-contact-page[data-contact-theme="XD0310"]{--tc-accent:var(--gold,#f0a629)}
html body .tc-contact-page[data-contact-theme="XD0311"]{--tc-accent:var(--gold,#f0a629)}
html body .tc-contact-page[data-contact-theme="XD0312"]{--tc-accent:var(--gold,#f0a629)}
html body .tc-contact-page[data-contact-theme="XD0313"]{--tc-accent:var(--rx13-green,#7bd615)}
html body .tc-contact-page[data-contact-theme="XD0314"]{--tc-accent:var(--bb14-navy,#07142c)}
html body .tc-contact-page[data-contact-theme="XD0315"]{--tc-accent:var(--af15-orange,#f47c00)}
html body .tc-contact-page[data-contact-theme="XD0318"]{--tc-accent:var(--fg18-orange,#f4512a)}
html body .tc-contact-page[data-contact-theme="XD0320"]{--tc-accent:var(--xd20-red,#e32918)}
html body .tc-contact-page[data-contact-theme="XD0322"]{--tc-accent:var(--o,#ff5b17)}
html body .tc-contact-page[data-contact-theme="XD0323"]{--tc-accent:var(--xd323-green,#07823f)}
html body .tc-contact-page[data-contact-theme="XD0324"]{--tc-accent:var(--wolf-gold,#c9a57d)}
html body .tc-contact-page[data-contact-theme="XD0325"]{--tc-accent:var(--x325-orange,#ff5b0b)}
html body .tc-contact-page[data-contact-theme="XD321"]{--tc-accent:var(--b,#153d78)}
body:has(.contact-theme-marker[data-contact-theme="BDS701"]) .bds-header{position:relative;top:auto;background:#1f2d40}
body:has(.contact-theme-marker[data-contact-theme="BDS702"]) .bds-header{position:relative;top:auto;background:#1f2d40}
body:has(.contact-theme-marker[data-contact-theme="DN302"]) .dn-header{position:relative;top:auto;background:#fff}
body:has(.contact-theme-marker[data-contact-theme="EC915"]) .ec15-header{position:relative;top:auto;background:#24201d}
body:has(.contact-theme-marker[data-contact-theme="FOOT403"]) .dr-header{position:relative;top:auto;background:#173d37}
body:has(.contact-theme-marker[data-contact-theme="FOOT408"]) .f408-mainbar{position:relative;top:auto;background:#fff}
body:has(.contact-theme-marker[data-contact-theme="XD0304"]) .xd4-header{position:relative;top:auto;background:var(--xd4-ink,#202326)}
body:has(.contact-theme-marker[data-contact-theme="XD0305"]) .xd5-header{position:relative;top:auto;background:#202326}
body:has(.contact-theme-marker[data-contact-theme="XD0306"]) .xd5-header{position:relative;top:auto;background:#202326}
body:has(.contact-theme-marker[data-contact-theme="XD0308"]) .xd4-header{position:relative;top:auto;background:var(--xd4-ink,#202326)}
body:has(.contact-theme-marker[data-contact-theme="XD0312"]) .xd12-header{position:relative;top:auto;background:#fff}
body:has(.contact-theme-marker[data-contact-theme="XD0315"]) .af15-site-header{position:relative;top:auto;background:#202326}
body:has(.contact-theme-marker[data-contact-theme="XD0323"]) .xd323-header{position:relative;top:auto;background:var(--xd323-green-dark,#043d2f)}
body:has(.contact-theme-marker[data-contact-theme="XD0324"]) .xd324-header{position:relative;top:auto;background:#202326}
body:has(.contact-theme-marker[data-contact-theme="XD0325"]) .x325-nav{position:relative;top:auto;background:#fff}
body:has(.contact-theme-marker) .f406-header__shell{margin-bottom:0}
body:has(.contact-theme-marker[data-contact-theme="FOOT401"]) .foot-navigation-wrap{height:auto}
body:has(.contact-theme-marker[data-contact-theme="FOOT401"]) .foot-navigation{position:relative;left:auto;transform:none;margin-inline:auto}
@media(max-width:480px){
body:has(.contact-theme-marker[data-contact-theme="EC902"]) .ec92-top-inner{gap:10px}
body:has(.contact-theme-marker[data-contact-theme="EC902"]) .ec92-logo{min-width:0;flex:1;max-width:calc(100% - 116px)}
body:has(.contact-theme-marker[data-contact-theme="EC902"]) .ec92-logo img{max-width:100%}
body:has(.contact-theme-marker[data-contact-theme="EC915"]) .ec15-header-inner{gap:12px}
body:has(.contact-theme-marker[data-contact-theme="EC915"]) .ec15-logo{min-width:0;flex:1;max-width:calc(100% - 174px)}
body:has(.contact-theme-marker[data-contact-theme="EC915"]) .ec15-logo img{max-width:100%}
body:has(.contact-theme-marker[data-contact-theme="EC915"]) .ec15-header-actions{gap:10px;flex-shrink:0}
}
</style>

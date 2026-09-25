<style>
.xd-catalog-page{--catalog-accent:#244b60;--catalog-action:color-mix(in srgb,var(--catalog-accent) 70%,#000);--catalog-tint:color-mix(in srgb,var(--catalog-accent) 7%,white);background:#f6f7f8;color:#202326;font-family:"Segoe UI","Noto Sans",Arial,sans-serif;line-height:1.6;padding:0 0 80px;margin:0;width:100%;max-width:none}

.xd-catalog-page *{box-sizing:border-box}

.xd-catalog-page a{text-decoration:none;color:inherit}

.xd-catalog-page a:focus-visible,.xd-catalog-page button:focus-visible,.xd-catalog-page summary:focus-visible{outline:3px solid var(--catalog-accent);outline-offset:4px}
 .xd-catalog-page .xdc-container{width:min(1240px,calc(100% - 48px));margin-inline:auto}
 .xd-catalog-page .xdc-hero{background:var(--catalog-tint);color:#202326;border-bottom:4px solid var(--catalog-accent);padding:26px 0 42px}
 .xd-catalog-page .xdc-breadcrumb{display:flex;flex-wrap:wrap;gap:12px;font-size:13px;color:#58636b;margin-bottom:32px}
 .xd-catalog-page .xdc-eyebrow{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:var(--catalog-action)}
 .xd-catalog-page .xdc-hero .xdc-eyebrow{color:var(--catalog-action)}
 .xd-catalog-page .xdc-hero h1{font:700 clamp(30px,3.4vw,46px)/1.25 "Segoe UI",sans-serif;letter-spacing:-1px;margin:8px 0 12px;overflow-wrap:anywhere}
 .xd-catalog-page .xdc-hero p{max-width:680px;color:#58636b;margin:0;font-size:16px}
 .xd-catalog-page .xdc-layout{display:grid;grid-template-columns:250px minmax(0,1fr);gap:32px;padding-top:40px}
 .xd-catalog-page .xdc-layout aside, .xd-catalog-page .xdc-results{min-width:0}
 .xd-catalog-page .xdc-filters{background:white;border:1px solid #e3e6e8;border-radius:10px;padding:20px}
 .xd-catalog-page .xdc-filters summary{font-size:17px;font-weight:700;cursor:pointer}
 .xd-catalog-page .xdc-search{margin:24px 0}
 .xd-catalog-page .xdc-search label{display:block;font-size:12px;font-weight:600;margin-bottom:8px}
 .xd-catalog-page .xdc-search>div{display:flex;border:1px solid #cdd3d8;border-radius:5px;overflow:hidden;background:#fff}
 .xd-catalog-page .xdc-search input[type=search]{width:100%;min-width:0;border:0;padding:12px 9px;font:inherit;font-size:12px;background:transparent}
 .xd-catalog-page .xdc-search button{border:0;background:var(--catalog-action);color:white;width:38px;flex-shrink:0;font-size:22px;cursor:pointer}
 .xd-catalog-page .xdc-filters h2{font-size:13px;margin:0 0 10px}
 .xd-catalog-page .xdc-categories a{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:11px 12px;font-size:13px;border-left:3px solid transparent;border-radius:3px;margin:4px 0;overflow-wrap:anywhere}
 .xd-catalog-page .xdc-categories a:hover{background:#f6f7f8}
 .xd-catalog-page .xdc-categories a[aria-current]{background:var(--catalog-tint);color:var(--catalog-action);border-left-color:var(--catalog-action);font-weight:700}
 .xd-catalog-page .xdc-category-children{padding-left:12px}
 .xd-catalog-page .xdc-help{background:var(--catalog-tint);color:#202326;padding:24px;border-radius:10px;margin-top:20px}
 .xd-catalog-page .xdc-help>span{font-size:28px;color:var(--catalog-action)}
 .xd-catalog-page .xdc-help h2{font-size:20px;line-height:1.4;margin:8px 0}
 .xd-catalog-page .xdc-help p{font-size:13px;color:#58636b}
 .xd-catalog-page .xdc-help a{font-size:13px;font-weight:700;display:inline-block;margin-top:12px}
 .xd-catalog-page .xdc-results-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px}
 .xd-catalog-page .xdc-results-heading h2{font-size:25px;margin:4px 0 0}
 .xd-catalog-page .xdc-query{font-size:14px;color:#697079;overflow-wrap:anywhere;max-width:50%}
 .xd-catalog-page .xdc-sort{display:flex;align-items:center;flex-wrap:wrap;gap:8px;padding:14px;background:white;border:1px solid #e3e6e8;border-radius:8px;margin-bottom:24px;font-size:12px}
 .xd-catalog-page .xdc-sort>span{color:#697079;margin-right:6px}
 .xd-catalog-page .xdc-sort a{padding:7px 12px;border:1px solid #e3e6e8;border-radius:4px}
 .xd-catalog-page .xdc-sort a[aria-current]{background:#202326;color:white;border-color:#202326}
 .xd-catalog-page .xdc-sort a:hover{border-color:var(--catalog-action)}
 .xd-catalog-page .xdc-reset{display:inline-block;font-size:12px;color:var(--catalog-action)!important;margin:-8px 0 16px}
 .xd-catalog-page .xdc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
 .xd-catalog-page .xdc-card{display:flex;flex-direction:column;background:#fff;border:1px solid #e3e6e8;border-radius:8px;overflow:hidden;transition:box-shadow .2s,border-color .2s}
 .xd-catalog-page .xdc-card:hover{border-color:#d3a39e;box-shadow:0 10px 25px #20232610}
 .xd-catalog-page .xdc-image{aspect-ratio:1.15;position:relative;background:#fff;display:grid;place-items:center;padding:18px;border-bottom:1px solid #f0f1f2;overflow:hidden}
 .xd-catalog-page .xdc-image img{width:100%;height:100%;object-fit:contain;min-height:0}
 .xd-catalog-page .xdc-image>span:not(.xdc-discount){font-size:12px;color:#697079}
 .xd-catalog-page .xdc-discount{position:absolute;top:12px;left:12px;background:var(--catalog-action);color:white;padding:3px 8px;font-size:11px;font-weight:700;border-radius:3px}
 .xd-catalog-page .xdc-card-body{padding:18px;display:flex;flex-direction:column;flex:1}
 .xd-catalog-page .xdc-tag{color:#697079;font-size:10px;text-transform:uppercase;letter-spacing:.7px}
 .xd-catalog-page .xdc-card h3{font-size:17px;line-height:1.45;margin:8px 0 16px;overflow-wrap:anywhere}
 .xd-catalog-page .xdc-price{margin-top:auto;display:flex;flex-wrap:wrap;align-items:center;gap:8px}
 .xd-catalog-page .xdc-price strong{font-size:19px;color:var(--catalog-action)}
 .xd-catalog-page .xdc-price del{font-size:12px;color:#697079}
 .xd-catalog-page .xdc-card-action{border-top:1px solid #eceef0;margin-top:18px;padding-top:12px;display:flex;justify-content:space-between;font-weight:600;font-size:12px}
 .xd-catalog-page .xdc-card-action>span{color:var(--catalog-action)}
 .xd-catalog-page .xdc-pagination{display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:20px;font-size:13px;margin-top:32px}
 .xd-catalog-page .xdc-pagination a{background:white;border:1px solid #e3e6e8;padding:10px 16px;border-radius:4px}
 .xd-catalog-page .xdc-empty{background:white;border:1px dashed #cdd3d8;border-radius:10px;padding:50px 24px;text-align:center}
 .xd-catalog-page .xdc-empty>span{font-size:40px;color:#697079}
 .xd-catalog-page .xdc-empty h2{font-size:22px}
 .xd-catalog-page .xdc-empty p{color:#697079;font-size:14px}
 .xd-catalog-page .xdc-empty a{display:inline-block;background:var(--catalog-action);color:white;padding:10px 20px;border-radius:4px;margin-top:12px}

@media(max-width:1100px){ .xd-catalog-page .xdc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
 .xd-catalog-page .xdc-layout{grid-template-columns:230px minmax(0,1fr);gap:24px}

}

@media(max-width:760px){ .xd-catalog-page .xdc-container{width:calc(100% - 32px)}
 .xd-catalog-page .xdc-layout{grid-template-columns:1fr;padding-top:24px}
 .xd-catalog-page .xdc-help{display:none}
 .xd-catalog-page .xdc-categories{display:flex;flex-wrap:wrap;gap:4px}
 .xd-catalog-page .xdc-categories a{border:1px solid #e3e6e8;padding:8px 12px}
 .xd-catalog-page .xdc-search{margin:16px 0}
 .xd-catalog-page .xdc-filters{padding:16px}
 .xd-catalog-page .xdc-hero{padding:20px 0 28px}
 .xd-catalog-page .xdc-breadcrumb{margin-bottom:20px}
 .xd-catalog-page .xdc-card-body{padding:14px}
 .xd-catalog-page .xdc-grid{gap:12px}
 .xd-catalog-page .xdc-card h3{font-size:15px}
 .xd-catalog-page .xdc-price strong{font-size:17px}

}

@media(max-width:380px){ .xd-catalog-page .xdc-grid{grid-template-columns:1fr}

}

@media(prefers-reduced-motion:reduce){ .xd-catalog-page .xdc-card{transition:none}

}



.xd-catalog-page[data-catalog-theme="AUTO850"]{--catalog-accent:var(--a850-red,#e10600)}

.xd-catalog-page[data-catalog-theme="AUTO851"]{--catalog-accent:var(--a851-navy,#1c3268)}

.xd-catalog-page[data-catalog-theme="AUTO852"]{--catalog-accent:var(--a852-orange,#ff5a00)}

.xd-catalog-page[data-catalog-theme="AUTO853"]{--catalog-accent:var(--a853-red,#ed2f3b)}

.xd-catalog-page[data-catalog-theme="BDS701"]{--catalog-accent:var(--bds-green,#84c441)}

.xd-catalog-page[data-catalog-theme="BOOK920"]{--catalog-accent:var(--book20-teal,#08728c)}

.xd-catalog-page[data-catalog-theme="BZ501"]{--catalog-accent:var(--bz-red,#ff3216)}

.xd-catalog-page[data-catalog-theme="CA0050"]{--catalog-accent:var(--ca-blue,#0887df)}

.xd-catalog-page[data-catalog-theme="DL750"]{--catalog-accent:var(--dl-olive,#3f5f2c)}

.xd-catalog-page[data-catalog-theme="DN202"]{--catalog-accent:var(--d202-blue,#3799ee)}

.xd-catalog-page[data-catalog-theme="DN302"]{--catalog-accent:var(--dn-navy,#465474)}

.xd-catalog-page[data-catalog-theme="DN350"]{--catalog-accent:var(--dn350-orange,#ff9d10)}

.xd-catalog-page[data-catalog-theme="DN351"]{--catalog-accent:var(--dn351-red,#c8212a)}

.xd-catalog-page[data-catalog-theme="E800"]{--catalog-accent:var(--e800-orange,#ff773d)}

.xd-catalog-page[data-catalog-theme="E801"]{--catalog-accent:var(--pink,#ed0060)}

.xd-catalog-page[data-catalog-theme="E802"]{--catalog-accent:var(--orange,#ffae25)}

.xd-catalog-page[data-catalog-theme="E803"]{--catalog-accent:var(--green,#72c800)}

.xd-catalog-page[data-catalog-theme="E804"]{--catalog-accent:var(--e804-red,#f02f49)}

.xd-catalog-page[data-catalog-theme="E805"]{--catalog-accent:var(--e805-red,#ef3e35)}

.xd-catalog-page[data-catalog-theme="E806"]{--catalog-accent:var(--e806-red,#df3035)}

.xd-catalog-page[data-catalog-theme="E807"]{--catalog-accent:var(--e807-orange,#f45b0b)}

.xd-catalog-page[data-catalog-theme="EC900"]{--catalog-accent:var(--ec9-blue,#177be6)}

.xd-catalog-page[data-catalog-theme="EC901"]{--catalog-accent:var(--ec91-red,#bd1015)}

.xd-catalog-page[data-catalog-theme="EC902"]{--catalog-accent:var(--ec92-blue,#071b9b)}

.xd-catalog-page[data-catalog-theme="EC903"]{--catalog-accent:var(--ec93-red,#ed1c24)}

.xd-catalog-page[data-catalog-theme="EC904"]{--catalog-accent:var(--ec94-red,#ef3c33)}

.xd-catalog-page[data-catalog-theme="EC905"]{--catalog-accent:var(--ec95-blue,#315d8c)}

.xd-catalog-page[data-catalog-theme="EC906"]{--catalog-accent:var(--ec96-green,#00763c)}

.xd-catalog-page[data-catalog-theme="EC907"]{--catalog-accent:var(--r,#f3182b)}

.xd-catalog-page[data-catalog-theme="EC908"]{--catalog-accent:var(--orange,#ff5037)}

.xd-catalog-page[data-catalog-theme="EC909"]{--catalog-accent:var(--ink,#080808)}

.xd-catalog-page[data-catalog-theme="EC910"]{--catalog-accent:var(--ec10-gold,#dba621)}

.xd-catalog-page[data-catalog-theme="EC911"]{--catalog-accent:var(--ec11-blue,#2f5be7)}

.xd-catalog-page[data-catalog-theme="EC912"]{--catalog-accent:var(--ec12-red,#c9142a)}

.xd-catalog-page[data-catalog-theme="EC913"]{--catalog-accent:var(--ec13-red,#c9142a)}

.xd-catalog-page[data-catalog-theme="EC914"]{--catalog-accent:var(--ec14-orange,#f47a26)}

.xd-catalog-page[data-catalog-theme="EC915"]{--catalog-accent:var(--ec15-copper,#b97c58)}

.xd-catalog-page[data-catalog-theme="EC916"]{--catalog-accent:var(--ec16-teal,#16b6a3)}

.xd-catalog-page[data-catalog-theme="EC917"]{--catalog-accent:var(--ec17-orange,#f57408)}

.xd-catalog-page[data-catalog-theme="FOOT401"]{--catalog-accent:var(--foot-gold,#d6a62d)}

.xd-catalog-page[data-catalog-theme="FOOT403"]{--catalog-accent:var(--dr-green,#0d3b35)}

.xd-catalog-page[data-catalog-theme="FOOT404"]{--catalog-accent:var(--f404-primary,#4c50b9)}

.xd-catalog-page[data-catalog-theme="FOOT405"]{--catalog-accent:var(--f405-green,#38ba82)}

.xd-catalog-page[data-catalog-theme="FOOT406"]{--catalog-accent:var(--f406-green,#9bc24b)}

.xd-catalog-page[data-catalog-theme="FOOT407"]{--catalog-accent:var(--f407-green,#164c3f)}

.xd-catalog-page[data-catalog-theme="FOOT408"]{--catalog-accent:var(--f408-red,#e90025)}

.xd-catalog-page[data-catalog-theme="FOOT409"]{--catalog-accent:var(--f409-red,#ed002b)}

.xd-catalog-page[data-catalog-theme="NEWS88"]{--catalog-accent:var(--n88-red,#e4002b)}

.xd-catalog-page[data-catalog-theme="NT501"]{--catalog-accent:var(--nt-gold,#dbae56)}

.xd-catalog-page[data-catalog-theme="NT502"]{--catalog-accent:var(--n502-orange,#ff9f16)}

.xd-catalog-page[data-catalog-theme="NT503"]{--catalog-accent:var(--n503-green,#064f38)}

.xd-catalog-page[data-catalog-theme="NT504"]{--catalog-accent:var(--n504-green,#174f40)}

.xd-catalog-page[data-catalog-theme="SER0101"]{--catalog-accent:var(--ser-primary,#0f766e)}

.xd-catalog-page[data-catalog-theme="SER102"]{--catalog-accent:var(--ser-orange,#ff5a00)}

.xd-catalog-page[data-catalog-theme="SER103"]{--catalog-accent:var(--ser103-accent,#ad8d89)}

.xd-catalog-page[data-catalog-theme="SHOP601"]{--catalog-accent:var(--s601-red,#ef3f24)}

.xd-catalog-page[data-catalog-theme="SHOP602"]{--catalog-accent:var(--s602-green,#174f3f)}

.xd-catalog-page[data-catalog-theme="SHOP603"]{--catalog-accent:var(--s603-green,#176044)}

.xd-catalog-page[data-catalog-theme="SHOP604"]{--catalog-accent:var(--s604-rose,#be5a5d)}

.xd-catalog-page[data-catalog-theme="SHOP605"]{--catalog-accent:var(--p,#ff6387)}

.xd-catalog-page[data-catalog-theme="SPA111"]{--catalog-accent:var(--sp11-teal,#176c6c)}

.xd-catalog-page[data-catalog-theme="SPA502"]{--catalog-accent:var(--spa-gold,#ffb400)}

.xd-catalog-page[data-catalog-theme="TH0050"]{--catalog-accent:var(--th5-green,#064638)}

.xd-catalog-page[data-catalog-theme="TOOL750"]{--catalog-accent:var(--t750-red,#e42838)}

.xd-catalog-page[data-catalog-theme="TOOL751"]{--catalog-accent:var(--t751-yellow,#ffbf00)}

.xd-catalog-page[data-catalog-theme="XD0301"]{--catalog-accent:var(--lime-dark,#9fb500)}

.xd-catalog-page[data-catalog-theme="XD0302"]{--catalog-accent:var(--xd2-red,#ef392a)}

.xd-catalog-page[data-catalog-theme="XD0303"]{--catalog-accent:var(--xd3-orange,#ef4c23)}

.xd-catalog-page[data-catalog-theme="XD0304"]{--catalog-accent:var(--xd4-green,#91bf3d)}

.xd-catalog-page[data-catalog-theme="XD0305"]{--catalog-accent:var(--gold,#f0a629)}

.xd-catalog-page[data-catalog-theme="XD0306"]{--catalog-accent:var(--gold,#e90025)}

.xd-catalog-page[data-catalog-theme="XD0307"]{--catalog-accent:var(--gold,#f0a629)}

.xd-catalog-page[data-catalog-theme="XD0308"]{--catalog-accent:var(--xd4-green,#91bf3d)}

.xd-catalog-page[data-catalog-theme="XD0309"]{--catalog-accent:var(--gold,#f0a629)}

.xd-catalog-page[data-catalog-theme="XD0310"]{--catalog-accent:var(--gold,#f0a629)}

.xd-catalog-page[data-catalog-theme="XD0311"]{--catalog-accent:var(--gold,#f0a629)}

.xd-catalog-page[data-catalog-theme="XD0312"]{--catalog-accent:var(--gold,#f0a629)}

.xd-catalog-page[data-catalog-theme="XD0313"]{--catalog-accent:var(--rx13-green,#7bd615)}

.xd-catalog-page[data-catalog-theme="XD0314"]{--catalog-accent:var(--bb14-navy,#07142c)}

.xd-catalog-page[data-catalog-theme="XD0315"]{--catalog-accent:var(--af15-orange,#f47c00)}

.xd-catalog-page[data-catalog-theme="XD0318"]{--catalog-accent:var(--fg18-orange,#f4512a)}

.xd-catalog-page[data-catalog-theme="XD0320"]{--catalog-accent:var(--xd20-red,#e32918)}

.xd-catalog-page[data-catalog-theme="XD0322"]{--catalog-accent:var(--o,#ff5b17)}

.xd-catalog-page[data-catalog-theme="XD0323"]{--catalog-accent:var(--xd323-green,#07823f)}

.xd-catalog-page[data-catalog-theme="XD0324"]{--catalog-accent:var(--wolf-gold,#c9a57d)}

.xd-catalog-page[data-catalog-theme="XD0325"]{--catalog-accent:var(--x325-orange,#ff5b0b)}

.xd-catalog-page[data-catalog-theme="XD321"]{--catalog-accent:var(--b,#153d78)}

.xd-catalog-page h2,.xd-catalog-page h3,.xd-catalog-page summary{font-weight:700;font-family:inherit;text-transform:none;letter-spacing:normal;color:inherit}

.xd-catalog-page input,.xd-catalog-page button{font-family:inherit}


/* Preserve the approved XD0320 dark hero. */
.xd-catalog-page[data-catalog-theme="XD0320"] .xdc-hero{background:#202326;color:white}

.xd-catalog-page[data-catalog-theme="XD0320"] .xdc-hero p,.xd-catalog-page[data-catalog-theme="XD0320"] .xdc-breadcrumb{color:#c9ced2}

.xd-catalog-page[data-catalog-theme="XD0320"] .xdc-hero .xdc-eyebrow{color:#ff867a}

/* Overlay headers must occupy space on catalog pages, without changing homepages. */
body:has(.xd-catalog-page[data-catalog-theme="BDS701"]) .bds-header{position:relative;top:auto;background:#1f2d40}
body:has(.xd-catalog-page[data-catalog-theme="BDS702"]) .bds-header{position:relative;top:auto;background:#1f2d40}
body:has(.xd-catalog-page[data-catalog-theme="DN302"]) .dn-header{position:relative;top:auto;background:#fff}
body:has(.xd-catalog-page[data-catalog-theme="EC915"]) .ec15-header{position:relative;top:auto;background:#24201d}
body:has(.xd-catalog-page[data-catalog-theme="FOOT403"]) .dr-header{position:relative;top:auto;background:#173d37}
body:has(.xd-catalog-page[data-catalog-theme="FOOT408"]) .f408-mainbar{position:relative;top:auto;background:#fff}
body:has(.xd-catalog-page[data-catalog-theme="XD0304"]) .xd4-header{position:relative;top:auto;background:var(--xd4-ink,#202326)}
body:has(.xd-catalog-page[data-catalog-theme="XD0305"]) .xd5-header{position:relative;top:auto;background:#202326}
body:has(.xd-catalog-page[data-catalog-theme="XD0306"]) .xd5-header{position:relative;top:auto;background:#202326}
body:has(.xd-catalog-page[data-catalog-theme="XD0308"]) .xd4-header{position:relative;top:auto;background:var(--xd4-ink,#202326)}
body:has(.xd-catalog-page[data-catalog-theme="XD0309"]) .xd5-header{position:relative;top:auto;background:#202326}
body:has(.xd-catalog-page[data-catalog-theme="XD0310"]) .xd5-header{position:relative;top:auto;background:#202326}
body:has(.xd-catalog-page[data-catalog-theme="XD0312"]) .xd12-header{position:relative;top:auto;background:#fff}
body:has(.xd-catalog-page[data-catalog-theme="XD0315"]) .af15-site-header{position:relative;top:auto;background:#202326}
body:has(.xd-catalog-page[data-catalog-theme="XD0323"]) .xd323-header{position:relative;top:auto;background:#fff}
body:has(.xd-catalog-page[data-catalog-theme="XD0324"]) .xd324-header{position:relative;top:auto;background:#202326}
body:has(.xd-catalog-page[data-catalog-theme="XD0325"]) .x325-nav{position:relative;top:auto;background:#fff}
body:has(.xd-catalog-page) .f406-header__shell{margin-bottom:0}
</style>

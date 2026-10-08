<span hidden class="contact-theme-marker" data-contact-theme="{{ data_get($activeTheme ?? [], 'key') }}"></span>
<style>
.tc-contact-icon svg{width:22px;height:22px}
.tc-contact-page,.tc-contact-page *{box-sizing:border-box}.tc-contact-page a{text-decoration:none}.tc-contact-copy{margin-bottom:28px;line-height:1.8;overflow-wrap:anywhere}.tc-contact-copy img,.tc-contact-copy iframe{max-width:100%}

.tc-contact-page{--tc-accent:var(--f404-primary,var(--primary,#315b65));padding:0 0 56px;background:#f6f8f9;color:#203548;font:400 15px/1.7 "Segoe UI","Be Vietnam Pro",Arial,sans-serif;text-align:left}
.tc-contact-container{width:min(1160px,calc(100% - 48px));margin:auto}
.tc-contact-breadcrumb{display:flex;gap:12px;flex-wrap:wrap;padding:24px 0;font-size:12px;color:#607c90}
.tc-contact-heading{max-width:720px;padding:0 0 24px}.tc-contact-heading>span{font-size:12px;font-weight:600;color:var(--tc-accent)}
.tc-contact-heading h1{font:700 clamp(28px,3vw,36px)/1.25 "Segoe UI","Be Vietnam Pro",Arial,sans-serif;text-transform:none;letter-spacing:-.02em;margin:8px 0 10px}.tc-contact-heading p{font-size:15px;line-height:1.7;color:#60788a;margin:0}
.tc-contact-layout{display:grid;grid-template-columns:minmax(0,.85fr) minmax(0,1.4fr);gap:28px;align-items:start}
.tc-contact-info{background:color-mix(in srgb,var(--tc-accent) 6%,white);border:1px solid #dce5e8;border-radius:18px;padding:28px}
.tc-contact-layout h2{font-size:22px;margin:0 0 12px;line-height:1.4}.tc-contact-layout>*>p{font-size:13px;line-height:1.8;color:#60788a;margin:0 0 26px}
.tc-contact-item{display:flex;gap:16px;padding:22px 0;border-top:1px solid #cfe3ef}.tc-contact-item:last-child{padding-bottom:0}
.tc-contact-item>.tc-contact-icon{display:grid;place-items:center;flex:0 0 42px;height:42px;background:#fff;color:var(--tc-accent);border-radius:12px}
.tc-contact-item h3{font-size:12px;font-weight:500;color:#587488;margin:0 0 8px}.tc-contact-item p,.tc-contact-item a{font-size:14px;line-height:1.8;margin:0;overflow-wrap:anywhere}.tc-contact-item>div{min-width:0}
.tc-contact-item .tc-contact-directions{display:inline-block;margin-top:12px;color:var(--tc-accent);font-weight:600;font-size:12px}
.tc-contact-form-card{padding:28px;border-radius:18px;background:#fff;border:1px solid #dce5e8;box-shadow:0 8px 28px #20354806}
.tc-contact-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.tc-contact-field-wide{grid-column:1/-1}.tc-contact-field-wide:has(input){grid-column:auto}
.tc-contact-fields label{display:block;font-size:13px;font-weight:600;margin-bottom:9px}.tc-contact-fields input,.tc-contact-fields textarea{display:block;width:100%;min-width:0;border:1px solid #ccdee9;background:#fbfdff;border-radius:10px;padding:13px 14px;color:#203548;font:14px/1.6 'Be Vietnam Pro',sans-serif}.tc-contact-fields textarea{resize:vertical;min-height:140px}
.tc-contact-fields small{display:block;margin-top:8px;font-size:11px;line-height:1.7;color:#708797}.tc-contact-fields [aria-invalid=true]{border-color:#c43939}
.tc-contact-form-card button{display:inline-flex;align-items:center;justify-content:center;gap:14px;padding:15px 28px;margin-top:24px;background:var(--tc-accent);color:#fff;border:0;border-radius:12px;font:600 14px 'Be Vietnam Pro',sans-serif;cursor:pointer}.tc-contact-form-card button:hover{background:var(--tc-accent)}
.tc-contact-page :focus-visible{outline:3px solid var(--tc-accent);outline-offset:3px}.tc-contact-notice,.tc-contact-error{padding:16px;margin-bottom:22px;border-radius:12px;font-size:13px;line-height:1.8}.tc-contact-notice{background:#eaf8ee;color:#216a39}.tc-contact-error{background:#fff1ef;color:#a72b28}.tc-contact-error ul{margin:8px 0 0;padding-left:20px}
@media(max-width:800px){.tc-contact-layout{grid-template-columns:1fr}.tc-contact-heading h1{font-size:30px}.tc-contact-heading{padding-top:10px}.tc-contact-info{padding:24px}}
@media(max-width:480px){.tc-contact-container{width:calc(100% - 32px)}.tc-contact-heading h1{font-size:27px}.tc-contact-form-card{padding:22px}.tc-contact-fields{grid-template-columns:1fr}.tc-contact-form-card button{width:100%}.tc-contact-page{padding-bottom:36px}}
.tc-contact-layout>*,.tc-contact-fields>*{min-width:0}.tc-contact-page h2,.tc-contact-page h3{font-family:inherit;text-transform:none;letter-spacing:normal}.tc-contact-page input,.tc-contact-page textarea{height:auto;max-width:100%}.tc-contact-page textarea{float:none}.tc-contact-page button{height:auto;max-width:100%}
.tc-contact-page[data-contact-theme="XD0323"]{--tc-accent:var(--xd323-green,#07823f);background:#f5f8f4;color:#153b2c}
.tc-contact-page[data-contact-theme="XD0323"] .tc-contact-info{background:var(--xd323-green-dark,#043d2f);border-color:transparent;color:#fff}
.tc-contact-page[data-contact-theme="XD0323"] .tc-contact-info h2,.tc-contact-page[data-contact-theme="XD0323"] .tc-contact-item a,.tc-contact-page[data-contact-theme="XD0323"] .tc-contact-item p{color:#fff}
.tc-contact-page[data-contact-theme="XD0323"] .tc-contact-info>p,.tc-contact-page[data-contact-theme="XD0323"] .tc-contact-item h3{color:#c7dfd1}
.tc-contact-page[data-contact-theme="XD0323"] .tc-contact-item{border-color:#ffffff26}
.tc-contact-page[data-contact-theme="XD0323"] .tc-contact-item>.tc-contact-icon{background:#ffffff12;color:#a7e0b8}
.tc-contact-page[data-contact-theme="XD0323"] .tc-contact-item .tc-contact-directions{color:#a7e0b8}
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
body:has(.contact-theme-marker[data-contact-theme="XD0309"]) .xd5-header{position:relative;top:auto;background:#202326}
body:has(.contact-theme-marker[data-contact-theme="XD0310"]) .xd5-header{position:relative;top:auto;background:#202326}
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

<style>
.xd-catalog-page{background:#f6f7f8;color:#202326;font-family:"Segoe UI","Noto Sans",Arial,sans-serif;line-height:1.6;padding-bottom:80px}
.xd-catalog-page *{box-sizing:border-box}
.xd-catalog-page a{text-decoration:none;color:inherit}
.xd-catalog-page a:focus-visible,.xd-catalog-page button:focus-visible,.xd-catalog-page summary:focus-visible{outline:3px solid #e32918;outline-offset:4px}
.xdc-container{width:min(1240px,calc(100% - 48px));margin-inline:auto}
.xdc-hero{background:#202326;color:#fff;border-bottom:4px solid #e32918;padding:26px 0 42px}
.xdc-breadcrumb{display:flex;flex-wrap:wrap;gap:12px;font-size:13px;color:#c9ced2;margin-bottom:32px}
.xdc-eyebrow{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#d73527}
.xdc-hero .xdc-eyebrow{color:#ff867a}
.xdc-hero h1{font:700 clamp(30px,3.4vw,46px)/1.25 "Segoe UI",sans-serif;letter-spacing:-1px;margin:8px 0 12px;overflow-wrap:anywhere}
.xdc-hero p{max-width:680px;color:#c9ced2;margin:0;font-size:16px}
.xdc-layout{display:grid;grid-template-columns:250px minmax(0,1fr);gap:32px;padding-top:40px}
.xdc-layout aside,.xdc-results{min-width:0}
.xdc-filters{background:white;border:1px solid #e3e6e8;border-radius:10px;padding:20px}
.xdc-filters summary{font-size:17px;font-weight:700;cursor:pointer}
.xdc-search{margin:24px 0}
.xdc-search label{display:block;font-size:12px;font-weight:600;margin-bottom:8px}
.xdc-search>div{display:flex;border:1px solid #cdd3d8;border-radius:5px;overflow:hidden;background:#fff}
.xdc-search input[type=search]{width:100%;min-width:0;border:0;padding:12px 9px;font:inherit;font-size:12px;background:transparent}
.xdc-search button{border:0;background:#e32918;color:white;width:38px;flex-shrink:0;font-size:22px;cursor:pointer}
.xdc-filters h2{font-size:13px;margin:0 0 10px}
.xdc-categories a{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:11px 12px;font-size:13px;border-left:3px solid transparent;border-radius:3px;margin:4px 0;overflow-wrap:anywhere}
.xdc-categories a:hover{background:#f6f7f8}
.xdc-categories a[aria-current]{background:#fff1ee;color:#b91d11;border-left-color:#e32918;font-weight:700}
.xdc-category-children{padding-left:12px}
.xdc-help{background:#202326;color:#fff;padding:24px;border-radius:10px;margin-top:20px}
.xdc-help>span{font-size:28px;color:#ff867a}
.xdc-help h2{font-size:20px;line-height:1.4;margin:8px 0}
.xdc-help p{font-size:13px;color:#c9ced2}
.xdc-help a{font-size:13px;font-weight:700;display:inline-block;margin-top:12px}
.xdc-results-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px}
.xdc-results-heading h2{font-size:25px;margin:4px 0 0}
.xdc-query{font-size:14px;color:#697079;overflow-wrap:anywhere;max-width:50%}
.xdc-sort{display:flex;align-items:center;flex-wrap:wrap;gap:8px;padding:14px;background:white;border:1px solid #e3e6e8;border-radius:8px;margin-bottom:24px;font-size:12px}
.xdc-sort>span{color:#697079;margin-right:6px}
.xdc-sort a{padding:7px 12px;border:1px solid #e3e6e8;border-radius:4px}
.xdc-sort a[aria-current]{background:#202326;color:white;border-color:#202326}
.xdc-sort a:hover{border-color:#e32918}
.xdc-reset{display:inline-block;font-size:12px;color:#b91d11!important;margin:-8px 0 16px}
.xdc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.xdc-card{display:flex;flex-direction:column;background:#fff;border:1px solid #e3e6e8;border-radius:8px;overflow:hidden;transition:box-shadow .2s,border-color .2s}
.xdc-card:hover{border-color:#d3a39e;box-shadow:0 10px 25px #20232610}
.xdc-image{aspect-ratio:1.15;position:relative;background:#fff;display:grid;place-items:center;padding:18px;border-bottom:1px solid #f0f1f2;overflow:hidden}
.xdc-image img{width:100%;height:100%;object-fit:contain;min-height:0}
.xdc-image>span:not(.xdc-discount){font-size:12px;color:#697079}
.xdc-discount{position:absolute;top:12px;left:12px;background:#e32918;color:white;padding:3px 8px;font-size:11px;font-weight:700;border-radius:3px}
.xdc-card-body{padding:18px;display:flex;flex-direction:column;flex:1}
.xdc-tag{color:#697079;font-size:10px;text-transform:uppercase;letter-spacing:.7px}
.xdc-card h3{font-size:17px;line-height:1.45;margin:8px 0 16px;overflow-wrap:anywhere}
.xdc-price{margin-top:auto;display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.xdc-price strong{font-size:19px;color:#c52a1d}
.xdc-price del{font-size:12px;color:#697079}
.xdc-card-action{border-top:1px solid #eceef0;margin-top:18px;padding-top:12px;display:flex;justify-content:space-between;font-weight:600;font-size:12px}
.xdc-card-action>span{color:#e32918}
.xdc-pagination{display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:20px;font-size:13px;margin-top:32px}
.xdc-pagination a{background:white;border:1px solid #e3e6e8;padding:10px 16px;border-radius:4px}
.xdc-empty{background:white;border:1px dashed #cdd3d8;border-radius:10px;padding:50px 24px;text-align:center}
.xdc-empty>span{font-size:40px;color:#697079}
.xdc-empty h2{font-size:22px}
.xdc-empty p{color:#697079;font-size:14px}
.xdc-empty a{display:inline-block;background:#e32918;color:white;padding:10px 20px;border-radius:4px;margin-top:12px}
@media(max-width:1100px){.xdc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
.xdc-layout{grid-template-columns:230px minmax(0,1fr);gap:24px}
}
@media(max-width:760px){.xdc-container{width:calc(100% - 32px)}
.xdc-layout{grid-template-columns:1fr;padding-top:24px}
.xdc-help{display:none}
.xdc-categories{display:flex;flex-wrap:wrap;gap:4px}
.xdc-categories a{border:1px solid #e3e6e8;padding:8px 12px}
.xdc-search{margin:16px 0}
.xdc-filters{padding:16px}
.xdc-hero{padding:20px 0 28px}
.xdc-breadcrumb{margin-bottom:20px}
.xdc-card-body{padding:14px}
.xdc-grid{gap:12px}
.xdc-card h3{font-size:15px}
.xdc-price strong{font-size:17px}
}
@media(max-width:380px){.xdc-grid{grid-template-columns:1fr}
}
@media(prefers-reduced-motion:reduce){.xdc-card{transition:none}
}

</style>

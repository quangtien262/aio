<style>
:root{--xd20-red:#e32918;--xd20-red-dark:#b91d11;--xd20-ink:#202124;--xd20-muted:#697079;--xd20-line:#e5e7e9;--xd20-soft:#f5f5f3;--xd20-dark:#202326;--xd20-white:#fff}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:#fff;color:var(--xd20-ink);font-family:"Segoe UI","Noto Sans",Arial,sans-serif;-webkit-font-smoothing:antialiased}
body,button,input{font-family:"Segoe UI","Noto Sans",Arial,sans-serif}
img{display:block;max-width:100%}
.sr-only{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}
.xd20-page{min-width:0;overflow:hidden}
.xd20-container,.foot-container{width:min(1240px,calc(100% - 48px));margin-inline:auto}
.foot-header{position:relative;z-index:20;background:#fff;border-bottom:1px solid var(--xd20-line)}
.foot-header__masthead{background:var(--xd20-dark);color:#fff}
.foot-header__masthead-inner{display:flex;align-items:center;justify-content:space-between;min-height:72px;padding-right:76px}
.foot-brand{display:flex;align-items:center;gap:12px;min-width:0;color:#fff;text-decoration:none}
.foot-brand img{width:auto;max-width:230px;height:auto;max-height:48px;object-fit:contain}
.foot-brand__monogram{display:grid;flex:0 0 46px;width:46px;height:46px;place-items:center;background:var(--xd20-red);color:#fff;font:800 25px/1 "Arial Narrow","Segoe UI",sans-serif}
.foot-brand>span:last-child{display:grid;gap:2px}
.foot-brand strong{font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:22px;line-height:1;text-transform:uppercase;letter-spacing:.035em}
.foot-brand small{color:#cdd0d3;font-size:11px;letter-spacing:.16em;text-transform:uppercase}
.foot-header__account{display:flex;align-items:center;gap:8px;color:#aeb3b7;font-size:13px}
.foot-header__account button,.foot-header__account a{padding:8px 2px;border:0;background:none;color:#fff;font-size:13px;text-decoration:none;cursor:pointer}
.foot-header__account button:hover,.foot-header__account a:hover{color:#ff6b5e}
.foot-navigation-wrap{position:relative;display:flex;align-items:center;min-height:64px}
.foot-navigation{display:flex;align-items:stretch;gap:2px;width:100%;min-height:64px}
.foot-navigation a{display:flex;align-items:center;padding:0 19px;color:#292c30;font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:16px;font-weight:800;letter-spacing:.035em;text-decoration:none;text-transform:uppercase;border-bottom:3px solid transparent}
.foot-navigation a:first-child{padding-left:0}
.foot-navigation a:hover,.foot-navigation a:focus-visible{color:var(--xd20-red);border-bottom-color:var(--xd20-red)}
.foot-mobile-toggle{display:none}
.foot-header+.sf-language-switcher{top:17px}
.xd20-main{min-width:0}
.xd20-hero{position:relative;background:#1e2224}
.xd20-hero__slide{display:none;position:relative;min-height:650px;color:#fff;isolation:isolate}
.xd20-hero__slide.is-active{display:block}
.xd20-hero__slide>img{position:absolute;z-index:-2;inset:0;width:100%;height:100%;object-fit:cover}
.xd20-hero__slide>div:nth-child(2){position:absolute;z-index:-1;inset:0;background:linear-gradient(90deg,rgba(14,18,20,.83) 0%,rgba(14,18,20,.57) 46%,rgba(14,18,20,.12) 82%)}
.xd20-hero__copy{display:flex;min-height:650px;flex-direction:column;align-items:flex-start;justify-content:center;padding-block:84px}
.xd20-hero__copy>p:first-child,.xd20-copy>p,.xd20-heading>p,.xd20-split__copy>p{margin:0 0 14px;color:var(--xd20-red);font-size:13px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}
.xd20-hero__copy h1{max-width:760px;margin:0;font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:clamp(42px,5.7vw,76px);font-weight:900;line-height:1.02;letter-spacing:-.025em;text-transform:uppercase}
.xd20-hero__copy>p:not(:first-child){max-width:630px;margin:22px 0 0;color:#eef0f1;font-size:18px;line-height:1.7}
.xd20-button,.foot-button{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:15px 22px;border:1px solid transparent;background:var(--xd20-red);color:#fff;font-size:13px;font-weight:800;letter-spacing:.055em;text-decoration:none;text-transform:uppercase;transition:background .2s ease,transform .2s ease}
.xd20-button{margin-top:28px}
.xd20-button:hover,.foot-button:hover{background:var(--xd20-red-dark);transform:translateY(-2px)}
.xd20-quality{position:relative;z-index:2;padding:56px 0;background:#fff}
.xd20-quality>.xd20-container{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px}
.xd20-quality article{display:flex;min-width:0;min-height:184px;flex-direction:column;align-items:center;justify-content:center;padding:24px 18px;border:1px solid var(--xd20-line);background:#fff;text-align:center;box-shadow:0 12px 34px rgba(22,26,29,.045);transition:border-color .25s ease,transform .25s ease,box-shadow .25s ease}
.xd20-quality article:hover{border-color:rgba(227,41,24,.45);transform:translateY(-5px);box-shadow:0 18px 40px rgba(22,26,29,.09)}
.xd20-quality i{margin-bottom:16px;color:var(--xd20-red);font-size:36px}
.xd20-quality h3{margin:0;font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:20px;line-height:1.15;text-transform:uppercase}
.xd20-quality p{margin:9px 0 0;color:var(--xd20-muted);font-size:14px;line-height:1.5}
.xd20-section{padding:100px 0}
.xd20-section--gray{background:var(--xd20-soft)}
.xd20-about{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.05fr);gap:76px;align-items:center}
.xd20-about__image{position:relative;min-width:0;padding-right:24px}
.xd20-about__image img{width:100%;height:520px;object-fit:cover}
.xd20-about__image strong{position:absolute;top:44px;right:0;min-width:142px;padding:22px 20px;background:var(--xd20-red);color:#fff;font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:54px;line-height:.9;text-align:center;box-shadow:0 18px 38px rgba(132,20,11,.2)}
.xd20-about__image small{display:block;margin-top:9px;font-family:"Segoe UI","Noto Sans",Arial,sans-serif;font-size:11px;line-height:1.3;letter-spacing:.09em;text-transform:uppercase}
.xd20-copy h2,.xd20-split h2,.xd20-heading h2{margin:0 0 22px;font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:clamp(38px,4.7vw,62px);font-weight:900;line-height:1.03;letter-spacing:-.025em;text-transform:uppercase}
.xd20-copy>div,.xd20-split__copy>div,.xd20-heading>div{color:#555d64;font-size:17px;line-height:1.75}
.xd20-copy ul,.xd20-split ul{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px 22px;margin:28px 0 0;padding:0;list-style:none}
.xd20-copy li,.xd20-split li{display:flex;gap:9px;align-items:flex-start;color:#626970;line-height:1.45}
.xd20-copy i{margin-top:3px;color:var(--xd20-red)}
.xd20-split{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);background:var(--xd20-red);color:#fff}
.xd20-split__copy{display:flex;min-height:680px;flex-direction:column;justify-content:center;padding:82px max(42px,calc((100vw - 1240px)/2));padding-right:76px}
.xd20-split__copy>p,.xd20-split__copy>div,.xd20-split li{color:#fff}
.xd20-split ul{grid-template-columns:1fr}
.xd20-split i{margin-top:3px}
.xd20-split>img{width:100%;height:680px;object-fit:cover}
.xd20-heading{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(280px,.9fr);gap:18px 56px;align-items:end;margin-bottom:42px}
.xd20-heading>p{grid-column:1/-1;margin-bottom:0}
.xd20-heading h2{margin:0}
.xd20-rail{display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - 48px)/3);gap:24px;padding:3px 2px 18px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-color:var(--xd20-red) #ddd}
.xd20-rail article{min-width:0;scroll-snap-align:start}
.xd20-rail a{display:block;color:var(--xd20-ink);text-decoration:none}
.xd20-rail img{width:100%;aspect-ratio:1.22;object-fit:cover;transition:transform .4s ease}
.xd20-rail a:hover img{transform:scale(1.025)}
.xd20-rail h3{margin:16px 0 0;font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:24px;line-height:1.15;text-transform:uppercase}
.xd20-team{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:24px}
.xd20-team article{min-width:0}
.xd20-team img{width:100%;aspect-ratio:.88;object-fit:cover;background:#e9eaeb}
.xd20-team h3{margin:16px 0 4px;font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:26px;line-height:1.15;text-align:center}
.xd20-team p{margin:0;color:var(--xd20-red);font-size:12px;font-weight:800;letter-spacing:.08em;text-align:center;text-transform:uppercase}
.xd20-partners{padding:74px 0;background:#fff;text-align:center}
.xd20-partners h2{margin:0 0 34px;font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:clamp(34px,4vw,48px);font-weight:900;text-transform:uppercase}
.xd20-partners>div>div{display:flex;gap:16px;justify-content:center;overflow-x:auto;padding:2px 2px 12px}
.xd20-partners a{display:grid;flex:1 0 170px;max-width:224px;height:110px;place-items:center;padding:18px;border:1px solid var(--xd20-line);color:#555d64;text-decoration:none;background:#fff}
.xd20-partners img{max-width:135px;max-height:66px;object-fit:contain;filter:grayscale(1);opacity:.75}
.xd20-partners strong{font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:18px;letter-spacing:.035em;text-transform:uppercase}
.foot-footer{background:var(--xd20-dark);color:#c9cdd0}
.foot-footer a{color:inherit;text-decoration:none}
.foot-footer__top{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);gap:44px;align-items:center;padding-block:54px}
.foot-footer__newsletter form{display:flex;max-width:390px}
.foot-footer__newsletter input{min-width:0;flex:1;height:46px;padding:0 14px;border:1px solid #51565b;border-right:0;background:#292d30;color:#fff;outline:0}
.foot-footer__newsletter input:focus{border-color:#f06b60}
.foot-footer__newsletter button{height:46px;padding:0 17px;border:0;background:var(--xd20-red);color:#fff;font-weight:800;cursor:pointer}
.foot-footer__eyebrow{margin:0 0 12px;color:#fff;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
.foot-footer__brand{display:flex;min-width:220px;flex-direction:column;align-items:center;text-align:center}
.foot-footer__brand .foot-brand__monogram{margin-bottom:12px}
.foot-footer__brand strong{max-width:260px;color:#fff;font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:21px;line-height:1.15;text-transform:uppercase}
.foot-footer__brand small{margin-top:5px;color:#969da2;font-size:10px;letter-spacing:.13em;text-transform:uppercase}
.foot-footer__social{text-align:right}
.foot-footer__social>div{display:flex;justify-content:flex-end;gap:8px}
.foot-footer__social a{display:grid;width:38px;height:38px;place-items:center;border:1px solid #52585d;color:#fff;text-transform:uppercase}
.foot-footer__social a:hover{border-color:var(--xd20-red);background:var(--xd20-red)}
.foot-footer__divider{border-top:1px solid #3c4145}
.foot-footer__grid{display:grid;grid-template-columns:minmax(0,1.35fr) repeat(2,minmax(0,.8fr)) minmax(0,1.1fr);gap:50px;padding-block:58px}
.foot-footer__grid>section{min-width:0}
.foot-footer__grid h3{margin:0 0 18px;color:#fff;font-family:"Arial Narrow","Segoe UI",sans-serif;font-size:20px;text-transform:uppercase}
.foot-footer__grid p{margin:0 0 10px;line-height:1.65;overflow-wrap:anywhere}
.foot-footer__grid ul{margin:0;padding:0;list-style:none}
.foot-footer__grid li{margin-bottom:10px}
.foot-footer__grid a:hover{color:#ff7468}
.foot-button--light{margin-top:8px;border-color:#747b80;background:transparent}
.foot-footer__bottom{padding-block:20px;border-top:1px solid #3c4145;color:#8f969b;font-size:12px;text-align:center}
.xd20-motion-ready .xd20-reveal{opacity:0;transform:translate3d(0,-34px,0);transition:opacity .68s cubic-bezier(.22,1,.36,1),transform .68s cubic-bezier(.22,1,.36,1);transition-delay:var(--xd20-delay,0ms);will-change:opacity,transform}
.xd20-motion-ready .xd20-reveal.is-visible{opacity:1;transform:none}
@media(max-width:1024px){
  .xd20-quality>.xd20-container{grid-template-columns:repeat(3,minmax(0,1fr))}
  .xd20-about{gap:44px}
  .xd20-split__copy{padding:68px 42px}
  .xd20-team{grid-template-columns:repeat(2,minmax(0,1fr))}
  .foot-footer__grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:760px){
  .xd20-container,.foot-container{width:min(100% - 32px,1240px)}
  .foot-header__masthead-inner{min-height:66px;padding-right:66px}
  .foot-brand img{max-width:170px;max-height:40px}
  .foot-header__account{display:none}
  .foot-navigation-wrap{min-height:52px}
  .foot-mobile-toggle{display:flex;width:100%;min-height:52px;align-items:center;padding:0;border:0;background:#fff;color:var(--xd20-ink);font-weight:800;text-transform:uppercase;cursor:pointer}
  .foot-mobile-toggle::after{content:"☰";margin-left:auto;font-size:20px}
  .foot-navigation{position:absolute;top:100%;left:-16px;display:none;width:calc(100% + 32px);min-height:0;flex-direction:column;align-items:stretch;padding:8px 16px 16px;background:#fff;border-top:1px solid var(--xd20-line);box-shadow:0 18px 28px rgba(10,15,20,.12)}
  .foot-navigation.is-open{display:flex}
  .foot-navigation a,.foot-navigation a:first-child{min-height:44px;padding:0 4px;border-bottom:1px solid #eee}
  .foot-header+.sf-language-switcher{top:14px}
  .xd20-hero__slide,.xd20-hero__copy{min-height:560px}
  .xd20-hero__copy{padding-block:66px}
  .xd20-quality>.xd20-container{grid-template-columns:repeat(2,minmax(0,1fr))}
  .xd20-section{padding:76px 0}
  .xd20-about,.xd20-split{grid-template-columns:1fr}
  .xd20-about{gap:42px}
  .xd20-about__image{padding-right:18px}
  .xd20-about__image img{height:460px}
  .xd20-about__image strong{right:0}
  .xd20-split__copy{min-height:0;padding:68px 24px}
  .xd20-split>img{height:460px}
  .xd20-heading{grid-template-columns:1fr;gap:16px}
  .xd20-heading>p{grid-column:auto}
  .xd20-rail{grid-auto-columns:82%}
  .foot-footer__top{grid-template-columns:1fr;gap:36px;text-align:center}
  .foot-footer__newsletter form{margin-inline:auto}
  .foot-footer__social{text-align:center}
  .foot-footer__social>div{justify-content:center}
}
@media(max-width:560px){
  .xd20-container,.foot-container{width:calc(100% - 28px)}
  .xd20-hero__copy h1{font-size:39px}
  .xd20-hero__copy>p:not(:first-child){font-size:16px}
  .xd20-quality{padding:38px 0}
  .xd20-quality>.xd20-container,.xd20-team{grid-template-columns:1fr}
  .xd20-quality article{min-height:150px}
  .xd20-about__image img{height:380px}
  .xd20-about__image strong{top:24px;min-width:120px;font-size:45px}
  .xd20-copy ul{grid-template-columns:1fr}
  .xd20-copy h2,.xd20-split h2,.xd20-heading h2{font-size:36px}
  .xd20-rail{grid-auto-columns:88%}
  .xd20-team{gap:32px}
  .xd20-partners>div>div{justify-content:flex-start}
  .xd20-partners a{flex-basis:145px}
  .foot-footer__newsletter form{display:grid}
  .foot-footer__newsletter input{border-right:1px solid #51565b}
  .foot-footer__grid{grid-template-columns:1fr;gap:34px;text-align:left}
}
@media(prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  .xd20-motion-ready .xd20-reveal{opacity:1;transform:none;transition:none}
  .xd20-button,.foot-button,.xd20-quality article,.xd20-rail img{transition:none}
}
</style>

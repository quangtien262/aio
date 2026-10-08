<style>
.theme-news-listing[data-news-theme="NT501"]{background:#fff;color:var(--nt-ink);padding:22px 0 64px;font-family:var(--nt-sans)}
.theme-news-listing[data-news-theme="NT501"] *{box-sizing:border-box}
.nt-news-container{width:min(1180px,calc(100% - 48px));margin-inline:auto}
.nt-news-container a{color:inherit;text-decoration:none}
.nt-news-container a:focus-visible,.nt-news-container button:focus-visible,.nt-news-container input:focus-visible{outline:2px solid #8a682d;outline-offset:4px}
.nt-news-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;color:#807b72;font-size:11px;line-height:1.6;margin-bottom:24px}
.nt-news-breadcrumb [aria-current]{color:var(--nt-ink)}
.nt-news-masthead{display:flex;align-items:flex-end;justify-content:space-between;gap:32px;padding-bottom:24px}
.nt-news-kicker{margin:0 0 10px;color:#856429;font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase}
.nt-news-masthead h1{font:700 clamp(36px,4vw,48px)/1.12 Georgia,"Times New Roman",serif;letter-spacing:-.03em;margin:0 0 12px;overflow-wrap:anywhere}
.nt-news-intro{margin:0;max-width:680px;color:#797268;font-size:13px;line-height:1.8}
.nt-news-search{display:flex;flex:0 0 260px;align-items:center;min-height:42px;max-width:100%;border:1px solid #e5e0d7;background:#faf9f6}
.nt-news-search input{min-width:0;width:100%;padding:12px 0 12px 14px;border:0;background:transparent;color:var(--nt-ink);font:12px var(--nt-sans)}
.nt-news-search input::placeholder{color:#807b72}
.nt-news-search button{display:grid;place-items:center;flex:0 0 42px;align-self:stretch;border:0;background:transparent;color:#797268;cursor:pointer}
.nt-news-search button:hover{background:var(--nt-paper)}
.nt-news-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.nt-news-sections{display:flex;flex-wrap:wrap;gap:8px 28px;border-top:2px solid var(--nt-dark);border-bottom:1px solid #ddd7cc;padding:14px 0;margin-bottom:28px;font-size:12px;font-weight:600;line-height:1.6}
.nt-news-sections a{position:relative;padding:2px 0}
.nt-news-sections a:hover,.nt-news-sections a[aria-current]{color:#856429}
.nt-news-sections a[aria-current]::after{content:"";position:absolute;height:2px;background:var(--nt-gold);bottom:-15px;left:0;right:0}
.nt-news-query{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0 0 28px;font-size:13px;line-height:1.7;color:#797268}
.nt-news-query strong{color:var(--nt-ink)}
.nt-news-query a{margin-left:auto;color:#856429;font-size:11px}
.nt-news-topic-image{display:block;width:100%;max-height:260px;object-fit:cover;margin-bottom:28px}
.nt-news-front{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(0,1fr);gap:40px}
.nt-news-front--single{grid-template-columns:1fr;max-width:850px}
.nt-news-lead,.nt-news-briefs{min-width:0}
.nt-news-briefs{padding-left:32px;border-left:1px solid #e5e0d7}
.nt-news-section-label{font:700 10px/1.6 var(--nt-sans);letter-spacing:.12em;text-transform:uppercase;margin:0 0 17px;color:#8a682d}
.nt-news-story-image{display:block;overflow:hidden;background:#f1eee7;aspect-ratio:1.9}
.nt-news-story-image img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .35s}
.nt-news-story-image:hover img{transform:scale(1.035)}
.nt-news-image-placeholder{display:grid;place-items:center;height:100%;color:#b2a58c}
.nt-news-story-meta{display:flex;align-items:center;flex-wrap:wrap;gap:8px 16px;font-size:10px;line-height:1.6;color:#8b8377;margin-bottom:9px}
.nt-news-story-meta a{color:#8a682d;font-weight:600}
.nt-news-story-meta time{white-space:nowrap}
.nt-news-story-title{margin:0;font:700 24px/1.35 Georgia,"Times New Roman",serif;letter-spacing:-.01em;overflow-wrap:anywhere}
.nt-news-story-title a:hover{color:#8a682d}
.nt-news-story-summary{margin:11px 0 0;color:#797268;font-size:13px;line-height:1.85;overflow-wrap:anywhere}
.nt-news-story--lead .nt-news-story-copy{padding-bottom:20px}
.nt-news-story--lead .nt-news-story-title{font-size:30px}
.nt-news-read{display:inline-flex;align-items:center;gap:20px;margin-top:16px;font-size:11px;font-weight:700;color:#8a682d!important}
.nt-news-story--brief{display:grid;grid-template-columns:minmax(0,1fr) 108px;gap:16px;border-bottom:1px solid #e5e0d7;padding:0 0 24px;margin-bottom:24px}
.nt-news-story--brief .nt-news-story-image{grid-column:2;grid-row:1;aspect-ratio:1.2}
.nt-news-story--brief .nt-news-story-copy{grid-column:1;grid-row:1}
.nt-news-story--brief .nt-news-story-title{font-size:21px}
.nt-news-story--brief .nt-news-story-summary{font-size:12px}
.nt-news-feed{margin-top:40px;border-top:2px solid var(--nt-dark)}
.nt-news-feed-heading{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:20px 0;border-bottom:1px solid #e5e0d7}
.nt-news-feed-heading h2{margin:0;font:700 24px/1.4 Georgia,"Times New Roman",serif}
.nt-news-feed-heading span{font-size:10px;color:#8b8377}
.nt-news-story--row{display:grid;grid-template-columns:210px minmax(0,1fr);gap:28px;padding:24px 0;border-bottom:1px solid #e5e0d7;align-items:start}
.nt-news-story--row .nt-news-story-image{aspect-ratio:1.5}
.nt-news-story--row .nt-news-story-image{grid-column:1;grid-row:1}
.nt-news-story--row .nt-news-story-copy{max-width:820px;grid-column:2;grid-row:1}
.nt-news-empty{padding:50px 20px;text-align:center;border-bottom:1px solid #e5e0d7}
.nt-news-empty h2{font:700 26px/1.4 Georgia,"Times New Roman",serif;margin:0 0 12px}
.nt-news-empty p{font-size:13px;color:#797268;line-height:1.8;margin:0 0 20px}
.nt-news-empty a{font-size:12px;font-weight:700;color:#8a682d}
.nt-news-pagination{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:24px;padding-top:30px;font-size:12px}
.nt-news-pagination a{padding:10px 0;border-bottom:1px solid #cfc5b2}
.nt-news-pagination span{color:#8b8377}
@media(max-width:1000px){.nt-news-front{grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:28px}.nt-news-briefs{padding-left:24px}.nt-news-story--brief{grid-template-columns:minmax(0,1fr) 80px;gap:12px}.nt-news-story--brief .nt-news-story-title{font-size:19px}.nt-news-masthead{gap:24px}.nt-news-search{flex-basis:230px}}
@media(max-width:760px){.nt-news-front{grid-template-columns:1fr;gap:28px}.nt-news-briefs{border-left:0;border-top:1px solid #e5e0d7;padding:24px 0 0}.nt-news-story--brief{grid-template-columns:minmax(0,1fr) 130px}.nt-news-story--brief .nt-news-story-title{font-size:22px}.nt-news-masthead{display:block}.nt-news-search{width:100%;margin-top:20px}.nt-news-story--row{grid-template-columns:160px minmax(0,1fr);gap:20px}}
@media(max-width:600px){
    .theme-news-listing[data-news-theme="NT501"]{padding:18px 0 40px}
    .nt-news-container{width:calc(100% - 32px)}
    .nt-news-breadcrumb{margin-bottom:20px}
    .nt-news-masthead{padding-bottom:20px}
    .nt-news-masthead h1{font-size:38px}
    .nt-news-sections{gap:10px 20px;padding:12px 0;margin-bottom:22px}
    .nt-news-sections a[aria-current]::after{bottom:-3px}
    .nt-news-story--lead .nt-news-story-title{font-size:26px}
    .nt-news-story-image{aspect-ratio:1.6}
    .nt-news-story--brief{grid-template-columns:minmax(0,1fr) 95px;gap:16px}
    .nt-news-story--brief .nt-news-story-title{font-size:21px}
    .nt-news-story--row{grid-template-columns:100px minmax(0,1fr);gap:16px;padding:20px 0}
    .nt-news-story--row .nt-news-story-title{font-size:19px}
    .nt-news-story--row .nt-news-story-summary{font-size:12px}
    .nt-news-feed{margin-top:28px}
}
@media(prefers-reduced-motion:reduce){.nt-news-story-image img{transition:none}.nt-news-story-image:hover img{transform:none}}
</style>

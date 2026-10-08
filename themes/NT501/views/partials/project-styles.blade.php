<style>
.nt-project-index{background:#f8f7f3;color:var(--nt-ink);padding:20px 0 64px}
.nt-project-index *{box-sizing:border-box}
.nt-project-container{width:min(1180px,calc(100% - 48px));margin-inline:auto}
.nt-project-index a{color:inherit;text-decoration:none}
.nt-project-index a:focus-visible,.nt-project-index button:focus-visible,.nt-project-index input:focus-visible{outline:2px solid #8a682d;outline-offset:4px}
.nt-project-breadcrumb{display:flex;gap:10px;align-items:center;font-size:11px;color:#79756c;line-height:1.5;margin-bottom:20px}
.nt-project-breadcrumb [aria-current]{color:var(--nt-ink)}
.nt-project-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:32px;margin-bottom:20px}
.nt-project-eyebrow{margin:0 0 10px;color:#856429;font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase}
.nt-project-heading h1{margin:0 0 8px;font:700 clamp(32px,3.5vw,40px)/1.1 var(--nt-serif);letter-spacing:-.025em;overflow-wrap:anywhere}
.nt-project-intro{max-width:660px;margin:0;color:#706b61;font-size:13px;line-height:1.8}
.nt-project-search{display:flex;align-items:center;flex:0 0 280px;max-width:100%;min-height:44px;background:#fff;border:1px solid #dfdbd2;border-radius:5px}
.nt-project-search input{width:100%;min-width:0;padding:12px 0 12px 15px;border:0;background:transparent;color:var(--nt-ink);font:12px var(--nt-sans)}
.nt-project-search input::placeholder{color:#79756c}
.nt-project-search button{display:grid;place-items:center;flex:0 0 44px;align-self:stretch;border:0;background:transparent;color:#625846;cursor:pointer;border-radius:4px}
.nt-project-search button:hover{background:var(--nt-paper)}
.nt-project-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.nt-project-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;border-top:1px solid #e5e0d6;padding:12px 0 16px;font-size:11px;color:#777165}
.nt-project-toolbar a{display:inline-flex;align-items:center;gap:12px;color:#856429}
.nt-project-toolbar a span{font-size:18px}
.nt-project-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:28px 24px}
.nt-project-item{min-width:0;background:#fff;border:1px solid #e9e5dc;border-radius:6px;overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .2s,border-color .2s}
.nt-project-item:hover{border-color:#cec3ac;box-shadow:0 10px 26px #302e270b}
.nt-project-image{display:block;aspect-ratio:1.5;overflow:hidden;background:#e9e3d7}
.nt-project-image img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .35s}
.nt-project-item:hover .nt-project-image img{transform:scale(1.035)}
.nt-project-placeholder{display:grid;place-items:center;height:100%;padding:24px;text-align:center;color:#746a58;font:600 20px/1.4 var(--nt-serif)}
.nt-project-body{display:flex;flex:1;flex-direction:column;padding:21px 22px 19px}
.nt-project-body h2{margin:0 0 10px;font:700 19px/1.4 var(--nt-serif);overflow-wrap:anywhere}
.nt-project-body h2 a:hover{color:#856429}
.nt-project-body>p{margin:0 0 20px;font-size:13px;line-height:1.8;color:#736e64;overflow-wrap:anywhere}
.nt-project-index .nt-project-link{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:auto;padding-top:14px;border-top:1px solid #eee9e0;color:#856429;font-size:11px;font-weight:700;line-height:1.6}
.nt-project-link svg{flex-shrink:0;transition:transform .2s}
.nt-project-link:hover svg{transform:translateX(3px)}
.nt-project-empty{grid-column:1/-1;text-align:center;background:#fff;border:1px solid #e5e0d6;border-radius:6px;padding:48px 24px}
.nt-project-empty h2{font:700 24px/1.4 var(--nt-serif);margin:0 0 12px}
.nt-project-empty p{font-size:14px;line-height:1.8;color:#736e64;margin:0}
.nt-project-empty .nt-project-link{display:inline-flex;margin-top:22px;border:0}
.nt-project-pagination{margin-top:32px}
.nt-project-pagination .pagination{display:flex;justify-content:center;gap:12px;list-style:none;margin:0;padding:0;font-size:13px}
.nt-project-pagination a,.nt-project-pagination .disabled span{display:block;padding:12px 18px;background:#fff;border:1px solid #dfdbd2;border-radius:4px}
.nt-project-pagination a:hover{border-color:#856429;color:#856429}
.nt-project-pagination .disabled{color:#9c978e}
@media(max-width:1000px){.nt-project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.nt-project-heading{gap:28px}.nt-project-search{flex-basis:240px}}
@media(max-width:600px){
    .nt-project-index{padding:18px 0 40px}
    .nt-project-container{width:calc(100% - 32px)}
    .nt-project-breadcrumb{margin-bottom:22px}
    .nt-project-heading{display:block;margin-bottom:20px}
    .nt-project-heading h1{font-size:34px}
    .nt-project-intro{font-size:13px}
    .nt-project-search{margin-top:20px;width:100%}
    .nt-project-toolbar{padding:14px 0 16px}
    .nt-project-grid{grid-template-columns:1fr;gap:20px}
    .nt-project-body{padding:18px}
    .nt-project-body h2{font-size:19px}
}
@media(prefers-reduced-motion:reduce){.nt-project-item,.nt-project-image img,.nt-project-link svg{transition:none}.nt-project-item:hover .nt-project-image img{transform:none}}
</style>

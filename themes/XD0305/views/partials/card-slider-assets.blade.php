@once
<style>
.xd5-card-slider{margin-top:34px;min-width:0}
.xd5-card-track{display:flex;gap:24px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:thin;scrollbar-color:#c38a23 #edf0f2;padding:2px 0 16px;overscroll-behavior-x:contain}
.xd5-slide-card{flex:0 0 calc((100% - 48px)/3);min-width:0;overflow:hidden;scroll-snap-align:start;border:1px solid #e5e7eb;border-radius:16px;background:#fff;color:#191a1d}
.xd5-slide-card img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover;transition:transform .3s}
.xd5-slide-card h3{margin:0;padding:22px 24px;font-size:21px;line-height:1.4;overflow-wrap:anywhere}
.xd5-slide-card a{display:block;height:100%}
.xd5-slide-card a:hover h3{color:#98620c}
.xd5-slide-card a:hover img{transform:scale(1.025)}
.xd5-card-controls{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}
.xd5-card-controls[hidden]{display:none}
.xd5-card-controls button{display:grid;place-items:center;width:44px;height:44px;border:1px solid #d9dce1;border-radius:50%;background:#fff;color:#191a1d;font-size:24px;cursor:pointer}
.xd5-card-controls button:hover:not(:disabled){background:#f0a629;border-color:#f0a629}
.xd5-card-controls button:disabled{opacity:.35;cursor:default}
.xd5-card-slider :focus-visible{outline:3px solid #98620c;outline-offset:-3px}
.xd5-customer-card img{aspect-ratio:4/3;object-position:center 30%}
@media(max-width:900px){.xd5-slide-card{flex-basis:calc((100% - 24px)/2)}}
@media(max-width:620px){.xd5-card-track{gap:16px}.xd5-slide-card{flex-basis:88%}.xd5-slide-card h3{padding:18px;font-size:19px}.xd5-compact-section .xd5-title{font-size:30px}}
@media(prefers-reduced-motion:reduce){.xd5-slide-card img{transition:none}}
</style>
@push('scripts')
<script>
(() => {
    document.querySelectorAll('[data-xd5-card-slider]').forEach(slider => {
        const track = slider.querySelector('[data-card-track]');
        const controls = slider.querySelector('.xd5-card-controls');
        const prev = slider.querySelector('[data-card-prev]');
        const next = slider.querySelector('[data-card-next]');
        const update = () => {
            const max = track.scrollWidth - track.clientWidth;
            controls.hidden = max <= 1;
            prev.disabled = track.scrollLeft <= 1;
            next.disabled = track.scrollLeft >= max - 1;
        };
        const move = direction => {
            const card = track.firstElementChild;
            if (!card) return;
            const step = card.getBoundingClientRect().width + (parseFloat(getComputedStyle(track).gap) || 0);
            track.scrollBy({left:direction * step,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
        };
        prev.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        track.addEventListener('keydown', event => {
            if (event.target !== track || !['ArrowLeft','ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            move(event.key === 'ArrowRight' ? 1 : -1);
        });
        track.addEventListener('scroll', update, {passive:true});
        new ResizeObserver(update).observe(track);
        update();
    });
})();
</script>
@endpush
@endonce

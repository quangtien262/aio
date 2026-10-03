<script>(()=>{const b=document.querySelector('[data-xd5-menu]'),n=document.querySelector('[data-xd5-nav]');b?.addEventListener('click',()=>{const o=n?.classList.toggle('is-open');b.setAttribute('aria-expanded',o?'true':'false')});document.querySelectorAll('[data-xd5-hero]').forEach(h=>{const s=[...h.querySelectorAll('[data-xd5-slide]')];if(s.length<2)return;let i=0;setInterval(()=>{s[i].classList.remove('is-active');i=(i+1)%s.length;s[i].classList.add('is-active')},Math.max(2500,Number(h.dataset.autoplay||6000)))})})();</script>

<script>
(() => {
    const track = document.querySelector('#foot-team-track');
    const controls = document.querySelector('[data-foot-team-controls]');
    if (!track || !controls) return;
    const previous = controls.querySelector('[data-foot-team-prev]');
    const next = controls.querySelector('[data-foot-team-next]');
    const update = () => {
        const maximum = track.scrollWidth - track.clientWidth;
        controls.hidden = maximum < 2;
        previous.disabled = track.scrollLeft < 2;
        next.disabled = track.scrollLeft >= maximum - 2;
    };
    const move = direction => {
        const card = track.querySelector('.foot-team-card');
        if (!card) return;
        const step = card.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || 0);
        track.scrollBy({left: direction * step, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
    };
    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    track.addEventListener('keydown', event => {
        if (event.target !== track || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        move(event.key === 'ArrowRight' ? 1 : -1);
    });
    track.addEventListener('scroll', update, {passive:true});
    new ResizeObserver(update).observe(track);
    update();
})();
</script>

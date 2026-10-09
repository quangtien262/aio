<script>(()=>{document.querySelector('[data-n502-category]')?.addEventListener('click',e=>e.currentTarget.closest('.n502-category')?.classList.toggle('is-open'));const nav=document.querySelector('[data-n502-nav]');document.querySelector('[data-n502-menu]')?.addEventListener('click',()=>nav?.classList.toggle('is-open'));document.querySelectorAll('[data-n502-slider]').forEach(slider=>{const slides=[...slider.querySelectorAll('[data-n502-slide]')];if(slides.length<2)return;let i=0;setInterval(()=>{slides[i].classList.remove('is-active');i=(i+1)%slides.length;slides[i].classList.add('is-active')},Math.max(2500,Number(slider.dataset.autoplay||6000)))})})();</script>

<script>
(() => {
    document.querySelectorAll('[data-n502-category-slider]').forEach(slider => {
        const track = slider.querySelector('[data-category-track]');
        const controls = slider.querySelector('.n502-category-controls');
        const prev = slider.querySelector('[data-category-prev]');
        const next = slider.querySelector('[data-category-next]');
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

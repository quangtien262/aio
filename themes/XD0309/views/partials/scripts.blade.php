<script>(()=>{document.querySelectorAll('[data-xd5-hero]').forEach(h=>{const s=[...h.querySelectorAll('[data-xd5-slide]')];if(s.length<2)return;let i=0;setInterval(()=>{s[i].classList.remove('is-active');i=(i+1)%s.length;s[i].classList.add('is-active')},Math.max(2500,Number(h.dataset.autoplay||6000)))})})();</script>
<script>
document.querySelectorAll('.xd9-news-slider').forEach((section) => {
    const track = section.querySelector('[data-xd9-news-track]');
    const previous = section.querySelector('[data-xd9-news-prev]');
    const next = section.querySelector('[data-xd9-news-next]');
    if (!track || !previous || !next) return;
    const update = () => {
        previous.disabled = track.scrollLeft <= 1;
        next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 1;
    };
    const move = (direction) => track.scrollBy({left: direction * (track.firstElementChild.getBoundingClientRect().width + parseFloat(getComputedStyle(track).gap || 0)), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    track.addEventListener('scroll', update, {passive: true});
    window.addEventListener('resize', update);
    update();
});
</script>

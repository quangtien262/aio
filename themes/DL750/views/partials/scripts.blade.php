<script>
(()=>{const menu=document.querySelector('[data-dl-menu]'),nav=document.querySelector('[data-dl-nav]');menu?.addEventListener('click',()=>nav?.classList.toggle('is-open'));document.querySelectorAll('[data-dl-slider]').forEach(slider=>{const slides=[...slider.querySelectorAll('.dl-slide')];if(slides.length<2)return;let index=0;const show=n=>{slides[index].classList.remove('is-active');index=(n+slides.length)%slides.length;slides[index].classList.add('is-active')};let timer=setInterval(()=>show(index+1),Number(slider.dataset.delay||5600));const move=d=>{clearInterval(timer);show(index+d);timer=setInterval(()=>show(index+1),Number(slider.dataset.delay||5600))};slider.querySelector('[data-dl-prev]')?.addEventListener('click',()=>move(-1));slider.querySelector('[data-dl-next]')?.addEventListener('click',()=>move(1))});document.querySelectorAll('[data-dl-faq]').forEach(button=>button.addEventListener('click',()=>button.closest('article')?.classList.toggle('is-open')));const targets=[...document.querySelectorAll('.dl-section>.dl-wrap')];if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches&&'IntersectionObserver'in window){targets.forEach(el=>el.classList.add('dl-reveal'));const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target)}}),{threshold:.1});targets.forEach(el=>observer.observe(el))}})();
</script>
<script>
document.querySelectorAll('[data-dl-partners]').forEach(section => {
    const rail = section.querySelector('.dl-partner-rail');
    const controls = section.querySelector('.dl-partner-controls');
    const toggle = section.querySelector('[data-dl-partner-toggle]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let paused = reducedMotion.matches;
    let visible = false;
    let timer;
    const move = direction => {
        const maximum = rail.scrollWidth - rail.clientWidth;
        const step = rail.firstElementChild.getBoundingClientRect().width + 18;
        const target = direction > 0 && rail.scrollLeft >= maximum - 2 ? 0
            : direction < 0 && rail.scrollLeft <= 2 ? maximum : rail.scrollLeft + direction * step;
        rail.scrollTo({ left: target, behavior: reducedMotion.matches ? 'instant' : 'smooth' });
    };
    const schedule = () => {
        clearInterval(timer);
        controls.hidden = rail.scrollWidth <= rail.clientWidth + 2;
        toggle.textContent = paused ? '▶' : 'Ⅱ';
        toggle.setAttribute('aria-label', paused ? toggle.dataset.playLabel : toggle.dataset.pauseLabel);
        if (!paused && visible && !document.hidden && !controls.hidden && !section.matches(':hover') && !section.contains(document.activeElement)) {
            timer = setInterval(() => move(1), 3500);
        }
    };
    section.querySelector('[data-dl-partner-prev]').addEventListener('click', () => { move(-1); schedule(); });
    section.querySelector('[data-dl-partner-next]').addEventListener('click', () => { move(1); schedule(); });
    toggle.addEventListener('click', () => { paused = !paused; schedule(); });
    section.addEventListener('mouseenter', schedule);
    section.addEventListener('mouseleave', schedule);
    section.addEventListener('focusin', schedule);
    section.addEventListener('focusout', () => setTimeout(schedule, 0));
    rail.addEventListener('pointerdown', () => clearInterval(timer));
    rail.addEventListener('pointerup', schedule);
    rail.addEventListener('pointercancel', schedule);
    document.addEventListener('visibilitychange', schedule);
    reducedMotion.addEventListener('change', () => { paused = reducedMotion.matches; schedule(); });
    if ('ResizeObserver' in window) new ResizeObserver(schedule).observe(rail);
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(entries => { visible = entries[0].isIntersecting; schedule(); }).observe(section);
    } else {
        visible = true;
    }
    schedule();
});
</script>

<script>
(() => {
    const toggle = document.querySelector('[data-foot-menu-toggle]');
    const menu = document.querySelector('[data-foot-menu]');
    toggle?.addEventListener('click', () => {
        const isOpen = menu?.classList.toggle('is-open') ?? false;
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    const slides = [...document.querySelectorAll('[data-x321-slide]')];
    if (slides.length > 1) {
        let index = 0;
        window.setInterval(() => {
            slides[index].classList.remove('is-active');
            index = (index + 1) % slides.length;
            slides[index].classList.add('is-active');
        }, 6500);
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    document.querySelectorAll('[data-x321-carousel]').forEach((carousel) => {
        const track = carousel.querySelector('[data-carousel-track]');
        const items = [...track.querySelectorAll('[data-carousel-item]')];
        const controls = carousel.querySelector('[data-carousel-controls]');
        const pause = carousel.querySelector('[data-carousel-pause]');
        if (!items.length) return;
        let paused = false;
        let visible = !('IntersectionObserver' in window);
        let lastInteraction = 0;
        const hasOverflow = () => track.scrollWidth > track.clientWidth + 4;
        const update = () => {
            controls.hidden = !hasOverflow();
            pause.hidden = reducedMotion.matches;
            const index = items.reduce((nearest, item, current) => Math.abs(item.offsetLeft - items[0].offsetLeft - track.scrollLeft) < Math.abs(items[nearest].offsetLeft - items[0].offsetLeft - track.scrollLeft) ? current : nearest, 0);
            carousel.querySelector('[data-carousel-status]').textContent = `${String(index + 1).padStart(2, '0')} / ${String(items.length).padStart(2, '0')}`;
            const maximum = track.scrollWidth - track.clientWidth;
            carousel.querySelector('[data-carousel-progress]').style.width = `${hasOverflow() ? Math.min(100, Math.max(8, track.scrollLeft / maximum * 100)) : 100}%`;
        };
        const move = (direction) => {
            if (!hasOverflow()) return;
            const maximum = track.scrollWidth - track.clientWidth;
            const step = items.length > 1 ? items[1].offsetLeft - items[0].offsetLeft : track.clientWidth;
            const position = direction > 0 && track.scrollLeft >= maximum - 4 ? 0 : direction < 0 && track.scrollLeft <= 4 ? maximum : Math.min(maximum, Math.max(0, track.scrollLeft + direction * step));
            track.scrollTo({ left: position, behavior: reducedMotion.matches ? 'instant' : 'smooth' });
        };
        carousel.querySelector('[data-carousel-prev]').addEventListener('click', () => { lastInteraction = Date.now(); move(-1); });
        carousel.querySelector('[data-carousel-next]').addEventListener('click', () => { lastInteraction = Date.now(); move(1); });
        pause.addEventListener('click', () => {
            paused = !paused;
            pause.setAttribute('aria-pressed', String(paused));
            pause.setAttribute('aria-label', paused ? pause.dataset.playLabel : pause.dataset.pauseLabel);
            pause.querySelector('i').className = `fa-solid fa-${paused ? 'play' : 'pause'}`;
        });
        track.addEventListener('scroll', update, { passive: true });
        track.addEventListener('pointerdown', () => { lastInteraction = Date.now(); }, { passive: true });
        track.addEventListener('wheel', () => { lastInteraction = Date.now(); }, { passive: true });
        track.addEventListener('keydown', (event) => {
            if (event.target !== track || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault(); lastInteraction = Date.now(); move(event.key === 'ArrowRight' ? 1 : -1);
        });
        if ('ResizeObserver' in window) new ResizeObserver(update).observe(track);
        else window.addEventListener('resize', update);
        reducedMotion.addEventListener('change', update);
        if ('IntersectionObserver' in window) new IntersectionObserver((entries) => { visible = entries[0].isIntersecting; }, { threshold: .25 }).observe(carousel);
        window.setInterval(() => {
            if (visible && !paused && !reducedMotion.matches && !document.hidden && !carousel.matches(':hover') && !carousel.contains(document.activeElement) && Date.now() - lastInteraction > 8000) move(1);
        }, 4800);
        update();
    });

    const revealItems = [...document.querySelectorAll('[data-x321-reveal]')];
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        revealItems.forEach((item) => item.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    revealItems.forEach((item) => observer.observe(item));
})();
</script>

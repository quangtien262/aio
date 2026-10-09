<script>
(() => {
    document.querySelectorAll('[data-xd8-slider]').forEach((slider) => {
        const track = slider.querySelector('[data-xd8-track]');
        const previous = slider.querySelector('[data-xd8-prev]');
        const next = slider.querySelector('[data-xd8-next]');
        if (!track || !previous || !next) return;
        const update = () => {
            previous.disabled = track.scrollLeft <= 1;
            next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 1;
        };
        const move = (direction) => track.scrollBy({
            left: direction * (track.firstElementChild.getBoundingClientRect().width + parseFloat(getComputedStyle(track).gap || 0)),
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
        });
        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        track.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    });

    document.querySelectorAll('[data-xd4-hero]').forEach((hero) => {
        const slides = [...hero.querySelectorAll('[data-xd4-slide]')];
        if (slides.length < 2) return;
        let active = 0;
        const show = (index) => {
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === active));
        };
        hero.querySelector('[data-xd4-prev]')?.addEventListener('click', () => show(active - 1));
        hero.querySelector('[data-xd4-next]')?.addEventListener('click', () => show(active + 1));
        window.setInterval(() => show(active + 1), Math.max(2500, Number(hero.dataset.autoplay || 6000)));
    });

    const revealTargets = [...document.querySelectorAll('main > section:not(.xd4-hero), .xd3-step, .xd8-discovery-card, .xd4-footer__grid > *')];
    revealTargets.forEach((element, index) => {
        element.dataset.xdReveal = '';
        element.style.setProperty('--reveal-delay', `${Math.min(index % 4, 3) * 80}ms`);
    });
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        revealTargets.forEach((element) => element.classList.add('is-visible'));
        return;
    }
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
    }), { threshold: .12, rootMargin: '0px 0px -8% 0px' });
    revealTargets.forEach((element) => observer.observe(element));
})();
</script>

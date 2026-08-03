<script>
(() => {
    const menuButton = document.querySelector('[data-xd5-menu]');
    const menu = document.querySelector('[data-xd5-nav]');
    menuButton?.addEventListener('click', () => {
        const isOpen = menu?.classList.toggle('is-open');
        menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    document.querySelectorAll('[data-xd5-hero]').forEach((hero) => {
        const slides = [...hero.querySelectorAll('[data-xd5-slide]')];
        if (slides.length < 2) return;
        let active = 0;
        window.setInterval(() => {
            slides[active].classList.remove('is-active');
            active = (active + 1) % slides.length;
            slides[active].classList.add('is-active');
        }, Math.max(2500, Number(hero.dataset.autoplay || 6000)));
    });
    const revealTargets = [...document.querySelectorAll('main > section:not(.xd5-hero), .xd5-service, .xd5-benefit-list > *, .xd5-quote, .xd5-team > *, .xd5-post, .xd5-footer-grid > *')];
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

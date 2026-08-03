<script>
(() => {
    const menuButton = document.querySelector('[data-foot-menu-toggle]');
    const menu = document.querySelector('[data-foot-menu]');
    menuButton?.addEventListener('click', () => {
        const isOpen = menu?.classList.toggle('is-open') ?? false;
        menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
        menu.classList.remove('is-open');
        menuButton?.setAttribute('aria-expanded', 'false');
    }));

    const slides = [...document.querySelectorAll('[data-c322-slide]')];
    if (slides.length > 1) {
        let active = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
        window.setInterval(() => {
            slides[active].classList.remove('is-active');
            active = (active + 1) % slides.length;
            slides[active].classList.add('is-active');
        }, 6500);
    }

    const targets = document.querySelectorAll([
        '.c322-values article', '.c322-about > div', '.c322-head > *',
        '.c322-services article', '.c322-products article', '.c322-team article',
        '.c322-projects article', '.c322-achievements > .c322-container > *',
        '.c322-stats span', '.c322-testimonials article', '.c322-pricing article',
        '.c322-news article', '.c322-partners a', '.foot-footer__grid > section',
    ].join(','));

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        targets.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    document.documentElement.classList.add('c322-motion-ready');
    targets.forEach((element, index) => {
        element.classList.add('c322-reveal');
        element.style.setProperty('--c322-delay', `${(index % 4) * 90}ms`);
    });
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -7% 0px' });
    targets.forEach((element) => observer.observe(element));
})();
</script>

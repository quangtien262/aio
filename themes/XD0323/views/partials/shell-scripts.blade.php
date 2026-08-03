<script>
(() => {
    const toggle = document.querySelector('[data-foot-menu-toggle]');
    const menu = document.querySelector('[data-foot-menu]');
    toggle?.addEventListener('click', () => {
        const isOpen = menu?.classList.toggle('is-open') ?? false;
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    const slides = [...document.querySelectorAll('[data-c323-slide]')];
    if (slides.length > 1) {
        let index = slides.findIndex((slide) => slide.classList.contains('is-active'));
        if (index < 0) index = 0;
        const show = (next) => {
            slides[index].classList.remove('is-active');
            index = (next + slides.length) % slides.length;
            slides[index].classList.add('is-active');
        };
        document.querySelector('[data-c323-prev]')?.addEventListener('click', () => show(index - 1));
        document.querySelector('[data-c323-next]')?.addEventListener('click', () => show(index + 1));
        window.setInterval(() => show(index + 1), 6500);
    }

    const revealTargets = document.querySelectorAll([
        '.xd323-benefits article',
        '.xd323-about__media', '.xd323-about__body > *',
        '.xd323-stats__grid > div', '.xd323-stats article',
        '.xd323-heading > *', '.xd323-circle-track a',
        '.xd323-product-grid article', '.xd323-wide-track article',
        '.xd323-process__steps article', '.xd323-faq__grid > *',
        '.xd323-testimonial-grid article', '.xd323-team-grid article',
        '.xd323-news-grid article', '.xd323-newsletter > *',
        '.xd323-footer__contact > *', '.xd323-footer__columns > *',
    ].join(','));

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        revealTargets.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    document.documentElement.classList.add('xd323-motion-ready');
    revealTargets.forEach((element, index) => {
        element.classList.add('xd323-reveal');
        element.style.setProperty('--xd323-delay', `${(index % 4) * 90}ms`);
    });

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -7% 0px' });

    revealTargets.forEach((element) => revealObserver.observe(element));
})();
</script>

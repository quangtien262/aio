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

    const slides = [...document.querySelectorAll('[data-xd20-hero-slide]')];
    if (slides.length > 1) {
        let activeIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
        window.setInterval(() => {
            slides[activeIndex].classList.remove('is-active');
            activeIndex = (activeIndex + 1) % slides.length;
            slides[activeIndex].classList.add('is-active');
        }, 6500);
    }

    const revealTargets = document.querySelectorAll([
        '.xd20-quality article',
        '.xd20-about__image',
        '.xd20-copy > *',
        '.xd20-split__copy > *',
        '.xd20-split > img',
        '.xd20-heading > *',
        '.xd20-rail article',
        '.xd20-team article',
        '.xd20-partners a',
        '.foot-footer__top > section',
        '.foot-footer__grid > section',
    ].join(','));

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        revealTargets.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    document.documentElement.classList.add('xd20-motion-ready');
    revealTargets.forEach((element, index) => {
        element.classList.add('xd20-reveal');
        element.style.setProperty('--xd20-delay', `${(index % 4) * 90}ms`);
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -7% 0px' });

    revealTargets.forEach((element) => observer.observe(element));
})();
</script>

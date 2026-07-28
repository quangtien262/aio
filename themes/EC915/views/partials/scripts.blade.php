<script>
(() => {
    const menuButton = document.querySelector('[data-ec15-menu]');
    const nav = document.querySelector('[data-ec15-nav]');
    const header = document.querySelector('.ec15-header');

    menuButton?.addEventListener('click', () => {
        const isOpen = nav?.classList.toggle('is-open') ?? false;
        menuButton.setAttribute('aria-expanded', String(isOpen));
    });

    const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 32);
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    const slider = document.querySelector('[data-ec15-slider]');
    if (slider) {
        const slides = [...slider.querySelectorAll('[data-ec15-slide]')];
        const dots = [...slider.querySelectorAll('[data-ec15-dot]')];
        const previous = slider.querySelector('[data-ec15-prev]');
        const next = slider.querySelector('[data-ec15-next]');
        let active = 0;
        let timer;

        const show = index => {
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, position) => slide.classList.toggle('is-active', position === active));
            dots.forEach((dot, position) => dot.classList.toggle('is-active', position === active));
        };
        const autoplay = () => {
            window.clearInterval(timer);
            if (slides.length > 1) timer = window.setInterval(() => show(active + 1), 5600);
        };

        previous?.addEventListener('click', () => { show(active - 1); autoplay(); });
        next?.addEventListener('click', () => { show(active + 1); autoplay(); });
        dots.forEach((dot, index) => dot.addEventListener('click', () => { show(index); autoplay(); }));
        autoplay();
    }

    document.querySelectorAll('[data-ec15-accordion] > button').forEach(button => {
        button.addEventListener('click', () => {
            const item = button.closest('[data-ec15-accordion]');
            const group = item?.parentElement;
            group?.querySelectorAll('[data-ec15-accordion]').forEach(candidate => {
                if (candidate !== item) candidate.classList.remove('is-open');
            });
            item?.classList.toggle('is-open');
        });
    });

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const revealGroups = [...document.querySelectorAll('[data-ec15-reveal]')];

    revealGroups.forEach(group => {
        group.querySelectorAll('[data-ec15-stagger]').forEach((item, index) => {
            item.style.setProperty('--ec15-delay', `${Math.min(index, 9) * 85}ms`);
        });
    });

    if (!reducedMotion && 'IntersectionObserver' in window) {
        document.documentElement.classList.add('ec15-motion-ready');
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.13,
            rootMargin: '0px 0px -8% 0px',
        });

        revealGroups.forEach(group => observer.observe(group));
    } else {
        revealGroups.forEach(group => group.classList.add('is-visible'));
    }
})();
</script>

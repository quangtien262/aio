<script>
(() => {
    const menuButton = document.querySelector('[data-ec16-menu]');
    const nav = document.querySelector('[data-ec16-nav]');
    menuButton?.addEventListener('click', () => {
        const open = nav?.classList.toggle('is-open') ?? false;
        menuButton.setAttribute('aria-expanded', String(open));
    });

    const slider = document.querySelector('[data-ec16-slider]');
    if (slider) {
        const slides = [...slider.querySelectorAll('[data-ec16-slide]')];
        let active = 0;
        let timer;
        const show = index => {
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, position) => slide.classList.toggle('is-active', position === active));
        };
        const autoplay = () => {
            window.clearInterval(timer);
            if (slides.length > 1) timer = window.setInterval(() => show(active + 1), 5600);
        };
        slider.querySelector('[data-ec16-prev]')?.addEventListener('click', () => { show(active - 1); autoplay(); });
        slider.querySelector('[data-ec16-next]')?.addEventListener('click', () => { show(active + 1); autoplay(); });
        autoplay();
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const groups = [...document.querySelectorAll('[data-ec16-reveal]')];
    groups.forEach(group => group.querySelectorAll('[data-ec16-stagger]').forEach((item, index) => {
        item.style.setProperty('--ec16-delay', `${Math.min(index, 11) * 75}ms`);
    }));

    if (!reducedMotion && 'IntersectionObserver' in window) {
        document.documentElement.classList.add('ec16-motion-ready');
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -7% 0px' });
        groups.forEach(group => observer.observe(group));
    } else {
        groups.forEach(group => group.classList.add('is-visible'));
    }
})();
</script>

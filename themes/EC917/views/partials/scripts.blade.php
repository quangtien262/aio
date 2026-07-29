<script>
(() => {
    const menu = document.querySelector('[data-ec17-menu]');
    const nav = document.querySelector('[data-ec17-nav]');
    menu?.addEventListener('click', () => {
        const open = nav?.classList.toggle('is-open') ?? false;
        menu.setAttribute('aria-expanded', String(open));
    });

    const slider = document.querySelector('[data-ec17-slider]');
    if (slider) {
        const slides = [...slider.querySelectorAll('[data-ec17-slide]')];
        const dots = [...slider.querySelectorAll('[data-ec17-dot]')];
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
        slider.querySelector('[data-ec17-prev]')?.addEventListener('click', () => { show(active - 1); autoplay(); });
        slider.querySelector('[data-ec17-next]')?.addEventListener('click', () => { show(active + 1); autoplay(); });
        dots.forEach(dot => dot.addEventListener('click', () => { show(Number(dot.dataset.ec17Dot)); autoplay(); }));
        autoplay();
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const groups = [...document.querySelectorAll('[data-ec17-reveal]')];
    groups.forEach(group => group.querySelectorAll('[data-ec17-stagger]').forEach((item, index) => {
        item.style.setProperty('--ec17-delay', `${Math.min(index, 12) * 70}ms`);
    }));
    if (!reducedMotion && 'IntersectionObserver' in window) {
        document.documentElement.classList.add('ec17-motion-ready');
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        }), {threshold:.1, rootMargin:'0px 0px -6% 0px'});
        groups.forEach(group => observer.observe(group));
    } else {
        groups.forEach(group => group.classList.add('is-visible'));
    }
})();
</script>

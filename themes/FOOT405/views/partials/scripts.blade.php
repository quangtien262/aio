<script>
(() => {
    const toggle = document.querySelector('[data-f405-menu-toggle]');
    const nav = document.querySelector('[data-f405-nav]');
    toggle?.addEventListener('click', () => { const open = !nav?.classList.contains('is-open'); nav?.classList.toggle('is-open', open); toggle.setAttribute('aria-expanded', String(open)); });
    nav?.querySelectorAll('a,button').forEach((item) => item.addEventListener('click', () => { nav.classList.remove('is-open'); toggle?.setAttribute('aria-expanded', 'false'); }));

    const revealItems = [...document.querySelectorAll('[data-f405-reveal]')];
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const observer = new IntersectionObserver((entries) => entries.forEach((entry) => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } }), { threshold: .1, rootMargin: '0px 0px -35px' });
        revealItems.forEach((item) => observer.observe(item));
    } else revealItems.forEach((item) => item.classList.add('is-visible'));

    document.querySelectorAll('[data-f405-slider]').forEach((slider) => {
        const slides = [...slider.querySelectorAll('[data-f405-slide]')];
        const dots = [...slider.querySelectorAll('[data-f405-dot]')];
        if (slides.length < 2) return;
        let active = 0; let timer;
        const show = (next) => { active = (next + slides.length) % slides.length; slides.forEach((slide, index) => slide.classList.toggle('is-active', index === active)); dots.forEach((dot, index) => dot.classList.toggle('is-active', index === active)); };
        const autoplay = () => { clearInterval(timer); timer = setInterval(() => show(active + 1), 5600); };
        slider.querySelector('[data-f405-prev]')?.addEventListener('click', () => { show(active - 1); autoplay(); });
        slider.querySelector('[data-f405-next]')?.addEventListener('click', () => { show(active + 1); autoplay(); });
        dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.f405Dot)); autoplay(); }));
        autoplay();
    });
})();
</script>

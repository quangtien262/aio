<script>
(() => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.documentElement.classList.add('dn350-motion');

    const reveal = [...document.querySelectorAll('[data-dn350-reveal]')];
    if (reduceMotion || !('IntersectionObserver' in window)) {
        reveal.forEach((item) => item.classList.add('is-visible'));
    } else {
        const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        }), {threshold: .08, rootMargin: '0px 0px -8% 0px'});
        reveal.forEach((item) => observer.observe(item));
    }

    const menuButton = document.querySelector('[data-dn350-menu]');
    const nav = document.querySelector('[data-dn350-nav]');
    menuButton?.addEventListener('click', () => {
        const open = nav?.classList.toggle('is-open') ?? false;
        menuButton.setAttribute('aria-expanded', String(open));
    });
    nav?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => nav.classList.remove('is-open')));

    document.querySelectorAll('[data-dn350-slider]').forEach((slider) => {
        const slides = [...slider.querySelectorAll('[data-dn350-slide]')];
        const dots = [...slider.querySelectorAll('[data-dn350-dot]')];
        if (slides.length < 2) return;
        let active = 0;
        const show = (index) => {
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === active));
            dots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === active));
        };
        dots.forEach((dot, index) => dot.addEventListener('click', () => show(index)));
        if (!reduceMotion) window.setInterval(() => show(active + 1), Number(slider.dataset.autoplay || 6500));
    });
})();
</script>

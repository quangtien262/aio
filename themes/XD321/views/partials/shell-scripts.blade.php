<script>
(() => {
    const toggle = document.querySelector('[data-foot-menu-toggle]');
    const menu = document.querySelector('[data-foot-menu]');
    toggle?.addEventListener('click', () => {
        const isOpen = menu?.classList.toggle('is-open') ?? false;
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    const slides = [...document.querySelectorAll('[data-x321-slide]')];
    if (slides.length > 1) {
        let index = 0;
        window.setInterval(() => {
            slides[index].classList.remove('is-active');
            index = (index + 1) % slides.length;
            slides[index].classList.add('is-active');
        }, 6500);
    }

    const revealItems = [...document.querySelectorAll('[data-x321-reveal]')];
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        revealItems.forEach((item) => item.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    revealItems.forEach((item) => observer.observe(item));
})();
</script>

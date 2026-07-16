<script>
(() => {
    const toggle = document.querySelector('[data-foot-menu-toggle]');
    const menu = document.querySelector('[data-foot-menu]');
    toggle?.addEventListener('click', () => {
        const open = !menu?.classList.contains('is-open');
        menu?.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.querySelectorAll('[data-foot-hero-slide]').forEach((slide, index, slides) => {
        if (slides.length < 2 || index !== 0) return;
        let active = 0;
        window.setInterval(() => {
            slides[active].classList.remove('is-active');
            active = (active + 1) % slides.length;
            slides[active].classList.add('is-active');
        }, 6500);
    });
})();
</script>

<script>
(() => {
    const toggle = document.querySelector('[data-rx13-menu-toggle]');
    const navigation = document.querySelector('[data-rx13-nav]');

    toggle?.addEventListener('click', () => {
        const isOpen = navigation.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    const slides = [...document.querySelectorAll('[data-rx13-hero-slide]')];
    if (slides.length > 1) {
        let active = 0;
        window.setInterval(() => {
            slides[active].classList.remove('is-active');
            active = (active + 1) % slides.length;
            slides[active].classList.add('is-active');
        }, 6500);
    }
})();
</script>

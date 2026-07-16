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
})();
</script>

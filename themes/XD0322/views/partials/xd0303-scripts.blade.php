<script>
(() => {
    const nav = document.querySelector('[data-xd3-nav]');
    const toggle = document.querySelector('[data-xd3-menu-toggle]');
    toggle?.addEventListener('click', () => {
        const open = !nav.classList.contains('is-open');
        nav.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    const slides = Array.from(document.querySelectorAll('[data-xd3-slide]'));
    let index = 0;
    const show = (next) => {
        if (!slides.length) return;
        index = (next + slides.length) % slides.length;
        slides.forEach((slide, position) => slide.classList.toggle('is-active', position === index));
    };
    document.querySelector('[data-xd3-prev]')?.addEventListener('click', () => show(index - 1));
    document.querySelector('[data-xd3-next]')?.addEventListener('click', () => show(index + 1));
    if (slides.length > 1) window.setInterval(() => show(index + 1), 6000);
})();
</script>

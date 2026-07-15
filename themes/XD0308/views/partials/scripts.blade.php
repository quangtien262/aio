<script>
(() => {
    const menuToggle = document.querySelector('[data-xd4-menu-toggle]');
    const menu = document.querySelector('[data-xd4-nav]');
    menuToggle?.addEventListener('click', () => {
        const isOpen = menu?.classList.toggle('is-open');
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.querySelectorAll('[data-xd4-hero]').forEach((hero) => {
        const slides = [...hero.querySelectorAll('[data-xd4-slide]')];
        if (slides.length < 2) return;
        let active = 0;
        const show = (index) => {
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === active));
        };
        hero.querySelector('[data-xd4-prev]')?.addEventListener('click', () => show(active - 1));
        hero.querySelector('[data-xd4-next]')?.addEventListener('click', () => show(active + 1));
        window.setInterval(() => show(active + 1), Math.max(2500, Number(hero.dataset.autoplay || 6000)));
    });
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menu = document.querySelector('[data-ec92-menu]');
    const nav = document.querySelector('[data-ec92-nav]');
    menu?.addEventListener('click', () => nav?.classList.toggle('is-open'));
    document.querySelectorAll('[data-ec92-slider]').forEach(function (slider) {
        const slides = [...slider.querySelectorAll('[data-ec92-slide]')];
        if (slides.length < 2) return;
        let current = 0;
        const show = (next) => { current = (next + slides.length) % slides.length; slides.forEach((slide, index) => slide.classList.toggle('is-active', index === current)); };
        slider.querySelector('[data-ec92-prev]')?.addEventListener('click', () => show(current - 1));
        slider.querySelector('[data-ec92-next]')?.addEventListener('click', () => show(current + 1));
        const delay = Number(slider.dataset.autoplay || 5600);
        if (delay > 0) setInterval(() => show(current + 1), delay);
    });
});
</script>

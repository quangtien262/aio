<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuButton = document.querySelector('[data-ec9-menu]');
    const nav = document.querySelector('[data-ec9-nav]');
    menuButton?.addEventListener('click', () => nav?.classList.toggle('is-open'));

    const categoryButton = document.querySelector('[data-ec9-categories]');
    const categoryRail = document.querySelector('[data-ec9-category-rail]');
    categoryButton?.addEventListener('click', () => categoryRail?.classList.toggle('is-open'));

    document.querySelectorAll('[data-ec9-slider]').forEach(function (slider) {
        const slides = [...slider.querySelectorAll('[data-ec9-slide]')];
        const dots = [...slider.querySelectorAll('[data-ec9-dot]')];
        if (slides.length < 2) return;
        let index = 0;
        const show = (next) => {
            index = (next + slides.length) % slides.length;
            slides.forEach((slide, i) => slide.classList.toggle('is-active', i === index));
            dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
        };
        slider.querySelector('[data-ec9-prev]')?.addEventListener('click', () => show(index - 1));
        slider.querySelector('[data-ec9-next]')?.addEventListener('click', () => show(index + 1));
        dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));
        const autoplay = Number(slider.dataset.autoplay || 5600);
        if (autoplay > 0) setInterval(() => show(index + 1), autoplay);
    });
});
</script>

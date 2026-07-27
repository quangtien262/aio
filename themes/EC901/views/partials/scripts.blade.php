<script>
document.addEventListener('DOMContentLoaded', function () {
    const menu = document.querySelector('[data-ec91-menu]');
    const nav = document.querySelector('[data-ec91-nav]');
    menu?.addEventListener('click', () => nav?.classList.toggle('is-open'));

    document.querySelectorAll('[data-ec91-slider]').forEach(function (slider) {
        const slides = [...slider.querySelectorAll('[data-ec91-slide]')];
        if (slides.length < 2) return;
        let current = 0;
        const show = (next) => {
            current = (next + slides.length) % slides.length;
            slides.forEach((slide, index) => slide.classList.toggle('is-active', index === current));
        };
        slider.querySelector('[data-ec91-prev]')?.addEventListener('click', () => show(current - 1));
        slider.querySelector('[data-ec91-next]')?.addEventListener('click', () => show(current + 1));
        const delay = Number(slider.dataset.autoplay || 5600);
        if (delay > 0) setInterval(() => show(current + 1), delay);
    });

    document.querySelectorAll('[data-ec91-countdown]').forEach(function (node) {
        const fallback = Date.now() + 15 * 60 * 60 * 1000;
        const target = Date.parse(node.dataset.ec91Countdown || '') || fallback;
        const update = () => {
            const total = Math.max(0, target - Date.now());
            const hours = String(Math.floor(total / 3600000)).padStart(2, '0');
            const minutes = String(Math.floor(total / 60000) % 60).padStart(2, '0');
            const seconds = String(Math.floor(total / 1000) % 60).padStart(2, '0');
            node.textContent = `${hours}:${minutes}:${seconds}`;
        };
        update();
        setInterval(update, 1000);
    });
});
</script>

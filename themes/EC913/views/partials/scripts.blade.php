<script>
(() => {
    const menuButton = document.querySelector('[data-ec13-menu]');
    const nav = document.querySelector('[data-ec13-nav]');
    menuButton?.addEventListener('click', () => nav?.classList.toggle('is-open'));

    const megaButton = document.querySelector('[data-ec13-mega-toggle]');
    const categoryMenu = document.querySelector('[data-ec13-category-menu]');
    megaButton?.addEventListener('click', () => categoryMenu?.classList.toggle('is-open'));

    const slider = document.querySelector('[data-ec13-slider]');
    if (slider) {
        const slides = [...slider.querySelectorAll('[data-ec13-slide]')];
        const dots = [...slider.querySelectorAll('[data-ec13-dot]')];
        let active = 0;
        const show = index => {
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, i) => slide.classList.toggle('is-active', i === active));
            dots.forEach((dot, i) => dot.classList.toggle('is-active', i === active));
        };
        dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));
        if (slides.length > 1) setInterval(() => show(active + 1), 5200);
    }

    const countdown = document.querySelector('[data-ec13-countdown]');
    if (countdown) {
        const end = new Date(countdown.dataset.end).getTime();
        const render = () => {
            const remaining = Math.max(0, end - Date.now());
            const day = 86400000, hour = 3600000, minute = 60000;
            countdown.querySelector('[data-days]').textContent = Math.floor(remaining / day);
            countdown.querySelector('[data-hours]').textContent = String(Math.floor((remaining % day) / hour)).padStart(2, '0');
            countdown.querySelector('[data-minutes]').textContent = String(Math.floor((remaining % hour) / minute)).padStart(2, '0');
            countdown.querySelector('[data-seconds]').textContent = String(Math.floor((remaining % minute) / 1000)).padStart(2, '0');
        };
        render();
        setInterval(render, 1000);
    }
})();
</script>

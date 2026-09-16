<script>
(() => {
    const nav = document.querySelector('[data-a850-nav]');
    document.querySelector('[data-a850-menu]')?.addEventListener('click', () => nav?.classList.toggle('is-open'));
    document.querySelectorAll('[data-a850-countdown]').forEach((countdown) => {
        let seconds = Math.max(1, Number(countdown.dataset.a850Countdown || 48)) * 3600;
        const render = () => {
            countdown.querySelector('[data-days]').textContent = String(Math.floor(seconds / 86400)).padStart(2, '0');
            countdown.querySelector('[data-hours]').textContent = String(Math.floor(seconds % 86400 / 3600)).padStart(2, '0');
            countdown.querySelector('[data-minutes]').textContent = String(Math.floor(seconds % 3600 / 60)).padStart(2, '0');
            countdown.querySelector('[data-seconds]').textContent = String(seconds % 60).padStart(2, '0');
            seconds = Math.max(0, seconds - 1);
        };
        render(); window.setInterval(render, 1000);
    });
    if (!('IntersectionObserver' in window)) {
        document.querySelectorAll('.a850-reveal').forEach((element) => element.classList.add('is-visible'));
        return;
    }
    const reveal = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); reveal.unobserve(entry.target); }
    }), { threshold: .08 });
    document.querySelectorAll('.a850-reveal').forEach((element) => reveal.observe(element));
})();
</script>

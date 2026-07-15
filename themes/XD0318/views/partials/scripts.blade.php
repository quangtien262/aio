<script>
    (() => {
        const nav = document.querySelector('[data-fg18-nav]');
        document.querySelector('[data-fg18-menu]')?.addEventListener('click', (event) => {
            const isOpen = nav?.classList.toggle('is-open') || false;
            event.currentTarget.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.querySelectorAll('[data-fg18-hero]').forEach((hero) => {
            const slides = Array.from(hero.querySelectorAll('[data-fg18-slide]'));
            if (!slides.length) return;
            let index = 0;
            const show = (next) => {
                index = (next + slides.length) % slides.length;
                slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === index));
            };
            let timer = window.setInterval(() => show(index + 1), Math.max(3000, Number(hero.dataset.autoplay || 6000)));
            const restart = () => {
                window.clearInterval(timer);
                timer = window.setInterval(() => show(index + 1), Math.max(3000, Number(hero.dataset.autoplay || 6000)));
            };
            hero.querySelector('[data-fg18-hero-prev]')?.addEventListener('click', () => { show(index - 1); restart(); });
            hero.querySelector('[data-fg18-hero-next]')?.addEventListener('click', () => { show(index + 1); restart(); });
        });
    })();
</script>

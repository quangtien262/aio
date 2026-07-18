<script>
    (() => {
        const menuToggle = document.querySelector('[data-spa502-menu-toggle]');
        const menu = document.querySelector('[data-spa502-menu]');
        menuToggle?.addEventListener('click', () => menu?.classList.toggle('is-open'));

        const slides = Array.from(document.querySelectorAll('[data-spa502-hero-slide]'));
        const dots = Array.from(document.querySelectorAll('[data-spa502-hero-dot]'));
        const prev = document.querySelector('[data-spa502-hero-prev]');
        const next = document.querySelector('[data-spa502-hero-next]');
        let active = 0;
        let timer = null;

        const show = (index) => {
            if (!slides.length) return;
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === active));
            dots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === active));
        };

        const play = () => {
            window.clearInterval(timer);
            if (slides.length > 1) timer = window.setInterval(() => show(active + 1), 6000);
        };

        prev?.addEventListener('click', () => { show(active - 1); play(); });
        next?.addEventListener('click', () => { show(active + 1); play(); });
        dots.forEach((dot, index) => dot.addEventListener('click', () => { show(index); play(); }));
        play();
    })();
</script>

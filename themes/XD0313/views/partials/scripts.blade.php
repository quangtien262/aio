<script>
    (() => {
        const nav = document.querySelector('[data-rx13-nav]');
        document.querySelector('[data-rx13-menu]')?.addEventListener('click', (event) => {
            const isOpen = nav?.classList.toggle('is-open') || false;
            event.currentTarget.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.querySelectorAll('[data-rx13-hero]').forEach((hero) => {
            const slides = Array.from(hero.querySelectorAll('[data-rx13-slide]'));
            const dots = Array.from(hero.querySelectorAll('[data-rx13-hero-dot]'));
            if (!slides.length) return;
            let index = 0;
            const show = (next) => {
                index = (next + slides.length) % slides.length;
                slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === index));
                dots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === index));
            };
            let timer = window.setInterval(() => show(index + 1), Math.max(3000, Number(hero.dataset.autoplay || 6500)));
            const restart = () => {
                window.clearInterval(timer);
                timer = window.setInterval(() => show(index + 1), Math.max(3000, Number(hero.dataset.autoplay || 6500)));
            };
            dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.rx13HeroDot || 0)); restart(); }));
        });

        document.querySelectorAll('[data-rx13-row]').forEach((track) => {
            let timer = null;
            const step = () => Math.max(300, Math.round(track.clientWidth * 0.34));
            const go = () => {
                if (track.scrollWidth <= track.clientWidth + 4) return;
                const nextLeft = track.scrollLeft + step();
                track.scrollTo({left: nextLeft >= track.scrollWidth - track.clientWidth - 4 ? 0 : nextLeft, behavior: 'smooth'});
            };
            timer = window.setInterval(go, 3600);
            track.addEventListener('pointerenter', () => window.clearInterval(timer));
            track.addEventListener('pointerleave', () => { timer = window.setInterval(go, 3600); });
        });
    })();
</script>

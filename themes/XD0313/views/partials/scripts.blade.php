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

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const revealSelectors = [
            '.rx13-benefit',
            '.rx13-about__media',
            '.rx13-about__copy > *',
            '.rx13-featured-card',
            '.rx13-service-card',
            '.rx13-promo__image',
            '.rx13-promo-card',
            '.rx13-stats > *',
            '.rx13-process__grid > *',
            '.rx13-step',
            '.rx13-testimonials__grid > *',
            '.rx13-post',
            '.rx13-footer__grid > section',
        ];
        const revealItems = Array.from(document.querySelectorAll(revealSelectors.join(',')));

        revealItems.forEach((item, index) => {
            item.classList.add('rx13-reveal');
            item.style.setProperty('--rx13-reveal-delay', `${Math.min(index % 4, 3) * 90}ms`);
        });

        document.documentElement.classList.add('rx13-motion-ready');

        if (reduceMotion || !('IntersectionObserver' in window)) {
            revealItems.forEach((item) => item.classList.add('is-visible'));
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.14,
            rootMargin: '0px 0px -8% 0px',
        });

        revealItems.forEach((item) => observer.observe(item));
    })();
</script>

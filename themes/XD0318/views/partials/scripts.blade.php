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

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const revealSelectors = [
            '.fg18-about__image',
            '.fg18-about__copy > *',
            '.fg18-video__content > *',
            '.fg18-service-card',
            '.fg18-quote-card',
            '.fg18-faq__grid > *',
            '.fg18-contact__grid > *',
            '.fg18-post',
            '.fg18-footer__grid > section',
        ];
        const revealItems = Array.from(document.querySelectorAll(revealSelectors.join(',')));

        revealItems.forEach((item, index) => {
            item.classList.add('fg18-reveal');
            item.style.setProperty('--fg18-reveal-delay', `${Math.min(index % 4, 3) * 90}ms`);
        });

        document.documentElement.classList.add('fg18-motion-ready');

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

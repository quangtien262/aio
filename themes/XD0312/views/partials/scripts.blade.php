<script>
    (() => {
        const menuButton = document.querySelector('[data-xd5-menu]');
        const navigation = document.querySelector('[data-xd5-nav]');

        menuButton?.addEventListener('click', () => {
            const isOpen = navigation?.classList.toggle('is-open');
            menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        navigation?.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                navigation.classList.remove('is-open');
                menuButton?.setAttribute('aria-expanded', 'false');
            });
        });

        document.querySelectorAll('[data-xd5-hero]').forEach((hero) => {
            const slides = [...hero.querySelectorAll('[data-xd5-slide]')];
            if (slides.length < 2) return;

            let activeIndex = 0;
            window.setInterval(() => {
                slides[activeIndex].classList.remove('is-active');
                activeIndex = (activeIndex + 1) % slides.length;
                slides[activeIndex].classList.add('is-active');
            }, Math.max(2500, Number(hero.dataset.autoplay || 6000)));
        });

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const revealGroups = document.querySelectorAll([
            '.xd5-services',
            '.xd5-about',
            '.xd3-steps',
            '.xd5-benefit-grid',
            '.xd5-team',
            '.xd5-partner-grid',
            '.xd5-posts',
        ].join(','));

        const revealItems = [];
        revealGroups.forEach((group) => {
            const children = [...group.children];
            const targets = children.length > 1 ? children : [group];
            targets.forEach((target, index) => {
                target.classList.add('xd12-reveal');
                target.style.setProperty('--xd12-delay', `${Math.min(index * 90, 360)}ms`);
                revealItems.push(target);
            });
        });

        document.querySelectorAll('.xd5-section > .xd5-container > .xd5-eyebrow, .xd5-team-head, .xd3-process__title, .xd3-process__intro')
            .forEach((target) => {
                target.classList.add('xd12-reveal');
                revealItems.push(target);
            });

        if (reduceMotion || !('IntersectionObserver' in window)) {
            revealItems.forEach((target) => target.classList.add('is-visible'));
            return;
        }

        document.documentElement.classList.add('xd12-motion-ready');
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

        revealItems.forEach((target) => observer.observe(target));
    })();
</script>

<script>
(() => {
    const root = document.documentElement;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const revealItems = [...document.querySelectorAll('[data-dn-reveal]')];

    root.classList.add('dn-motion-ready');
    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    } else {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -9% 0px', threshold: 0.08 });
        revealItems.forEach((item) => observer.observe(item));
    }

    const header = document.querySelector('[data-dn-header]');
    const compactHeader = () => header?.classList.toggle('is-compact', window.scrollY > 520);
    window.addEventListener('scroll', compactHeader, { passive: true });
    compactHeader();

    const menuButton = document.querySelector('[data-dn-menu]');
    const nav = document.querySelector('[data-dn-nav]');
    menuButton?.addEventListener('click', () => {
        const open = nav?.classList.toggle('is-open') ?? false;
        menuButton.setAttribute('aria-expanded', String(open));
    });
    nav?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
        nav.classList.remove('is-open');
        menuButton?.setAttribute('aria-expanded', 'false');
    }));

    document.querySelectorAll('[data-dn-slider]').forEach((slider) => {
        const slides = [...slider.querySelectorAll('[data-dn-slide]')];
        const dots = [...slider.querySelectorAll('[data-dn-dot]')];
        if (slides.length < 2) return;
        let active = 0;
        const show = (next) => {
            active = (next + slides.length) % slides.length;
            slides.forEach((slide, index) => slide.classList.toggle('is-active', index === active));
            dots.forEach((dot, index) => dot.classList.toggle('is-active', index === active));
        };
        dots.forEach((dot, index) => dot.addEventListener('click', () => show(index)));
        if (!reduceMotion) window.setInterval(() => show(active + 1), Number(slider.dataset.autoplay || 6500));
    });

    const tabs = [...document.querySelectorAll('[data-dn-project-tab]')];
    const projects = [...document.querySelectorAll('[data-dn-project]')];
    tabs.forEach((tab) => tab.addEventListener('click', () => {
        const filter = tab.dataset.dnProjectTab;
        tabs.forEach((item) => item.classList.toggle('is-active', item === tab));
        projects.forEach((project) => {
            const visible = filter === 'all' || project.dataset.category === filter;
            project.style.display = visible ? '' : 'none';
        });
    }));

    if (!reduceMotion) {
        const parallax = document.querySelector('[data-dn-parallax]');
        let ticking = false;
        const paint = () => {
            if (parallax) parallax.style.transform = `translate3d(0, ${Math.min(window.scrollY * 0.055, 30)}px, 0)`;
            ticking = false;
        };
        window.addEventListener('scroll', () => {
            if (!ticking) requestAnimationFrame(paint);
            ticking = true;
        }, { passive: true });
    }
})();
</script>

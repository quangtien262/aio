<script>
(() => {
    const toggle = document.querySelector('[data-f404-menu-toggle]');
    const nav = document.querySelector('[data-f404-nav]');
    toggle?.addEventListener('click', () => {
        const open = !nav?.classList.contains('is-open');
        nav?.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('f404-menu-open', open);
    });
    nav?.querySelectorAll('a,button').forEach((item) => item.addEventListener('click', () => {
        nav.classList.remove('is-open');
        toggle?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('f404-menu-open');
    }));

    const revealItems = [...document.querySelectorAll('[data-f404-reveal]')];
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
            if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
        }), { threshold: .12, rootMargin: '0px 0px -35px' });
        revealItems.forEach((item) => observer.observe(item));
    } else revealItems.forEach((item) => item.classList.add('is-visible'));

    document.querySelectorAll('[data-f404-copy]').forEach((button) => button.addEventListener('click', async () => {
        const code = button.dataset.f404Copy || '';
        try { await navigator.clipboard.writeText(code); button.firstChild.textContent = 'Đã sao chép '; }
        catch (_) { window.prompt('Sao chép mã ưu đãi:', code); }
    }));
})();
</script>

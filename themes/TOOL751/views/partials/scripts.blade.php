<script>
(() => {
    const nav = document.querySelector('[data-t751-nav]');
    document.querySelector('[data-t751-menu]')?.addEventListener('click', event => { const open = nav?.classList.toggle('is-open'); event.currentTarget.setAttribute('aria-expanded', String(!!open)); });
    const categories = document.querySelector('[data-t751-category-menu]');
    document.addEventListener('click', event => { if (categories && !categories.contains(event.target)) categories.open = false; });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && categories?.open) { categories.open = false; categories.querySelector('summary').focus(); } });
    document.querySelectorAll('[data-t751-tabs] button').forEach((button) => button.addEventListener('click', () => {
        button.parentElement?.querySelectorAll('button').forEach((item) => item.classList.remove('is-active'));
        button.classList.add('is-active');
    }));
    if (!('IntersectionObserver' in window)) {
        document.querySelectorAll('.t751-reveal').forEach((element) => element.classList.add('is-visible'));
        return;
    }
    const reveal = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); reveal.unobserve(entry.target); }
    }), { threshold: .08 });
    document.querySelectorAll('.t751-reveal').forEach((element) => reveal.observe(element));
})();
</script>

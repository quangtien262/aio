<script>
(() => {
    const body = document.body;
    const nav = document.querySelector('[data-t750-nav]');
    const search = document.querySelector('[data-t750-search-panel]');
    document.querySelector('[data-t750-menu]')?.addEventListener('click', () => nav?.classList.toggle('is-open'));
    document.querySelector('[data-t750-search]')?.addEventListener('click', () => {
        search?.classList.toggle('is-open');
        search?.querySelector('input')?.focus();
    });
    document.querySelectorAll('[data-t750-tabs] button').forEach((button) => button.addEventListener('click', () => {
        button.parentElement?.querySelectorAll('button').forEach((item) => item.classList.remove('is-active'));
        button.classList.add('is-active');
    }));
    const reveal = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            reveal.unobserve(entry.target);
        }
    }), { threshold: .08 });
    body.querySelectorAll('.t750-reveal').forEach((element) => reveal.observe(element));
})();
</script>

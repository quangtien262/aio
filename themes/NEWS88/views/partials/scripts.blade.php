<script>
(() => {
    const nav = document.querySelector('[data-n88-nav]');
    document.querySelector('[data-n88-menu]')?.addEventListener('click', () => nav?.classList.toggle('is-open'));
    const search = document.querySelector('[data-n88-search-panel]');
    document.querySelector('[data-n88-search]')?.addEventListener('click', () => { search?.classList.toggle('is-open'); search?.querySelector('input')?.focus(); });
    const observer = new IntersectionObserver(entries => entries.forEach(entry => entry.isIntersecting && entry.target.classList.add('is-visible')), {threshold: .08});
    document.querySelectorAll('[data-n88-reveal]').forEach(element => observer.observe(element));
})();
</script>

<script>
(() => {
    const nav = document.querySelector('[data-t751-nav]');
    document.querySelector('[data-t751-menu]')?.addEventListener('click', () => nav?.classList.toggle('is-open'));
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

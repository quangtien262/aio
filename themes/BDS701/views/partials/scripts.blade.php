<script>
(() => {
    const menuButton = document.querySelector('[data-bds-menu]');
    const nav = document.querySelector('[data-bds-nav]');
    menuButton?.addEventListener('click', () => nav?.classList.toggle('is-open'));

    const mainImage = document.querySelector('[data-bds-gallery-main]');
    document.querySelectorAll('[data-bds-thumb]').forEach((button) => button.addEventListener('click', () => {
        if (mainImage) mainImage.src = button.dataset.bdsThumb;
        document.querySelectorAll('[data-bds-thumb]').forEach((item) => item.classList.remove('is-active'));
        button.classList.add('is-active');
    }));
})();
</script>

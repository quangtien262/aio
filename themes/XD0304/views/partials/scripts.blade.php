<script>
(() => {
    const menuToggle = document.querySelector('[data-xd4-menu-toggle]');
    const menu = document.querySelector('[data-xd4-nav]');
    menuToggle?.addEventListener('click', () => {
        const isOpen = menu?.classList.toggle('is-open');
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    const dialog = document.querySelector('[data-xd4-consultation]');
    let opener;
    if (dialog && typeof dialog.showModal === 'function') {
        document.querySelectorAll('[data-xd4-consultation-open]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                if (dialog.open) return;
                opener = button;
                dialog.showModal();
                document.body.classList.add('xd4-consultation-open');
                dialog.querySelector('input[name="name"]')?.focus();
            });
        });
        dialog.querySelector('[data-xd4-consultation-close]')?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
        dialog.addEventListener('close', () => {
            document.body.classList.remove('xd4-consultation-open');
            opener?.focus();
        });
    }

    document.querySelectorAll('[data-xd4-hero]').forEach((hero) => {
        const slides = [...hero.querySelectorAll('[data-xd4-slide]')];
        if (slides.length < 2) return;
        let active = 0;
        const show = (index) => {
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === active));
        };
        hero.querySelector('[data-xd4-prev]')?.addEventListener('click', () => show(active - 1));
        hero.querySelector('[data-xd4-next]')?.addEventListener('click', () => show(active + 1));
        window.setInterval(() => show(active + 1), Math.max(2500, Number(hero.dataset.autoplay || 6000)));
    });
})();
</script>

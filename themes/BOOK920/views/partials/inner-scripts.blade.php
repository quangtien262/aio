<script>
document.querySelectorAll('[data-book20-gallery]').forEach(gallery => {
    gallery.querySelectorAll('[data-book20-thumb]').forEach(button => button.addEventListener('click', () => {
        const image = button.querySelector('img');
        const main = gallery.querySelector('[data-book20-main]');
        main.src = image.src; main.alt = image.alt;
        gallery.querySelector('[data-book20-full]').href = image.src;
        gallery.querySelectorAll('[data-book20-thumb]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    }));
});
document.querySelectorAll('[data-book20-quantity]').forEach(control => {
    const input = control.querySelector('input');
    control.querySelectorAll('[data-book20-step]').forEach(button => button.addEventListener('click', () => {
        input.value = Math.min(99, Math.max(1, (Math.trunc(Number(input.value)) || 1) + Number(button.dataset.book20Step)));
        input.dispatchEvent(new Event('change', {bubbles:true}));
    }));
});
</script>

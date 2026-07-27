<script>
document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('[data-ec99-menu]');
    const nav = document.querySelector('[data-ec99-nav]');
    menu?.addEventListener('click', () => nav?.classList.toggle('is-open'));
    const slider = document.querySelector('[data-ec99-slider]');
    if (slider) {
        const slides = [...slider.querySelectorAll('[data-ec99-slide]')];
        if (slides.length > 1) {
            let index = 0;
            setInterval(() => {
                slides[index].classList.remove('is-active');
                index = (index + 1) % slides.length;
                slides[index].classList.add('is-active');
            }, Number(slider.dataset.autoplay) || 6500);
        }
    }
});
</script>

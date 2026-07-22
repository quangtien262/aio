<script>
document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('[data-d202-menu]');
    document.querySelector('[data-d202-menu-toggle]')?.addEventListener('click', () => menu?.classList.toggle('is-open'));

    const modal = document.querySelector('[data-d202-auth]');
    document.querySelectorAll('[data-d202-auth-open]').forEach((element) => element.addEventListener('click', () => {
        if (modal) {
            modal.hidden = false;
            document.body.classList.add('d202-lock');
        }
    }));
    document.querySelectorAll('[data-d202-auth-close]').forEach((element) => element.addEventListener('click', () => {
        if (modal) {
            modal.hidden = true;
            document.body.classList.remove('d202-lock');
        }
    }));

    document.querySelectorAll('[data-d202-slider]').forEach((slider) => {
        const slides = [...slider.querySelectorAll('[data-d202-slide]')];
        const dots = [...slider.querySelectorAll('[data-d202-dot]')];
        if (slides.length < 2) return;
        let index = 0;
        const show = (nextIndex) => {
            index = (nextIndex + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === index));
            dots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === index));
        };
        dots.forEach((dot, dotIndex) => dot.addEventListener('click', () => show(dotIndex)));
        window.setInterval(() => show(index + 1), Number(slider.dataset.autoplay) || 6000);
    });

    document.querySelectorAll('[data-d202-service-carousel]').forEach((carousel) => {
        const track = carousel.querySelector('[data-d202-service-track]');
        const previous = carousel.querySelector('[data-d202-service-prev]');
        const next = carousel.querySelector('[data-d202-service-next]');
        if (!track) return;

        let timer;
        const canSlide = () => track.scrollWidth > track.clientWidth + 2;
        const step = () => {
            const card = track.querySelector('.d202-service');
            const gap = Number.parseFloat(window.getComputedStyle(track).gap) || 0;
            return card ? card.getBoundingClientRect().width + gap : track.clientWidth;
        };
        const move = (direction) => {
            if (!canSlide()) return;
            const maximum = Math.max(0, track.scrollWidth - track.clientWidth);
            const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 3;
            const atStart = track.scrollLeft <= 3;
            const target = direction > 0
                ? (atEnd ? 0 : Math.min(maximum, track.scrollLeft + step()))
                : (atStart ? maximum : Math.max(0, track.scrollLeft - step()));
            track.scrollTo({ left: target, behavior: 'smooth' });
        };
        const stop = () => window.clearInterval(timer);
        const start = () => {
            stop();
            if (canSlide() && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                timer = window.setInterval(() => move(1), 3500);
            }
        };
        const refresh = () => {
            carousel.classList.toggle('is-static', !canSlide());
            start();
        };

        previous?.addEventListener('click', () => move(-1));
        next?.addEventListener('click', () => move(1));
        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', start);
        carousel.addEventListener('focusin', stop);
        carousel.addEventListener('focusout', start);
        window.addEventListener('resize', refresh);
        refresh();
    });
});
</script>

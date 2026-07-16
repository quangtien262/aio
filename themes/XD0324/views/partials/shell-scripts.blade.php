<script>
(() => {
    const header = document.querySelector('[data-xd324-header]');
    const syncHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 20);
    syncHeader();
    window.addEventListener('scroll', syncHeader, { passive: true });

    const toggle = document.querySelector('[data-foot-menu-toggle]');
    const menu = document.querySelector('[data-foot-menu]');
    toggle?.addEventListener('click', () => {
        const isOpen = menu?.classList.toggle('is-open') ?? false;
        header?.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    const slides = [...document.querySelectorAll('[data-c324-slide]')];
    if (slides.length > 1) {
        let index = slides.findIndex((slide) => slide.classList.contains('is-active'));
        if (index < 0) index = 0;
        const show = (next) => {
            slides[index].classList.remove('is-active');
            index = (next + slides.length) % slides.length;
            slides[index].classList.add('is-active');
        };
        document.querySelector('[data-c324-prev]')?.addEventListener('click', () => show(index - 1));
        document.querySelector('[data-c324-next]')?.addEventListener('click', () => show(index + 1));
        window.setInterval(() => show(index + 1), 7000);
    }

    document.querySelectorAll('[data-drag-scroll]').forEach((track) => {
        let isDown = false;
        let startX = 0;
        let scrollLeft = 0;
        let lastX = 0;
        let velocity = 0;
        let frame = 0;
        const glide = () => {
            velocity *= 0.92;
            track.scrollLeft -= velocity;
            if (Math.abs(velocity) > 0.2) {
                frame = window.requestAnimationFrame(glide);
            }
        };
        track.addEventListener('pointerdown', (event) => {
            isDown = true;
            window.cancelAnimationFrame(frame);
            track.classList.add('is-dragging');
            startX = lastX = event.clientX;
            velocity = 0;
            scrollLeft = track.scrollLeft;
            track.setPointerCapture?.(event.pointerId);
        });
        track.addEventListener('pointermove', (event) => {
            if (!isDown) return;
            event.preventDefault();
            velocity = event.clientX - lastX;
            lastX = event.clientX;
            track.scrollLeft = scrollLeft - (event.clientX - startX);
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach((eventName) => {
            track.addEventListener(eventName, () => {
                if (isDown) {
                    frame = window.requestAnimationFrame(glide);
                }
                isDown = false;
                track.classList.remove('is-dragging');
            });
        });
    });

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.18 });
    document.querySelectorAll('.reveal').forEach((element) => revealObserver.observe(element));
})();
</script>

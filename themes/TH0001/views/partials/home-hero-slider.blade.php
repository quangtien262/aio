<section class="th-hero-card" data-th-hero-slider>
    @foreach ($heroSlides as $slide)
        <article class="th-hero-slide {{ $loop->first ? 'is-active' : '' }}" data-th-hero-slide>
            <img src="{{ $slide['image'] ?? 'https://picsum.photos/seed/th0001-fallback-hero/960/520' }}" alt="{{ $slide['title'] ?? 'Hero banner' }}">
            <div class="th-hero-overlay">
                <span class="th-eyebrow">{{ $slide['eyebrow'] ?? 'Flash sale' }}</span>
                <h1 class="th-hero-title">{{ $slide['title'] ?? 'Deal nổi bật hôm nay' }}</h1>
                <p class="th-hero-summary">{{ $slide['summary'] ?? 'Dữ liệu banner đang được lấy trực tiếp từ bảng banner riêng.' }}</p>
                <div class="th-hero-actions">
                    <span class="th-badge-price">{{ $slide['badge'] ?? 'Ưu đãi mới' }}</span>
                    <a href="{{ $slide['link_url'] ?? '#featured' }}" class="th-hero-button">{{ $slide['cta'] ?? 'Mua ngay' }}</a>
                </div>
            </div>
        </article>
    @endforeach

    @if ($heroSlides->count() > 1)
        <button type="button" class="th-hero-nav th-hero-nav-prev" data-th-hero-prev aria-label="Slide trước">‹</button>
        <button type="button" class="th-hero-nav th-hero-nav-next" data-th-hero-next aria-label="Slide sau">›</button>
        <div class="th-hero-dots">
            @foreach ($heroSlides as $slide)
                <button type="button" class="th-hero-dot {{ $loop->first ? 'is-active' : '' }}" data-th-hero-dot aria-label="Slide {{ $loop->iteration }}"></button>
            @endforeach
        </div>
    @endif
</section>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-th-hero-slider]').forEach((slider) => {
                const slides = Array.from(slider.querySelectorAll('[data-th-hero-slide]'));
                const dots = Array.from(slider.querySelectorAll('[data-th-hero-dot]'));
                const prevButton = slider.querySelector('[data-th-hero-prev]');
                const nextButton = slider.querySelector('[data-th-hero-next]');

                if (slides.length <= 1) {
                    return;
                }

                let activeIndex = 0;
                let intervalId = null;

                const render = (index) => {
                    activeIndex = (index + slides.length) % slides.length;

                    slides.forEach((slide, slideIndex) => {
                        slide.classList.toggle('is-active', slideIndex === activeIndex);
                    });

                    dots.forEach((dot, dotIndex) => {
                        dot.classList.toggle('is-active', dotIndex === activeIndex);
                    });
                };

                const stop = () => {
                    window.clearInterval(intervalId);
                };

                const start = () => {
                    stop();
                    intervalId = window.setInterval(() => {
                        render(activeIndex + 1);
                    }, 4500);
                };

                dots.forEach((dot, dotIndex) => {
                    dot.addEventListener('click', () => {
                        render(dotIndex);
                        start();
                    });
                });

                prevButton?.addEventListener('click', () => {
                    render(activeIndex - 1);
                    start();
                });

                nextButton?.addEventListener('click', () => {
                    render(activeIndex + 1);
                    start();
                });

                slider.addEventListener('mouseenter', stop);
                slider.addEventListener('mouseleave', start);

                render(0);
                start();
            });
        });
    </script>
@endonce

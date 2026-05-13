<section class="th-hero-card" data-th-hero-slider>
    @foreach ($heroSlides as $slide)
        <article class="th-hero-slide {{ $loop->first ? 'is-active' : '' }}" data-th-hero-slide>
            <img src="{{ $slide['image'] ?? 'https://picsum.photos/seed/th0002-fallback-hero/960/520' }}" alt="{{ $slide['title'] ?? 'Garment workshop hero banner' }}">
            <div class="th-hero-overlay">
                <div class="th-inline-edit-head">
                    <span class="th-eyebrow" data-translation-display="{{ $slide['translation_keys']['eyebrow'] ?? ($loop->first ? ($heroBannerEditKeyMap['eyebrow']['key'] ?? 'theme.fallback.hero_eyebrow') : ($heroSlideDefaultKeyMap['eyebrow'] ?? 'theme_block.th0002.hero_slide.eyebrow')) }}">{{ $slide['eyebrow'] ?? $slide['kicker'] ?? 'Xưởng may theo yêu cầu' }}</span>
                    @if (!empty($canQuickEditThemeBlocks))
                        <button
                            type="button"
                            class="sf-inline-edit-btn"
                            data-sf-inline-edit-trigger
                            data-edit-title="Sửa hero TH0002"
                            data-edit-fields='@json($heroQuickEditFields ?? [])'
                        >
                            Sửa hero
                        </button>
                    @endif
                </div>
                <h1 class="th-hero-title" data-translation-display="{{ $slide['translation_keys']['title'] ?? ($loop->first ? ($heroBannerEditKeyMap['title']['key'] ?? 'theme.fallback.hero_title') : '') }}">{{ $slide['title'] ?? 'May sỉ lẻ theo form dáng riêng cho thương hiệu của bạn' }}</h1>
                <p class="th-hero-summary" data-translation-display="{{ $slide['translation_keys']['summary'] ?? ($loop->first ? ($heroBannerEditKeyMap['summary']['key'] ?? 'theme.fallback.hero_summary') : '') }}">{{ $slide['summary'] ?? 'TH0002 tập trung vào đồng phục, local brand, capsule collection và sản xuất theo yêu cầu với quy trình duyệt mẫu rõ ràng.' }}</p>
                <div class="th-hero-actions">
                    <span class="th-badge-price" data-translation-display="{{ $slide['translation_keys']['badge'] ?? ($loop->first ? ($heroBannerEditKeyMap['badge']['key'] ?? 'theme.fallback.hero_badge') : ($heroSlideDefaultKeyMap['badge'] ?? 'theme_block.th0002.hero_slide.badge')) }}">{{ $slide['badge'] ?? 'MOQ từ 30 sản phẩm' }}</span>
                    <a href="{{ $slide['link_url'] ?? '#featured' }}" class="th-hero-button" data-translation-display="{{ $slide['translation_keys']['cta'] ?? ($loop->first ? ($heroBannerEditKeyMap['cta']['key'] ?? 'theme.fallback.hero_cta') : ($heroSlideDefaultKeyMap['cta'] ?? 'theme_block.th0002.hero_slide.cta')) }}">{{ $slide['cta'] ?? 'Xem bộ sưu tập' }}</a>
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

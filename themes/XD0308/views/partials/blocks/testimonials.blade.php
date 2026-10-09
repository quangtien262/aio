@php
    $testimonialItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['quote'] ?? null))
        ->values();
@endphp
@if ($testimonialItems->isNotEmpty())
<section id="{{ $anchor }}" class="xd-section xd8-reviews xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="xd-container">
        <div class="xd8-review-heading">
            <div><p class="xd8-kicker">{{ $data['subtitle'] ?? 'Chia sẻ từ học viên' }}</p><h2>{{ $data['title'] ?? '' }}</h2></div>
            <p>Mỗi hành trình bắt đầu từ sự tin tưởng.</p>
        </div>
        <div class="xd8-review-slider" data-xd8-slider role="region" aria-label="Chia sẻ từ khách hàng" aria-roledescription="carousel">
            <div class="xd8-review-track" data-xd8-track tabindex="0" aria-label="Danh sách đánh giá, cuộn ngang để xem thêm">
                @foreach ($testimonialItems as $testimonial)
                    <article class="xd8-review-card">
                        <span class="xd8-review-quote" aria-hidden="true">“</span>
                        <blockquote>{{ $testimonial['quote'] }}</blockquote>
                        <div class="xd8-review-person">
                            @if (filled($testimonial['image'] ?? $testimonial['image_url'] ?? null))
                                <img src="{{ $testimonial['image'] ?? $testimonial['image_url'] }}" alt="{{ $testimonial['alt'] ?? $testimonial['name'] ?? '' }}" loading="lazy">
                            @endif
                            <div><strong>{{ $testimonial['name'] ?? '' }}</strong>@if (filled($testimonial['company'] ?? $testimonial['role'] ?? null))<small>{{ $testimonial['company'] ?? $testimonial['role'] }}</small>@endif</div>
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($testimonialItems->count() > 1)
                <div class="xd8-review-controls">
                    <span>Chia sẻ từ khách hàng</span>
                    <div><button type="button" data-xd8-prev aria-label="Đánh giá trước">←</button><button type="button" data-xd8-next aria-label="Đánh giá tiếp theo">→</button></div>
                </div>
            @endif
        </div>
    </div>
</section>
@endif

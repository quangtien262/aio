@php
    $image = $media['image'] ?? $content['image'] ?? 'https://images.unsplash.com/photo-1521790797524-b2497295b8a0?auto=format&fit=crop&w=900&q=85';
    $cardImage = $media['card_image'] ?? $content['card_image'] ?? 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=900&q=85';
    $stats = collect($content['stats'] ?? [])->take(4)->values();
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-promo xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container rx13-promo__grid">
        <div class="rx13-promo__image">
            <img src="{{ $image }}" alt="{{ $data['title'] ?? 'RouteX' }}">
        </div>
        <div>
            <div class="rx13-promo-card">
                <div>
                    <h3>{{ $data['title'] ?? 'Nhận ưu đãi tốt nhất của chúng tôi một cách nhanh chóng' }}</h3>
                    @if (filled($data['description'] ?? null))
                        <p>{{ $data['description'] }}</p>
                    @endif
                    <a class="rx13-button" href="{{ $settings['cta_url'] ?? '#footer' }}">{{ $data['button_label'] ?? 'Liên hệ ngay' }} <span>→</span></a>
                </div>
                <img src="{{ $cardImage }}" alt="Visa offer">
            </div>
            <div class="rx13-stats">
                @foreach ($stats as $stat)
                    <div><strong>{{ $stat['value'] ?? '' }}</strong><span>{{ $stat['label'] ?? '' }}</span></div>
                @endforeach
            </div>
        </div>
    </div>
</section>

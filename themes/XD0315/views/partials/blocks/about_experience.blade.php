@php
    $primaryImage = $media['image'] ?? $content['image'] ?? 'https://images.unsplash.com/photo-1571019613914-85f342c6a11e?auto=format&fit=crop&w=1200&q=85';
    $videoImage = $media['video_image'] ?? $content['video_image'] ?? 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=1200&q=85';
@endphp

<section id="{{ $anchor }}" class="af15-about af15-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="af15-container af15-about__grid">
        <div class="af15-about__photo">
            <img src="{{ $primaryImage }}" alt="{{ $data['title'] ?? 'About' }}">
            @if (filled($data['button_label'] ?? null))
                <a class="af15-button" href="{{ $settings['cta_url'] ?? '#lop-tap' }}">{{ $data['button_label'] }}</a>
            @endif
        </div>
        <div class="af15-about__copy">
            <h2>{{ $data['title'] ?? 'Chúng tôi tạo ra sự khác biệt' }}</h2>
            @if (filled($data['subtitle'] ?? null))
                <em>{{ $data['subtitle'] }}</em>
            @endif
            @if (filled($data['description'] ?? null))
                <p>{{ $data['description'] }}</p>
            @endif
            <a class="af15-video" href="{{ $settings['video_url'] ?? '#video' }}">
                <img src="{{ $videoImage }}" alt="Video">
                <span>></span>
            </a>
        </div>
    </div>
</section>

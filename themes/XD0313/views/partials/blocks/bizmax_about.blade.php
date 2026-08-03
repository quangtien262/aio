@php
    $image = $media['image'] ?? $content['image'] ?? 'https://images.unsplash.com/photo-1580674285054-bed31e145f59?auto=format&fit=crop&w=1100&q=85';
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-about xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container rx13-about__grid">
        <div class="rx13-about__image">
            <img src="{{ $image }}" alt="{{ $data['title'] ?? 'RouteX' }}">
        </div>
        <div class="rx13-about__copy">
            <p class="rx13-kicker">{{ $data['subtitle'] ?? 'Về chúng tôi' }}</p>
            <h2 class="rx13-title">{{ $data['title'] ?? 'Giải pháp tư vấn visa toàn diện' }}</h2>
            @if (filled($data['description'] ?? null))
                {!! collect(explode("\n", (string) $data['description']))->filter()->map(fn ($line) => '<p>'.e($line).'</p>')->implode('') !!}
            @endif
            <a class="rx13-button" href="{{ $settings['cta_url'] ?? '#dich-vu' }}">{{ $data['button_label'] ?? 'Xem thêm' }}</a>
        </div>
    </div>
</section>

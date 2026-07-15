@php
    $image = $media['image'] ?? $content['image'] ?? 'https://images.unsplash.com/photo-1580674285054-bed31e145f59?auto=format&fit=crop&w=1100&q=85';
@endphp

<section id="{{ $anchor }}" class="fg18-section fg18-about xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="fg18-container fg18-about__grid">
        <div class="fg18-about__image">
            <img src="{{ $image }}" alt="{{ $data['title'] ?? 'Fast Gear' }}">
        </div>
        <div class="fg18-about__copy">
            <p class="fg18-kicker">{{ $data['subtitle'] ?? 'Ve chung toi' }}</p>
            <h2 class="fg18-title">{{ $data['title'] ?? 'Giai phap logistics toan cau tot nhat' }}</h2>
            @if (filled($data['description'] ?? null))
                {!! collect(explode("\n", (string) $data['description']))->filter()->map(fn ($line) => '<p>'.e($line).'</p>')->implode('') !!}
            @endif
            <a class="fg18-button" href="{{ $settings['cta_url'] ?? '#dich-vu' }}">{{ $data['button_label'] ?? 'Xem them' }}</a>
        </div>
    </div>
</section>

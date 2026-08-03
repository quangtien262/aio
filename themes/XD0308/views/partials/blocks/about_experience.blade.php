<section id="{{ $anchor }}" class="xd4-section xd8-about xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="about_experience">
    <div class="xd4-container xd8-about__grid">
        <div class="xd8-about__media">
            <img src="{{ $media['image'] ?? 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1000&q=85' }}" alt="{{ $data['title'] ?? 'Giới thiệu' }}">
            <div class="xd8-about__badge"><span><b>{{ data_get($content, 'years', '12+') }}</b>{{ data_get($content, 'years_label', 'Năm đồng hành') }}</span></div>
        </div>
        <div class="xd8-about__copy">
            <p class="xd4-eyebrow">{{ $data['subtitle'] ?? '' }}</p>
            <h2 class="xd4-section-title">{{ $data['title'] ?? '' }}</h2>
            <p>{{ $data['description'] ?? '' }}</p>
            <a class="xd4-button" href="{{ $settings['cta_url'] ?? '#lien-he' }}">{{ $data['button_label'] ?? 'Xem thêm' }}</a>
        </div>
    </div>
    @if($canEditLanding && filled($block['id'] ?? null))<button type="button" class="xd-edit-block" data-xd-edit-block="{{ $block['id'] }}">Sửa khối</button>@endif
</section>

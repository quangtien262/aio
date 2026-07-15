<section id="{{ $anchor }}" class="xd4-section xd8-about xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="about_experience">
    <div class="xd4-container xd8-about__grid">
        <img src="{{ $media['image'] ?? 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1000&q=85' }}" alt="{{ $data['title'] ?? 'Giới thiệu' }}">
        <div>
            <p class="xd4-eyebrow">{{ $data['subtitle'] ?? '' }}</p>
            <h2 class="xd4-section-title">{{ $data['title'] ?? '' }}</h2>
            <p>{{ $data['description'] ?? '' }}</p>
            <a class="xd4-button" href="{{ $settings['cta_url'] ?? '#lien-he' }}">{{ $data['button_label'] ?? 'Xem thêm' }}</a>
        </div>
    </div>
</section>

<style>
    .xd8-about__grid { display: grid; grid-template-columns: minmax(0, .95fr) minmax(0, 1fr); align-items: center; gap: 72px; }
    .xd8-about__grid > img { width: 100%; height: 420px; object-fit: cover; border-radius: 8px; }
    .xd8-about__grid h2 { color: #39257d; font-size: clamp(32px, 3.4vw, 52px); margin-bottom: 22px; }
    .xd8-about__grid p:not(.xd4-eyebrow) { color: var(--xd4-muted); font-size: 17px; line-height: 1.75; margin-bottom: 28px; }
    @media (max-width: 720px) { .xd8-about__grid { grid-template-columns: 1fr; gap: 28px; } .xd8-about__grid > img { height: 300px; } }
</style>

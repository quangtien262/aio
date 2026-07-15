@php
    $bgImage = $media['background'] ?? $content['background'] ?? 'https://images.unsplash.com/photo-1494412685616-a5d310fbb07d?auto=format&fit=crop&w=1800&q=85';
@endphp

<section id="{{ $anchor }}" class="fg18-video xd-landing-block" style="--fg18-bg: url('{{ $bgImage }}')" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="fg18-video__content">
        <p class="fg18-kicker">{{ $data['subtitle'] ?? 'Ve chung toi' }}</p>
        <h2>{{ $data['title'] ?? 'Doi tac logistics toan cau doi voi the gioi' }}</h2>
        <a class="fg18-play" href="{{ $settings['video_url'] ?? '#video' }}" aria-label="Xem video">></a>
    </div>
</section>

@php($c = $content ?? [])
<section id="{{ $anchor }}" class="xd5-section">
    <div class="xd5-container xd5-about">
        <div class="xd5-about-media">
            <img src="{{ $c['image_primary'] ?? 'https://images.unsplash.com/photo-1556761175-4b46a572b786?auto=format&fit=crop&w=1000&q=85' }}" alt="">
            <div class="xd5-about-badge"><b>{{ $c['years'] ?? '40+' }}</b><br>{{ $c['years_label'] ?? '' }}</div>
        </div>
        <div>
            <p class="xd5-eyebrow">{{ $data['subtitle'] ?? '' }}</p>
            <h2 class="xd5-title">{{ $data['title'] ?? '' }}</h2>
            <p>{{ $data['description'] ?? '' }}</p>
            <b>{{ $c['progress_label'] ?? '' }}</b>
            <span style="float:right">{{ $c['progress_value'] ?? 90 }}%</span>
            <div class="xd5-progress"><i style="width:{{ $c['progress_value'] ?? 90 }}%"></i></div>
            <p><a class="xd5-btn" href="#lien-he">{{ $data['button_label'] ?? 'Nhận báo giá' }}</a></p>
        </div>
    </div>
</section>

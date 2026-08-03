@php
    $imageOne = $media['image_one'] ?? $content['image_one'] ?? 'https://images.unsplash.com/photo-1500835556837-99ac94a94552?auto=format&fit=crop&w=900&q=85';
    $imageTwo = $media['image_two'] ?? $content['image_two'] ?? 'https://images.unsplash.com/photo-1521791055366-0d553872125f?auto=format&fit=crop&w=900&q=85';
    $cards = collect($content['items'] ?? [])->take(2)->values();
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-about xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container rx13-about__grid">
        <div class="rx13-about__media">
            <img src="{{ $imageOne }}" alt="{{ $data['title'] ?? 'RouteX' }}">
            <div class="rx13-years"><strong>{{ $content['years'] ?? '25' }}</strong><span>Năm kinh nghiệm</span></div>
            <img src="{{ $imageTwo }}" alt="Tư vấn visa">
        </div>
        <div class="rx13-about__copy">
            <p class="rx13-eyebrow">{{ $data['subtitle'] ?? 'Về chúng tôi' }}</p>
            <h2 class="rx13-title">{{ $data['title'] ?? 'Nơi niềm đam mê chạm đến những điểm đến trong mơ' }}</h2>
            @if (filled($data['description'] ?? null))
                <p>{{ $data['description'] }}</p>
            @endif
            <div class="rx13-about-cards">
                @foreach ($cards as $item)
                    <article class="rx13-about-card">
                        <span>{{ $item['icon'] ?? $loop->iteration }}</span>
                        <div>
                            <h3>{{ $item['title'] ?? '' }}</h3>
                            <ul>
                                @foreach (collect($item['bullets'] ?? [])->take(2) as $bullet)
                                    <li>✓ {{ $bullet }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </article>
                @endforeach
            </div>
            <a class="rx13-button" href="{{ $settings['cta_url'] ?? '#dich-vu' }}">{{ $data['button_label'] ?? 'Đọc thêm' }} <span>→</span></a>
            <a class="rx13-hotline" href="tel:{{ $phoneHref }}"><span>☎</span><small>Cần hỗ trợ?<br><strong>{{ $hotline }}</strong></small></a>
        </div>
    </div>
</section>

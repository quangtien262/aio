@php
    $content = is_array($data['content'] ?? null) ? $data['content'] : [];
    $items = collect($content['items'] ?? []);
    $asideImage = $block['media']['aside_image'] ?? 'https://images.unsplash.com/photo-1509391366360-2e959784a276?auto=format&fit=crop&w=800&q=85';
    $asideTitle = $content['aside_title'] ?? 'Giải pháp được triển khai thực tế';
    $asideDescription = $content['aside_description'] ?? 'Đội ngũ tư vấn cùng doanh nghiệp xác định quy mô, mục tiêu tiết kiệm và lộ trình đầu tư phù hợp.';
    $asideButtonLabel = $content['aside_button_label'] ?? 'Nhận tư vấn';
    $asideButtonUrl = $content['aside_button_url'] ?? '#lien-he';
@endphp

<section id="{{ $anchor }}" class="xd2-section xd2-faq xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="faq_showcase">
    <div class="xd2-container xd2-faq__grid">
        {!! $editButton !!}
        <div>
            <p class="xd2-kicker">{{ $data['subtitle'] ?? '' }}</p>
            <h2>{{ $data['title'] ?? '' }}</h2>
            <div class="xd2-faq__list">
                @foreach ($items as $item)
                    <details {{ $loop->first ? 'open' : '' }}>
                        <summary>{{ $loop->iteration }}. {{ $item['question'] ?? '' }}</summary>
                        <p>{{ $item['answer'] ?? '' }}</p>
                    </details>
                @endforeach
            </div>
        </div>
        <aside class="xd2-faq__aside">
            @if (filled($asideImage))
                <img src="{{ $asideImage }}" alt="{{ $asideTitle }}">
            @endif
            @if (filled($asideTitle))
                <h3>{{ $asideTitle }}</h3>
            @endif
            @if (filled($asideDescription))
                <p>{{ $asideDescription }}</p>
            @endif
            @if (filled($asideButtonLabel) && filled($asideButtonUrl))
                <a class="xd2-button" href="{{ $asideButtonUrl }}">{{ $asideButtonLabel }} →</a>
            @endif
        </aside>
    </div>
</section>

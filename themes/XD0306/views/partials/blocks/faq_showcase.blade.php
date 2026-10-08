@php($items = collect($content['items'] ?? []))
<section id="{{ $anchor }}" class="xd6-section xd6-faq xd-landing-block" data-landing-block-id="{{ $block['id'] ?? '' }}" data-block-type="faq_showcase">
    <div class="xd6-container xd6-faq__grid">
        <div>
            @if (!empty($data['subtitle']))
                <p class="xd6-eyebrow">{{ $data['subtitle'] }}</p>
            @endif
            <h2 class="xd6-section-title">{{ $data['title'] ?? 'Câu hỏi thường gặp' }}</h2>
            @if(filled($data['description'] ?? null))<p class="xd6-faq__intro">{{ $data['description'] }}</p>@endif
            <div class="xd6-faq__items">
                @foreach ($items as $item)
                    <details {{ $loop->first ? 'open' : '' }}>
                        <summary><strong>{{ $item['question'] ?? '' }}</strong><span aria-hidden="true"></span></summary>
                        <p>{{ $item['answer'] ?? '' }}</p>
                    </details>
                @endforeach
            </div>
        </div>
        <div class="xd6-faq__media"><img src="{{ ($content['image'] ?? '') ?: '/theme-demo/xd-shared/business-2.jpg' }}" alt="{{ $data['title'] ?? '' }}" loading="lazy"><a href="{{ route('site.contact') }}">{{ __('theme_contact_page.heading') }} <span aria-hidden="true">↗</span></a></div>
    </div>
</section>

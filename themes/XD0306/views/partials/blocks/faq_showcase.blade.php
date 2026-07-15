@php($items = collect($content['items'] ?? []))
<section id="{{ $anchor }}" class="xd6-section xd6-faq xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="faq_showcase">
    <div class="xd6-container xd6-faq__grid">
        <div>
            @if (!empty($data['subtitle']))
                <p class="xd6-eyebrow">{{ $data['subtitle'] }}</p>
            @endif
            <h2 class="xd6-section-title">{{ $data['title'] ?? 'Câu hỏi thường gặp' }}</h2>
            <div class="xd6-faq__items">
                @foreach ($items as $item)
                    <details {{ $loop->first ? 'open' : '' }}>
                        <summary>{{ $item['question'] ?? '' }} <span>+</span></summary>
                        <p>{{ $item['answer'] ?? '' }}</p>
                    </details>
                @endforeach
            </div>
        </div>
        <img src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=1000&q=85" alt="Đội ngũ tư vấn">
    </div>
</section>

<style>
    .xd6-faq__grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(340px, .94fr); gap: 46px; align-items: center; }
    .xd6-faq__grid > img { width: 100%; height: 480px; object-fit: cover; }
    .xd6-faq__items { margin-top: 28px; }
    .xd6-faq__items details { border: 1px solid #e7e8eb; margin-top: 12px; padding: 0 20px; background: #fff; }
    .xd6-faq__items summary { min-height: 66px; display: flex; align-items: center; justify-content: space-between; gap: 16px; cursor: pointer; font-weight: 700; list-style: none; }
    .xd6-faq__items summary::-webkit-details-marker { display: none; }
    .xd6-faq__items summary span { color: var(--gold); font-size: 26px; font-weight: 400; }
    .xd6-faq__items p { color: var(--muted); line-height: 1.7; margin: 0 0 20px; }
    @media (max-width: 900px) { .xd6-faq__grid { grid-template-columns: 1fr; } .xd6-faq__grid > img { height: 320px; } }
</style>

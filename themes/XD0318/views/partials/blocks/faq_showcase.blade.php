@php
    $items = collect($content['items'] ?? [])->filter(fn ($item) => is_array($item));
    $imageOne = $media['image_one'] ?? $content['image_one'] ?? 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=900&q=85';
    $imageTwo = $media['image_two'] ?? $content['image_two'] ?? 'https://images.unsplash.com/photo-1565793298595-6a879b1d9492?auto=format&fit=crop&w=900&q=85';
@endphp

<section id="{{ $anchor }}" class="fg18-section fg18-faq xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="fg18-container fg18-faq__grid">
        <div>
            <p class="fg18-kicker">{{ $data['subtitle'] ?? 'Cau hoi thuong gap' }}</p>
            <h2 class="fg18-title">{{ $data['title'] ?? 'Giai dap cac thac mac ve dich vu cua ban' }}</h2>
            @if (filled($data['description'] ?? null))
                <p class="fg18-faq__lead">{{ $data['description'] }}</p>
            @endif
            <div class="fg18-faq-list">
                @foreach ($items as $item)
                    <details>
                        <summary>{{ $item['question'] ?? $item['title'] ?? '' }}</summary>
                        @if (filled($item['answer'] ?? $item['summary'] ?? null))
                            <p>{{ $item['answer'] ?? $item['summary'] }}</p>
                        @endif
                    </details>
                @endforeach
            </div>
        </div>
        <aside class="fg18-faq-images">
            <img src="{{ $imageOne }}" alt="Kho van">
            <img src="{{ $imageTwo }}" alt="Nhan vien logistics">
        </aside>
    </div>
</section>

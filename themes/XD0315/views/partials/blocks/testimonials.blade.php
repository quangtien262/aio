@php
    $storyItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 4))
        ->values();
    $storyBg = $media['background'] ?? $content['background'] ?? 'https://images.unsplash.com/photo-1534258936925-c58bed479fcb?auto=format&fit=crop&w=1800&q=80';
@endphp

<section id="{{ $anchor }}" class="af15-stories xd-landing-block" style="--af15-story-bg: url('{{ $storyBg }}')" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="af15-title-row af15-title-row--dark">
        <h2>{{ $data['title'] ?? 'Cau chuyen thanh cong' }}</h2>
        @if (filled($data['description'] ?? null))
            <p>{{ $data['description'] }}</p>
        @endif
    </div>
    <div class="af15-container">
        <div class="af15-story-track" data-af15-row>
            @foreach ($storyItems as $item)
                @php
                    $name = $item['name'] ?? $item['title'] ?? '';
                    $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
                    $beforeWeight = $item['before_weight'] ?? '100kg';
                    $afterWeight = $item['after_weight'] ?? '77kg';
                    $beforeMuscle = $item['before_muscle'] ?? '40.5kg';
                    $afterMuscle = $item['after_muscle'] ?? '47.2kg';
                    $beforeFat = $item['before_fat'] ?? '25%';
                    $afterFat = $item['after_fat'] ?? '14.3%';
                @endphp
                <article class="af15-story-card">
                    @if (filled($image))
                        <img src="{{ $image }}" alt="{{ $item['alt'] ?? $name }}">
                    @endif
                    <div>
                        <h3>{{ $name }}</h3>
                        <div class="af15-story-tabs"><span>Truoc</span><span>Sau</span></div>
                        <div class="af15-story-stats">
                            <div><span>Can nang</span><strong>{{ $beforeWeight }}</strong><strong>{{ $afterWeight }}</strong></div>
                            <div><span>Chi so co</span><strong>{{ $beforeMuscle }}</strong><strong>{{ $afterMuscle }}</strong></div>
                            <div><span>Ti le mo</span><strong>{{ $beforeFat }}</strong><strong>{{ $afterFat }}</strong></div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

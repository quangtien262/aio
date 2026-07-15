@php
    $mosaicItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 8))
        ->values();
    $tabs = collect($content['tabs'] ?? [])->whenEmpty(fn () => collect([
        ['label' => 'Mau nha dang hot'],
        ['label' => 'Mau nha don gian dep 2019'],
        ['label' => 'Mau nha cao cap dep 2019'],
        ['label' => 'Mau nha cap 4 dep 2019'],
    ]));
    $bgImage = $media['background'] ?? $content['background'] ?? 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1800&q=85';
@endphp

<section id="{{ $anchor }}" class="bb14-projects xd-landing-block" style="--bb14-project-bg: url('{{ $bgImage }}')" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="bb14-projects__head">
        <h2>{{ $data['title'] ?? 'Du an moi nhat' }}</h2>
        <div class="bb14-project-tabs">
            @foreach ($tabs as $tab)
                <span class="{{ $loop->first ? 'is-active' : '' }}">{{ $tab['label'] ?? $tab['title'] ?? '' }}</span>
            @endforeach
        </div>
    </div>
    <div class="bb14-project-grid">
        @foreach ($mosaicItems as $item)
            @php
                $title = $item['title'] ?? $item['name'] ?? '';
                $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
            @endphp
            <a class="bb14-project-card" href="{{ $item['url'] ?? $item['href'] ?? '#du-an' }}">
                @if (filled($image))
                    <img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}">
                @endif
                <span>{{ $title }}</span>
            </a>
        @endforeach
    </div>
</section>


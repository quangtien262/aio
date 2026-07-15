@php
    $items = collect($block['dynamic_items'] ?? data_get($content, 'items', []))->filter()->values();
    $featured = ($anchorId ?? '') === 'visa-noi-bat';
@endphp

<section id="{{ $anchorId ?? '' }}" class="rx13-section {{ $featured ? 'rx13-featured-section' : 'rx13-common-section' }}">
    @include('theme-xd0313::partials.inline-block-edit', ['block' => $block, 'blockIndex' => $blockIndex ?? 0])
    <div class="rx13-container">
        <div class="rx13-section-heading {{ $featured ? 'rx13-section-heading--light' : '' }}">
            @if(filled($subtitle))<p class="rx13-eyebrow">{{ $subtitle }}</p>@endif
            <h2>{{ $title }}</h2>
            @if(filled(data_get($content, 'description')))<p>{{ data_get($content, 'description') }}</p>@endif
        </div>
        @if($featured)
            <div class="rx13-featured-grid">
                @foreach($items as $item)
                    @php $itemImage = data_get($item, 'image_url', data_get($item, 'image')); @endphp
                    <a class="rx13-featured-card" href="{{ data_get($item, 'url', '#') }}">
                        @if(filled($itemImage))<img src="{{ $itemImage }}" alt="{{ data_get($item, 'title') }}">@endif
                        <span>{{ data_get($item, 'title') }}</span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="rx13-common-grid">
                @foreach($items as $item)
                    @php $itemImage = data_get($item, 'image_url', data_get($item, 'image')); @endphp
                    <article class="rx13-common-card">
                        @if(filled($itemImage))<img src="{{ $itemImage }}" alt="{{ data_get($item, 'title') }}">@endif
                        <div>
                            <h3>{{ data_get($item, 'title') }}</h3>
                            <p>{{ data_get($item, 'excerpt', data_get($item, 'description')) }}</p>
                            <a href="{{ data_get($item, 'url', '#') }}" aria-label="Xem {{ data_get($item, 'title') }}">&nearr;</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

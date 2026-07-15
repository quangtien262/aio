@php
    $mode = data_get($content, 'mode', 'benefits');
    $items = collect(data_get($content, 'items', []))->filter()->values();
    $stats = collect(data_get($content, 'stats', []))->filter()->values();
@endphp

<section id="{{ $anchorId ?? '' }}" class="rx13-section {{ $mode === 'stats' ? 'rx13-promo-section' : 'rx13-benefit-section' }}">
    @include('theme-xd0313::partials.inline-block-edit', ['block' => $block, 'blockIndex' => $blockIndex ?? 0])
    <div class="rx13-container">
        @if($mode === 'stats')
            <div class="rx13-promo">
                <div class="rx13-promo__image">
                    @if(filled(data_get($content, 'image_url')))
                        <img src="{{ data_get($content, 'image_url') }}" alt="{{ $title }}">
                    @endif
                </div>
                <div class="rx13-promo__body">
                    @if(filled($subtitle))<p class="rx13-eyebrow">{{ $subtitle }}</p>@endif
                    <h2>{{ $title }}</h2>
                    @if(filled(data_get($content, 'description')))<p>{{ data_get($content, 'description') }}</p>@endif
                    <a class="rx13-button rx13-button--outline-dark" href="{{ data_get($content, 'button_url', '#lien-he') }}">{{ data_get($content, 'button_label', 'Lien he ngay') }} <span aria-hidden="true">&rarr;</span></a>
                    <div class="rx13-stat-grid">
                        @foreach($stats as $stat)
                            <div class="rx13-stat"><strong>{{ data_get($stat, 'value') }}</strong><span>{{ data_get($stat, 'label') }}</span></div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            @if(filled($title) || filled($subtitle))
                <div class="rx13-section-heading">
                    @if(filled($subtitle))<p class="rx13-eyebrow">{{ $subtitle }}</p>@endif
                    @if(filled($title))<h2>{{ $title }}</h2>@endif
                </div>
            @endif
            <div class="rx13-benefit-grid">
                @foreach($items as $index => $item)
                    <article class="rx13-benefit-card">
                        <span class="rx13-benefit-card__icon">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <h3>{{ data_get($item, 'title') }}</h3>
                        <p>{{ data_get($item, 'description') }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

                        @php
                            $slides = collect($content['slides'] ?? [])
                                ->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null))
                                ->whenEmpty(fn () => collect($block['dynamic_items'] ?? [])->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null)))
                                ->values();
                            $firstSlide = $slides->first() ?? [];
                            $showHeroCard = is_array($firstSlide) && $hasMeaningfulHeroText($firstSlide, $data);
                        @endphp
                        <section id="{{ $anchor }}" class="xd-hero xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            @foreach ($slides as $slide)
                                <article class="xd-slide {{ $loop->first ? 'is-active' : '' }}"><img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] ?? $slide['title'] ?? $data['title'] ?? 'Banner' }}"></article>
                            @endforeach
                            @if ($showHeroCard)
                                <div class="xd-container xd-hero-content"><div class="xd-hero-card"><small data-hero-kicker>{{ $firstSlide['kicker'] ?? $data['subtitle'] ?? '' }}</small><h1 data-hero-title>{{ $firstSlide['title'] ?? $data['title'] ?? '' }}</h1><p data-hero-summary>{{ $firstSlide['summary'] ?? $data['description'] ?? '' }}</p><a class="xd-button" href="{{ $firstSlide['link_url'] ?? '#du-an' }}" data-hero-link>{{ $firstSlide['button_label'] ?? $data['button_label'] ?? 'Xem dự án →' }}</a></div></div>
                            @endif
                            <button class="xd-hero-arrow prev" type="button" data-slide-prev aria-label="Slide trước">&#8249;</button><button class="xd-hero-arrow next" type="button" data-slide-next aria-label="Slide sau">&#8250;</button>
                            <div class="xd-hero-dots" aria-label="Chọn slide">@foreach ($slides as $slide)<button class="xd-dot {{ $loop->first ? 'is-active' : '' }}" type="button" data-slide-dot="{{ $loop->index }}" aria-label="Slide {{ $loop->iteration }}"></button>@endforeach</div>
                        </section>

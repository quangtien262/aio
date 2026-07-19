@php
    $contentSlides = collect(data_get($block, 'data.content.slides', []));
    $dynamicSlides = collect($block['dynamic_items'] ?? []);
    $blockHeroSlides = $contentSlides->isNotEmpty() ? $contentSlides : ($dynamicSlides->isNotEmpty() ? $dynamicSlides : $heroSlides);
    $autoplayMs = max(1500, (int) data_get($block, 'settings.autoplay_ms', 5200));
@endphp

<section class="th-hero-layout">
    <aside class="th-sidebar">
        @foreach ($sidebarCategories as $category)
            <div class="th-sidebar-entry">
                <a href="{{ $category['url'] ?? '#' }}" target="{{ $category['target'] ?? '_self' }}" class="th-sidebar-item {{ !empty($category['highlight']) ? 'is-accent' : '' }}">
                    <span><span class="th-sidebar-icon">{{ $category['icon'] ?? '◌' }}</span> {{ $category['label'] }}</span>
                    <span>›</span>
                </a>

                @if (!empty($category['children']))
                    @php($submenuColumns = collect($category['children'])->chunk(3))
                    <div class="th-sidebar-mega {{ $loop->first ? 'mega-hot' : ($loop->index % 2 === 0 ? 'mega-beauty' : 'mega-food') }}">
                        <div class="th-sidebar-mega-content {{ $submenuColumns->count() > 3 ? 'has-four' : '' }}">
                            @foreach ($submenuColumns as $chunk)
                                <div class="th-sidebar-mega-column">
                                    <h4>{{ $category['label'] }}</h4>
                                    <ul>
                                        @foreach ($chunk as $child)
                                            <li><a href="{{ $child['url'] ?? ($category['url'] ?? '#') }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? __('common.child_group') }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                        <div class="th-sidebar-mega-promo">
                            @foreach ($sidePromos as $promo)
                                <a href="{{ $promo['link_url'] ?? '#featured' }}" target="{{ $promo['target'] ?? '_self' }}">
                                    <img src="{{ $promo['image'] }}" alt="{{ $promo['title'] }}">
                                    <span>{{ $promo['title'] }} · {{ $promo['subtitle'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </aside>

    <div class="th-hero-stack">
        @include('theme-th0001::partials.home-hero-slider', [
            'heroSlides' => $blockHeroSlides,
            'canQuickEditThemeBlocks' => false,
            'heroQuickEditFields' => [],
            'heroBannerEditKeyMap' => [],
            'heroSlideDefaultKeyMap' => [],
            'autoplayMs' => $autoplayMs,
        ])
        <div class="th-side-promo-grid">
            @foreach ($sidePromos as $promo)
                <a href="{{ $promo['link_url'] ?? '#featured' }}" target="{{ $promo['target'] ?? '_self' }}" class="th-side-promo">
                    <img src="{{ $promo['image'] }}" alt="{{ $promo['title'] }}">
                    <span>{{ $promo['title'] }} · {{ $promo['subtitle'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

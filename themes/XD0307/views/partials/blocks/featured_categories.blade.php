                        @php
                            $categoryItems = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? []))->values();
                        @endphp
                        <section id="{{ $anchor }}" class="xd-featured-cats xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container">
                                <div class="xd-featured-cats-head">
                                    <div class="xd-featured-cats-copy">
                                        <span class="xd-kicker">{{ $data['subtitle'] ?? 'KhÃ¡m phÃ¡ nhanh' }}</span>
                                        <h2>{{ $data['title'] ?? 'Danh má»¥c trá»ng tÃ¢m' }}</h2>
                                        @if(filled($data['description'] ?? null))
                                            <p>{{ $data['description'] }}</p>
                                        @endif
                                    </div>
                                    @if(filled($data['button_label'] ?? null))
                                        <a class="xd-text-link" href="#dich-vu">{{ $data['button_label'] }}</a>
                                    @endif
                                </div>
                                <div class="xd-featured-cat-grid">
                                    @foreach ($categoryItems as $category)
                                        <a class="xd-featured-cat {{ $categoryItems->count() > 4 ? 'is-compact' : '' }}" href="{{ $category['url'] ?? '#dich-vu' }}">
                                            @if(filled($category['image'] ?? null))
                                                <img src="{{ $category['image'] }}" alt="{{ $category['alt'] ?? $category['title'] ?? '' }}">
                                            @endif
                                            <span class="xd-featured-cat-body">
                                                <h3>{{ $category['title'] ?? $category['name'] ?? '' }}</h3>
                                                @if(filled($category['count_label'] ?? $category['summary'] ?? null))
                                                    <span>{{ $category['count_label'] ?? $category['summary'] }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </section>

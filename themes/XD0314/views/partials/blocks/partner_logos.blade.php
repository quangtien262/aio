                        @php $partnerItems = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? [])); @endphp
                        <section id="{{ $anchor }}" class="xd-partners xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container xd-partner-grid">@foreach ($partnerItems as $partner)<a class="xd-partner" href="{{ $partner['href'] ?? $partner['url'] ?? '#' }}" aria-label="Đối tác {{ $partner['name'] ?? $partner['title'] ?? '' }}">@if(filled($partner['image'] ?? null))<img src="{{ $partner['image'] }}" alt="{{ $partner['alt'] ?? $partner['name'] ?? $partner['title'] ?? '' }}">@else{{ $partner['name'] ?? $partner['title'] ?? '' }}@endif</a>@endforeach</div>
                        </section>


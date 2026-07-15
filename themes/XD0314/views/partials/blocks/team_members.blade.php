@php
    $teamItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['name'] ?? $item['title'] ?? null))
        ->values();
@endphp

<section id="{{ $anchor }}" class="bb14-team bb14-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="bb14-container">
        <div class="bb14-section-title">
            <h2>{{ $data['title'] ?? 'Doi cua chung toi' }}</h2>
            @if (filled($data['description'] ?? null))
                <p>{{ $data['description'] }}</p>
            @endif
        </div>
        <div class="bb14-team__row">
            @foreach ($teamItems as $member)
                @php
                    $name = $member['name'] ?? $member['title'] ?? '';
                    $role = $member['role'] ?? $member['department'] ?? $member['company'] ?? '';
                    $image = $member['image'] ?? $member['avatar'] ?? '';
                @endphp
                <article class="bb14-team-card">
                    @if (filled($image))
                        <img src="{{ $image }}" alt="{{ $member['alt'] ?? $name }}">
                    @endif
                    <div>
                        <span>{{ $role }}</span>
                        <strong>{{ $name }}</strong>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>


@php
    $teamItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['name'] ?? $item['title'] ?? null))
        ->take((int) ($settings['limit'] ?? 4))
        ->values();
@endphp

<section id="{{ $anchor }}" class="af15-trainers af15-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="af15-container">
        <div class="af15-title-row is-light">
            <h2>{{ $data['title'] ?? 'Gap go chuyen gia' }}</h2>
            @if (filled($data['description'] ?? null))
                <p>{{ $data['description'] }}</p>
            @endif
        </div>
        <div class="af15-trainer-grid">
            @foreach ($teamItems as $member)
                @php
                    $name = $member['name'] ?? $member['title'] ?? '';
                    $role = $member['role'] ?? $member['department'] ?? $member['company'] ?? '';
                    $image = $member['image'] ?? $member['avatar'] ?? '';
                @endphp
                <article class="af15-trainer-card">
                    @if (filled($image))
                        <img src="{{ $image }}" alt="{{ $member['alt'] ?? $name }}">
                    @endif
                    <span><strong>{{ $name }}</strong><small>{{ $role }}</small></span>
                </article>
            @endforeach
        </div>
    </div>
</section>

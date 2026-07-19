@php
    $partnerItems = ($settings['source'] ?? 'custom') === 'custom'
        ? collect($content['items'] ?? [])->filter(fn ($item) => is_array($item))->values()
        : $items;
@endphp
<section class="th5-section th5-partners" id="{{ $anchor }}" data-landing-block-id="{{ $block['id'] ?? '' }}" data-block-type="partner_logos"><div class="th5-container"><header class="th5-heading"><span>{{ $data['subtitle'] ?? '' }}</span><h2>{{ $data['title'] ?? '' }}</h2><p>{{ $data['description'] ?? '' }}</p><i></i></header><div class="th5-partners__track">@foreach($partnerItems as $item)<a href="{{ $item['url'] ?? '#' }}" aria-label="{{ $item['name'] ?? $item['title'] ?? 'Đối tác' }}">@if(filled($item['image'] ?? null))<img src="{{ $item['image'] }}" alt="{{ $item['alt'] ?? $item['name'] ?? '' }}">@else<strong>{{ $item['name'] ?? $item['title'] ?? '' }}</strong>@endif</a>@endforeach</div></div>{!! $editButton !!}</section>

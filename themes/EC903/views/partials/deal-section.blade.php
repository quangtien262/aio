@php
    $sectionItems = collect($block['dynamic_items'] ?? [])->filter()->values();
    if ($sectionItems->isEmpty()) $sectionItems = collect(data_get($block, 'data.content.items', []))->filter()->values();
@endphp
<section id="{{ data_get($block, 'anchor_id', $sectionId ?? null) }}" class="ec93-section ec93-{{ $theme ?? 'red' }} xd-landing-block" data-landing-block-id="{{ data_get($block, 'id') }}" data-block-type="{{ $blockType }}">
    <div class="ec93-container">
        <header class="ec93-section-head"><h2>@if(!empty($icon))<i class="{{ $icon }}"></i>@endif {{ data_get($block, 'data.title', $fallbackTitle) }}</h2>@if(($theme ?? 'red') !== 'red')<nav><span>MỚI NHẤT</span><span>BÁN CHẠY</span><span>GIÁ TỐT</span></nav>@endif<a href="{{ route('site.catalog.search') }}">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a></header>
        <div class="ec93-deal-grid">@foreach($sectionItems as $item)@include('theme-ec903::partials.product-card', ['item' => $item])@endforeach</div>
    </div>
</section>

@extends('theme-auto850::layout')
@section('content')
<main><section class="a850-inner-hero"><div class="a850-container"><small>AUTO850</small><h1>{{ $pageTitle ?? app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO850', app()->getLocale(), 'products', 'Sản phẩm') }}</h1>@if($pageDescription ?? null)<p>{{ $pageDescription }}</p>@endif</div></section><section class="a850-content"><div class="a850-container"><div class="a850-listing-grid">
    @forelse(collect($entries ?? []) as $entry)@include('theme-auto850::partials.product-card', ['item' => $entry])@empty<p class="a850-empty" style="grid-column:1/-1">@themeT('empty', 'Nội dung đang được cập nhật.')</p>@endforelse
</div>@if(is_object($entries ?? null) && method_exists($entries, 'links'))<div style="margin-top:35px">{{ $entries->links() }}</div>@endif</div></section></main>
@endsection

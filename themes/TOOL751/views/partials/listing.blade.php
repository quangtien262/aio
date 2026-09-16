@extends('theme-tool751::layout')
@section('content')
<main><section class="t751-inner-hero"><div class="t751-container"><small>TOOL751</small><h1>{{ $pageTitle ?? app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL751', app()->getLocale(), 'products', 'Sản phẩm') }}</h1>@if($pageDescription ?? null)<p>{{ $pageDescription }}</p>@endif</div></section><section class="t751-content"><div class="t751-container"><div class="t751-listing-grid">
    @forelse(collect($entries ?? []) as $entry)@include('theme-tool751::partials.product-card', ['item' => $entry])@empty<p class="t751-empty" style="grid-column:1/-1">@themeT('empty', 'Nội dung đang được cập nhật.')</p>@endforelse
</div>@if(is_object($entries ?? null) && method_exists($entries, 'links'))<div style="margin-top:35px">{{ $entries->links() }}</div>@endif</div></section></main>
@endsection

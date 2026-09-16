@extends('theme-auto851::layout')
@section('content')
<main><section class="a851-inner-hero"><div class="a851-container"><small>AUTO851</small><h1>{{ $pageTitle ?? app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO851', app()->getLocale(), 'products', 'Sản phẩm') }}</h1>@if($pageDescription ?? null)<p>{{ $pageDescription }}</p>@endif</div></section><section class="a851-content"><div class="a851-container"><div class="a851-listing-grid">
    @forelse(collect($entries ?? []) as $entry)@include('theme-auto851::partials.product-card', ['item' => $entry])@empty<p class="a851-empty" style="grid-column:1/-1">@themeT('empty', 'Nội dung đang được cập nhật.')</p>@endforelse
</div>@if(is_object($entries ?? null) && method_exists($entries, 'links'))<div style="margin-top:35px">{{ $entries->links() }}</div>@endif</div></section></main>
@endsection

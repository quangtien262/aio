@extends('theme-tool750::layout')
@section('content')
<main>
    <section class="t750-inner-hero"><div class="t750-container"><small>TOOL750</small><h1>{{ $pageTitle ?? app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL750', app()->getLocale(), 'products', 'Sản phẩm') }}</h1>@if($pageDescription ?? null)<p>{{ $pageDescription }}</p>@endif</div></section>
    <section class="t750-content"><div class="t750-container">
        <div class="t750-listing-grid">
            @forelse(collect($entries ?? []) as $entry)
                @include('theme-tool750::partials.product-card', ['item' => $entry])
            @empty
                <p class="t750-empty" style="grid-column:1/-1">@themeT('empty', 'Nội dung đang được cập nhật.')</p>
            @endforelse
        </div>
        @if(is_object($entries ?? null) && method_exists($entries, 'links'))<div style="margin-top:35px">{{ $entries->links() }}</div>@endif
    </div></section>
</main>
@endsection

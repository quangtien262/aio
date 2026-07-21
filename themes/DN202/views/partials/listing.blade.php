@php
    $collection = collect($items ?? $products ?? $posts ?? $services ?? $projects ?? data_get($data ?? [], 'items', []));
    $resolvedTitle = $title ?? $pageTitle ?? 'DN202 Arc';
    $fallback = '/theme-demo/dn202/interior-01.jpg';
@endphp
<main><section class="d202-inner-hero"><div class="d202-container"><h1>{{ $resolvedTitle }}</h1></div></section><section class="d202-content"><div class="d202-container d202-list">@forelse($collection as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', $fallback)) }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"></a><div><h2><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title', data_get($item, 'name')) }}</a></h2><p>{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($item, 'summary', data_get($item, 'excerpt'))), 150) }}</p></div></article>@empty<p>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('DN202', app()->getLocale(), 'common.no_data') }}</p>@endforelse</div></section></main>

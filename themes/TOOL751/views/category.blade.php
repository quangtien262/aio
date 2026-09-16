@php($pageTitle = data_get($category ?? null, 'name', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL751', app()->getLocale(), 'products', 'Sản phẩm')))
@include('theme-tool751::partials.listing', ['entries' => $entries ?? $products ?? []])

@php($pageTitle = data_get($category ?? null, 'name', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL750', app()->getLocale(), 'products', 'Sản phẩm')))
@include('theme-tool750::partials.listing', ['entries' => $entries ?? $products ?? []])

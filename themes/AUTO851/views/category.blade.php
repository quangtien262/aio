@php($pageTitle = data_get($category ?? null, 'name', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO851', app()->getLocale(), 'products', 'Sản phẩm')))
@include('theme-auto851::partials.listing', ['entries' => $entries ?? $products ?? []])

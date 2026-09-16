@php($pageTitle = data_get($category ?? null, 'name', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO852', app()->getLocale(), 'products', 'Sản phẩm')))
@include('theme-auto852::partials.listing', ['entries' => $entries ?? $products ?? []])

@php($pageTitle = data_get($category ?? null, 'name', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO850', app()->getLocale(), 'products', 'Sản phẩm')))
@include('theme-auto850::partials.listing', ['entries' => $entries ?? $products ?? []])

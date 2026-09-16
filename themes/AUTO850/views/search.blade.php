@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO850', app()->getLocale(), 'search', 'Tìm kiếm'))
@include('theme-auto850::partials.listing', ['entries' => $entries ?? $products ?? []])

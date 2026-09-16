@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO851', app()->getLocale(), 'search', 'Tìm kiếm'))
@include('theme-auto851::partials.listing', ['entries' => $entries ?? $products ?? []])

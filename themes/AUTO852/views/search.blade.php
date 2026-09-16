@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO852', app()->getLocale(), 'search', 'Tìm kiếm'))
@include('theme-auto852::partials.listing', ['entries' => $entries ?? $products ?? []])

@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO850', app()->getLocale(), 'news', 'Tin tức'))
@include('theme-auto850::partials.listing', ['entries' => $entries ?? $posts ?? []])

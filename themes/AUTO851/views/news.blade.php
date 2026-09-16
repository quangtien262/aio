@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO851', app()->getLocale(), 'news', 'Tin tức'))
@include('theme-auto851::partials.listing', ['entries' => $entries ?? $posts ?? []])

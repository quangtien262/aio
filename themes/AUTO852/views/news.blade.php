@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO852', app()->getLocale(), 'news', 'Tin tức'))
@include('theme-auto852::partials.listing', ['entries' => $entries ?? $posts ?? []])

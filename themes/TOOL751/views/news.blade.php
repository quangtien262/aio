@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL751', app()->getLocale(), 'news', 'Tin tức'))
@include('theme-tool751::partials.listing', ['entries' => $entries ?? $posts ?? []])

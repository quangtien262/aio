@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL750', app()->getLocale(), 'news', 'Tin tức'))
@include('theme-tool750::partials.listing', ['entries' => $entries ?? $posts ?? []])

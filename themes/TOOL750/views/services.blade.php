@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL750', app()->getLocale(), 'services', 'Dịch vụ'))
@include('theme-tool750::partials.listing', ['entries' => $entries ?? $services ?? []])

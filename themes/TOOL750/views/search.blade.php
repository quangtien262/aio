@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL750', app()->getLocale(), 'search', 'Tìm kiếm'))
@include('theme-tool750::partials.listing', ['entries' => $entries ?? $products ?? []])

@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL751', app()->getLocale(), 'search', 'Tìm kiếm'))
@include('theme-tool751::partials.listing', ['entries' => $entries ?? $products ?? []])

@php($pageTitle = __('Dự án'))
@include('theme-tool751::partials.listing', ['entries' => $entries ?? $projects ?? []])

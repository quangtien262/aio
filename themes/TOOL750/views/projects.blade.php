@php($pageTitle = __('Dự án'))
@include('theme-tool750::partials.listing', ['entries' => $entries ?? $projects ?? []])

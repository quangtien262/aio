@php($pageTitle = __('Dự án'))
@include('theme-auto851::partials.listing', ['entries' => $entries ?? $projects ?? []])

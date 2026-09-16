@php($pageTitle = __('Dự án'))
@include('theme-auto850::partials.listing', ['entries' => $entries ?? $projects ?? []])

@php($pageTitle = __('Dự án'))
@include('theme-auto852::partials.listing', ['entries' => $entries ?? $projects ?? []])

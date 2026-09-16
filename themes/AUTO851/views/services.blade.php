@php($pageTitle = __('Dịch vụ'))
@include('theme-auto851::partials.listing', ['entries' => $entries ?? $services ?? []])

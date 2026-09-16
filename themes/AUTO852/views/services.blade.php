@php($pageTitle = __('Dịch vụ'))
@include('theme-auto852::partials.listing', ['entries' => $entries ?? $services ?? []])

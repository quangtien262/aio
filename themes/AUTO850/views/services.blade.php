@php($pageTitle = __('Dịch vụ'))
@include('theme-auto850::partials.listing', ['entries' => $entries ?? $services ?? []])

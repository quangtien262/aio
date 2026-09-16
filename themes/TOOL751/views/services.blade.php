@php($pageTitle = __('Dịch vụ'))
@include('theme-tool751::partials.listing', ['entries' => $entries ?? $services ?? []])

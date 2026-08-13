@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', []);
    $company = data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', 'NEWS88'));
@endphp
@extends('theme-news88::layout')
@section('title', $pageTitle ?? 'Liên hệ')
@section('content')
<main class="n88-inner"><div class="n88-container"><header class="n88-inner-head"><h1>{{ $pageTitle ?? 'Liên hệ' }}</h1></header><section class="n88-contact-card"><h2>{{ $company }}</h2>@if(data_get($branding, 'support_location'))<p><i class="fa-solid fa-location-dot"></i> {{ data_get($branding, 'support_location') }}</p>@endif @if(data_get($branding, 'support_hotline'))<p><i class="fa-solid fa-phone"></i> <a href="tel:{{ preg_replace('/\s+/', '', data_get($branding, 'support_hotline')) }}">{{ data_get($branding, 'support_hotline') }}</a></p>@endif @if(data_get($branding, 'support_email'))<p><i class="fa-solid fa-envelope"></i> <a href="mailto:{{ data_get($branding, 'support_email') }}">{{ data_get($branding, 'support_email') }}</a></p>@endif</section></div></main>
@endsection

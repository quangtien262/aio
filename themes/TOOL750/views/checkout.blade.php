@extends('theme-tool750::layout')
@section('content')
<main><section class="t750-inner-hero"><div class="t750-container"><small>TOOL750</small><h1>@themeT('checkout', 'Thanh toán')</h1></div></section><section class="t750-content"><div class="t750-container">@include('site.checkout.partials.form')</div></section></main>
@endsection

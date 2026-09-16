@extends('theme-auto852::layout')
@section('content')
<main><section class="a852-inner-hero"><div class="a852-container"><small>AUTO852</small><h1>{{ __('Thanh toán') }}</h1></div></section><section class="a852-content"><div class="a852-container">@include('site.checkout.partials.form')</div></section></main>
@endsection

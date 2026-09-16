@extends('theme-auto850::layout')
@section('content')
<main><section class="a850-inner-hero"><div class="a850-container"><small>AUTO850</small><h1>{{ __('Thanh toán') }}</h1></div></section><section class="a850-content"><div class="a850-container">@include('site.checkout.partials.form')</div></section></main>
@endsection

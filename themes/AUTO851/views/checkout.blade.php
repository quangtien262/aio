@extends('theme-auto851::layout')
@section('content')
<main><section class="a851-inner-hero"><div class="a851-container"><small>AUTO851</small><h1>{{ __('Thanh toán') }}</h1></div></section><section class="a851-content"><div class="a851-container">@include('site.checkout.partials.form')</div></section></main>
@endsection

@extends('theme-tool751::layout')
@section('content')
<main><section class="t751-inner-hero"><div class="t751-container"><small>TOOL751</small><h1>{{ __('Thanh toán') }}</h1></div></section><section class="t751-content"><div class="t751-container">@include('site.checkout.partials.form')</div></section></main>
@endsection

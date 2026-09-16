@extends('theme-auto850::layout')
@section('content')
<main><section class="a850-content"><div class="a850-container a850-empty"><i class="fa-solid fa-circle-check" style="font-size:64px;color:#22a55b"></i><h1>{{ __('Đặt hàng thành công') }}</h1><p>{{ __('Cảm ơn bạn. Đội ngũ của chúng tôi sẽ sớm xác nhận đơn hàng.') }}</p><a class="a850-button" href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">@themeT('home', 'Trang chủ')</a></div></section></main>
@endsection

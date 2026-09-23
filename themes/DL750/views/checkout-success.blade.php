@extends('theme-dl750::layout')
@section('title')@themeT('checkout.success', 'Đặt hàng thành công')@endsection
@section('content')
<main class="dl-inner">
    <section class="dl-inner-hero"><div class="dl-wrap"><h1>@themeT('checkout.success', 'Đặt hàng thành công')</h1></div></section>
    <div class="dl-wrap dl-content dl-prose">
        <p>@themeT('checkout.thanks', 'Cảm ơn bạn đã đặt hàng. Chúng tôi sẽ sớm liên hệ để xác nhận thông tin.')</p>
        <a class="dl-primary" href="{{ route('site.home') }}">@themeT('home', 'Trang chủ')</a>
    </div>
</main>
@endsection

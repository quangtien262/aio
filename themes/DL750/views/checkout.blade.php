@extends('theme-dl750::layout')
@section('title')@themeT('checkout', 'Thanh toán')@endsection
@section('content')
<main class="dl-inner">
    <section class="dl-inner-hero"><div class="dl-wrap"><h1>@themeT('checkout', 'Thanh toán')</h1></div></section>
    <div class="dl-wrap dl-content dl-prose">
        <p>@themeT('checkout.instructions', 'Vui lòng điền đầy đủ thông tin nhận hàng trong biểu mẫu thanh toán.')</p>
    </div>
</main>
@endsection

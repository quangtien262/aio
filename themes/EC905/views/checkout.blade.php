@extends('theme-ec905::layout')
@section('title', 'Thanh toán')
@section('content')
<main><section class="ec95-inner-hero"><div class="ec95-container"><p>EGO HOME</p><h1>Thanh toán</h1></div></section><section class="ec95-content"><form class="ec95-container ec95-form" method="POST" action="{{ route('site.checkout.store') }}">@csrf<input name="customer_name" required placeholder="Họ và tên"><input name="customer_email" type="email" required placeholder="Email"><input name="customer_phone" required placeholder="Số điện thoại"><input name="shipping_address" required placeholder="Địa chỉ nhận hàng"><textarea name="note" placeholder="Ghi chú đơn hàng"></textarea><button class="ec95-button">Đặt hàng</button></form></section></main>
@endsection

@extends('theme-ec908::layout')
@section('title', 'Thanh toán')
@section('content')
<main><section class="ec98-inner-hero"><div class="ec98-container"><p>EGO FITNESS</p><h1>Thanh toán</h1></div></section><section class="ec98-content"><form class="ec98-container ec98-form" method="POST" action="{{ route('site.checkout.store') }}">@csrf<input name="customer_name" required placeholder="Họ và tên"><input name="customer_email" type="email" required placeholder="Email"><input name="customer_phone" required placeholder="Số điện thoại"><input name="shipping_address" required placeholder="Địa chỉ nhận hàng"><textarea name="note" placeholder="Ghi chú đơn hàng"></textarea><button class="ec98-button">Đặt hàng</button></form></section></main>
@endsection


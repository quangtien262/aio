@extends('theme-ec900::layout')
@section('title', 'Thanh toán')
@section('content')
<main><section class="ec9-inner-hero"><div class="ec9-container"><h1>Thông tin thanh toán</h1></div></section><section class="ec9-content"><form class="ec9-container ec9-form" method="POST" action="{{ route('site.checkout.store') }}">@csrf<input name="customer_name" required placeholder="Họ và tên"><input name="customer_phone" required placeholder="Số điện thoại"><input name="customer_email" type="email" placeholder="Email"><input name="delivery_address" required placeholder="Địa chỉ nhận hàng"><textarea name="note" placeholder="Ghi chú"></textarea><select name="payment_method" required><option value="cod">Thanh toán khi nhận hàng</option><option value="bank_transfer">Chuyển khoản</option></select><button class="ec9-button">Đặt hàng</button></form></section></main>
@endsection

@extends('theme-ec100::layout')
@section('title', 'Thanh toán')
@section('content')
<main><section class="ec10-inner-hero"><div class="ec10-container"><p>TEMPO WATCH STORE</p><h1>Thông tin thanh toán</h1></div></section><section class="ec10-content"><form class="ec10-container ec10-form" method="POST" action="{{ route('site.checkout.store') }}">@csrf<input name="customer_name" required placeholder="Họ và tên"><input name="customer_phone" required placeholder="Số điện thoại"><input name="customer_email" type="email" placeholder="Email"><input name="delivery_address" required placeholder="Địa chỉ nhận hàng"><textarea name="note" placeholder="Ghi chú"></textarea><select name="payment_method" required><option value="cod">Thanh toán khi nhận hàng</option><option value="bank_transfer">Chuyển khoản</option></select><button class="ec10-button">Đặt hàng</button></form></section></main>
@endsection

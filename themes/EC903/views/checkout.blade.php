@extends('theme-ec903::layout')
@section('title', 'Thanh toán voucher')
@section('content')
<main><section class="ec93-inner-hero"><div class="ec93-container"><p>DEALVUI E-VOUCHER</p><h1>Thông tin thanh toán</h1></div></section><section class="ec93-content"><form class="ec93-container ec93-form" method="POST" action="{{ route('site.checkout.store') }}">@csrf<input name="customer_name" required placeholder="Họ và tên"><input name="customer_phone" required placeholder="Số điện thoại"><input name="customer_email" type="email" required placeholder="Email nhận voucher"><input name="delivery_address" required placeholder="Địa chỉ"><textarea name="note" placeholder="Ghi chú"></textarea><select name="payment_method" required><option value="cod">Thanh toán khi nhận hàng</option><option value="bank_transfer">Chuyển khoản</option></select><button class="ec93-button">Đặt voucher</button></form></section></main>
@endsection

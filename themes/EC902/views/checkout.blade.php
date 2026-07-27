@extends('theme-ec902::layout')
@section('title', 'Thanh toán')
@section('content')
<main><section class="ec92-inner-hero"><div class="ec92-container"><p>NOVAPHONE TECHNOLOGY</p><h1>Thông tin thanh toán</h1></div></section><section class="ec92-content"><form class="ec92-container ec92-form" method="POST" action="{{ route('site.checkout.store') }}">@csrf<input name="customer_name" required placeholder="Họ và tên"><input name="customer_phone" required placeholder="Số điện thoại"><input name="customer_email" type="email" placeholder="Email"><input name="delivery_address" required placeholder="Địa chỉ nhận hàng"><textarea name="note" placeholder="Ghi chú"></textarea><select name="payment_method" required><option value="cod">Thanh toán khi nhận hàng</option><option value="bank_transfer">Chuyển khoản</option></select><button class="ec92-button">Đặt hàng</button></form></section></main>
@endsection

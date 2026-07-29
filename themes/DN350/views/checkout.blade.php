@extends('theme-dn350::layout')
@section('title', 'Thanh toán')
@section('content')
<main><section class="dn-inner-hero"><div class="dn-container"><h1>Thông tin thanh toán</h1></div></section><section class="dn-section"><div class="dn-container dn-contact-form"><form method="POST" action="{{ route('site.checkout.store') }}">@csrf<input name="customer_name" required placeholder="Họ và tên"><input name="customer_phone" required placeholder="Điện thoại"><input type="email" name="customer_email" placeholder="Email"><input name="delivery_address" required placeholder="Địa chỉ"><textarea name="note" placeholder="Ghi chú"></textarea><button class="dn-btn">Đặt hàng</button></form></div></section></main>
@endsection

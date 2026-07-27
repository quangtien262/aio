@extends('theme-spa111::layout')

@section('title', 'Thanh toán')

@section('content')
    <section class="spa111-page-head">
        <div class="spa111-container">
            <nav class="spa111-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Thanh toán</span></nav>
            <h1>Hoàn tất đơn hàng</h1>
            <p>Nhập thông tin nhận hàng để chúng tôi xác nhận đơn trong thời gian sớm nhất.</p>
        </div>
    </section>
    <section class="spa111-subpage">
        <div class="spa111-container">
            <form class="spa111-form" method="POST" action="{{ route('site.checkout.store') }}">
                @csrf
                <input name="name" value="{{ old('name') }}" placeholder="Họ và tên" required>
                <input name="phone" value="{{ old('phone') }}" placeholder="Số điện thoại" required>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email">
                <input name="address" value="{{ old('address') }}" placeholder="Địa chỉ nhận hàng" required>
                <textarea name="note" placeholder="Ghi chú đơn hàng">{{ old('note') }}</textarea>
                <button type="submit" style="width:max-content;border:0;border-radius:8px;background:#71458a;color:#fff;padding:15px 28px;font-weight:900">Đặt hàng</button>
            </form>
        </div>
    </section>
@endsection

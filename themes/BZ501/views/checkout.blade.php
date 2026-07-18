@extends('theme-bz501::layout')

@section('title', 'Thanh toán')

@section('content')
    <section class="bz501-page-head">
        <div class="bz501-container">
            <nav class="bz501-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Thanh toán</span></nav>
            <h1>Hoàn tất đơn hàng</h1>
            <p>Nhập thông tin nhận hàng để chúng tôi xác nhận đơn trong thời gian sớm nhất.</p>
        </div>
    </section>
    <section class="bz501-subpage">
        <div class="bz501-container">
            <form class="bz501-form" method="POST" action="{{ route('site.checkout.store') }}">
                @csrf
                <input name="name" value="{{ old('name') }}" placeholder="Họ và tên" required>
                <input name="phone" value="{{ old('phone') }}" placeholder="Số điện thoại" required>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email">
                <input name="address" value="{{ old('address') }}" placeholder="Địa chỉ nhận hàng" required>
                <textarea name="note" placeholder="Ghi chú đơn hàng">{{ old('note') }}</textarea>
                <button type="submit" style="width:max-content;border:0;border-radius:8px;background:#ff3216;color:#fff;padding:15px 28px;font-weight:900">Đặt hàng</button>
            </form>
        </div>
    </section>
@endsection

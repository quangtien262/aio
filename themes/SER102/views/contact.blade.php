@extends('theme-ser102::layout')

@section('title', 'Liên hệ')

@section('content')
    <section class="ser102-page-head">
        <div class="ser102-container">
            <nav class="ser102-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Liên hệ</span></nav>
            <h1>Liên hệ</h1>
            <p>Gửi thông tin để đội ngũ tư vấn liên hệ và hỗ trợ bạn nhanh nhất.</p>
        </div>
    </section>
    <section class="ser102-subpage">
        <div class="ser102-container ser102-detail">
            <div class="ser102-detail-body">
                <h2>Thông tin liên hệ</h2>
                <p><strong>Địa chỉ:</strong> {{ data_get($siteProfile ?? [], 'support_location', 'Tòa Ladeco, 266 Đội Cấn - Ba Đình - Hà Nội') }}</p>
                <p><strong>Hotline:</strong> {{ data_get($siteProfile ?? [], 'hotline', '19006750') }}</p>
                <p><strong>Email:</strong> {{ data_get($siteProfile ?? [], 'support_email', 'support@htvietnam.vn') }}</p>
            </div>
            <form class="ser102-form" method="POST" action="{{ route('site.contact.submit') }}">
                @csrf
                <input type="hidden" name="source" value="SER102-contact">
                <input name="name" value="{{ old('name') }}" placeholder="Họ và tên" required>
                <input name="phone" value="{{ old('phone') }}" placeholder="Số điện thoại">
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required>
                <textarea name="message" placeholder="Nội dung cần tư vấn" required>{{ old('message') }}</textarea>
                <button class="ser102-newsletter button" type="submit" style="width:max-content;border:0;border-radius:8px;background:#71458a;color:#fff;padding:15px 28px;font-weight:900">Gửi liên hệ</button>
            </form>
        </div>
    </section>
@endsection

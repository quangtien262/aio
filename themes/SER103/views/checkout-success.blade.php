@extends('theme-ser103::layout')

@section('title', 'Đặt hàng thành công')

@section('content')
    <section class="ser103-page-head">
        <div class="ser103-container">
            <nav class="ser103-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Thành công</span></nav>
            <h1>Đơn hàng đã được ghi nhận</h1>
            <p>Đội ngũ tư vấn sẽ liên hệ lại để xác nhận thông tin sản phẩm, giao nhận và thanh toán.</p>
        </div>
    </section>
    <section class="ser103-subpage">
        <div class="ser103-container">
            <div class="ser103-empty">Cảm ơn bạn đã đặt hàng. Vui lòng giữ điện thoại để chúng tôi hỗ trợ nhanh nhất.</div>
        </div>
    </section>
@endsection

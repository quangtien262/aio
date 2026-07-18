@extends('theme-bz501::layout')

@section('title', 'Đặt hàng thành công')

@section('content')
    <section class="bz501-page-head">
        <div class="bz501-container">
            <nav class="bz501-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Thành công</span></nav>
            <h1>Đơn hàng đã được ghi nhận</h1>
            <p>Đội ngũ tư vấn sẽ liên hệ lại để xác nhận thông tin sản phẩm, giao nhận và thanh toán.</p>
        </div>
    </section>
    <section class="bz501-subpage">
        <div class="bz501-container">
            <div class="bz501-empty">Cảm ơn bạn đã đặt hàng. Vui lòng giữ điện thoại để chúng tôi hỗ trợ nhanh nhất.</div>
        </div>
    </section>
@endsection

@extends('theme-spa502::layout')

@section('title', 'Đặt hàng thành công')

@section('content')
    <section class="spa502-page-head">
        <div class="spa502-container">
            <nav class="spa502-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Thành công</span></nav>
            <h1>Đơn hàng đã được ghi nhận</h1>
            <p>Đội ngũ tư vấn sẽ liên hệ lại để xác nhận thông tin sản phẩm, giao nhận và thanh toán.</p>
        </div>
    </section>
    <section class="spa502-subpage">
        <div class="spa502-container">
            <div class="spa502-empty">Cảm ơn bạn đã đặt hàng. Vui lòng giữ điện thoại để chúng tôi hỗ trợ nhanh nhất.</div>
        </div>
    </section>
@endsection

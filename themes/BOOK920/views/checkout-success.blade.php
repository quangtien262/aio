@extends('theme-book920::layout')
@section('content')
<main class="book20-inner"><div class="book20-container"><section class="book20-success book20-panel"><i class="fa-regular fa-circle-check" aria-hidden="true"></i><p class="book20-kicker">@themeT('inner.thanks', 'Cảm ơn bạn đã chọn sách')</p><h1>@themeT('inner.success', 'Đơn hàng đã được ghi nhận')</h1><p>@themeT('inner.success_note', 'Chúng tôi sẽ liên hệ để xác nhận thông tin và hướng dẫn các bước tiếp theo.')</p>
@if(isset($order))<dl class="book20-order-details"><div><dt>@themeT('inner.order_code', 'Mã đơn hàng')</dt><dd>{{ $order->order_code }}</dd></div><div><dt>@themeT('inner.subtotal', 'Tạm tính')</dt><dd>{{ number_format((float) $order->subtotal, 0, ',', '.') }}đ</dd></div><div><dt>@themeT('inner.payment', 'Phương thức thanh toán')</dt><dd>{{ $order->payment_label }}</dd></div></dl>@endif
<a class="book20-button" href="{{ route('site.catalog.search') }}">@themeT('inner.continue', 'Tiếp tục chọn sách') &rarr;</a></section></div></main>
@endsection

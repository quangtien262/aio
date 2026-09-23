@extends('theme-book920::layout')
@section('content')
<main class="book20-inner"><div class="book20-container">
    @include('theme-book920::partials.breadcrumb', ['current' => ''])
    <header class="book20-inner-hero"><p class="book20-kicker">@themeT('inner.order_step', 'Tủ sách của bạn')</p><h1>@themeT('BOOK920.cart', 'Giỏ hàng')</h1></header>
    @include('theme-book920::partials.feedback')
    @php $basket = data_get($themeShellData ?? [], 'cart_summary', $cartSummary ?? []); @endphp
    @if(count(data_get($basket, 'items', [])))
    <div class="book20-checkout-layout"><div class="book20-cart-lines">
    @foreach(data_get($basket, 'items', []) as $line)
        <article class="book20-cart-line">
            <a href="{{ data_get($line, 'url') }}">@if(data_get($line, 'image'))<img src="{{ $line['image'] }}" alt="{{ $line['title'] }}" width="100" height="130">@endif</a>
            <div><h2><a href="{{ data_get($line, 'url') }}">{{ data_get($line, 'title') }}</a></h2><p>{{ number_format((float) data_get($line, 'price'), 0, ',', '.') }}đ</p>
                <form method="post" action="{{ route('site.cart.update', ['productId' => $line['product_id']]) }}">@csrf<label for="qty-{{ $line['product_id'] }}">@themeT('inner.quantity', 'Số lượng')</label><input id="qty-{{ $line['product_id'] }}" type="number" name="quantity" value="{{ $line['quantity'] }}" min="1" max="99" required><button class="book20-text-button" type="submit">@themeT('inner.update', 'Cập nhật')</button></form>
                <form method="post" action="{{ route('site.cart.remove', ['productId' => $line['product_id']]) }}">@csrf<button class="book20-text-button" type="submit">@themeT('inner.remove', 'Xóa khỏi giỏ')</button></form>
            </div><strong>{{ number_format((float) $line['price'] * $line['quantity'], 0, ',', '.') }}đ</strong>
        </article>
    @endforeach
    <a class="book20-text-link" href="{{ route('site.catalog.search') }}">&larr; @themeT('inner.continue', 'Tiếp tục chọn sách')</a>
    </div><aside class="book20-order-summary"><h2>@themeT('inner.order_summary', 'Thông tin đơn hàng')</h2><p>{{ data_get($basket, 'count', 0) }} @themeT('inner.items', 'sản phẩm')</p><div class="book20-total"><span>@themeT('inner.subtotal', 'Tạm tính')</span><strong>{{ number_format((float) data_get($basket, 'subtotal', 0), 0, ',', '.') }}đ</strong></div><p class="book20-muted">@themeT('inner.total_note', 'Chi phí giao nhận và thông tin đơn hàng sẽ được xác nhận khi liên hệ.')</p><a class="book20-button" href="{{ route('site.checkout.index') }}">@themeT('inner.checkout', 'Tiến hành đặt hàng') &rarr;</a></aside></div>
    @else<div class="book20-empty"><i class="fa-solid fa-basket-shopping" aria-hidden="true"></i><h2>@themeT('inner.empty_cart', 'Giỏ hàng của bạn đang trống')</h2><p>@themeT('inner.empty_cart_text', 'Chọn một cuốn sách để bắt đầu hành trình đọc mới.')</p><a class="book20-button" href="{{ route('site.catalog.search') }}">@themeT('inner.explore', 'Khám phá tủ sách')</a></div>@endif
</div></main>
@endsection

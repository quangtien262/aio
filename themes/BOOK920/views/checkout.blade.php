@extends('theme-book920::layout')
@section('content')
<main class="book20-inner"><div class="book20-container">
    @include('theme-book920::partials.breadcrumb', ['current' => ''])
    <header class="book20-inner-hero"><p class="book20-kicker">@themeT('inner.complete_order', 'Hoàn tất lựa chọn của bạn')</p><h1>@themeT('inner.checkout_title', 'Thông tin đặt hàng')</h1></header>
    @include('theme-book920::partials.feedback')
    <form method="post" action="{{ route('site.checkout.store') }}" class="book20-checkout-layout">@csrf
        <section class="book20-panel"><h2>@themeT('inner.recipient', 'Thông tin nhận hàng')</h2><div class="book20-form-grid">
            <label>@themeT('inner.name', 'Họ và tên')<input name="customer_name" autocomplete="name" value="{{ old('customer_name', data_get($checkoutForm ?? [], 'customer_name')) }}" maxlength="120" required></label>
            <label>@themeT('inner.phone', 'Số điện thoại')<input name="customer_phone" type="tel" autocomplete="tel" value="{{ old('customer_phone', data_get($checkoutForm ?? [], 'customer_phone')) }}" maxlength="30" required></label>
            <label class="book20-wide">Email<input name="customer_email" type="email" autocomplete="email" value="{{ old('customer_email', data_get($checkoutForm ?? [], 'customer_email')) }}" maxlength="120"></label>
            <label class="book20-wide">@themeT('inner.address', 'Địa chỉ nhận hàng')<input name="delivery_address" autocomplete="street-address" value="{{ old('delivery_address', data_get($checkoutForm ?? [], 'delivery_address')) }}" maxlength="255" required></label>
            <label class="book20-wide">@themeT('inner.order_note', 'Ghi chú đơn hàng')<textarea name="note" maxlength="500">{{ old('note', data_get($checkoutForm ?? [], 'note')) }}</textarea></label>
        </div><h2>@themeT('inner.payment', 'Phương thức thanh toán')</h2>
        <div class="book20-payments">@foreach($paymentMethods ?? [] as $key => $method)<label><input type="radio" name="payment_method" value="{{ $key }}" @checked(old('payment_method', data_get($checkoutForm ?? [], 'payment_method', 'cod')) === $key) required><span>{{ data_get($method, 'label') }}</span></label>@endforeach</div>
        </section>
        <aside class="book20-order-summary"><h2>@themeT('inner.order_summary', 'Thông tin đơn hàng')</h2>@php $basket = data_get($themeShellData ?? [], 'cart_summary', $cartSummary ?? []); @endphp
            @foreach(data_get($basket, 'items', []) as $line)<p class="book20-summary-line"><span>{{ $line['title'] }} × {{ $line['quantity'] }}</span><strong>{{ number_format((float) $line['price'] * $line['quantity'], 0, ',', '.') }}đ</strong></p>@endforeach
            <div class="book20-total"><span>@themeT('inner.subtotal', 'Tạm tính')</span><strong>{{ number_format((float) data_get($basket, 'subtotal', 0), 0, ',', '.') }}đ</strong></div>
            <p class="book20-muted">@themeT('inner.total_note', 'Chi phí giao nhận và thông tin đơn hàng sẽ được xác nhận khi liên hệ.')</p>
            <button class="book20-button" type="submit">@themeT('inner.place_order', 'Xác nhận đặt hàng') &rarr;</button><a class="book20-text-link" href="{{ route('site.cart.index') }}">@themeT('inner.back_cart', 'Quay lại giỏ hàng')</a>
        </aside>
    </form>
</div></main>
@endsection

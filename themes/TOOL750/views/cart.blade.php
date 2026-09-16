@extends('theme-tool750::layout')
@section('content')
<main>
    <section class="t750-inner-hero"><div class="t750-container"><small>TOOL750</small><h1>@themeT('cart', 'Giỏ hàng')</h1></div></section>
    <section class="t750-content"><div class="t750-container">
        @if(collect($lines ?? [])->isNotEmpty())
            <div class="t750-cart-list">@foreach($lines as $line)<article class="t750-cart-line"><div><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b><small> × {{ data_get($line, 'quantity', 1) }}</small></div><strong>{{ number_format((float) data_get($line, 'line_total', data_get($line, 'subtotal', 0)), 0, ',', '.').'đ' }}</strong></article>@endforeach</div>
            <div class="t750-cart-summary"><b>@themeT('total', 'Tổng cộng'): {{ number_format((float) ($total ?? 0), 0, ',', '.').'đ' }}</b><a class="t750-button" href="{{ route('site.checkout.index', ['locale' => app()->getLocale()]) }}">@themeT('checkout', 'Thanh toán') <i class="fa-solid fa-arrow-right"></i></a></div>
        @else
            <div class="t750-empty"><p>@themeT('empty', 'Nội dung đang được cập nhật.')</p><a class="t750-button" href="{{ route('site.home', ['locale' => app()->getLocale()]) }}#san-pham">@themeT('continue_shopping', 'Tiếp tục mua sắm')</a></div>
        @endif
    </div></section>
</main>
@endsection

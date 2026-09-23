@extends('theme-dl750::layout')
@section('title')@themeT('cart', 'Giỏ hàng')@endsection
@section('content')
<main class="dl-inner">
    <section class="dl-inner-hero"><div class="dl-wrap"><h1>@themeT('cart', 'Giỏ hàng')</h1></div></section>
    <div class="dl-wrap dl-content dl-prose">
        @forelse(data_get($themeShellData ?? [], 'cart_summary.items', []) as $line)
            <p><b>{{ data_get($line, 'title') }}</b> × {{ data_get($line, 'quantity', 1) }}</p>
        @empty
            <p>@themeT('cart.empty', 'Giỏ hàng của bạn đang trống.')</p>
        @endforelse
        <a class="dl-primary" href="{{ route('site.checkout.index') }}">@themeT('checkout', 'Thanh toán')</a>
    </div>
</main>
@endsection

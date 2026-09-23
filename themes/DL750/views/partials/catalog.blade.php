<main class="dl-inner">
    <section class="dl-inner-hero"><div class="dl-wrap"><h1>@if($catalogTitle){{ $catalogTitle }}@else @themeT('products', 'Sản phẩm') @endif</h1></div></section>
    <div class="dl-wrap dl-content">
        <div class="dl-catalog-grid">
            @forelse($products ?? [] as $item)
                @include('theme-dl750::partials.product-card', ['item' => $item])
            @empty
                <p>@themeT('products.empty', 'Không tìm thấy sản phẩm phù hợp.')</p>
            @endforelse
        </div>
        @if(($pagination ?? null) instanceof \Illuminate\Contracts\Pagination\Paginator)
            @include('theme-dl750::partials.pagination', ['paginator' => $pagination])
        @endif
    </div>
</main>

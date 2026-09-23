<main class="book20-inner"><div class="book20-container">
    @include('theme-book920::partials.breadcrumb', ['current' => $catalogTitle])
    <header class="book20-inner-hero"><p class="book20-kicker">@themeT('inner.reading', 'Một cuốn sách, mở thêm thế giới')</p><h1>@if($catalogTitle){{ $catalogTitle }}@else @themeT('inner.all_books', 'Xem tất cả sách') @endif</h1>@if(data_get($category ?? null, 'description'))<p>{{ strip_tags($category->description) }}</p>@endif</header>
    <form class="book20-filter" method="get" action="{{ route('site.catalog.search') }}">
        @if(data_get($category ?? null, 'slug') || (data_get($searchFilters ?? [], 'category') ?? ''))<input type="hidden" name="category" value="{{ data_get($category ?? null, 'slug', data_get($searchFilters ?? [], 'category') ?? '') }}">@endif
        <label><span>@themeT('inner.keyword', 'Tìm tên sách, chủ đề')</span><input name="q" value="{{ $searchQuery ?? request('q', '') }}" placeholder="@themeT('BOOK920.search_placeholder', 'Nhập từ khóa...')"></label>
        <label><span>@themeT('inner.sort', 'Sắp xếp')</span><select name="sort"><option value="default">@themeT('inner.default_sort', 'Mặc định')</option><option value="newest" @selected(data_get($searchFilters ?? [], 'sort', '') === 'newest')>@themeT('inner.newest', 'Mới nhất')</option><option value="price_asc" @selected(data_get($searchFilters ?? [], 'sort', '') === 'price_asc')>@themeT('inner.price_asc', 'Giá tăng dần')</option><option value="price_desc" @selected(data_get($searchFilters ?? [], 'sort', '') === 'price_desc')>@themeT('inner.price_desc', 'Giá giảm dần')</option></select></label>
        <button class="book20-button" type="submit">@themeT('inner.filter', 'Tìm sách')</button>
    </form>
    <div class="book20-catalog book20-product-grid">@forelse($products ?? [] as $item)@include('theme-book920::partials.product-card', ['item' => $item])@empty<div class="book20-empty"><i class="fa-solid fa-book-open" aria-hidden="true"></i><h2>@themeT('inner.no_books', 'Chưa tìm thấy sách phù hợp')</h2><a class="book20-button secondary" href="{{ route('site.catalog.search') }}">@themeT('inner.all_books', 'Xem tất cả sách')</a></div>@endforelse</div>
    @if(($pagination ?? null) instanceof \Illuminate\Contracts\Pagination\Paginator)@include('theme-book920::partials.pagination', ['paginator' => $pagination])@endif
</div></main>

<main class="ca50-inner-page">
<div class="ca50-inner-container">
    @include('theme-ca0050::partials.inner-heading', ['heading' => $pageTitle, 'intro' => __('Khám phá cá cảnh và phụ kiện phù hợp với không gian thủy sinh của bạn.')])
    <form class="ca50-catalog-filters" action="{{ route('site.catalog.search', ['locale' => app()->getLocale()]) }}" method="GET">
        <div><label for="ca50-filter-q">{{ __('Tìm sản phẩm') }}</label><input id="ca50-filter-q" name="q" value="{{ $searchQuery ?? '' }}" placeholder="{{ __('Tên sản phẩm, mã sản phẩm…') }}"></div>
        <div><label for="ca50-filter-category">{{ __('Danh mục') }}</label><select id="ca50-filter-category" name="category"><option value="">{{ __('Tất cả danh mục') }}</option>@foreach(($searchCategories ?? $sidebarCategories ?? []) as $option)<option value="{{ data_get($option, 'slug') }}" @selected(data_get($searchFilters ?? [], 'category', data_get($category ?? null, 'slug')) === data_get($option, 'slug'))>{{ data_get($option, 'name') }}</option>@endforeach</select></div>
        <div><label for="ca50-filter-sort">{{ __('Sắp xếp') }}</label><select id="ca50-filter-sort" name="sort">@foreach(['default' => __('Mặc định'), 'newest' => __('Mới nhất'), 'price_asc' => __('Giá tăng dần'), 'price_desc' => __('Giá giảm dần')] as $value => $label)<option value="{{ $value }}" @selected(request('sort', 'default') === $value)>{{ $label }}</option>@endforeach</select></div>
        <button class="ca50-inner-button" type="submit">{{ __('Lọc sản phẩm') }}</button>
    </form>
    <div class="ca50-results-meta"><span>{{ __('Hiển thị') }} {{ count($products ?? []) }} {{ __('sản phẩm') }}@isset($resultCount) / {{ $resultCount }}@endisset</span><a href="{{ route('site.catalog.search', ['locale' => app()->getLocale()]) }}">{{ __('Xóa bộ lọc') }}</a></div>
    @if(count($products ?? []))
    <div class="ca50-catalog-grid">@foreach($products as $item)@include('theme-ca0050::partials.product-card', ['item' => $item])@endforeach</div>
    @else
    <div class="ca50-empty"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><h2>{{ __('Chưa tìm thấy sản phẩm phù hợp') }}</h2><p>{{ __('Hãy thử từ khóa khác hoặc bỏ bớt bộ lọc.') }}</p></div>
    @endif
    @if(isset($pagination) && $pagination->hasPages())
    <nav class="ca50-inner-pagination" aria-label="{{ __('Phân trang') }}">@if($pagination->previousPageUrl())<a href="{{ $pagination->previousPageUrl() }}">{{ __('Trang trước') }}</a>@endif<span>{{ $pagination->currentPage() }} / {{ $pagination->lastPage() }}</span>@if($pagination->nextPageUrl())<a href="{{ $pagination->nextPageUrl() }}">{{ __('Trang sau') }}</a>@endif</nav>
    @endif
</div>
</main>

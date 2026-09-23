@if($paginator->hasPages())
    <nav class="dl-pagination" aria-label="@themeT('pagination', 'Phân trang')">
        @if($paginator->previousPageUrl())<a class="dl-primary" href="{{ $paginator->previousPageUrl() }}" rel="prev">@themeT('pagination.previous', 'Trang trước')</a>@endif
        <span>{{ $paginator->currentPage() }}</span>
        @if($paginator->nextPageUrl())<a class="dl-primary" href="{{ $paginator->nextPageUrl() }}" rel="next">@themeT('pagination.next', 'Trang sau')</a>@endif
    </nav>
@endif

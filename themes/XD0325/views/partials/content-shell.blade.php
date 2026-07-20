<main class="xd-content-shell">
    <div class="xd-container">
        <nav class="xd-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('site.home') }}">Trang chủ</a>
            <span>/</span>
            <span>{{ $title ?? 'Nội dung' }}</span>
        </nav>

        <article class="xd-content-card">
            <p class="xd-eyebrow">BEAN CONSTRUCTION</p>
            <h1>{{ $title ?? 'Nội dung' }}</h1>

            @if (! empty($cover ?? null))
                <img class="xd-content-cover" src="{{ $cover }}" alt="{{ $title ?? 'Nội dung' }}">
            @endif

            @if (! empty($summary ?? null))
                <p class="xd-content-summary">{{ $summary }}</p>
            @endif

            <div class="xd-richtext">
                {!! $body ?? '' !!}
            </div>
        </article>
    </div>
</main>

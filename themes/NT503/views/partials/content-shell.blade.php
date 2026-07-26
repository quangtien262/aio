<main>
    <section class="n503-inner-hero">
        <div class="n503-container">
            <p>WOLFBED</p>
            <h1>{{ $title ?? 'Nội dung' }}</h1>
            @if ($summary ?? null)
                <span>{{ $summary }}</span>
            @endif
        </div>
    </section>
    <section>
        <div class="n503-container n503-prose">
            @if ($cover ?? null)
                <img src="{{ $cover }}" alt="{{ $title ?? '' }}">
            @endif
            <div>{!! ($body ?? null) ?: '<p>Nội dung đang được cập nhật.</p>' !!}</div>
        </div>
    </section>
</main>

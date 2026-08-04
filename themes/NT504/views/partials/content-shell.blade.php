<main>
    <section class="n504-inner-hero">
        <div class="n504-container">
            <p>WOLFBED</p>
            <h1>{{ $title ?? 'Nội dung' }}</h1>
            @if ($summary ?? null)
                <span>{{ $summary }}</span>
            @endif
        </div>
    </section>
    <section>
        <div class="n504-container n504-prose">
            @if ($cover ?? null)
                <img src="{{ $cover }}" alt="{{ $title ?? '' }}">
            @endif
            <div>{!! ($body ?? null) ?: '<p>Nội dung đang được cập nhật.</p>' !!}</div>
        </div>
    </section>
</main>

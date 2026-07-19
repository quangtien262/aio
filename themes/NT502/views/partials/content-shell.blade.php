<main>
    <section class="n502-inner-hero">
        <div class="n502-container">
            <p>DOLA FURNITURE</p>
            <h1>{{ $title ?? 'Nội dung' }}</h1>
            @if ($summary ?? null)
                <span>{{ $summary }}</span>
            @endif
        </div>
    </section>
    <section>
        <div class="n502-container n502-prose">
            @if ($cover ?? null)
                <img src="{{ $cover }}" alt="{{ $title ?? '' }}">
            @endif
            <div>{!! ($body ?? null) ?: '<p>Nội dung đang được cập nhật.</p>' !!}</div>
        </div>
    </section>
</main>

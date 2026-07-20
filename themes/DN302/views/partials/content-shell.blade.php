<main>
    <section class="dn-inner-hero"><div class="dn-container" data-dn-reveal="up"><p class="dn-eyebrow">Janelas Windows &amp; Doors</p><h1>{{ $title ?? 'Nội dung' }}</h1></div></section>
    <section class="dn-section"><article class="dn-container dn-content-card" data-dn-reveal="up">
        @if(!empty($cover ?? null))<img class="dn-content-cover" src="{{ $cover }}" alt="{{ $title ?? 'Nội dung' }}">@endif
        @if(!empty($summary ?? null))<p>{{ $summary }}</p>@endif
        <div class="dn-richtext">{!! $body ?? '' !!}</div>
    </article></section>
</main>

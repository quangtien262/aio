@php
    $entryImage = data_get($entry ?? null, 'featuredMedia.file_url')
        ?: data_get($entry ?? null, 'featuredImage.image_url')
        ?: data_get($entry ?? null, 'image_url');
    $entryBody = data_get($entry ?? null, 'body') ?: data_get($entry ?? null, 'content');
@endphp
<main class="dl-inner">
    <section class="dl-inner-hero">
        <div class="dl-wrap">
            <a href="{{ route('site.home') }}">@themeT('home', 'Trang chủ')</a>
            <h1>{{ data_get($entry ?? null, 'title', $pageTitle ?? '') }}</h1>
        </div>
    </section>
    <article class="dl-wrap dl-content dl-prose">
        @if($entryImage)<img class="dl-content-image" src="{{ $entryImage }}" alt="{{ data_get($entry ?? null, 'title') }}">@endif
        {!! $entryBody !!}
        @if(($contentType ?? '') === 'contact' && blank($entryBody))
            <p>@themeT('contact.instructions', 'Vui lòng liên hệ qua hotline hoặc email hiển thị ở chân trang.')</p>
        @endif
    </article>
</main>

@php
    $title = $title ?? data_get($page ?? null, 'title', data_get($entry ?? null, 'title', 'Nội dung'));
    $summary = $summary ?? data_get($page ?? null, 'excerpt', data_get($entry ?? null, 'summary'));
    $cover = $cover ?? data_get($page ?? null, 'cover_image_url', data_get($entry ?? null, 'image'));
    $body = $body ?? data_get($page ?? null, 'body', data_get($entry ?? null, 'body'));
@endphp
<main>
    <section class="ec9-inner-hero"><div class="ec9-container"><p>ECOMAX SMART HOME</p><h1>{{ $title }}</h1>@if($summary)<span>{{ $summary }}</span>@endif</div></section>
    <section class="ec9-content"><div class="ec9-container ec9-prose">@if($cover)<img src="{{ $cover }}" alt="{{ $title }}">@endif<div>{!! $body ?: '<p>Nội dung đang được cập nhật.</p>' !!}</div></div></section>
</main>

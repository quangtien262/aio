@php
    $entity = $page ?? $post ?? $service ?? $project ?? null;
    $title = $pageTitle ?? data_get($entity, 'title', data_get($entity, 'name', 'Nội dung'));
    $summary = $pageDescription ?? data_get($entity, 'summary', data_get($entity, 'excerpt', data_get($entity, 'description')));
    $body = data_get($entity, 'content', data_get($entity, 'detail_content', data_get($entity, 'description')));
    $cover = data_get($entity, 'featuredMedia.file_url', data_get($entity, 'image_url'));
@endphp
<main class="s601-inner"><section class="s601-inner-hero"><div class="s601-container"><p>SHOP601</p><h1>{{ $title }}</h1>@if($summary)<span>{{ $summary }}</span>@endif</div></section><section class="s601-section"><div class="s601-container s601-prose">@if($cover)<img src="{{ $cover }}" alt="{{ $title }}">@endif<div>{!! $body ?: '<p>Nội dung đang được cập nhật.</p>' !!}</div></div></section></main>
@once @push('head')<style>.s601-inner-hero{padding:70px 0;background:linear-gradient(135deg,#fff0eb,#fff)}.s601-inner-hero p{color:var(--s601-red);font-weight:800}.s601-inner-hero h1{max-width:950px;margin:8px 0;font-size:46px}.s601-inner-hero span{color:#666}.s601-prose{max-width:1050px;line-height:1.8}.s601-prose>img{width:100%;max-height:600px;margin-bottom:30px;object-fit:cover;border-radius:14px}.s601-prose img{max-width:100%}</style>@endpush @endonce

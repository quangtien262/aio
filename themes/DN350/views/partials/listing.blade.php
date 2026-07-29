@php
    $listingSource = $listingItems ?? $entries ?? $products ?? $posts ?? $services ?? $projects ?? [];
    $entries = is_object($listingSource) && method_exists($listingSource, 'getCollection')
        ? $listingSource->getCollection()
        : collect($listingSource);
    $listingTitle = $pageTitle ?? $title ?? 'Khám phá';
    $listingDescription = $pageDescription ?? $description ?? null;
    $companyName = data_get($themeShellData ?? [], 'branding.company_name', data_get($siteProfile ?? null, 'site_name', ''));
@endphp
<main>
    <section class="dn-inner-hero"><div class="dn-container" data-dn-reveal="up">
        @if (filled($companyName))<p class="dn-eyebrow">{{ $companyName }}</p>@endif
        <h1>{{ $listingTitle }}</h1>
        @if (filled($listingDescription))<p class="dn-inner-hero__description">{{ $listingDescription }}</p>@endif
    </div></section>
    <section class="dn-section"><div class="dn-container dn-list-grid">
        @forelse($entries as $index => $item)
            @php
                $itemSlug = data_get($item, 'slug');
                $itemUrl = data_get($item, 'url') ?: match ($contentType ?? null) {
                    'services' => $itemSlug ? route('site.services.show', ['slug' => $itemSlug]) : '#',
                    'projects' => $itemSlug ? route('site.projects.show', ['slug' => $itemSlug]) : '#',
                    'posts' => $itemSlug ? route('site.blog.show', ['slug' => $itemSlug]) : '#',
                    default => '#',
                };
                $itemImage = data_get($item, 'image')
                    ?: data_get($item, 'image_url')
                    ?: data_get($item, 'featuredImage.image_url')
                    ?: data_get($item, 'featuredMedia.file_url')
                    ?: '/theme-demo/dn350/gallery-kitchen.webp';
            @endphp
            <article class="dn-list-card" style="--dn-delay:{{ ($index % 3) * 90 }}ms" data-dn-reveal="up"><a href="{{ $itemUrl }}"><img src="{{ $itemImage }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"><div><h3>{{ data_get($item, 'title', data_get($item, 'name')) }}</h3><p>{{ data_get($item, 'summary', data_get($item, 'description')) }}</p></div></a></article>
        @empty
            <p>Nội dung đang được cập nhật.</p>
        @endforelse
    </div></section>
</main>

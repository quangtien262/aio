@php
    $contentTitle = $title ?? $pageTitle ?? 'Nội dung';
    $contentSummary = $summary ?? $pageDescription ?? null;
    $companyName = data_get($themeShellData ?? [], 'branding.company_name', data_get($siteProfile ?? null, 'site_name', ''));
@endphp
<main>
    <section class="dn-inner-hero"><div class="dn-container" data-dn-reveal="up">
        @if (filled($companyName))<p class="dn-eyebrow">{{ $companyName }}</p>@endif
        <h1>{{ $contentTitle }}</h1>
        @if (filled($contentSummary))<p class="dn-inner-hero__description">{{ $contentSummary }}</p>@endif
    </div></section>
    <section class="dn-section"><article class="dn-container dn-content-card" data-dn-reveal="up">
        @if(!empty($cover ?? null))<img class="dn-content-cover" src="{{ $cover }}" alt="{{ $contentTitle }}">@endif
        <div class="dn-richtext">{!! $body ?? '' !!}</div>
    </article></section>
</main>

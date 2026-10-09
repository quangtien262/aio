@php
    $contentLayout = config('storefront-layouts.'.strtoupper((string) ($layoutThemeKey ?? '')));
    $contentContainer = data_get($contentLayout, 'content_container', data_get($contentLayout, 'container'));
    $nativeContainers = array_filter(array_unique(array_merge(
        explode(',', (string) $contentContainer),
        explode(',', (string) data_get($contentLayout, 'container')),
    )), fn ($selector) => str_starts_with(trim($selector), '.'));
@endphp
@if(is_array($contentLayout))
<style data-storefront-content-alignment>
    :root { --storefront-container-width: {{ $contentLayout['width'] }}; }
    @foreach(($contentLayout['breakpoints'] ?? []) as $query => $width)
    @media {!! $query !!} { :root { --storefront-container-width: {{ $width }}; } }
    @endforeach

    html:root body { margin: 0; }

    /* Only outer content shells: article prose and full-width backgrounds retain their own layout. */
    html:root body .xd-page-main > .xd-container,
    html:root body .xd305-projects-container,
    html:root body .tc-contact-page .tc-contact-container,
    html:root body .theme-news-listing .tnl-container,
    html:root body .tna-article .tna-article-wrap,
    html:root body .xd-catalog-page .xdc-container,
    html:root body .catalog-recommendations,
    html:root body .catalog-container
    @foreach($nativeContainers as $selector)
    , html:root body {!! trim($selector) !!}
    @endforeach
    {
        width: var(--storefront-container-width);
        max-width: none;
        margin-inline: auto;
        min-width: 0;
        box-sizing: border-box;
    }
    @foreach($nativeContainers as $selector)
    /* Older page templates set an inline prose max-width on the outer shell. */
    html:root body {!! trim($selector) !!} { max-width: none !important; }
    html:root body header {!! trim($selector) !!} { padding-inline: 0; }
    html:root body main > section:has(> {!! trim($selector) !!}) { padding-inline: 0; }
    html:root body {!! trim($selector) !!} .catalog-recommendations { width: 100%; }
    @endforeach
    html:root body .xd-container .catalog-recommendations,
    html:root body .catalog-container .catalog-recommendations { width: 100%; }
    @if(($layoutThemeKey ?? '') === 'XD0315')
    html:root body .af15-site-header { padding-inline: 0; }
    @endif
    @if(($layoutThemeKey ?? '') === 'SER0101')
    html:root body .ser-shell-header .wrap,
    html:root body .ser-shell-nav .wrap { width: var(--storefront-container-width); }
    @endif
    html:root body .e803-product-grid > * { min-width: 0; }
    @media (max-width: 760px) {
        html:root body .e803-product-grid { grid-template-columns: minmax(0, 1fr) !important; gap: 24px !important; }
    }
    /* Keep mobile header controls inside the same shell as the page content. */
    @media (max-width: 600px) {
        html:root body header :is(.e802-logo, .ec9-logo, .ec96-logo, .ec97-logo, .ec98-logo, .ec99-logo, .ec10-logo, .ec16-logo, .ser102-brand, .ser103-brand, .s603-logo) {
            width: 120px; max-width: 120px; min-width: 0; flex-shrink: 1;
        }
        html:root body header :is(.e802-logo, .ec9-logo, .ec96-logo, .ec97-logo, .ec98-logo, .ec99-logo, .ec10-logo, .ec16-logo, .ser102-brand, .ser103-brand, .s603-logo) img {
            width: 100%; max-width: 120px; height: auto; object-fit: contain;
        }
        html:root body header :is(.ec9-header-main, .ec97-head-top, .ec98-head-main, .ec10-head-main, .s603-top-grid) { grid-template-columns: minmax(0, 1fr) auto; }
        html:root body header :is(.e802-main, .ec99-shell) { grid-template-columns: minmax(0, 1fr) auto auto; }
        html:root body header .ec96-head-main { grid-template-columns: auto minmax(0, 1fr) auto; }
        html:root body header :is(.ec9-search, .s603-search) { min-width: 0; width: 100%; }
        html:root body header :is(.ec9-search, .s603-search) input { min-width: 0; }
    }
    @if(($layoutThemeKey ?? '') === 'TOOL750')
    html:root body .t750-nav-main { width: var(--storefront-container-width); }
    @endif
</style>
@endif


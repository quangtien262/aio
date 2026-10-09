@php
    $contentLayout = config('storefront-layouts.'.strtoupper((string) ($layoutThemeKey ?? '')));
    $contentContainer = data_get($contentLayout, 'content_container', data_get($contentLayout, 'container'));
@endphp
@if(is_array($contentLayout))
<style data-storefront-content-alignment>
    :root { --storefront-container-width: {{ $contentLayout['width'] }}; }
    @foreach(($contentLayout['breakpoints'] ?? []) as $query => $width)
    @media {!! $query !!} { :root { --storefront-container-width: {{ $width }}; } }
    @endforeach

    /* Only outer content shells: article prose and full-width backgrounds retain their own layout. */
    html:root body .xd-page-main > .xd-container,
    html:root body .xd305-projects-container,
    html:root body .tc-contact-page .tc-contact-container,
    html:root body .theme-news-listing .tnl-container,
    html:root body .tna-article .tna-article-wrap,
    html:root body .xd-catalog-page .xdc-container,
    html:root body .catalog-recommendations,
    html:root body main{{ $contentContainer }},
    html:root body main > {{ $contentContainer }},
    html:root body main > section > {{ $contentContainer }} {
        width: var(--storefront-container-width);
        max-width: none;
        margin-inline: auto;
        min-width: 0;
        box-sizing: border-box;
    }
    @if(($layoutThemeKey ?? '') === 'SER0101')
    html:root body .ser-shell-header .wrap,
    html:root body .ser-shell-nav .wrap { width: var(--storefront-container-width); }
    @endif
</style>
@endif


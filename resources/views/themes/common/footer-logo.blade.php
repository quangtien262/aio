@php
    $footerBranding = (array) data_get($themeShellData ?? $themeHomeData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $footerBrandLogo = trim((string) ($footerBranding['logo_url'] ?? ''));
    $footerBrandName = trim((string) ($footerBranding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', '')));
@endphp
@if($footerBrandLogo !== '' || $footerBrandName !== '')
<a class="theme-footer-logo" href="{{ route('site.home') }}" aria-label="{{ $footerBrandName }}">
    @if($footerBrandLogo !== '')<img src="{{ $footerBrandLogo }}" alt="{{ $footerBrandName }}" loading="lazy">@else<span>{{ $footerBrandName }}</span>@endif
</a>
<style>
footer a.theme-footer-logo{display:inline-flex;align-items:center;max-width:100%;margin:0 0 16px;padding:0;border:0;background:transparent;box-shadow:none;text-decoration:none;color:inherit;font:inherit;filter:none}
footer a.theme-footer-logo img{display:block!important;width:auto!important;height:auto!important;max-width:min(180px,100%)!important;max-height:70px!important;object-fit:contain!important;object-position:left center;filter:none!important;opacity:1!important;transform:none!important}
footer a.theme-footer-logo:focus-visible{outline:2px solid currentColor;outline-offset:5px}
</style>
@endif

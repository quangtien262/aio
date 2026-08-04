@php
    $shell=$themeShellData??$themeHomeData??[];
    $branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
    $siteName=trim((string)data_get($siteProfile??[],'site_name','FOOT408'));
    $logo=trim((string)data_get($branding,'logo_url'));
    $hotline=trim((string)data_get($branding,'support_hotline'));
    $email=trim((string)data_get($branding,'support_email'));
    $address=trim((string)data_get($branding,'support_location'));
    $hours=trim((string)data_get($branding,'business_hours'));
    $copyright=trim((string)data_get($branding,'copyright_text'));
@endphp
<footer class="f408-footer">
    <div class="f408-container f408-footer__content">
        <a class="f408-footer__logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<i class="fa-solid fa-utensils"></i>@endif</a>
        <h2>{{ data_get($branding,'company_name',$siteName) }}</h2>
        @if($hours)<p><i class="fa-regular fa-clock"></i> {{ $hours }}</p>@endif
        @if($hotline)<p><i class="fa-solid fa-phone"></i> <a href="tel:{{ preg_replace('/\s+/','',$hotline) }}">{{ $hotline }}</a></p>@endif
        @if($email)<p><i class="fa-regular fa-envelope"></i> <a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
        @if($address)<div class="f408-location"><b>1</b><span>{{ $address }}</span><a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($address) }}" target="_blank" rel="noopener"><i class="fa-solid fa-location-arrow"></i> Xem bản đồ</a></div>@endif
    </div>
    <div class="f408-copyright">{{ $copyright!==''?$copyright:'© '.now()->year.' '.$siteName.'. All rights reserved.' }}</div>
</footer>

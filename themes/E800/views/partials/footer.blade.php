@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $t = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('E800', app()->getLocale(), $key);
    $name = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', ''))) ?: $t('E800.brand.default');
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $description = trim((string) ($branding['company_description'] ?? ''));
    $phone = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $address = trim((string) ($branding['support_location'] ?? ''));
    $copyright = trim((string) ($branding['copyright_text'] ?? '')) ?: $t('E800.footer.rights');
@endphp
<footer id="footer" class="e800-footer">
    <div class="e800-container e800-benefits">
        @foreach([
          ['fa-truck-fast','E800.footer.fast_delivery','E800.footer.fast_delivery_note'],
          ['fa-box-open','E800.footer.free_return','E800.footer.free_return_note'],
          ['fa-message','E800.footer.support','E800.footer.support_note'],
          ['fa-tags','E800.footer.hot_deals','E800.footer.hot_deals_note']
        ] as [$icon,$title,$note])<article><i class="fa-solid {{ $icon }}"></i><div><b>{{ $t($title) }}</b><span>{{ $t($note) }}</span></div></article>@endforeach
    </div>
    <div class="e800-container e800-footer__grid">
        <section class="e800-footer__brand">
            <div class="e800-logo e800-logo--footer">@if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@else<span>{{ $name }}</span>@endif</div>
            <h3>{{ $name }}</h3>@if($description)<p>{{ $description }}</p>@endif
            @if($address)<p><i class="fa-solid fa-location-dot"></i> {{ $address }}</p>@endif
            <div class="e800-contact">@if($phone)<a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"><i class="fa-solid fa-phone"></i> {{ $phone }}</a>@endif @if($email)<a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i> {{ $email }}</a>@endif</div>
            <div class="e800-social"><a href="{{ data_get($branding,'facebook_url','#') }}" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="{{ data_get($branding,'youtube_url','#') }}" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a><a href="{{ data_get($branding,'tiktok_url','#') }}" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a><a href="{{ data_get($branding,'instagram_url','#') }}" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a></div>
        </section>
        <nav><h3>{{ $t('E800.footer.about') }}</h3><a href="{{ route('site.home') }}">{{ $t('E800.nav.home') }}</a><a href="{{ route('site.catalog.search') }}">{{ $t('E800.nav.products') }}</a><a href="{{ route('site.blog.index') }}">{{ $t('E800.nav.blog') }}</a><a href="{{ route('site.contact') }}">{{ $t('E800.nav.guide') }}</a></nav>
        <nav><h3>{{ $t('E800.footer.customer_support') }}</h3>@if($phone)<p>{{ $phone }}</p>@endif<a href="{{ route('site.contact') }}">{{ $t('E800.footer.support') }}</a><a href="{{ route('site.cart.index') }}">{{ $t('E800.header.cart') }}</a></nav>
        <section class="e800-newsletter"><h3>{{ $t('E800.footer.newsletter') }}</h3><p>{{ $t('E800.footer.newsletter_note') }}</p><form method="POST" action="{{ route('site.newsletter.subscribe', \App\Support\FrontendLocalization::routeParameterDefaults()) }}">@csrf<input type="email" name="email" required placeholder="{{ $t('E800.footer.email_placeholder') }}"><button aria-label="Đăng ký"><i class="fa-solid fa-arrow-right"></i></button></form><h3>{{ $t('E800.footer.payment') }}</h3><div class="e800-payment"><span>VISA</span><span>MC</span><span>COD</span><span>QR</span></div></section>
    </div>
    <div class="e800-container e800-copyright">© {{ now()->year }} {{ $copyright }}</div>
</footer>

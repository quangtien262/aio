@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'XD321 Cargo'))) ?: 'XD321 Cargo';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $address = trim((string) ($branding['support_location'] ?? ''));
    $socialLinks = collect([
        ['key' => 'facebook_url', 'label' => 'Facebook', 'icon' => 'fa-facebook-f'],
        ['key' => 'x_url', 'label' => 'X', 'icon' => 'fa-x-twitter'],
        ['key' => 'youtube_url', 'label' => 'YouTube', 'icon' => 'fa-youtube'],
    ])->map(fn ($social) => array_merge($social, ['url' => trim((string) ($branding[$social['key']] ?? ''))]))
        ->filter(fn ($social) => filter_var($social['url'], FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($social['url'], PHP_URL_SCHEME)), ['http', 'https'], true));
@endphp
<footer id="footer" class="foot-footer">
    <div class="foot-container foot-footer__top {{ $socialLinks->isEmpty() ? 'foot-footer__top--without-social' : '' }}">
        <section class="foot-footer__newsletter">
            <p class="foot-footer__eyebrow">@themeT('xd321.footer.newsletter_label')</p>
            <form action="{{ route('site.newsletter.subscribe') }}" method="post">
                @csrf
                <label class="sr-only" for="foot-newsletter-email">@themeT('xd321.footer.email')</label>
                <input id="foot-newsletter-email" name="email" type="email" required placeholder="@themeT('xd321.footer.email_placeholder')">
                <button type="submit">@themeT('xd321.footer.subscribe')</button>
            </form>
        </section>
        <section class="foot-footer__brand">
            @if ($logoUrl !== '')
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @else
                <span class="foot-brand__monogram">X</span>
            @endif
            <strong>{{ $companyName }}</strong>
            <small>@themeT('xd321.brand.tagline')</small>
        </section>
        @if($socialLinks->isNotEmpty())
        <section class="foot-footer__social">
            <p class="foot-footer__eyebrow">@themeT('xd321.footer.follow')</p>
            <div aria-label="@themeT('xd321.footer.social')">
                @foreach($socialLinks as $social)
                    <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $social['label'] }}"><i class="fa-brands {{ $social['icon'] }}" aria-hidden="true"></i></a>
                @endforeach
            </div>
        </section>
        @endif
    </div>
    <div class="foot-footer__divider"></div>
    <div class="foot-container foot-footer__grid">
        <section><h3>{{ $companyName }}</h3>@if ($address !== '')<p><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ $address }}</p>@endif @if ($email !== '')<p><i class="fa-solid fa-envelope" aria-hidden="true"></i><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif @if ($hotline !== '')<p><i class="fa-solid fa-phone" aria-hidden="true"></i><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a></p>@endif</section>
        <section><h3>@themeT('xd321.footer.services')</h3><ul><li><a href="#dich-vu">@themeT('xd321.footer.private_dining')</a></li><li><a href="#dich-vu">@themeT('xd321.footer.events')</a></li><li><a href="#san-pham">@themeT('xd321.footer.menu')</a></li></ul></section>
        <section><h3>@themeT('xd321.footer.explore')</h3><ul><li><a href="#gioi-thieu">@themeT('xd321.nav.story')</a></li><li><a href="#tin-tuc">@themeT('xd321.nav.news')</a></li><li><a href="#doi-ngu">@themeT('xd321.nav.team')</a></li></ul></section>
        <section><h3>@themeT('xd321.footer.reservation')</h3><p>@themeT('xd321.footer.reservation_text')</p><a class="foot-button foot-button--light" href="#dich-vu">@themeT('xd321.footer.contact')</a></section>
    </div>
    <div class="foot-container foot-footer__bottom">&copy; {{ now()->year }} {{ $companyName }}. @themeT('xd321.footer.rights')</div>
</footer>

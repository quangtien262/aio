@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'XD0320 Industrial'))) ?: 'XD0320 Industrial';
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $address = trim((string) ($branding['support_location'] ?? ''));
    $socials = collect(['facebook_url' => ['Facebook', 'facebook-f'], 'instagram_url' => ['Instagram', 'instagram'], 'x_url' => ['X', 'x-twitter'], 'youtube_url' => ['YouTube', 'youtube'], 'tiktok_url' => ['TikTok', 'tiktok']])->map(function ($social, $key) use ($branding) {
        return ['label' => $social[0], 'icon' => $social[1], 'url' => trim((string) ($branding[$key] ?? ''))];
    })->filter(fn ($social) => filter_var($social['url'], FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($social['url'], PHP_URL_SCHEME)), ['http', 'https'], true));
@endphp
<footer id="footer" class="foot-footer">
    <div class="foot-container foot-footer__top">
        <section class="foot-footer__newsletter">
            <p class="foot-footer__eyebrow">@themeT('xd0320.footer.newsletter_label')</p>
            <form action="{{ route('site.newsletter.subscribe') }}" method="post">
                @csrf
                <label class="sr-only" for="foot-newsletter-email">@themeT('xd0320.footer.email')</label>
                <input id="foot-newsletter-email" name="email" type="email" required placeholder="@themeT('xd0320.footer.email_placeholder')">
                <button type="submit">@themeT('xd0320.footer.subscribe')</button>
            </form>
        </section>
        @if($socials->isNotEmpty())
        <section class="foot-footer__social">
            <p class="foot-footer__eyebrow">@themeT('xd0320.footer.follow')</p>
            <div aria-label="@themeT('xd0320.footer.social')">@foreach($socials as $social)<a href="{{ $social['url'] }}" aria-label="{{ $social['label'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-{{ $social['icon'] }}" aria-hidden="true"></i></a>@endforeach</div>
        </section>
        @endif
    </div>
    <div class="foot-footer__divider"></div>
    <div class="foot-container foot-footer__grid">
        <section class="xd20-footer-company">@include('themes.common.footer-logo')
        <h3>{{ $companyName }}</h3><p>{{ $address }}</p><p><a href="mailto:{{ $email }}">{{ $email }}</a></p><p><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a></p></section>
        <section><h3>@themeT('xd0320.footer.services')</h3><ul><li><a href="#dich-vu">@themeT('xd0320.footer.private_dining')</a></li><li><a href="#dich-vu">@themeT('xd0320.footer.events')</a></li><li><a href="#thuc-don">@themeT('xd0320.footer.menu')</a></li></ul></section>
        <section><h3>@themeT('xd0320.footer.explore')</h3><ul><li><a href="#gioi-thieu">@themeT('xd0320.nav.story')</a></li><li><a href="#tin-tuc">@themeT('xd0320.nav.news')</a></li><li><a href="#doi-ngu">@themeT('xd0320.nav.team')</a></li></ul></section>
        <section><h3>@themeT('xd0320.footer.reservation')</h3><p>@themeT('xd0320.footer.reservation_text')</p><a class="foot-button foot-button--light" href="#dich-vu">@themeT('xd0320.footer.contact')</a></section>
    </div>
    <div class="foot-container foot-footer__bottom">&copy; {{ now()->year }} {{ $companyName }}. @themeT('xd0320.footer.rights')</div>
</footer>

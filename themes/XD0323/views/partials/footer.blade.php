@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'XD0323 Construction'))) ?: 'XD0323 Construction';
    $hotline = trim((string) ($branding['support_hotline'] ?? '028 7307 3737')) ?: '028 7307 3737';
    $email = trim((string) ($branding['support_email'] ?? 'hello@XD0323.vn')) ?: 'hello@XD0323.vn';
    $address = trim((string) ($branding['support_location'] ?? '196 Nguyen Dinh Chieu, Quan 3, TP.HCM')) ?: '196 Nguyen Dinh Chieu, Quan 3, TP.HCM';
@endphp
<footer id="footer" class="foot-footer">
    <div class="foot-container foot-footer__top">
        <section class="foot-footer__newsletter">
            <p class="foot-footer__eyebrow">@themeT('XD0323.footer.newsletter_label')</p>
            <form action="{{ route('site.newsletter.subscribe') }}" method="post">
                @csrf
                <label class="sr-only" for="foot-newsletter-email">@themeT('XD0323.footer.email')</label>
                <input id="foot-newsletter-email" name="email" type="email" required placeholder="@themeT('XD0323.footer.email_placeholder')">
                <button type="submit">@themeT('XD0323.footer.subscribe')</button>
            </form>
        </section>
        <section class="foot-footer__brand">
            <span class="foot-brand__monogram">N</span>
            <strong>{{ $companyName }}</strong>
            <small>@themeT('XD0323.brand.tagline')</small>
        </section>
        <section class="foot-footer__social">
            <p class="foot-footer__eyebrow">@themeT('XD0323.footer.follow')</p>
            <div aria-label="@themeT('XD0323.footer.social')"><a href="#footer">f</a><a href="#footer">i</a><a href="#footer">t</a><a href="#footer">y</a></div>
        </section>
    </div>
    <div class="foot-footer__divider"></div>
    <div class="foot-container foot-footer__grid">
        <section><h3>{{ $companyName }}</h3><p>{{ $address }}</p><p><a href="mailto:{{ $email }}">{{ $email }}</a></p><p><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a></p></section>
        <section><h3>@themeT('XD0323.footer.services')</h3><ul><li><a href="#dich-vu">@themeT('XD0323.footer.private_dining')</a></li><li><a href="#dich-vu">@themeT('XD0323.footer.events')</a></li><li><a href="#san-pham">@themeT('XD0323.footer.menu')</a></li></ul></section>
        <section><h3>@themeT('XD0323.footer.explore')</h3><ul><li><a href="#gioi-thieu">@themeT('XD0323.nav.story')</a></li><li><a href="#tin-tuc">@themeT('XD0323.nav.news')</a></li><li><a href="#doi-ngu">@themeT('XD0323.nav.team')</a></li></ul></section>
        <section><h3>@themeT('XD0323.footer.reservation')</h3><p>@themeT('XD0323.footer.reservation_text')</p><a class="foot-button foot-button--light" href="#dich-vu">@themeT('XD0323.footer.contact')</a></section>
    </div>
    <div class="foot-container foot-footer__bottom">&copy; {{ now()->year }} {{ $companyName }}. @themeT('XD0323.footer.rights')</div>
</footer>

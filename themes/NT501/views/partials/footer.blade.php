@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'NT501 Interior Studio'))) ?: 'NT501 Interior Studio';
    $hotline = trim((string) ($branding['support_hotline'] ?? '028 7307 3737')) ?: '028 7307 3737';
    $email = trim((string) ($branding['support_email'] ?? 'hello@nt501.vn')) ?: 'hello@nt501.vn';
    $address = trim((string) ($branding['support_location'] ?? '196 Nguyen Dinh Chieu, Quan 3, TP.HCM')) ?: '196 Nguyen Dinh Chieu, Quan 3, TP.HCM';
@endphp
<footer id="footer" class="foot-footer">
    <div class="foot-container foot-footer__top">
        <section class="foot-footer__newsletter">
            <p class="foot-footer__eyebrow">@themeT('nt501.footer.newsletter_label')</p>
            <form action="{{ route('site.newsletter.subscribe') }}" method="post">
                @csrf
                <label class="sr-only" for="foot-newsletter-email">@themeT('nt501.footer.email')</label>
                <input id="foot-newsletter-email" name="email" type="email" required placeholder="@themeT('nt501.footer.email_placeholder')">
                <button type="submit">@themeT('nt501.footer.subscribe')</button>
            </form>
        </section>
        <section class="foot-footer__brand">
            <span class="foot-brand__monogram">N</span>
            <strong>{{ $companyName }}</strong>
            <small>@themeT('nt501.brand.tagline')</small>
        </section>
        <section class="foot-footer__social">
            <p class="foot-footer__eyebrow">@themeT('nt501.footer.follow')</p>
            <div aria-label="@themeT('nt501.footer.social')"><a href="#footer">f</a><a href="#footer">i</a><a href="#footer">t</a><a href="#footer">y</a></div>
        </section>
    </div>
    <div class="foot-footer__divider"></div>
    <div class="foot-container foot-footer__grid">
        <section><h3>{{ $companyName }}</h3><p>{{ $address }}</p><p><a href="mailto:{{ $email }}">{{ $email }}</a></p><p><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a></p></section>
        <section><h3>@themeT('nt501.footer.services')</h3><ul><li><a href="#dich-vu">@themeT('nt501.footer.private_dining')</a></li><li><a href="#dich-vu">@themeT('nt501.footer.events')</a></li><li><a href="#du-an">@themeT('nt501.footer.menu')</a></li></ul></section>
        <section><h3>@themeT('nt501.footer.explore')</h3><ul><li><a href="#gioi-thieu">@themeT('nt501.nav.story')</a></li><li><a href="#tin-tuc">@themeT('nt501.nav.news')</a></li><li><a href="#doi-ngu">@themeT('nt501.nav.team')</a></li></ul></section>
        <section><h3>@themeT('nt501.footer.reservation')</h3><p>@themeT('nt501.footer.reservation_text')</p><a class="foot-button foot-button--light" href="#dich-vu">@themeT('nt501.footer.contact')</a></section>
    </div>
    <div class="foot-container foot-footer__bottom">&copy; {{ now()->year }} {{ $companyName }}. @themeT('nt501.footer.rights')</div>
</footer>

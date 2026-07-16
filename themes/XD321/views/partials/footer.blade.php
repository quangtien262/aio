@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'XD321 Cargo'))) ?: 'XD321 Cargo';
    $hotline = trim((string) ($branding['support_hotline'] ?? '028 7307 3737')) ?: '028 7307 3737';
    $email = trim((string) ($branding['support_email'] ?? 'hello@xd321.vn')) ?: 'hello@xd321.vn';
    $address = trim((string) ($branding['support_location'] ?? '196 Nguyen Dinh Chieu, Quan 3, TP.HCM')) ?: '196 Nguyen Dinh Chieu, Quan 3, TP.HCM';
@endphp
<footer id="footer" class="foot-footer">
    <div class="foot-container foot-footer__top">
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
            <span class="foot-brand__monogram">X</span>
            <strong>{{ $companyName }}</strong>
            <small>@themeT('xd321.brand.tagline')</small>
        </section>
        <section class="foot-footer__social">
            <p class="foot-footer__eyebrow">@themeT('xd321.footer.follow')</p>
            <div aria-label="@themeT('xd321.footer.social')"><a href="#footer">f</a><a href="#footer">i</a><a href="#footer">t</a><a href="#footer">y</a></div>
        </section>
    </div>
    <div class="foot-footer__divider"></div>
    <div class="foot-container foot-footer__grid">
        <section><h3>{{ $companyName }}</h3><p>{{ $address }}</p><p><a href="mailto:{{ $email }}">{{ $email }}</a></p><p><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a></p></section>
        <section><h3>@themeT('xd321.footer.services')</h3><ul><li><a href="#dich-vu">@themeT('xd321.footer.private_dining')</a></li><li><a href="#dich-vu">@themeT('xd321.footer.events')</a></li><li><a href="#san-pham">@themeT('xd321.footer.menu')</a></li></ul></section>
        <section><h3>@themeT('xd321.footer.explore')</h3><ul><li><a href="#gioi-thieu">@themeT('xd321.nav.story')</a></li><li><a href="#tin-tuc">@themeT('xd321.nav.news')</a></li><li><a href="#doi-ngu">@themeT('xd321.nav.team')</a></li></ul></section>
        <section><h3>@themeT('xd321.footer.reservation')</h3><p>@themeT('xd321.footer.reservation_text')</p><a class="foot-button foot-button--light" href="#dich-vu">@themeT('xd321.footer.contact')</a></section>
    </div>
    <div class="foot-container foot-footer__bottom">&copy; {{ now()->year }} {{ $companyName }}. @themeT('xd321.footer.rights')</div>
</footer>

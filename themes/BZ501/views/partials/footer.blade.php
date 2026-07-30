@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Halufin'))) ?: 'Halufin';
    $hotline = trim((string) ($branding['support_hotline'] ?? '19006750')) ?: '19006750';
    $address = trim((string) ($branding['support_location'] ?? 'Tòa Ladeco, 266 Đội Cấn - Ba Đình - Hà Nội')) ?: 'Tòa Ladeco, 266 Đội Cấn - Ba Đình - Hà Nội';
    $email = trim((string) ($branding['support_email'] ?? 'support@example.com')) ?: 'support@example.com';
@endphp

<section id="dang-ky-nhan-tin" class="bz501-newsletter">
    <div class="bz501-container bz501-newsletter__inner">
        <div>
            <h2>@themeT('BZ501.footer.newsletter_label')</h2>
            <p>@themeT('BZ501.footer.newsletter_text')</p>
        </div>
        <form action="{{ route('site.newsletter.subscribe') }}" method="post">
            @csrf
            <label class="sr-only" for="bz501-newsletter-email">@themeT('BZ501.footer.email')</label>
            <input id="bz501-newsletter-email" name="email" type="email" required placeholder="@themeT('BZ501.footer.email_placeholder')">
            <button type="submit">@themeT('BZ501.footer.subscribe')</button>
        </form>
    </div>
</section>

<footer id="footer" class="bz501-footer">
    <div class="bz501-container bz501-footer__grid">
        <section>
            <h3>@themeT('BZ501.footer.locations')</h3>
            <div class="bz501-footer__rule"></div>
            <p><i class="fa-solid fa-location-dot"></i><strong>{{ $companyName }} Đội Cấn</strong></p>
            <p>Địa chỉ: {{ $address }}</p>
            <p>Hotline: {{ $hotline }}</p>
            <p>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></p>
            <p><i class="fa-solid fa-location-dot"></i><strong>{{ $companyName }} Lữ Gia</strong></p>
            <p>Địa chỉ: 70 Lữ Gia - Quận 11 - TP.Hồ Chí Minh</p>
            <p>Hotline: {{ $hotline }}</p>
        </section>

        <section>
            <h3>@themeT('BZ501.footer.policy')</h3>
            <div class="bz501-footer__rule"></div>
            <ul>
                <li><a href="#footer">@themeT('BZ501.footer.policy_buy')</a></li>
                <li><a href="#footer">@themeT('BZ501.footer.policy_return')</a></li>
                <li><a href="#footer">@themeT('BZ501.footer.policy_ship')</a></li>
                <li><a href="#footer">@themeT('BZ501.footer.policy_privacy')</a></li>
                <li><a href="#footer">@themeT('BZ501.footer.commitment')</a></li>
            </ul>
        </section>

        <section>
            <h3>@themeT('BZ501.footer.connect')</h3>
            <div class="bz501-footer__rule"></div>
            <div class="bz501-footer__social">
                <a href="#footer" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                <a href="#footer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#footer" aria-label="Pinterest"><i class="fa-brands fa-pinterest-p"></i></a>
                <a href="#footer" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                <a href="#footer" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
            </div>

            <h3 class="bz501-footer__payment-title">@themeT('BZ501.footer.payment')</h3>
            <div class="bz501-footer__rule"></div>
            <div class="bz501-payment">
                <span>ZaloPay</span><span>JCB</span><span>AMEX</span><span>VISA</span><span>MC</span><span>VNPAY</span>
            </div>
        </section>
    </div>

    <div class="bz501-footer__bottom">
        <button type="button" class="bz501-float bz501-float--bell" aria-label="Notification"><i class="fa-solid fa-bell"></i></button>
        <p>&copy; @themeT('BZ501.footer.rights')</p>
        <a class="bz501-float bz501-float--top" href="#top"><i class="fa-solid fa-angles-up"></i><span>Top</span></a>
        <a class="bz501-float bz501-float--chat" href="#footer" aria-label="Chat"><i class="fa-brands fa-facebook-messenger"></i></a>
    </div>
</footer>

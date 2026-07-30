@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $supportEmail = data_get($branding, 'support_email', '');
    $hotline = data_get($branding, 'support_hotline', '');
    $supportAddress = data_get($branding, 'support_location', '');
    $locations = data_get($siteProfile ?? [], 'branding.locations', [
        ['name' => 'HALU', 'address' => $supportAddress, 'hotline' => $hotline],
        ['name' => 'HALU', 'address' => $supportAddress, 'hotline' => $hotline],
    ]);
@endphp

<section id="dang-ky" class="spa502-newsletter">
    <div class="spa502-container">
        <div class="spa502-title spa502-title--center">
            <span class="spa502-lotus"><i></i><b>✦</b><i></i></span>
            <h2>@themeT('SPA502.footer.newsletter_title')</h2>
            <p>@themeT('SPA502.footer.newsletter_text')</p>
        </div>
        <form method="POST" action="{{ route('site.newsletter.subscribe') }}" class="spa502-newsletter__form">
            @csrf
            <input type="email" name="email" required placeholder="@themeT('SPA502.footer.email_placeholder')" value="{{ old('email') }}">
            <input type="hidden" name="source" value="SPA502-footer">
            <button type="submit">@themeT('SPA502.footer.subscribe')</button>
        </form>
    </div>
</section>

<footer id="footer" class="spa502-footer">
    <div class="spa502-container spa502-footer__grid">
        <section>
            <h3>@themeT('SPA502.footer.locations')</h3>
            <span class="spa502-footer__rule"></span>
            <p>Địa chỉ: {{ $supportAddress }}</p>
            <p>Hotline: {{ $hotline }}</p>
            <p>Email: <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
            @foreach($locations as $location)
                <p><i class="fa-solid fa-location-dot"></i> <strong>{{ $location['name'] ?? 'HALU' }}</strong></p>
                <p>Địa chỉ: {{ $location['address'] ?? '' }}</p>
                <p>Hotline: {{ $location['hotline'] ?? $hotline }}</p>
            @endforeach
        </section>
        <section>
            <h3>@themeT('SPA502.footer.policy')</h3>
            <span class="spa502-footer__rule"></span>
            <ul>
                <li><a href="#">@themeT('SPA502.footer.policy_buy')</a></li>
                <li><a href="#">@themeT('SPA502.footer.policy_return')</a></li>
                <li><a href="#">@themeT('SPA502.footer.policy_shipping')</a></li>
                <li><a href="#">@themeT('SPA502.footer.policy_privacy')</a></li>
            </ul>
        </section>
        <section>
            <h3>@themeT('SPA502.footer.connect')</h3>
            <span class="spa502-footer__rule"></span>
            <div class="spa502-footer__social">
                <a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" aria-label="Pinterest"><i class="fa-brands fa-pinterest"></i></a>
                <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
            </div>
            <h3 class="spa502-footer__payment-title">@themeT('SPA502.footer.payment')</h3>
            <span class="spa502-footer__rule"></span>
            <div class="spa502-payment">
                <span>mPay</span><span>JCB</span><span>AMEX</span><span>VISA</span><span>Master</span><span>VNPay</span>
            </div>
        </section>
    </div>
    <div class="spa502-footer__bottom">
        <p>@themeT('SPA502.footer.rights')</p>
    </div>
</footer>

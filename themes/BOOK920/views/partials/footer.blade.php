@php
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $footerCategories = collect(data_get($themeShellData ?? [], 'footer_featured_categories', []))->filter(fn ($item) => filled(data_get($item, 'url')))->take(5);
@endphp
<footer class="book20-footer">
    <div class="book20-container book20-contact-strip">
        @if(data_get($branding, 'support_hotline'))<div><i class="fa-solid fa-headphones" aria-hidden="true"></i><span>@themeT('inner.phone', 'Số điện thoại')<strong><a href="tel:{{ preg_replace('/[^0-9+]/', '', $branding['support_hotline']) }}">{{ $branding['support_hotline'] }}</a></strong></span></div>@endif
        @if(data_get($branding, 'support_email'))<div><i class="fa-regular fa-envelope" aria-hidden="true"></i><span>Email<strong><a href="mailto:{{ $branding['support_email'] }}">{{ $branding['support_email'] }}</a></strong></span></div>@endif
        @if(data_get($branding, 'support_location'))<div><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>@themeT('inner.location', 'Địa chỉ')<strong>{{ $branding['support_location'] }}</strong></span></div>@endif
    </div>
    <div class="book20-container book20-footer-grid">
        <section><h2>@if(filled(data_get($branding, 'logo_url')))<img class="book20-footer-logo" src="{{ data_get($branding, 'logo_url') }}" alt="{{ data_get($siteProfile ?? [], 'site_name', 'Bookle') }}">@else{{ data_get($siteProfile ?? [], 'site_name', 'Bookle') }}@endif</h2><p>@themeT('inner.footer_about', 'Không gian sách gần gũi dành cho mọi độc giả. Khám phá những trang sách và tìm thêm cảm hứng mỗi ngày.')</p></section>
        <section><h3>@themeT('inner.explore', 'Khám phá tủ sách')</h3>@foreach($footerCategories as $item)<a href="{{ data_get($item, 'url') }}">{{ data_get($item, 'title', data_get($item, 'label', data_get($item, 'name'))) }}</a>@endforeach<a href="{{ route('site.catalog.search') }}">@themeT('inner.all_books', 'Xem tất cả sách') &rarr;</a></section>
        <section><h3>@themeT('inner.reader_support', 'Đồng hành cùng độc giả')</h3><a href="{{ route('site.services.index') }}">@themeT('inner.services', 'Dịch vụ')</a><a href="{{ route('site.blog.index') }}">@themeT('BOOK920.news', 'Tin tức')</a><a href="{{ route('site.contact') }}">@themeT('BOOK920.contact', 'Liên hệ')</a><a href="{{ route('site.cart.index') }}">@themeT('BOOK920.cart', 'Giỏ hàng')</a></section>
        <section><h3>@themeT('inner.newsletter', 'Đăng ký nhận tin')</h3><p>@themeT('inner.newsletter_text', 'Nhận những cập nhật mới từ nhà sách qua email.')</p><form method="post" action="{{ route('site.newsletter.subscribe') }}">@csrf<input type="hidden" name="source" value="book920-footer"><input type="email" name="email" required maxlength="255" placeholder="Email" aria-label="Email"><button type="submit" aria-label="@themeT('inner.subscribe', 'Đăng ký')"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button></form>@if(session('cart_success'))<p role="status">{{ session('cart_success') }}</p>@endif</section>
    </div>
</footer>

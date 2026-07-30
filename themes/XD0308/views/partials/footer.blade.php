<footer id="footer" class="xd4-footer">
    <div class="xd4-container xd4-footer__grid">
        <div><a class="xd4-brand" href="#top">@if(filled($logoUrl ?? null))<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif</a><p>{{ $companyDescription ?? 'Giải pháp vận tải và hậu cần linh hoạt cho doanh nghiệp.' }}</p><p>{{ $supportAddress ?? '' }}</p><p><a href="tel:{{ $phoneHref ?? '' }}">{{ $hotline ?? '' }}</a></p><p><a href="mailto:{{ $supportEmail ?? '' }}">{{ $supportEmail ?? '' }}</a></p></div>
        <div><h3>Dịch vụ</h3><ul>@foreach (($navItems ?? []) as $item)<li><a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Liên kết' }}</a></li>@endforeach</ul></div>
        <div><h3>Liên kết hữu ích</h3><ul><li><a href="#gioi-thieu">Giới thiệu</a></li><li><a href="#dich-vu">Dịch vụ</a></li><li><a href="#thu-vien">Thư viện</a></li><li><a href="#footer">Liên hệ</a></li></ul></div>
        <div><h3>Đăng ký bản tin</h3><p>Nhận thông tin mới nhất về dịch vụ và giải pháp vận tải của chúng tôi.</p><form class="xd4-newsletter"><input type="email" placeholder="Địa chỉ email" aria-label="Địa chỉ email"><button type="submit" aria-label="Đăng ký">→</button></form></div>
    </div>
    <div class="xd4-footer__bottom">© {{ now()->year }} {{ $companyName }}. All rights reserved.</div>
</footer>

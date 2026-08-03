<footer id="footer" class="xd4-footer">
    <div class="xd4-container xd4-footer__grid">
        <div><a class="xd4-brand" href="#top">@if(filled($logoUrl ?? null))<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif</a><p>{{ $companyDescription ?? 'Đồng hành chọn trường, hoàn thiện hồ sơ và chuẩn bị hành trang du học.' }}</p><p>{{ $supportAddress ?? '' }}</p><p><a href="tel:{{ $phoneHref ?? '' }}">{{ $hotline ?? '' }}</a></p><p><a href="mailto:{{ $supportEmail ?? '' }}">{{ $supportEmail ?? '' }}</a></p></div>
        <div><h3>Hành trình du học</h3><ul>@foreach (($navItems ?? []) as $item)<li><a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Liên kết' }}</a></li>@endforeach</ul></div>
        <div><h3>Thông tin hữu ích</h3><ul><li><a href="#gioi-thieu">Về Comgo</a></li><li><a href="#dich-vu">Dịch vụ tư vấn</a></li><li><a href="#quoc-gia">Quốc gia du học</a></li><li><a href="#footer">Đăng ký tư vấn</a></li></ul></div>
        <div><h3>Nhận cẩm nang du học</h3><p>Cập nhật học bổng, lịch tuyển sinh và kinh nghiệm chuẩn bị hồ sơ.</p><form class="xd4-newsletter"><input type="email" placeholder="Địa chỉ email" aria-label="Địa chỉ email"><button type="submit" aria-label="Đăng ký">→</button></form></div>
    </div>
    <div class="xd4-footer__bottom">© {{ now()->year }} {{ $companyName }}. All rights reserved.</div>
</footer>

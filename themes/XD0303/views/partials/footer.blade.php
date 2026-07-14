<footer id="footer" class="xd3-footer">
    <div class="xd3-container xd3-footer__grid">
        <div><a class="xd3-logo" href="#top">@if(filled($logoUrl ?? null))<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@else<span class="xd3-logo__mark">SYS</span><strong>{{ $companyName ?? 'Dịch vụ vận hành' }}</strong>@endif</a><p>{{ $companyDescription ?? 'Giải pháp dịch vụ linh hoạt, rõ ràng và đặt trải nghiệm khách hàng làm trọng tâm.' }}</p></div>
        <div><h3>Liên kết nhanh</h3><ul>@foreach (($navItems ?? []) as $item)<li><a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Menu' }}</a></li>@endforeach</ul></div>
        <div><h3>Thông tin liên hệ</h3><p>{{ $hotline ?? '1900 9477' }}</p><p>{{ $supportEmail ?? 'admin@example.vn' }}</p><p>{{ $supportAddress ?? '' }}</p></div>
    </div>
    <div class="xd3-footer__bottom">© Bản quyền nội dung thuộc về {{ $companyName ?? 'doanh nghiệp' }}.</div>
</footer>

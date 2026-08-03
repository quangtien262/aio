<footer id="footer" class="xd5-footer">
    <div class="xd5-container">
        <div class="xd5-footer-top">
            <a class="xd5-brand" href="#top">@if(filled($logoUrl ?? null))<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@else<span>{{ $companyName }}</span>@endif</a>
            <div><small>Tư vấn nhanh</small><b>{{ $hotline }}</b></div>
            <div><small>Gửi yêu cầu</small><b>{{ $supportEmail }}</b></div>
        </div>
        <div class="xd5-footer-grid">
            <div><p>{{ $supportAddress }}</p><p>{{ $companyDescription ?? 'Dịch vụ kế toán, thuế và tư vấn tài chính minh bạch cho doanh nghiệp.' }}</p></div>
            <div><h3>Khám phá</h3><ul>@foreach($navItems ?? [] as $item)<li><a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Liên kết' }}</a></li>@endforeach</ul></div>
            <div><h3>Nhận bản tin tài chính</h3><form><input type="email" placeholder="Địa chỉ email"><button>Đăng ký</button></form></div>
        </div>
    </div>
</footer>

<footer id="footer" class="xd5-footer">
    <div class="xd5-container">
        <div class="xd5-footer-top">
            <a class="xd5-brand" href="#top">@if(filled($logoUrl ?? null))<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@else<span>{{ $companyName }}</span>@endif</a>
            <div><small>Đặt câu hỏi</small><b>{{ $hotline }}</b></div>
            <div><small>Gửi email</small><b>{{ $supportEmail }}</b></div>
        </div>
        <div class="xd5-footer-grid">
            <div><p>{{ $supportAddress }}</p><p>{{ $companyDescription ?? 'Thiết kế, thi công và chăm sóc cảnh quan xanh bền vững.' }}</p></div>
            <div><h3>Khám phá</h3><ul>@foreach($navItems ?? [] as $item)<li><a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Liên kết' }}</a></li>@endforeach</ul></div>
            <div><h3>Nhận cẩm nang sân vườn</h3><form><input type="email" placeholder="Địa chỉ email"><button>Đăng ký</button></form></div>
        </div>
    </div>
</footer>

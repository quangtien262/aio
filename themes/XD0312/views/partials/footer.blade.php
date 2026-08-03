<footer id="footer" class="xd5-footer xd12-footer">
    <div class="xd5-container">
        <div class="xd12-footer-grid">
            <section>
                <a class="xd5-brand" href="#top">
                    @if(filled($logoUrl ?? null))
                        <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
                    @else
                        <span>{{ $companyName }}</span>
                    @endif
                </a>
                <p>{{ $companyDescription ?? 'Giải pháp logistics, kho bãi và vận chuyển linh hoạt cho doanh nghiệp.' }}</p>
            </section>
            <section>
                <h3>Thông tin liên hệ</h3>
                <ul class="xd12-contact-list">
                    <li>{{ $supportEmail }}</li>
                    <li>{{ $hotline }}</li>
                    <li>{{ $supportAddress }}</li>
                </ul>
            </section>
            <section>
                <h3>Dịch vụ</h3>
                <ul>
                    @foreach(collect($navItems ?? [])->take(5) as $item)
                        <li><a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Dịch vụ logistics' }}</a></li>
                    @endforeach
                </ul>
            </section>
            <section>
                <h3>Nhận bản tin</h3>
                <p>Nhận thông tin mới nhất về vận chuyển và kho bãi.</p>
                <form><input type="email" placeholder="Địa chỉ email"><button type="submit">Đăng ký</button></form>
            </section>
        </div>
    </div>
</footer>

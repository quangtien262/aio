@php
    $footerServices = collect($blocks ?? [])
        ->firstWhere('block_type', 'business_service_grid');
    $footerServiceItems = collect(data_get($footerServices, 'data.content.items', []))
        ->whenEmpty(fn () => collect(data_get($footerServices, 'dynamic_items', [])))
        ->take(5)
        ->values();
@endphp

<footer id="footer" class="fg18-footer">
    <div class="fg18-container fg18-footer__grid">
        <section>
            <a class="fg18-footer-brand" href="#top" aria-label="{{ $companyName }}">
                @if (filled($logoUrl ?? null))
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
                @else
                    <span class="fg18-brand__mark" aria-hidden="true"></span>
                    <span><strong>{{ $companyName }}</strong><small>LOGISTICS &amp; TRANSPORT</small></span>
                @endif
            </a>
            <p>{{ $companyDescription }}</p>
            <div class="fg18-socials">
                <a href="#" aria-label="Facebook">f</a>
                <a href="#" aria-label="Zalo">z</a>
                <a href="#" aria-label="Twitter">t</a>
                <a href="#" aria-label="Youtube">y</a>
            </div>
        </section>

        <section>
            <h3>Dịch vụ của chúng tôi</h3>
            <ul class="fg18-footer-list">
                @foreach ($footerServiceItems as $item)
                    <li><a href="{{ $item['url'] ?? $item['href'] ?? '#dich-vu' }}">{{ $item['title'] ?? $item['name'] ?? 'Dịch vụ' }}</a></li>
                @endforeach
            </ul>
        </section>

        <section>
            <h3>Thông tin liên hệ</h3>
            <p>Trong hơn 30 năm qua, Fast Gear là đối tác tin cậy trong lĩnh vực logistics.</p>
            <ul class="fg18-contact-list">
                <li><span>+</span>{{ $supportAddress }}</li>
                <li><span>☎</span><a href="tel:{{ $phoneHref }}">{{ $hotline }}</a></li>
                <li><span>@</span><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>
            </ul>
        </section>
    </div>
</footer>

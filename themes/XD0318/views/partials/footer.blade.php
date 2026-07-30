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
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif
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
            <h3>Dich vu cua chung toi</h3>
            <ul class="fg18-footer-list">
                @foreach ($footerServiceItems as $item)
                    <li><a href="{{ $item['url'] ?? $item['href'] ?? '#dich-vu' }}">{{ $item['title'] ?? $item['name'] ?? 'Dich vu' }}</a></li>
                @endforeach
            </ul>
        </section>

        <section>
            <h3>Thong tin lien he</h3>
            <p>Trong hon 30 nam qua, Fast Gear la doi tac tin cay trong linh vuc logistics.</p>
            <ul class="fg18-contact-list">
                <li><span>+</span>{{ $supportAddress }}</li>
                <li><span>c</span><a href="tel:{{ $phoneHref }}">{{ $hotline }}</a></li>
                <li><span>@</span><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>
            </ul>
        </section>
    </div>
</footer>

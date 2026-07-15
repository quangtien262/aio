@php
    $footerGallery = collect($blocks ?? [])->flatMap(fn ($block) => collect(data_get($block, 'dynamic_items', []))->merge(data_get($block, 'data.content.items', [])))
        ->filter(fn ($item) => is_array($item) && filled($item['image'] ?? $item['image_url'] ?? null))
        ->take(9)
        ->values();
@endphp

<footer id="footer" class="af15-footer">
    <div class="af15-container af15-footer__grid">
        <section>
            <a class="af15-footer-brand" href="#top">
                @if (filled($logoUrl ?? null))
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
                @else
                    <span class="af15-logo-mark"><strong>ATHLETIC</strong><i>✊</i><em>FITNESS CENTER</em></span>
                @endif
            </a>
            <p>{{ $companyDescription }}</p>
            <div class="af15-socials"><a>f</a><a>z</a><a>t</a><a>▶</a></div>
            <p class="af15-contact-line">☎ {{ $hotline }}</p>
            <p class="af15-contact-line">✉ {{ $supportEmail }}</p>
        </section>

        <section>
            <h3>Hinh anh</h3>
            <div class="af15-footer-gallery">
                @foreach ($footerGallery as $item)
                    @php $image = $item['image'] ?? $item['image_url'] ?? ''; @endphp
                    <img src="{{ $image }}" alt="{{ $item['alt'] ?? $item['title'] ?? $item['name'] ?? 'Gallery' }}">
                @endforeach
            </div>
        </section>

        <section>
            <div class="af15-map">
                <span>Open in Maps ↗</span>
            </div>
            <p class="af15-address">📍 {{ $supportAddress }}</p>
        </section>

        <section>
            <h3>Gio lam viec</h3>
            <p><strong>Thu 2 - Thu 6</strong><br>06:00 - 22:00</p>
            <p><strong>Thu 7 - Chu nhat</strong><br>06:00 - 21:00</p>
            <a class="af15-outline" href="#top">Xem them ↪</a>
        </section>
    </div>
</footer>

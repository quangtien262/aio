@php
    $footerGallery = collect($blocks ?? [])->flatMap(fn ($block) => collect(data_get($block, 'dynamic_items', []))->merge(data_get($block, 'data.content.items', [])))
        ->filter(fn ($item) => is_array($item) && filled($item['image'] ?? $item['image_url'] ?? null))
        ->take(9)
        ->values();
    $hotItems = collect(($blocks ?? collect())->firstWhere('block_type', 'featured_services')['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect(data_get(($blocks ?? collect())->firstWhere('block_type', 'featured_services'), 'data.content.items', [])))
        ->take(5)
        ->values();
@endphp

<footer id="footer" class="bb14-footer">
    <div class="bb14-container bb14-footer__top">
        <section>
            <h3>Giới thiệu công ty</h3>
            <a class="bb14-footer-brand" href="#top">
                @if (filled($logoUrl ?? null))
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif
            </a>
            <p>{{ $companyDescription }}</p>
        </section>

        <section>
            <h3>Thư viện hình ảnh</h3>
            <div class="bb14-footer-gallery">
                @foreach ($footerGallery as $item)
                    @php $image = $item['image'] ?? $item['image_url'] ?? ''; @endphp
                    <img src="{{ $image }}" alt="{{ $item['alt'] ?? $item['title'] ?? $item['name'] ?? 'Gallery' }}">
                @endforeach
            </div>
        </section>

        <section>
            <h3>Dịch vụ nổi bật</h3>
            <ul class="bb14-footer-list">
                @foreach ($hotItems as $item)
                    <li><a href="{{ $item['url'] ?? $item['href'] ?? '#dich-vu' }}">{{ \Illuminate\Support\Str::limit($item['title'] ?? $item['name'] ?? 'Dịch vụ', 42) }}</a></li>
                @endforeach
            </ul>
        </section>

        <section>
            <h3>Nhận tin khuyến mãi</h3>
            <form class="bb14-newsletter" method="post" action="{{ route('site.newsletter.subscribe') }}">
                @csrf
                <input type="email" name="email" placeholder="Địa chỉ email..." required>
                <button type="submit" aria-label="Đăng ký">✈</button>
            </form>
            <h3 class="bb14-share-title">Chia sẻ mạng xã hội</h3>
            <div class="bb14-socials">
                <a href="#footer">f</a><a href="#footer">z</a><a href="#footer">t</a><a href="#footer">▶</a><a href="#footer">p</a>
            </div>
        </section>
    </div>

    <div class="bb14-container bb14-contact-cards">
        <article><span>▰</span><strong>Địa chỉ</strong><p>{{ $supportAddress }}</p></article>
        <article><span>☎</span><strong>Phone</strong><p>{{ $hotline }}</p></article>
        <article><span>▣</span><strong>Fax</strong><p>{{ $hotline }}</p></article>
        <article><span>✉</span><strong>Email</strong><p>{{ $supportEmail }}</p></article>
    </div>

    <div class="bb14-copyright">© Bản quyền nội dung thuộc về {{ $companyName }}</div>
</footer>

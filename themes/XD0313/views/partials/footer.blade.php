@php
    $visaBlock = collect($blocks ?? [])->firstWhere('block_type', 'featured_services');
    $visaItems = collect(data_get($visaBlock, 'data.content.items', []))
        ->whenEmpty(fn () => collect(data_get($visaBlock, 'dynamic_items', [])))
        ->take(4)
        ->values();
    $blogBlock = collect($blocks ?? [])->firstWhere('block_type', 'bizmax_latest_posts');
    $videoItems = collect(data_get($blogBlock, 'data.content.items', []))
        ->whenEmpty(fn () => collect(data_get($blogBlock, 'dynamic_items', [])))
        ->take(4)
        ->values();
@endphp

<footer id="footer" class="rx13-footer">
    <div class="rx13-footer__map"></div>
    <div class="rx13-container rx13-footer__grid">
        <section>
            <a class="rx13-footer-brand" href="#top" aria-label="{{ $companyName }}">
                @if (filled($logoUrl ?? null))
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
                @else
                    <span class="rx13-brand__mark"></span>
                    <strong>RouteX</strong>
                @endif
            </a>
            <p>{{ $companyDescription }}</p>
            <p>{{ $supportAddress }} · <a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a> · <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
            <div class="rx13-socials">
                <a href="#" aria-label="Facebook">f</a>
                <a href="#" aria-label="Twitter">t</a>
                <a href="#" aria-label="Youtube">y</a>
                <a href="#" aria-label="Pinterest">p</a>
            </div>
        </section>
        <section>
            <h3>Danh Muc Visa</h3>
            <ul class="rx13-footer-list">
                @foreach ($visaItems as $item)
                    <li><a href="{{ $item['url'] ?? $item['href'] ?? '#dich-vu' }}">{{ $item['title'] ?? $item['name'] ?? 'Visa' }}</a></li>
                @endforeach
            </ul>
        </section>
        <section>
            <h3>Video Noi Bat</h3>
            <div class="rx13-footer-videos">
                @foreach ($videoItems as $item)
                    @php $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? ''; @endphp
                    <a href="{{ $item['url'] ?? $item['href'] ?? '#blog' }}">
                        @if (filled($image))
                            <img src="{{ $image }}" alt="{{ $item['alt'] ?? $item['title'] ?? 'Video' }}">
                        @endif
                        <span>></span>
                    </a>
                @endforeach
            </div>
        </section>
        <section>
            <h3>Dang Ky Nhan Tin</h3>
            <p>Dang ky nhan ban tin hang tuan de nhan thong tin cap nhat moi nhat.</p>
            <form class="rx13-newsletter" method="POST" action="{{ route('site.contact.submit') }}">
                @csrf
                <input type="hidden" name="source" value="XD0313-newsletter">
                <input type="email" name="email" placeholder="Dia chi email....." required>
                <button type="submit">Dang ky</button>
            </form>
        </section>
    </div>
</footer>

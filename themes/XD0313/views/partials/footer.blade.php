@php
    $visaBlock = collect($blocks ?? [])->firstWhere('block_type', 'featured_services');
    $visaItems = collect(data_get($visaBlock, 'data.content.items', []))
        ->whenEmpty(fn () => collect(data_get($visaBlock, 'dynamic_items', [])))
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
                    <span class="rx13-brand__mark" aria-hidden="true"></span>
                    <strong>{{ $companyName }}</strong>
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
            <h3>Danh mục Visa</h3>
            <ul class="rx13-footer-list">
                @foreach ($visaItems as $item)
                    <li><a href="{{ $item['url'] ?? $item['href'] ?? '#dich-vu' }}">{{ $item['title'] ?? $item['name'] ?? 'Visa' }}</a></li>
                @endforeach
            </ul>
        </section>

        <section>
            <h3>Đăng ký nhận tin</h3>
            <p>Đăng ký nhận bản tin hằng tuần để cập nhật các thông tin visa mới nhất.</p>
            <form class="rx13-newsletter" method="POST" action="{{ route('site.newsletter.subscribe', ['locale' => app()->getLocale()]) }}">
                @csrf
                <input type="hidden" name="source" value="XD0313-newsletter">
                <input type="email" name="email" placeholder="Địa chỉ email" required maxlength="255">
                <button type="submit">Đăng ký</button>
            </form>
        </section>
    </div>
</footer>

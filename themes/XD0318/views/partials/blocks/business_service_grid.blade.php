@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 5))
        ->values();
    $quoteBg = $media['quote_background'] ?? $content['quote_background'] ?? 'https://images.unsplash.com/photo-1494412685616-a5d310fbb07d?auto=format&fit=crop&w=1000&q=85';
@endphp

<section id="{{ $anchor }}" class="fg18-section fg18-services xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="fg18-container">
        <header class="fg18-center">
            <p class="fg18-kicker">{{ $data['subtitle'] ?? 'Dịch vụ của chúng tôi' }}</p>
            <h2 class="fg18-title">{{ $data['title'] ?? 'Giải pháp thực tế, nhanh chóng và hiệu quả' }}</h2>
        </header>
        <div class="fg18-services__grid">
            @foreach ($items as $item)
                @php
                    $title = $item['title'] ?? $item['name'] ?? '';
                    $summary = $item['summary'] ?? $item['description'] ?? $item['excerpt'] ?? '';
                    $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
                @endphp
                <article class="fg18-service-card">
                    @if (filled($image))
                        <img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}">
                    @endif
                    <h3>{{ $title }}</h3>
                    @if (filled($summary))
                        <p>{{ \Illuminate\Support\Str::limit(strip_tags($summary), 135) }}</p>
                    @endif
                    <a class="fg18-more" href="{{ $item['url'] ?? $item['href'] ?? '#dich-vu' }}">+ Xem thêm</a>
                </article>
            @endforeach
            <aside class="fg18-quote-card" style="--fg18-bg: url('{{ $quoteBg }}')">
                <h3>Nhận báo giá cho bất kỳ dịch vụ nào ngay từ đây.</h3>
                <form method="POST" action="{{ route('site.contact.submit') }}">
                    @csrf
                    <input type="hidden" name="source" value="XD0318-service-quote">
                    <input type="email" name="email" placeholder="Địa chỉ email" required>
                    <button class="fg18-button" type="submit">Nhận báo giá</button>
                </form>
            </aside>
        </div>
    </div>
</section>

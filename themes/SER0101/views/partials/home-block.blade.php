@php
    $homeBlockTitle = $data['title'] ?? '';
    $homeBlockSummary = $data['description'] ?? '';
@endphp
<div class="aio-landing-block-inner ser-home-block">
    @if($type === 'hero_slider')
        <div class="ser-home-hero-copy">
            <span class="aio-landing-kicker">{{ data_get($heroItem, 'kicker', $data['subtitle'] ?? '') }}</span>
            <h1 class="aio-landing-title">{{ data_get($heroItem, 'title', $homeBlockTitle) }}</h1>
            @if(filled(data_get($heroItem, 'summary', $homeBlockSummary)))<p class="aio-landing-summary">{{ data_get($heroItem, 'summary', $homeBlockSummary) }}</p>@endif
            <div class="ser-home-actions">
                <button class="aio-landing-action" type="button" data-open-quote-modal>{{ $t('home.redesign.quote', 'Nhận tư vấn & báo giá') }} <span aria-hidden="true">↗</span></button>
                <a class="ser-home-secondary" href="#dich-vu">{{ $t('home.redesign.services', 'Khám phá dịch vụ') }} <span aria-hidden="true">→</span></a>
            </div>
        </div>
    @else
        <header class="aio-landing-heading">
            @if(filled($data['subtitle'] ?? null))<span class="aio-landing-kicker">{{ $data['subtitle'] }}</span>@endif
            <h2 class="aio-landing-title">{{ $homeBlockTitle }}</h2>
            @if(filled($homeBlockSummary))<p class="aio-landing-summary">{{ $homeBlockSummary }}</p>@endif
        </header>
        @if($type === 'landing_contact')
            <div class="ser-home-contact-card">
                <div><h3>{{ data_get($data, 'content.form_title', $t('home.redesign.contact_title', 'Trao đổi nhu cầu của bạn')) }}</h3><p>{{ $t('home.redesign.contact_note', 'Cho chúng tôi biết lịch trình và yêu cầu của bạn để nhận phương án phù hợp.') }}</p></div>
                <button type="button" class="aio-landing-action" data-open-quote-modal>{{ $data['button_label'] ?? $t('home.redesign.quote', 'Nhận tư vấn & báo giá') }} <span aria-hidden="true">↗</span></button>
            </div>
        @elseif($blockItems->isNotEmpty())
            <div class="aio-landing-grid {{ $type === 'featured_categories' ? 'aio-landing-categories' : '' }} {{ $type === 'process_steps' ? 'aio-landing-steps' : '' }}">
                @foreach($blockItems as $item)
                    @php
                        $title = data_get($item, 'title', data_get($item, 'name', ''));
                        $image = data_get($item, 'image', data_get($item, 'image_url', data_get($item, 'thumbnail')));
                        $url = data_get($item, 'url', data_get($item, 'link_url', ''));
                        $hasLink = filled($url) && !str_ends_with($url, '#');
                        $summary = data_get($item, 'summary', data_get($item, 'description', data_get($item, 'quote')));
                        $features = is_array(data_get($item, 'features')) ? data_get($item, 'features') : array_filter(explode('|', (string) data_get($item, 'features', '')));
                    @endphp
                    <article @class(['aio-landing-card', 'aio-landing-step' => $type === 'process_steps', 'ser-home-card-featured' => (bool) data_get($item, 'featured', false)])>
                        @if(filled($image))
                            @if($hasLink)<a class="aio-landing-card-media" href="{{ $url }}" aria-label="{{ $title }}">@else<div class="aio-landing-card-media">@endif
                                <img src="{{ $image }}" alt="{{ data_get($item, 'alt', $title) }}" loading="lazy">
                            @if($hasLink)</a>@else</div>@endif
                        @endif
                        <div class="aio-landing-card-body">
                            <h3>@if($hasLink)<a href="{{ $url }}">{{ $title }}</a>@else{{ $title }}@endif</h3>
                            @if(filled($summary))<p>{{ strip_tags($summary) }}</p>@endif
                            @if(array_key_exists('price', $item))<span class="aio-landing-price">{{ $landingCurrency($item['price']) }}</span>@endif
                            @if($type === 'service_pricing' && $features !== [])<ul class="ser-home-features">@foreach($features as $feature)<li>{{ $feature }}</li>@endforeach</ul>@endif
                            @if($type === 'service_pricing')
                                <button type="button" class="ser-home-card-link" data-open-quote-modal>{{ $t('home.redesign.quote', 'Nhận tư vấn & báo giá') }} <span aria-hidden="true">↗</span></button>
                            @elseif($hasLink && $type !== 'process_steps')
                                <a class="ser-home-card-link" href="{{ $url }}">{{ $t('home.redesign.details', 'Xem chi tiết') }} <span aria-hidden="true">→</span></a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endif
</div>

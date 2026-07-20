@php
    $aboutUrl = $localizeMenuUrl($settings['cta_url'] ?? '/gioi-thieu');
    $secondaryImage = ($media['image_secondary'] ?? data_get($content, 'image_secondary')) ?: 'https://images.unsplash.com/photo-1551830820-330a71b99659?auto=format&fit=crop&w=900&q=85';
    $aboutTabs = collect(data_get($content, 'tabs', []))
        ->map(function ($tab) use ($data) {
            if (is_array($tab)) {
                return [
                    'label' => trim((string) ($tab['label'] ?? $tab['title'] ?? '')),
                    'description' => trim((string) ($tab['description'] ?? $tab['content'] ?? '')),
                ];
            }

            return [
                'label' => trim((string) $tab),
                'description' => trim((string) ($data['description'] ?? '')),
            ];
        })
        ->filter(fn ($tab) => $tab['label'] !== '')
        ->values();
    $aboutBlockKey = $block['id'] ?? $anchor;
@endphp
<section id="{{ $anchor }}" class="xd2-section xd2-about xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="about_experience">
    {!! $editButton !!}
    <div class="xd2-container xd2-about__grid">
        <div class="xd2-about__media">
            <img src="{{ $media['image'] ?? 'https://images.unsplash.com/photo-1486406146926-c627a92ad742?auto=format&fit=crop&w=800&q=85' }}" alt="{{ $data['title'] ?? 'Giới thiệu' }}">
            <img src="{{ $secondaryImage }}" alt="">
            <div><strong>{{ $settings['years'] ?? 29 }}+</strong><span>Năm kinh nghiệm</span></div>
        </div>
        <div>
            <p class="xd2-kicker">{{ $data['subtitle'] ?? '' }}</p>
            <h2>{{ $data['title'] ?? '' }}</h2>
            @if ($aboutTabs->isNotEmpty())
                <div class="xd2-tabs" role="tablist" aria-label="{{ $data['subtitle'] ?? 'Giới thiệu' }}" data-xd2-tabs>
                    @foreach ($aboutTabs as $tab)
                        <button type="button" class="{{ $loop->first ? 'is-active' : '' }}" id="xd2-about-tab-{{ $aboutBlockKey }}-{{ $loop->index }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="xd2-about-panel-{{ $aboutBlockKey }}-{{ $loop->index }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-xd2-tab="{{ $loop->index }}">{{ $tab['label'] }}</button>
                    @endforeach
                </div>
                @foreach ($aboutTabs as $tab)
                    <div class="xd2-about__tab-panel" id="xd2-about-panel-{{ $aboutBlockKey }}-{{ $loop->index }}" role="tabpanel" aria-labelledby="xd2-about-tab-{{ $aboutBlockKey }}-{{ $loop->index }}" data-xd2-tab-panel="{{ $loop->index }}" @if (! $loop->first) hidden @endif>
                        <p>{{ $tab['description'] !== '' ? $tab['description'] : ($data['description'] ?? '') }}</p>
                    </div>
                @endforeach
            @else
                <p>{{ $data['description'] ?? '' }}</p>
            @endif
            <a class="xd2-button" href="{{ $aboutUrl }}">{{ $data['button_label'] ?? 'Về chúng tôi' }}</a>
        </div>
    </div>
</section>

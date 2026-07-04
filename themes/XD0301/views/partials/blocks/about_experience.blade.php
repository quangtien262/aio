                        @php $aboutCtaUrl = $localizeMenuUrl($settings['cta_url'] ?? '/gioi-thieu'); @endphp
                        <section id="{{ $anchor }}" class="xd-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container xd-intro"><div class="xd-intro-copy"><h2>{!! nl2br(e($data['title'] ?? '')) !!}</h2><p>{!! nl2br(e($data['description'] ?? '')) !!}</p><a class="xd-button" href="{{ $aboutCtaUrl }}">{{ $data['button_label'] ?? 'Tìm hiểu thêm' }}</a></div><div class="xd-intro-visual"><div class="xd-years"><strong>{{ $settings['years'] ?? 10 }}</strong><span>Năm kinh nghiệm</span></div><img src="{{ $media['image'] ?? '' }}" alt="{{ $data['title'] ?? 'Giới thiệu' }}"></div></div>
                        </section>

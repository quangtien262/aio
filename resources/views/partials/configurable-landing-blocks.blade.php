@php
    $configurableBlocks = collect($landingBlocks ?? [])->values();
    $landingThemeKey = strtoupper((string) data_get($activeTheme ?? [], 'key', data_get($landingPage ?? [], 'theme_key', '')));
    $landingCanEdit = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $landingCurrency = static fn ($value): string => is_numeric($value) ? number_format((float) $value, 0, ',', '.').'đ' : (filled($value) ? (string) $value : 'Liên hệ');
    $landingItems = static function (array $block): \Illuminate\Support\Collection {
        $dynamic = collect($block['dynamic_items'] ?? []);
        if ($dynamic->isNotEmpty()) return $dynamic->values();

        foreach (['items', 'slides', 'steps', 'services', 'products', 'posts', 'plans'] as $key) {
            $items = collect(data_get($block, 'data.content.'.$key, []));
            if ($items->isNotEmpty()) return $items->values();
        }

        return collect();
    };
@endphp

@once
    <style>
        .aio-landing-stack { --aio-accent: var(--th-red, var(--ser-primary, var(--th-landing-accent, #0f766e))); --aio-deep: var(--th-red-deep, var(--ser-primary-deep, var(--th-landing-accent-deep, #0f172a))); display:grid; gap:28px; padding:24px 0 10px; }
        .aio-landing-page-intro { padding:18px 4px 0; }
        .aio-landing-page-intro h1 { margin:0; color:var(--aio-deep); font-size:clamp(30px,4vw,52px); line-height:1.1; }
        .aio-landing-page-intro p { max-width:760px; margin:12px 0 0; color:#64748b; line-height:1.75; }
        .aio-landing-block { position:relative; overflow:hidden; border:1px solid rgba(15,23,42,.1); border-radius:24px; background:var(--th-surface, #fff); box-shadow:0 18px 48px rgba(15,23,42,.08); scroll-margin-top:24px; }
        .aio-landing-block-inner { padding:clamp(22px,4vw,48px); }
        .aio-landing-heading { max-width:760px; margin-bottom:24px; }
        .aio-landing-kicker { display:inline-flex; margin-bottom:10px; color:var(--aio-accent); font-size:12px; font-weight:900; letter-spacing:.12em; text-transform:uppercase; }
        .aio-landing-title { margin:0; color:var(--aio-deep); font-size:clamp(28px,4vw,48px); line-height:1.08; }
        .aio-landing-summary { margin:12px 0 0; color:#64748b; line-height:1.75; }
        .aio-landing-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; }
        .aio-landing-card { overflow:hidden; border:1px solid rgba(15,23,42,.09); border-radius:18px; background:#fff; }
        .aio-landing-card-media { display:block; aspect-ratio:4/3; overflow:hidden; background:#eef2f7; }
        .aio-landing-card-media img { width:100%; height:100%; object-fit:cover; transition:transform .25s ease; }
        .aio-landing-card:hover img { transform:scale(1.04); }
        .aio-landing-card-body { padding:16px; }
        .aio-landing-card h3 { margin:0; color:#172033; font-size:18px; line-height:1.35; }
        .aio-landing-card p { margin:8px 0 0; color:#64748b; line-height:1.6; }
        .aio-landing-price { display:block; margin-top:12px; color:var(--aio-accent); font-size:20px; font-weight:900; }
        .aio-landing-hero { min-height:520px; display:grid; align-items:end; color:#fff; background:#111827 center/cover no-repeat; }
        .aio-landing-hero::before { content:''; position:absolute; inset:0; background:linear-gradient(90deg,rgba(5,12,24,.88),rgba(5,12,24,.28)); }
        .aio-landing-hero .aio-landing-block-inner { position:relative; z-index:1; max-width:760px; }
        .aio-landing-hero .aio-landing-title { color:#fff; font-size:clamp(38px,6vw,76px); }
        .aio-landing-hero .aio-landing-summary { color:rgba(255,255,255,.82); font-size:17px; }
        .aio-landing-action { display:inline-flex; margin-top:22px; padding:12px 20px; border-radius:999px; background:var(--aio-accent); color:#fff; font-weight:900; }
        .aio-landing-categories .aio-landing-card-media { aspect-ratio:16/10; }
        .aio-landing-steps { counter-reset:aio-step; }
        .aio-landing-step { padding:22px; }
        .aio-landing-step::before { counter-increment:aio-step; content:counter(aio-step, decimal-leading-zero); display:block; margin-bottom:18px; color:var(--aio-accent); font-size:32px; font-weight:900; }
        .aio-landing-contact { display:grid; grid-template-columns:1.1fr .9fr; gap:24px; align-items:center; background:linear-gradient(135deg,var(--aio-deep),#20334d); color:#fff; }
        .aio-landing-contact .aio-landing-title { color:#fff; }
        .aio-landing-contact .aio-landing-summary { color:rgba(255,255,255,.78); }
        .aio-landing-contact-panel { padding:22px; border-radius:18px; background:rgba(255,255,255,.1); }
        .aio-landing-edit { position:absolute; top:12px; right:12px; z-index:10; padding:9px 13px; border-radius:999px; background:#111827; color:#fff; font-size:12px; font-weight:900; }
        @media(max-width:960px){.aio-landing-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.aio-landing-contact{grid-template-columns:1fr}}
        @media(max-width:620px){.aio-landing-grid{grid-template-columns:1fr}.aio-landing-hero{min-height:440px}.aio-landing-block{border-radius:16px}}
    </style>
@endonce

<div class="aio-landing-stack" data-configurable-landing-theme="{{ $landingThemeKey }}">
    @if (! (bool) data_get($landingPage ?? [], 'is_home', false))
        <header class="aio-landing-page-intro">
            <span class="aio-landing-kicker">{{ $landingThemeKey }}</span>
            <h1>{{ data_get($landingPage ?? [], 'title', 'Landing page') }}</h1>
            @if (filled(data_get($landingPage ?? [], 'excerpt')))
                <p>{{ data_get($landingPage, 'excerpt') }}</p>
            @endif
        </header>
    @endif

    @foreach ($configurableBlocks as $block)
        @php
            $type = (string) ($block['block_type'] ?? '');
            $data = (array) ($block['data'] ?? []);
            $items = $landingItems($block);
            $anchor = $block['anchor_id'] ?: $type;
            $heroItem = $items->first() ?? [];
            $heroImage = data_get($heroItem, 'image', data_get($block, 'media.image', ''));
        @endphp
        <section id="{{ $anchor }}" class="aio-landing-block {{ $type === 'hero_slider' ? 'aio-landing-hero' : '' }}" data-landing-block-id="{{ $block['id'] ?? '' }}" data-block-type="{{ $type }}" @if($type === 'hero_slider' && filled($heroImage)) style="background-image:url('{{ $heroImage }}')" @endif>
            @if ($landingCanEdit && filled($block['id'] ?? null))
                <a class="aio-landing-edit" href="{{ route('admin.index', ['any' => 'cms/landing-pages']) }}">Cấu hình khối</a>
            @endif

            @if ($type === 'hero_slider')
                <div class="aio-landing-block-inner">
                    <span class="aio-landing-kicker">{{ data_get($heroItem, 'kicker', $data['subtitle'] ?? $landingThemeKey) }}</span>
                    <h1 class="aio-landing-title">{{ data_get($heroItem, 'title', $data['title'] ?? 'Landing page') }}</h1>
                    <p class="aio-landing-summary">{{ data_get($heroItem, 'summary', $data['description'] ?? '') }}</p>
                    <a class="aio-landing-action" href="{{ data_get($heroItem, 'link_url', data_get($heroItem, 'url', '#'.($configurableBlocks->get(1)['anchor_id'] ?? 'noi-dung'))) }}">{{ data_get($heroItem, 'button_label', $data['button_label'] ?? 'Khám phá ngay') }}</a>
                </div>
            @elseif ($type === 'landing_contact')
                <div class="aio-landing-block-inner aio-landing-contact">
                    <div><span class="aio-landing-kicker">{{ $data['subtitle'] ?? 'Liên hệ' }}</span><h2 class="aio-landing-title">{{ $data['title'] ?? 'Nhận tư vấn' }}</h2><p class="aio-landing-summary">{{ $data['description'] ?? '' }}</p></div>
                    <div class="aio-landing-contact-panel"><strong>{{ data_get($data, 'content.form_title', 'Gửi yêu cầu tư vấn') }}</strong><p>{{ data_get($data, 'content.note_text', data_get($data, 'content.note_title', 'Đội ngũ sẽ liên hệ trong thời gian sớm nhất.')) }}</p><a class="aio-landing-action" href="{{ route('site.contact') }}">{{ $data['button_label'] ?? 'Liên hệ ngay' }}</a></div>
                </div>
            @else
                <div class="aio-landing-block-inner">
                    <div class="aio-landing-heading"><span class="aio-landing-kicker">{{ $data['subtitle'] ?? \Illuminate\Support\Str::headline($type) }}</span><h2 class="aio-landing-title">{{ $data['title'] ?? \Illuminate\Support\Str::headline($type) }}</h2>@if(filled($data['description'] ?? null))<p class="aio-landing-summary">{{ $data['description'] }}</p>@endif</div>
                    <div class="aio-landing-grid {{ $type === 'featured_categories' ? 'aio-landing-categories' : '' }} {{ $type === 'process_steps' ? 'aio-landing-steps' : '' }}">
                        @foreach ($items as $item)
                            @php
                                $title = data_get($item, 'title', data_get($item, 'name', 'Nội dung'));
                                $image = data_get($item, 'image', data_get($item, 'image_url', data_get($item, 'thumbnail')));
                                $url = data_get($item, 'url', data_get($item, 'link_url', '#'));
                                $summary = data_get($item, 'summary', data_get($item, 'description', data_get($item, 'quote')));
                            @endphp
                            <article class="aio-landing-card {{ $type === 'process_steps' ? 'aio-landing-step' : '' }}">
                                @if (filled($image))<a class="aio-landing-card-media" href="{{ $url }}"><img src="{{ $image }}" alt="{{ data_get($item, 'alt', $title) }}"></a>@endif
                                <div class="aio-landing-card-body"><h3><a href="{{ $url }}">{{ $title }}</a></h3>@if(filled($summary))<p>{{ $summary }}</p>@endif @if(array_key_exists('price', $item))<span class="aio-landing-price">{{ $landingCurrency($item['price']) }}</span>@endif</div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endforeach
</div>

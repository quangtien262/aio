@php
    $blocks = collect($landingBlocks ?? [])->filter(fn($block) => (bool) data_get($block, 'is_visible', true))->values();
    $block = fn(string $type): array => (array) ($blocks->first(fn($item) => data_get($item, 'block_type') === $type) ?? []);
    $items = function(array $block) {
        $dynamic = collect(data_get($block, 'dynamic_items', []))->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $block('news88_hero_posts'); $heroItems = $items($hero);
    $latest = $block('news88_latest_video'); $latestItems = $items($latest);
    $health = $block('news88_health_posts'); $healthItems = $items($health);
    $cars = $block('news88_car_posts'); $carItems = $items($cars);
    $travel = $block('news88_travel_posts'); $travelItems = $items($travel);
    $entertainment = $block('news88_entertainment_posts'); $entertainmentItems = $items($entertainment);
    $date = fn($item) => data_get($item, 'date') ?: (data_get($item, 'published_at') ? \Illuminate\Support\Carbon::parse(data_get($item, 'published_at'))->format('d/m/Y') : now()->format('d/m/Y'));
    $views = fn($item) => 80 + (((int) data_get($item, 'id', 1) * 37) % 151);
@endphp
@extends('theme-news88::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'NEWS88')))
@section('content')
<main class="n88-main">
    <div class="n88-container">
        <section class="n88-hotbar" data-n88-reveal>
            <strong><i class="fa-solid fa-fire-flame-curved"></i> @themeT('NEWS88.hot_news', 'Tin nóng')</strong>
            @foreach($heroItems->take(2) as $item)<a href="{{ data_get($item, 'url', '#') }}"><span>{{ mb_strtoupper(mb_substr(data_get($item, 'category', 'N'), 0, 1)) }}</span>{{ data_get($item, 'title') }}</a>@endforeach
        </section>

        <section class="n88-hero xd-landing-block" id="tin-noi-bat" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="news88_hero_posts">
            @include('theme-news88::partials.edit-button', ['block' => $hero])
            @foreach($heroItems->take(5) as $index => $item)
                <article class="n88-hero-card n88-hero-card--{{ $index }}" data-n88-reveal>
                    <a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', '/theme-demo/news88/hero-mekong.png') }}" alt="{{ data_get($item, 'alt', data_get($item, 'title')) }}"><span class="n88-shade"></span><div><h{{ $index === 0 ? '1' : '2' }}>{{ data_get($item, 'title') }}</h{{ $index === 0 ? '1' : '2' }}>@if($index === 0)<p>{{ data_get($item, 'summary') }}</p>@endif<small>@themeT('NEWS88.date', 'Ngày'): {{ $date($item) }} <b>@themeT('NEWS88.views', 'Lượt xem'): {{ $views($item) }}</b></small></div></a>
                </article>
            @endforeach
        </section>

        <section class="n88-latest-row xd-landing-block" id="tin-moi" data-landing-block-id="{{ data_get($latest, 'id') }}" data-block-type="news88_latest_video">
            @include('theme-news88::partials.edit-button', ['block' => $latest])
            <div class="n88-panel n88-latest-panel"><header><h2>{{ data_get($latest, 'data.title', __('NEWS88.latest')) }}</h2></header><div class="n88-latest-grid">
                @foreach($latestItems->take(6) as $item)<article data-n88-reveal><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"></a><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><small>@themeT('NEWS88.date', 'Ngày'): {{ $date($item) }} <b>@themeT('NEWS88.views', 'Lượt xem'): {{ $views($item) }}</b></small><p>{{ data_get($item, 'summary') }}</p></article>@endforeach
            </div></div>
            <aside class="n88-panel n88-video-panel"><header><h2>@themeT('NEWS88.video', 'Video')</h2></header><div class="n88-video-grid">@foreach($latestItems->slice(6, 2) as $item)<article data-n88-reveal><a href="{{ data_get($item, 'url', '#') }}"><span><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"><i class="fa-solid fa-play"></i></span><h3>{{ data_get($item, 'title') }}</h3></a><p>{{ data_get($item, 'summary') }}</p></article>@endforeach</div></aside>
        </section>

        <section class="n88-panel n88-health xd-landing-block" id="suc-khoe" data-landing-block-id="{{ data_get($health, 'id') }}" data-block-type="news88_health_posts">
            @include('theme-news88::partials.edit-button', ['block' => $health])
            <header><h2>{{ data_get($health, 'data.title', 'Tin Sức Khỏe') }}</h2></header><div class="n88-health-grid">@foreach($healthItems->take(6) as $item)<article data-n88-reveal><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"></a><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><small>@themeT('NEWS88.date', 'Ngày'): {{ $date($item) }} <b>@themeT('NEWS88.views', 'Lượt xem'): {{ $views($item) }}</b></small><p>{{ data_get($item, 'summary') }}</p></article>@endforeach</div>
        </section>

        <section class="n88-columns">
            @foreach([[$cars, $carItems, 'news88_car_posts'], [$travel, $travelItems, 'news88_travel_posts'], [$entertainment, $entertainmentItems, 'news88_entertainment_posts']] as [$section, $sectionItems, $type])
                <div class="n88-panel n88-column xd-landing-block" id="{{ data_get($section, 'anchor_id') }}" data-landing-block-id="{{ data_get($section, 'id') }}" data-block-type="{{ $type }}">
                    @include('theme-news88::partials.edit-button', ['block' => $section])
                    <header><h2>{{ data_get($section, 'data.title') }}</h2></header>
                    @foreach($sectionItems->take(4) as $index => $item)
                        @if($index === 0)<article class="n88-column-feature" data-n88-reveal><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"></a><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><small>@themeT('NEWS88.date', 'Ngày'): {{ $date($item) }} <b>@themeT('NEWS88.views', 'Lượt xem'): {{ $views($item) }}</b></small></article>
                        @else<article class="n88-column-small" data-n88-reveal><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"><span><h3>{{ data_get($item, 'title') }}</h3><small>{{ $date($item) }} · {{ $views($item) }}</small></span></a></article>@endif
                    @endforeach
                </div>
            @endforeach
        </section>
    </div>
</main>
@endsection

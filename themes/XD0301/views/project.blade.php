@php
    $projectLabel = app(\App\Core\Themes\ThemeTranslationService::class)
        ->bladeText('XD0301', app()->getLocale(), 'legacy_inline.6980f6dccf6e96cb', 'Dự án');
    $title = $pageTitle ?? ($entry->title ?? $projectLabel);
    $description = $pageDescription ?? ($entry->summary ?? '');
    $projectImages = $entry->relationLoaded('images')
        ? $entry->images->filter(fn ($image) => filled($image->image_url))->values()
        : collect();

    if ($projectImages->isEmpty() && $entry->featuredImage?->image_url) {
        $projectImages = collect([$entry->featuredImage]);
    }

    $recentProjectItems = collect($recentProjects ?? []);
    $footerNewsletterSource = 'theme-footer-xd0301-project';
    $canEditLanding = false;
@endphp

@extends('theme-xd0301::layout')

@section('title', $title)

@if (!empty($description))
    @push('head')
        <meta name="description" content="{{ $description }}">
    @endpush
@endif

@push('head')
    <style>
        .xd-page-main{padding:76px 0 90px}
        .xd-kicker{position:relative;display:inline-block;margin:0 0 14px 18px;font-size:14px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}
        .xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}
        .xd-detail{display:grid;grid-template-columns:minmax(0,.85fr) minmax(300px,.35fr);gap:44px}
        .xd-detail-card,.xd-side-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.06)}
        .xd-detail-card{overflow:hidden}
        .xd-project-detail-slider{position:relative;background:#101d28}
        .xd-project-detail-stage{position:relative;aspect-ratio:16/10;min-height:420px;overflow:hidden}
        .xd-project-detail-slide{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transform:scale(1.02);transition:opacity .28s ease,transform .45s ease}
        .xd-project-detail-slide.is-active{opacity:1;transform:scale(1)}
        .xd-project-detail-caption{position:absolute;left:28px;right:28px;bottom:24px;z-index:3;display:flex;justify-content:space-between;gap:18px;align-items:end;color:#fff;text-shadow:0 2px 16px rgba(0,0,0,.45)}
        .xd-project-detail-caption span{font-size:15px;font-weight:800}
        .xd-project-detail-caption small{color:rgba(255,255,255,.82);font-weight:750}
        .xd-project-detail-slider:after{content:"";position:absolute;inset:auto 0 0;height:38%;background:linear-gradient(180deg,transparent,rgba(6,13,18,.74));pointer-events:none}
        .xd-project-detail-nav{position:absolute;top:50%;z-index:4;display:grid;place-items:center;width:46px;height:46px;border:0;border-radius:999px;background:rgba(255,255,255,.9);color:var(--ink);box-shadow:0 16px 34px rgba(16,29,40,.2);font-size:30px;font-weight:900;cursor:pointer;transform:translateY(-50%)}
        .xd-project-detail-nav:hover{background:var(--lime);color:#fff}
        .xd-project-detail-nav.prev{left:22px}
        .xd-project-detail-nav.next{right:22px}
        .xd-project-detail-thumbs{display:flex;gap:10px;overflow-x:auto;padding:16px 18px;background:#fff;border-top:1px solid var(--line);scrollbar-width:none}
        .xd-project-detail-thumbs::-webkit-scrollbar{display:none}
        .xd-project-detail-thumb{flex:0 0 96px;width:96px;height:68px;overflow:hidden;padding:0;border:3px solid transparent;background:#eef2ef;cursor:pointer;opacity:.72;transition:.18s}
        .xd-project-detail-thumb img{width:100%;height:100%;object-fit:cover}
        .xd-project-detail-thumb:hover,.xd-project-detail-thumb.is-active{border-color:var(--lime);opacity:1;transform:translateY(-1px)}
        .xd-detail-body{padding:44px 52px}
        .xd-detail-body h1{margin:0 0 18px;font-size:clamp(38px,4vw,62px);line-height:1.1;letter-spacing:-.05em}
        .xd-detail-summary{margin:0 0 28px;color:var(--muted);font-size:20px}
        .xd-rich-content{color:#465461;font-size:18px}
        .xd-rich-content :first-child{margin-top:0}
        .xd-side-stack{display:grid;gap:22px;align-content:start}
        .xd-side-card{padding:28px}
        .xd-side-card h3{margin:0 0 18px;font-size:24px}
        .xd-side-card a,.xd-side-card span{display:block;padding:12px 0;border-top:1px solid var(--line);color:var(--muted);font-weight:750}
        .xd-side-card a:hover{color:var(--lime-dark)}
        .xd-recent-projects{display:grid;gap:14px}
        .xd-recent-project{display:grid!important;grid-template-columns:82px minmax(0,1fr);gap:14px;align-items:center;padding:12px 0!important;border-top:1px solid var(--line);color:var(--ink)!important}
        .xd-recent-project:first-child{border-top:0}
        .xd-recent-project img,.xd-recent-project-placeholder{width:82px;height:62px;object-fit:cover;background:#eef2ef}
        .xd-recent-project-placeholder{display:block;border:1px solid var(--line)}
        .xd-recent-project strong{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-size:15px;line-height:1.35}
        .xd-recent-project:hover strong{color:var(--lime-dark)}
        @media (max-width:1180px){.xd-detail{grid-template-columns:1fr}}
        @media (max-width:640px){.xd-page-main{padding:38px 0 56px}.xd-project-detail-stage{aspect-ratio:4/3;min-height:260px}.xd-project-detail-nav{display:none}.xd-project-detail-caption{left:18px;right:18px;bottom:18px}.xd-project-detail-thumb{flex-basis:78px;width:78px;height:56px}.xd-detail-body{padding:28px 22px}.xd-detail-body h1{font-size:34px}.xd-detail-summary,.xd-rich-content{font-size:16px}}
    </style>
@endpush

@section('content')
<main class="xd-page-main">
    <div class="xd-container">
        <section class="xd-detail">
            <article class="xd-detail-card">
                @if ($projectImages->isNotEmpty())
                    <div class="xd-project-detail-slider" data-project-detail-slider>
                        <div class="xd-project-detail-stage">
                            @foreach ($projectImages as $image)
                                <img
                                    class="xd-project-detail-slide {{ $loop->first ? 'is-active' : '' }}"
                                    src="{{ $image->image_url }}"
                                    alt="{{ $image->alt_text ?: $entry->title }}"
                                    data-project-detail-slide="{{ $loop->index }}"
                                >
                            @endforeach
                            @if ($projectImages->count() > 1)
                                <button class="xd-project-detail-nav prev" type="button" data-project-detail-prev aria-label="Previous image">&#8249;</button>
                                <button class="xd-project-detail-nav next" type="button" data-project-detail-next aria-label="Next image">&#8250;</button>
                            @endif
                            <div class="xd-project-detail-caption">
                                <span data-project-detail-counter>1 / {{ $projectImages->count() }}</span>
                                <small data-project-detail-caption>{{ $projectImages->first()?->caption }}</small>
                            </div>
                        </div>
                        @if ($projectImages->count() > 1)
                            <div class="xd-project-detail-thumbs" aria-label="Project images">
                                @foreach ($projectImages as $image)
                                    <button
                                        class="xd-project-detail-thumb {{ $loop->first ? 'is-active' : '' }}"
                                        type="button"
                                        data-project-detail-thumb="{{ $loop->index }}"
                                        data-caption="{{ $image->caption }}"
                                        aria-label="View image {{ $loop->iteration }} of {{ $entry->title }}"
                                    >
                                        <img src="{{ $image->image_url }}" alt="">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
                <div class="xd-detail-body">
                    <span class="xd-kicker">
                        @if ($isEnglish)
                            Project
                        @else
                            D&#7921; &aacute;n
                        @endif
                    </span>
                    <h1>{{ $entry->title }}</h1>
                    @if (!empty($entry->summary))
                        <p class="xd-detail-summary">{{ $entry->summary }}</p>
                    @endif
                    <div class="xd-rich-content">
                        {!! $entry->content ?: '<p>N&#7897;i dung &#273;ang &#273;&#432;&#7907;c c&#7853;p nh&#7853;t.</p>' !!}
                    </div>
                </div>
            </article>
            <aside class="xd-side-stack">
                <div class="xd-side-card">
                    <h3>
                        @if ($isEnglish)
                            Project categories
                        @else
                            Danh m&#7909;c d&#7921; &aacute;n
                        @endif
                    </h3>
                    @if ($entry->category?->name)
                        @if ($entry->category?->slug)
                            <a href="{{ route('site.projects.category', ['slug' => $entry->category->slug]) }}">{{ $entry->category->name }}</a>
                        @else
                            <span>{{ $entry->category->name }}</span>
                        @endif
                    @endif
                    <a href="{{ route('site.projects.index') }}">
                        @if ($isEnglish)
                            All projects
                        @else
                            T&#7845;t c&#7843; d&#7921; &aacute;n
                        @endif
                    </a>
                </div>

                @if ($recentProjectItems->isNotEmpty())
                    <div class="xd-side-card">
                        <h3>
                            @if ($isEnglish)
                                Recent projects
                            @else
                                D&#7921; &aacute;n g&#7847;n &#273;&acirc;y
                            @endif
                        </h3>
                        <div class="xd-recent-projects">
                            @foreach ($recentProjectItems as $recentProject)
                                @php
                                    $recentImage = $recentProject->featuredImage?->image_url;
                                    $recentAlt = $recentProject->featuredImage?->alt_text ?: $recentProject->title;
                                @endphp
                                <a class="xd-recent-project" href="{{ route('site.projects.show', ['slug' => $recentProject->slug]) }}">
                                    @if ($recentImage)
                                        <img src="{{ $recentImage }}" alt="{{ $recentAlt }}">
                                    @else
                                        <i class="xd-recent-project-placeholder" aria-hidden="true"></i>
                                    @endif
                                    <strong>{{ $recentProject->title }}</strong>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </aside>
        </section>
    </div>
</main>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-project-detail-slider]').forEach((slider) => {
                const slides = Array.from(slider.querySelectorAll('[data-project-detail-slide]'));
                const thumbs = Array.from(slider.querySelectorAll('[data-project-detail-thumb]'));
                const counter = slider.querySelector('[data-project-detail-counter]');
                const caption = slider.querySelector('[data-project-detail-caption]');
                const previousButton = slider.querySelector('[data-project-detail-prev]');
                const nextButton = slider.querySelector('[data-project-detail-next]');
                let currentIndex = 0;

                if (!slides.length) {
                    return;
                }

                const showSlide = (nextIndex = 0) => {
                    currentIndex = ((nextIndex % slides.length) + slides.length) % slides.length;
                    slides.forEach((slide, index) => slide.classList.toggle('is-active', index === currentIndex));
                    thumbs.forEach((thumb, index) => thumb.classList.toggle('is-active', index === currentIndex));

                    if (counter) {
                        counter.textContent = `${currentIndex + 1} / ${slides.length}`;
                    }

                    if (caption) {
                        caption.textContent = thumbs[currentIndex]?.dataset.caption || '';
                    }
                };

                previousButton?.addEventListener('click', () => showSlide(currentIndex - 1));
                nextButton?.addEventListener('click', () => showSlide(currentIndex + 1));
                thumbs.forEach((thumb) => {
                    thumb.addEventListener('click', () => showSlide(Number(thumb.dataset.projectDetailThumb || 0)));
                });
            });
        });
    </script>
@endpush

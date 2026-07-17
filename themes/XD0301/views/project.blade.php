@php
    $title = $pageTitle ?? ($entry->title ?? (app()->getLocale() === 'en' ? 'Project' : 'Dự án'));
    $description = $pageDescription ?? ($entry->summary ?? '');
    $projectImages = $entry->relationLoaded('images')
        ? $entry->images->filter(fn ($image) => filled($image->image_url))->values()
        : collect();

    if ($projectImages->isEmpty() && $entry->featuredImage?->image_url) {
        $projectImages = collect([$entry->featuredImage]);
    }

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
        .xd-side-card{padding:28px}
        .xd-side-card h3{margin:0 0 18px;font-size:24px}
        .xd-side-card a,.xd-side-card span{display:block;padding:12px 0;border-top:1px solid var(--line);color:var(--muted);font-weight:750}
        .xd-side-card a:hover{color:var(--lime-dark)}
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
                                <button class="xd-project-detail-nav prev" type="button" data-project-detail-prev aria-label="Ảnh trước">&#8249;</button>
                                <button class="xd-project-detail-nav next" type="button" data-project-detail-next aria-label="Ảnh tiếp theo">&#8250;</button>
                            @endif
                            <div class="xd-project-detail-caption">
                                <span data-project-detail-counter>1 / {{ $projectImages->count() }}</span>
                                <small data-project-detail-caption>{{ $projectImages->first()?->caption }}</small>
                            </div>
                        </div>
                        @if ($projectImages->count() > 1)
                            <div class="xd-project-detail-thumbs" aria-label="Chọn ảnh dự án">
                                @foreach ($projectImages as $image)
                                    <button
                                        class="xd-project-detail-thumb {{ $loop->first ? 'is-active' : '' }}"
                                        type="button"
                                        data-project-detail-thumb="{{ $loop->index }}"
                                        data-caption="{{ $image->caption }}"
                                        aria-label="Xem ảnh {{ $loop->iteration }} của {{ $entry->title }}"
                                    >
                                        <img src="{{ $image->image_url }}" alt="">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
                <div class="xd-detail-body">
                    <span class="xd-kicker">{{ app()->getLocale() === 'en' ? 'Project' : 'Dự án' }}</span>
                    <h1>{{ $entry->title }}</h1>
                    @if (!empty($entry->summary))
                        <p class="xd-detail-summary">{{ $entry->summary }}</p>
                    @endif
                    <div class="xd-rich-content">
                        {!! $entry->content ?: '<p>Nội dung đang được cập nhật.</p>' !!}
                    </div>
                </div>
            </article>
            <aside class="xd-side-card">
                <h3>{{ app()->getLocale() === 'en' ? 'Project info' : 'Thông tin dự án' }}</h3>
                @if ($entry->category?->name)
                    <span>{{ $entry->category->name }}</span>
                @endif
                <a href="{{ route('site.projects.index') }}">{{ app()->getLocale() === 'en' ? 'All projects' : 'Tất cả dự án' }}</a>
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

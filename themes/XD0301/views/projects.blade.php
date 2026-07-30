@php
    $title = $pageTitle ?? (app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0301', app()->getLocale(), 'legacy_inline.6980f6dccf6e96cb', 'Dự án'));
    $description = $pageDescription ?? (app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0301', app()->getLocale(), 'legacy_inline.01f5fc41dc67335e', 'Danh sách dự án đã xuất bản.'));
    $footerNewsletterSource = 'theme-footer-xd0301-projects';
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
        .xd-cms-hero{display:grid;grid-template-columns:minmax(0,.75fr) minmax(300px,.35fr);gap:48px;align-items:end;margin-bottom:54px;padding:56px;border:1px solid var(--line);background:#fff;box-shadow:0 20px 55px rgba(28,45,60,.08)}
        .xd-kicker{position:relative;display:inline-block;margin:0 0 14px 18px;font-size:14px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}
        .xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}
        .xd-cms-hero h1{margin:0;color:var(--ink);font-size:clamp(42px,5vw,72px);line-height:1.08;letter-spacing:-.055em}
        .xd-cms-hero p{margin:18px 0 0;color:var(--muted);font-size:20px;font-weight:550}
        .xd-cms-stats{display:grid;gap:12px;color:#fff;background:var(--ink);padding:26px 30px}
        .xd-cms-stats strong{font-size:46px;line-height:1}
        .xd-cms-stats span{color:rgba(255,255,255,.75);font-weight:800;text-transform:uppercase}
        .xd-projects-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:34px}
        .xd-projects-list .xd-project-card{min-height:520px}
        .xd-projects-list .xd-project-caption small{color:rgba(255,255,255,.82)}
        @media (max-width:1180px){.xd-cms-hero{grid-template-columns:1fr}.xd-projects-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){.xd-page-main{padding:38px 0 56px}.xd-cms-hero{padding:30px 22px;margin-bottom:26px}.xd-cms-hero h1{font-size:36px}.xd-cms-hero p{font-size:16px}.xd-projects-list{grid-template-columns:1fr}.xd-projects-list .xd-project-card{min-height:430px;border-radius:18px}}
    </style>
@endpush

@section('content')
<main class="xd-page-main">
    <div class="xd-container">
        <section class="xd-cms-hero">
            <div>
                <span class="xd-kicker">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0301', app()->getLocale(), 'legacy_inline.6980f6dccf6e96cb', 'Dự án') }}</span>
                <h1>{{ $title }}</h1>
                <p>{{ $description }}</p>
            </div>
            <div class="xd-cms-stats">
                <strong>{{ collect($listingItems ?? [])->count() }}</strong>
                <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0301', app()->getLocale(), 'legacy_inline.d9f11545f501258d', 'Dự án đang hiển thị') }}</span>
            </div>
        </section>

        <section class="xd-projects-list">
            @forelse ($listingItems as $project)
                @php
                    $projectUrl = route('site.projects.show', ['slug' => $project->slug]);
                    $projectImages = $project->relationLoaded('images')
                        ? $project->images->filter(fn ($image) => filled($image->image_url))->values()
                        : collect();

                    if ($projectImages->isEmpty() && $project->featuredImage?->image_url) {
                        $projectImages = collect([$project->featuredImage]);
                    }

                    $summary = $project->summary ?: \Illuminate\Support\Str::limit(strip_tags($project->content ?? ''), 150);
                @endphp
                <article class="xd-project-card" data-project-gallery>
                    <div class="xd-project-media">
                        @foreach ($projectImages as $projectImage)
                            <img
                                class="xd-project-slide {{ $loop->first ? 'is-active' : '' }}"
                                src="{{ $projectImage->image_url }}"
                                alt="{{ $projectImage->alt_text ?: $project->title }}"
                                data-project-slide="{{ $loop->index }}"
                            >
                        @endforeach
                    </div>
                    @if ($projectImages->count() > 1)
                        <div class="xd-project-thumbs" aria-label="Chọn ảnh dự án">
                            @foreach ($projectImages as $projectImage)
                                <button
                                    class="xd-project-thumb {{ $loop->first ? 'is-active' : '' }}"
                                    type="button"
                                    data-project-thumb="{{ $loop->index }}"
                                    aria-label="Xem ảnh {{ $loop->iteration }} của {{ $project->title }}"
                                >
                                    <img src="{{ $projectImage->image_url }}" alt="">
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <span class="xd-project-caption">
                        @if ($summary)
                            <small>{{ $summary }}</small>
                        @endif
                        <a href="{{ $projectUrl }}">{{ $project->title }}</a>
                    </span>
                </article>
            @empty
                <p>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0301', app()->getLocale(), 'legacy_inline.cb746a768450cd69', 'Chưa có dự án nào được xuất bản.') }}</p>
            @endforelse
        </section>
    </div>
</main>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-project-gallery]').forEach((gallery) => {
                const slides = Array.from(gallery.querySelectorAll('[data-project-slide]'));
                const thumbs = Array.from(gallery.querySelectorAll('[data-project-thumb]'));

                if (slides.length <= 1 || !thumbs.length) {
                    return;
                }

                const showProjectImage = (nextIndex = 0) => {
                    const resolvedIndex = ((nextIndex % slides.length) + slides.length) % slides.length;
                    slides.forEach((slide, index) => slide.classList.toggle('is-active', index === resolvedIndex));
                    thumbs.forEach((thumb, index) => thumb.classList.toggle('is-active', index === resolvedIndex));
                };

                thumbs.forEach((thumb) => {
                    thumb.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        showProjectImage(Number(thumb.dataset.projectThumb || 0));
                    });
                });
            });
        });
    </script>
@endpush

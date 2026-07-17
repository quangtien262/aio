@php
    $title = $pageTitle ?? (app()->getLocale() === 'en' ? 'Projects' : 'Dự án');
    $description = $pageDescription ?? (app()->getLocale() === 'en' ? 'Explore our completed projects.' : 'Danh sách dự án đã xuất bản.');
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
        .xd-project-card{background:#fff;box-shadow:0 5px 20px rgba(16,29,40,.08);transition:.25s}
        .xd-project-card:hover{transform:translateY(-8px);box-shadow:var(--shadow)}
        .xd-project-image{display:block;height:300px;overflow:hidden;background:#eef2ef}
        .xd-project-image img{width:100%;height:100%;object-fit:cover;transition:.4s}
        .xd-project-card:hover img{transform:scale(1.05)}
        .xd-project-body{padding:34px 36px 38px}
        .xd-project-card h2{margin:0 0 14px;font-size:22px;line-height:1.32;letter-spacing:.015em;text-transform:uppercase}
        .xd-project-card p{margin:0 0 24px;color:var(--muted);font-size:17px}
        .xd-project-category{display:inline-flex;margin-bottom:14px;padding:5px 10px;background:#eef7d7;color:var(--lime-dark);font-size:12px;font-weight:950;text-transform:uppercase}
        .xd-text-link{color:var(--lime-dark);font-weight:900;text-transform:uppercase}
        @media (max-width:1180px){.xd-cms-hero{grid-template-columns:1fr}.xd-projects-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){.xd-page-main{padding:38px 0 56px}.xd-cms-hero{padding:30px 22px;margin-bottom:26px}.xd-cms-hero h1{font-size:36px}.xd-cms-hero p{font-size:16px}.xd-projects-list{grid-template-columns:1fr}.xd-project-card{border-radius:18px;overflow:hidden}.xd-project-image{height:215px}.xd-project-body{padding:26px 22px}.xd-project-card h2{font-size:19px}}
    </style>
@endpush

@section('content')
<main class="xd-page-main">
    <div class="xd-container">
        <section class="xd-cms-hero">
            <div>
                <span class="xd-kicker">{{ app()->getLocale() === 'en' ? 'Projects' : 'Dự án' }}</span>
                <h1>{{ $title }}</h1>
                <p>{{ $description }}</p>
            </div>
            <div class="xd-cms-stats">
                <strong>{{ collect($listingItems ?? [])->count() }}</strong>
                <span>{{ app()->getLocale() === 'en' ? 'Visible projects' : 'Dự án đang hiển thị' }}</span>
            </div>
        </section>

        <section class="xd-projects-list">
            @forelse ($listingItems as $project)
                @php
                    $projectUrl = route('site.projects.show', ['slug' => $project->slug]);
                    $image = $project->featuredImage?->image_url;
                    $alt = $project->featuredImage?->alt_text ?: $project->title;
                @endphp
                <article class="xd-project-card">
                    <a class="xd-project-image" href="{{ $projectUrl }}" aria-label="{{ $project->title }}">
                        @if ($image)
                            <img src="{{ $image }}" alt="{{ $alt }}">
                        @endif
                    </a>
                    <div class="xd-project-body">
                        @if ($project->category?->name)
                            <span class="xd-project-category">{{ $project->category->name }}</span>
                        @endif
                        <h2><a href="{{ $projectUrl }}">{{ $project->title }}</a></h2>
                        <p>{{ $project->summary ?: \Illuminate\Support\Str::limit(strip_tags($project->content ?? ''), 150) }}</p>
                        <a class="xd-text-link" href="{{ $projectUrl }}">{{ app()->getLocale() === 'en' ? 'View project' : 'Xem dự án' }}</a>
                    </div>
                </article>
            @empty
                <p>{{ app()->getLocale() === 'en' ? 'No projects are available yet.' : 'Chưa có dự án nào được xuất bản.' }}</p>
            @endforelse
        </section>
    </div>
</main>
@endsection

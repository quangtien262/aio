<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle ?? ($siteProfile?->site_name ?? config('app.name', 'AIO Platform')) }}</title>
        @if (!empty($pageDescription))
            <meta name="description" content="{{ $pageDescription }}">
        @endif
        @vite('resources/css/app.css')
        <style>
            :root {
                --site-accent: {{ data_get($siteProfile, 'branding.primary_color', '#0f766e') }};
                --site-surface: #ffffff;
                --site-ink: #17302b;
                --site-muted: #60766f;
                --site-line: #d8e5e1;
            }

            body { margin: 0; font-family: 'Segoe UI', sans-serif; background: linear-gradient(180deg, #f6fbfa 0%, #ffffff 100%); color: var(--site-ink); }
            .site-shell { min-height: 100vh; }
            .site-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 18px 28px; background: rgba(255,255,255,0.92); border-bottom: 1px solid var(--site-line); backdrop-filter: blur(12px); position: sticky; top: 0; z-index: 10; }
            .site-brand strong { display: block; font-size: 20px; }
            .site-brand span { color: var(--site-muted); font-size: 13px; }
            .site-nav { display: flex; flex-wrap: wrap; gap: 10px; }
            .site-nav a { color: var(--site-ink); text-decoration: none; padding: 10px 14px; border-radius: 12px; }
            .site-nav a:hover { background: color-mix(in srgb, var(--site-accent) 10%, white); }
            .site-main { width: min(1100px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 60px; }
            .site-preview-banner { margin-bottom: 18px; padding: 12px 16px; border-radius: 14px; background: #fff7e6; border: 1px solid #ffd591; color: #8a5a00; }
            .site-hero { padding: 28px; border: 1px solid var(--site-line); border-radius: 24px; background: radial-gradient(circle at top left, color-mix(in srgb, var(--site-accent) 18%, white) 0%, transparent 28%), var(--site-surface); box-shadow: 0 18px 48px rgba(15,34,30,0.08); }
            .site-kicker { display: inline-block; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.12em; font-size: 12px; color: var(--site-accent); }
            .site-hero h1, .site-listing-title { margin: 0 0 12px; font-size: clamp(30px, 5vw, 48px); line-height: 1.08; }
            .site-summary { font-size: 18px; line-height: 1.75; color: var(--site-muted); }
            .site-featured-image { width: 100%; max-height: 420px; object-fit: cover; border-radius: 20px; margin: 22px 0; border: 1px solid var(--site-line); }
            .site-content, .site-list-grid { margin-top: 28px; }
            .site-content { padding: 26px 28px; border: 1px solid var(--site-line); border-radius: 22px; background: var(--site-surface); line-height: 1.8; }
            .site-list-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; }
            .site-list-card { padding: 22px; border: 1px solid var(--site-line); border-radius: 22px; background: var(--site-surface); }
            .site-list-card a { color: inherit; text-decoration: none; }
            .site-list-card p { color: var(--site-muted); line-height: 1.65; }
            .site-footer { padding: 22px 28px 42px; color: var(--site-muted); text-align: center; }

            @media (max-width: 768px) {
                .site-header { align-items: flex-start; flex-direction: column; padding: 16px; }
                .site-main { width: min(100% - 24px, 1100px); padding: 18px 0 44px; }
                .site-hero, .site-content, .site-list-card { padding: 18px; }
            }
        </style>
    </head>
    <body>
        <div class="site-shell">
            <header class="site-header">
                <div class="site-brand">
                    <strong>{{ data_get($siteProfile, 'branding.company_name', $siteProfile?->site_name ?? 'AIO Website') }}</strong>
                    <span>{{ $activeTheme['name'] ?? 'Default Theme' }} | {{ $activeTheme['key'] ?? ($siteProfile?->active_theme_key ?? 'default') }}</span>
                </div>
                <nav class="site-nav">
                    <a href="/">Trang chủ</a>
                    @foreach (($menus['primary'] ?? []) as $item)
                        <a href="{{ $item['url'] ?? '#' }}" @if(($item['target'] ?? '') === '_blank') target="_blank" rel="noreferrer" @endif>{{ $item['label'] ?? 'Menu' }}</a>
                    @endforeach
                    <a href="/blog">Tin tức</a>
                </nav>
            </header>

            <main class="site-main">
                @if (!empty($isPreview))
                    <div class="site-preview-banner">Đây là chế độ preview unpublished chỉ dành cho admin.</div>
                @endif

                @if (($contentType ?? null) === 'posts')
                    <section class="site-hero">
                        <span class="site-kicker">CMS Listing</span>
                        <h1 class="site-listing-title">{{ $pageTitle }}</h1>
                        <p class="site-summary">{{ $pageDescription }}</p>
                    </section>

                    <section class="site-list-grid">
                        @foreach ($listingItems as $post)
                            <article class="site-list-card">
                                <span class="site-kicker">Post</span>
                                <h2 style="margin-top:0;"><a href="{{ url('/blog/'.$post->slug) }}">{{ $post->title }}</a></h2>
                                <p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 140) }}</p>
                            </article>
                        @endforeach
                    </section>
                @else
                    <section class="site-hero">
                        <span class="site-kicker">{{ strtoupper($contentType ?? 'PAGE') }}</span>
                        <h1>{{ $entry->title }}</h1>
                        @if (!empty($entry->excerpt))
                            <p class="site-summary">{{ $entry->excerpt }}</p>
                        @endif

                        @if (!empty($entry->featuredMedia?->file_url ?? null))
                            <img class="site-featured-image" src="{{ $entry->featuredMedia->file_url }}" alt="{{ $entry->title }}">
                        @endif
                    </section>

                    <section class="site-content">
                        {!! nl2br(e($entry->body ?? 'Nội dung đang được cập nhật.')) !!}
                    </section>

                    @if (!empty($latestPosts) && count($latestPosts) > 0)
                        <section class="site-list-grid">
                            @foreach ($latestPosts as $post)
                                <article class="site-list-card">
                                    <span class="site-kicker">Latest Post</span>
                                    <h3 style="margin-top:0;"><a href="{{ url('/blog/'.$post->slug) }}">{{ $post->title }}</a></h3>
                                    <p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 120) }}</p>
                                </article>
                            @endforeach
                        </section>
                    @endif
                @endif
            </main>

            <footer class="site-footer">{{ $siteProfile?->site_name ?? 'AIO Website' }} © {{ now()->year }}</footer>
        </div>
    </body>
</html>

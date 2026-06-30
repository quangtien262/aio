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
            .site-nav { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
            .site-nav a { color: var(--site-ink); text-decoration: none; padding: 10px 14px; border-radius: 12px; }
            .site-nav a:hover { background: color-mix(in srgb, var(--site-accent) 10%, white); }
            .site-nav .site-admin-entry { border: 1px solid color-mix(in srgb, var(--site-accent) 45%, white); background: var(--site-accent); color: #fff; font-weight: 700; box-shadow: 0 10px 24px rgba(15, 118, 110, 0.18); }
            .site-nav .site-admin-entry:hover { background: color-mix(in srgb, var(--site-accent) 86%, #0f172a); color: #fff; }
            .site-main { width: min(1100px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 60px; }
            .site-preview-banner { margin-bottom: 18px; padding: 12px 16px; border-radius: 14px; background: #fff7e6; border: 1px solid #ffd591; color: #8a5a00; }
            .site-hero { padding: 28px; border: 1px solid var(--site-line); border-radius: 24px; background: radial-gradient(circle at top left, color-mix(in srgb, var(--site-accent) 18%, white) 0%, transparent 28%), var(--site-surface); box-shadow: 0 18px 48px rgba(15,34,30,0.08); }
            .site-kicker { display: inline-block; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.12em; font-size: 12px; color: var(--site-accent); }
            .site-hero h1, .site-listing-title { margin: 0 0 12px; font-size: clamp(30px, 5vw, 48px); line-height: 1.08; }
            .site-summary { font-size: 18px; line-height: 1.75; color: var(--site-muted); }
            .site-featured-image { width: 100%; max-height: 420px; object-fit: cover; border-radius: 20px; margin: 22px 0; border: 1px solid var(--site-line); }
            .site-auth-panel { margin-top: 24px; padding: 24px 28px; border: 1px solid var(--site-line); border-radius: 22px; background: linear-gradient(135deg, color-mix(in srgb, var(--site-accent) 9%, white) 0%, #ffffff 100%); box-shadow: 0 18px 48px rgba(15,34,30,0.06); }
            .site-auth-panel h2 { margin: 0 0 8px; font-size: 24px; }
            .site-auth-panel p { margin: 0; color: var(--site-muted); line-height: 1.7; }
            .site-auth-errors { margin-top: 16px; padding: 12px 14px; border-radius: 14px; background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }
            .site-auth-form { display: grid; gap: 14px; margin-top: 18px; }
            .site-auth-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .site-auth-field { display: grid; gap: 8px; }
            .site-auth-field span { font-size: 13px; font-weight: 600; color: var(--site-ink); }
            .site-auth-field input { min-height: 48px; padding: 0 14px; border: 1px solid var(--site-line); border-radius: 14px; background: #fff; font: inherit; color: var(--site-ink); }
            .site-auth-field input:focus { outline: 2px solid color-mix(in srgb, var(--site-accent) 28%, white); outline-offset: 1px; }
            .site-auth-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 4px; }
            .site-auth-submit, .site-auth-link { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px; border-radius: 14px; text-decoration: none; font-weight: 700; }
            .site-auth-submit { border: 0; background: var(--site-accent); color: #fff; cursor: pointer; }
            .site-auth-link { border: 1px solid var(--site-line); color: var(--site-ink); background: rgba(255,255,255,0.92); }
            .site-auth-note { font-size: 13px; color: var(--site-muted); }
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
                .site-hero, .site-auth-panel, .site-content, .site-list-card { padding: 18px; }
                .site-auth-grid { grid-template-columns: 1fr; }
                .site-auth-actions { align-items: stretch; }
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
                    <a href="{{ route('site.blog.index') }}">Tin tức</a>
                    @auth('admin')
                        <a class="site-admin-entry" href="{{ route('admin.index') }}">Vào quản trị</a>
                    @else
                        <a class="site-admin-entry" href="#admin-login">Đăng nhập quản trị</a>
                    @endauth
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
                                <h2 style="margin-top:0;"><a href="{{ route('site.blog.show', $post->slug) }}">{{ $post->title }}</a></h2>
                                <p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 140) }}</p>
                            </article>
                        @endforeach
                    </section>
                @elseif (($contentType ?? null) === 'services')
                    <section class="site-hero">
                        <span class="site-kicker">Services</span>
                        <h1 class="site-listing-title">{{ $pageTitle }}</h1>
                        <p class="site-summary">{{ $pageDescription }}</p>
                    </section>

                    <section class="site-list-grid">
                        @foreach ($listingItems as $service)
                            <article class="site-list-card">
                                @if (!empty($service->featuredImage?->image_url ?? null))
                                    <a href="{{ route('site.services.show', ['slug' => $service->slug]) }}">
                                        <img src="{{ $service->featuredImage->image_url }}" alt="{{ $service->featuredImage->alt_text ?: $service->title }}" style="width:100%;height:220px;object-fit:cover;border-radius:18px;border:1px solid var(--site-line);margin-bottom:16px;">
                                    </a>
                                @endif
                                <span class="site-kicker">Service</span>
                                <h2 style="margin-top:0;"><a href="{{ route('site.services.show', ['slug' => $service->slug]) }}">{{ $service->title }}</a></h2>
                                <p>{{ $service->summary ?: \Illuminate\Support\Str::limit(strip_tags($service->content ?? ''), 140) }}</p>
                                <a class="site-auth-link" href="{{ route('site.services.show', ['slug' => $service->slug]) }}">Xem chi tiết</a>
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

                        @if (($contentType ?? null) === 'service' && !empty($entry->featuredImage?->image_url ?? null))
                            <img class="site-featured-image" src="{{ $entry->featuredImage->image_url }}" alt="{{ $entry->featuredImage->alt_text ?: $entry->title }}">
                        @elseif (!empty($entry->featuredMedia?->file_url ?? null))
                            <img class="site-featured-image" src="{{ $entry->featuredMedia->file_url }}" alt="{{ $entry->title }}">
                        @endif
                    </section>

                    <section id="admin-login" class="site-auth-panel">
                        @auth('admin')
                            <span class="site-kicker">Admin session</span>
                            <h2>Đã đăng nhập quản trị</h2>
                            <p>Tài khoản admin đang hoạt động. Sếp có thể vào khu quản trị để kích hoạt theme khác hoặc tiếp tục setup website.</p>
                            <div class="site-auth-actions" style="margin-top:18px;">
                                <a class="site-auth-link" href="{{ route('admin.index') }}">Vào khu quản trị</a>
                                <form method="POST" action="{{ route('admin.auth.logout') }}">
                                    @csrf
                                    <button type="submit" class="site-auth-submit" style="background:#334155;">Đăng xuất admin</button>
                                </form>
                            </div>
                        @else
                            <span class="site-kicker">Setup access</span>
                            <h2>Đăng nhập để đổi theme và hoàn tất setup</h2>
                            <p>Theme mặc định này hiển thị sẵn form đăng nhập để lúc mới khởi tạo website, Sếp có thể vào admin ngay và chọn lại theme storefront mong muốn.</p>

                            @if ($errors->has('login') || $errors->has('email') || $errors->has('password'))
                                <div class="site-auth-errors">{{ $errors->first('login') ?: ($errors->first('email') ?: $errors->first('password')) }}</div>
                            @endif

                            <form method="POST" action="{{ route('customer.auth.store') }}" class="site-auth-form" novalidate>
                                @csrf
                                <input type="hidden" name="redirect_to" value="{{ route('admin.index') }}">
                                <div class="site-auth-grid">
                                    <label class="site-auth-field">
                                        <span>Username admin hoặc email</span>
                                        <input type="text" name="login" value="{{ old('login', 'admin') }}" required autofocus>
                                    </label>
                                    <label class="site-auth-field">
                                        <span>Mật khẩu</span>
                                        <input type="password" name="password" value="password" required>
                                    </label>
                                </div>
                                <div class="site-auth-actions">
                                    <button type="submit" class="site-auth-submit">Đăng nhập</button>
                                    <span class="site-auth-note">Tài khoản seed mặc định: <strong>admin</strong> / <strong>password</strong></span>
                                </div>
                            </form>
                        @endauth
                    </section>

                    <section class="site-content">
                        {!! $entry->body ?: '<p>Nội dung đang được cập nhật.</p>' !!}
                    </section>

                    @if (($contentType ?? null) === 'service' && !empty($entry->images) && $entry->images->count() > 1)
                        <section class="site-list-grid">
                            @foreach ($entry->images as $image)
                                <article class="site-list-card">
                                    <img src="{{ $image->image_url }}" alt="{{ $image->alt_text ?: $entry->title }}" style="width:100%;height:220px;object-fit:cover;border-radius:18px;border:1px solid var(--site-line);">
                                    @if (!empty($image->caption))
                                        <p>{{ $image->caption }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </section>
                    @endif

                    @if (!empty($latestPosts) && count($latestPosts) > 0)
                        <section class="site-list-grid">
                            @foreach ($latestPosts as $post)
                                <article class="site-list-card">
                                    <span class="site-kicker">Latest Post</span>
                                    <h3 style="margin-top:0;"><a href="{{ route('site.blog.show', $post->slug) }}">{{ $post->title }}</a></h3>
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

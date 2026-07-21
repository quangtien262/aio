@php
    $branding = (array) ($siteProfile?->branding ?? []);
    $siteName = trim((string) ($branding['company_name'] ?? $siteProfile?->site_name ?? config('app.name', 'AIO Platform')));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $brandInitials = collect(preg_split('/\s+/u', $siteName) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
    $configuredPrimary = trim((string) ($branding['primary_color'] ?? '#0f766e'));
    $primaryColor = preg_match('/^#[0-9a-fA-F]{6}$/', $configuredPrimary) ? $configuredPrimary : '#0f766e';
    $redirectTo = old('redirect_to', request()->query('redirect_to'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="{{ $primaryColor }}">
        <title>Đăng nhập | {{ $siteName }}</title>
        <style>
            :root {
                --auth-primary: {{ $primaryColor }};
                --auth-ink: #12211e;
                --auth-muted: #64736f;
                --auth-line: #dce5e2;
                --auth-surface: #ffffff;
                --auth-danger: #c92a2a;
            }
            * { box-sizing: border-box; }
            html { min-width: 320px; background: #eef4f2; }
            body { margin: 0; min-height: 100vh; font-family: Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: var(--auth-ink); background: #eef4f2; -webkit-font-smoothing: antialiased; }
            button, input { font: inherit; }
            button, a { -webkit-tap-highlight-color: transparent; }
            a { color: inherit; }
            .auth-shell { min-height: 100svh; display: grid; grid-template-columns: minmax(420px, .95fr) minmax(520px, 1.05fr); }
            .auth-story { position: relative; isolation: isolate; min-height: 100%; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; padding: clamp(30px, 5vw, 72px); color: #fff; background: linear-gradient(145deg, #071c18 0%, color-mix(in srgb, var(--auth-primary) 76%, #071c18) 58%, var(--auth-primary) 100%); }
            .auth-story::before, .auth-story::after { content: ""; position: absolute; z-index: -1; border-radius: 50%; border: 1px solid rgba(255,255,255,.13); }
            .auth-story::before { width: 620px; height: 620px; right: -270px; top: -230px; box-shadow: 0 0 0 70px rgba(255,255,255,.025), 0 0 0 150px rgba(255,255,255,.018); }
            .auth-story::after { width: 360px; height: 360px; left: -190px; bottom: -170px; box-shadow: 0 0 0 62px rgba(255,255,255,.025); }
            .auth-brand { display: inline-flex; align-items: center; gap: 14px; width: fit-content; text-decoration: none; }
            .auth-brand__mark { width: 50px; height: 50px; display: grid; place-items: center; overflow: hidden; border-radius: 16px; background: #fff; color: var(--auth-primary); box-shadow: 0 12px 32px rgba(0,0,0,.16); font-size: 15px; font-weight: 900; letter-spacing: -.03em; }
            .auth-brand__mark img { width: 100%; height: 100%; object-fit: contain; padding: 7px; }
            .auth-brand__copy { display: grid; gap: 2px; }
            .auth-brand__copy strong { max-width: 330px; font-size: 17px; line-height: 1.25; }
            .auth-brand__copy span { color: rgba(255,255,255,.66); font-size: 12px; letter-spacing: .12em; text-transform: uppercase; }
            .auth-story__content { max-width: 590px; padding: 64px 0; }
            .auth-eyebrow { display: flex; align-items: center; gap: 10px; margin: 0 0 20px; color: rgba(255,255,255,.72); font-size: 12px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
            .auth-eyebrow::before { content: ""; width: 30px; height: 2px; background: #fff; border-radius: 99px; }
            .auth-story h1 { max-width: 620px; margin: 0; font-size: clamp(42px, 5.2vw, 76px); line-height: .98; letter-spacing: -.055em; text-wrap: balance; }
            .auth-story__lead { max-width: 530px; margin: 28px 0 0; color: rgba(255,255,255,.74); font-size: clamp(16px, 1.5vw, 19px); line-height: 1.75; }
            .auth-benefits { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 14px; margin-top: 42px; }
            .auth-benefit { min-height: 112px; padding: 18px; border: 1px solid rgba(255,255,255,.14); border-radius: 20px; background: rgba(255,255,255,.07); backdrop-filter: blur(10px); }
            .auth-benefit svg { width: 22px; height: 22px; margin-bottom: 16px; fill: none; stroke: #fff; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
            .auth-benefit span { display: block; color: rgba(255,255,255,.82); font-size: 13px; font-weight: 700; line-height: 1.45; }
            .auth-story__footer { display: flex; align-items: center; justify-content: space-between; gap: 18px; color: rgba(255,255,255,.58); font-size: 12px; }
            .auth-status { display: inline-flex; align-items: center; gap: 8px; }
            .auth-status::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: #6ee7b7; box-shadow: 0 0 0 5px rgba(110,231,183,.12); }
            .auth-main { position: relative; display: grid; place-items: center; min-height: 100%; padding: clamp(24px, 5vw, 72px); background: radial-gradient(circle at 90% 8%, color-mix(in srgb, var(--auth-primary) 8%, transparent), transparent 28%), #f7faf9; }
            .auth-home { position: absolute; top: 28px; right: 32px; display: inline-flex; align-items: center; gap: 9px; padding: 10px 13px; border-radius: 12px; color: #50605c; text-decoration: none; font-size: 13px; font-weight: 700; transition: .2s ease; }
            .auth-home:hover { color: var(--auth-primary); background: #fff; box-shadow: 0 10px 30px rgba(21,45,39,.08); }
            .auth-home svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
            .auth-card { width: min(100%, 500px); padding: 26px 0; }
            .auth-card__icon { width: 48px; height: 48px; display: grid; place-items: center; margin-bottom: 28px; border: 1px solid color-mix(in srgb, var(--auth-primary) 22%, #fff); border-radius: 16px; color: var(--auth-primary); background: color-mix(in srgb, var(--auth-primary) 8%, #fff); }
            .auth-card__icon svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
            .auth-card h2 { margin: 0; font-size: clamp(32px, 4vw, 44px); line-height: 1.08; letter-spacing: -.045em; }
            .auth-card__intro { margin: 13px 0 0; color: var(--auth-muted); font-size: 15px; line-height: 1.7; }
            .auth-alert { display: flex; gap: 11px; margin: 24px 0 0; padding: 13px 15px; border: 1px solid #ffd7d7; border-radius: 14px; color: #9d2525; background: #fff5f5; font-size: 13px; line-height: 1.5; }
            .auth-alert svg { flex: 0 0 auto; width: 18px; height: 18px; margin-top: 1px; fill: none; stroke: currentColor; stroke-width: 2; }
            .auth-form { margin-top: 30px; }
            .auth-field { display: grid; gap: 9px; margin-top: 17px; }
            .auth-field:first-child { margin-top: 0; }
            .auth-field__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .auth-field label { font-size: 13px; font-weight: 800; }
            .auth-optional { color: #899692; font-size: 11px; font-weight: 600; }
            .auth-control { position: relative; }
            .auth-control__leading { position: absolute; left: 16px; top: 50%; width: 19px; height: 19px; transform: translateY(-50%); fill: none; stroke: #81908c; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; pointer-events: none; }
            .auth-control input { width: 100%; height: 54px; padding: 0 48px 0 48px; border: 1px solid var(--auth-line); border-radius: 15px; outline: none; color: var(--auth-ink); background: var(--auth-surface); font-size: 15px; transition: border-color .2s ease, box-shadow .2s ease, background .2s ease; }
            .auth-control input::placeholder { color: #9aa7a3; }
            .auth-control input:hover { border-color: #b8c8c3; }
            .auth-control input:focus { border-color: var(--auth-primary); box-shadow: 0 0 0 4px color-mix(in srgb, var(--auth-primary) 12%, transparent); }
            .auth-control input.is-invalid { border-color: var(--auth-danger); box-shadow: 0 0 0 4px rgba(201,42,42,.08); }
            .auth-password-toggle { position: absolute; right: 9px; top: 50%; width: 36px; height: 36px; display: grid; place-items: center; padding: 0; transform: translateY(-50%); border: 0; border-radius: 10px; color: #73817d; background: transparent; cursor: pointer; }
            .auth-password-toggle:hover { color: var(--auth-primary); background: #f0f5f3; }
            .auth-password-toggle svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
            .auth-field-error { min-height: 18px; color: var(--auth-danger); font-size: 12px; line-height: 1.45; }
            .auth-row { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-top: 9px; }
            .auth-check { display: inline-flex; align-items: center; gap: 9px; color: #596863; font-size: 13px; cursor: pointer; }
            .auth-check input { width: 17px; height: 17px; margin: 0; accent-color: var(--auth-primary); }
            .auth-help { color: var(--auth-primary); font-size: 12px; font-weight: 750; }
            .auth-button { width: 100%; min-height: 56px; display: inline-flex; align-items: center; justify-content: center; gap: 10px; margin-top: 24px; padding: 0 22px; border: 0; border-radius: 16px; color: #fff; background: var(--auth-primary); box-shadow: 0 16px 32px color-mix(in srgb, var(--auth-primary) 24%, transparent); font-weight: 850; cursor: pointer; transition: transform .2s ease, filter .2s ease, box-shadow .2s ease; }
            .auth-button:hover { transform: translateY(-2px); filter: brightness(.94); box-shadow: 0 19px 38px color-mix(in srgb, var(--auth-primary) 31%, transparent); }
            .auth-button:active { transform: translateY(0); }
            .auth-button svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
            .auth-register { margin: 26px 0 0; padding-top: 24px; border-top: 1px solid var(--auth-line); color: #65736f; text-align: center; font-size: 14px; }
            .auth-register a { margin-left: 5px; color: var(--auth-primary); font-weight: 850; text-decoration: none; }
            .auth-register a:hover { text-decoration: underline; text-underline-offset: 4px; }
            @media (max-width: 1000px) {
                .auth-shell { grid-template-columns: minmax(320px, .8fr) minmax(460px, 1.2fr); }
                .auth-story { padding: 36px; }
                .auth-benefits { grid-template-columns: 1fr; }
                .auth-benefit { min-height: auto; display: flex; align-items: center; gap: 12px; padding: 13px 15px; }
                .auth-benefit svg { margin: 0; }
            }
            @media (max-width: 760px) {
                .auth-shell { display: block; background: #f7faf9; }
                .auth-story { min-height: 260px; padding: 24px 22px 52px; border-radius: 0 0 30px 30px; }
                .auth-story__content { padding: 42px 0 0; }
                .auth-story h1 { max-width: 500px; font-size: clamp(36px, 11vw, 54px); }
                .auth-story__lead { margin-top: 18px; font-size: 15px; }
                .auth-benefits, .auth-story__footer, .auth-brand__copy span { display: none; }
                .auth-main { min-height: auto; margin-top: -24px; padding: 24px 20px 44px; background: transparent; }
                .auth-home { position: static; width: fit-content; margin: 0 0 18px auto; padding: 8px 0; }
                .auth-card { padding: 20px 0 0; }
                .auth-card__icon { display: none; }
                .auth-card h2 { font-size: 34px; }
            }
            @media (max-width: 430px) {
                .auth-story { min-height: 230px; }
                .auth-story__lead { display: none; }
                .auth-row { align-items: flex-start; flex-direction: column; gap: 12px; }
            }
            @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; } }
        </style>
    </head>
    <body>
        <main class="auth-shell">
            <aside class="auth-story" aria-label="Giới thiệu hệ thống">
                <a class="auth-brand" href="{{ route('site.home') }}" aria-label="Về trang chủ {{ $siteName }}">
                    <span class="auth-brand__mark">
                        @if ($logoUrl !== '')
                            <img src="{{ $logoUrl }}" alt="Logo {{ $siteName }}">
                        @else
                            {{ $brandInitials !== '' ? $brandInitials : 'AIO' }}
                        @endif
                    </span>
                    <span class="auth-brand__copy"><strong>{{ $siteName }}</strong><span>Digital workspace</span></span>
                </a>

                <div class="auth-story__content">
                    <p class="auth-eyebrow">Một tài khoản, mọi tiện ích</p>
                    <h1>Chào mừng bạn trở lại.</h1>
                    <p class="auth-story__lead">Đăng nhập an toàn để tiếp tục quản lý công việc, theo dõi dịch vụ và sử dụng các tiện ích dành riêng cho tài khoản của bạn.</p>
                    <div class="auth-benefits" aria-label="Lợi ích tài khoản">
                        <div class="auth-benefit">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4.5 6v5.5c0 4.6 3 7.8 7.5 9.5 4.5-1.7 7.5-4.9 7.5-9.5V6L12 3Z"/><path d="m9 12 2 2 4-4"/></svg>
                            <span>Bảo mật và phân quyền rõ ràng</span>
                        </div>
                        <div class="auth-benefit">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9l8-5 8 5v10"/><path d="M8 19v-6h8v6M2 19h20"/></svg>
                            <span>Truy cập đúng không gian làm việc</span>
                        </div>
                        <div class="auth-benefit">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            <span>Thông tin được đồng bộ liên tục</span>
                        </div>
                    </div>
                </div>

                <div class="auth-story__footer"><span class="auth-status">Hệ thống đang hoạt động</span><span>© {{ now()->year }} {{ $siteName }}</span></div>
            </aside>

            <section class="auth-main">
                <a class="auth-home" href="{{ route('site.home') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    Về trang chủ
                </a>

                <div class="auth-card">
                    <div class="auth-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 15v2"/></svg>
                    </div>
                    <h2>Đăng nhập</h2>
                    <p class="auth-card__intro">Sử dụng email khách hàng hoặc tên đăng nhập quản trị để tiếp tục.</p>

                    @if ($errors->any())
                        <div class="auth-alert" role="alert">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form class="auth-form" method="POST" action="{{ route('customer.auth.store') }}" novalidate data-customer-auth-form="login">
                        @csrf
                        @if (filled($redirectTo))
                            <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
                        @endif
                        <div class="auth-field">
                            <div class="auth-field__head"><label for="login">Email hoặc tên đăng nhập</label></div>
                            <div class="auth-control">
                                <svg class="auth-control__leading" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>
                                <input id="login" name="login" type="text" value="{{ old('login', old('email')) }}" placeholder="email@domain.com hoặc username" required autofocus autocomplete="username" class="{{ $errors->has('login') || $errors->has('email') ? 'is-invalid' : '' }}" aria-describedby="login-error">
                            </div>
                            <div id="login-error" class="auth-field-error" data-field-error="login">{{ $errors->first('login') ?? $errors->first('email') }}</div>
                        </div>

                        <div class="auth-field">
                            <div class="auth-field__head"><label for="password">Mật khẩu</label></div>
                            <div class="auth-control">
                                <svg class="auth-control__leading" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                <input id="password" name="password" type="password" placeholder="Nhập mật khẩu của bạn" required autocomplete="current-password" class="{{ $errors->has('password') ? 'is-invalid' : '' }}" aria-describedby="password-error">
                                <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Hiện mật khẩu" aria-pressed="false">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                </button>
                            </div>
                            <div id="password-error" class="auth-field-error" data-field-error="password">{{ $errors->first('password') }}</div>
                        </div>

                        <div class="auth-field">
                            <div class="auth-field__head"><label for="two_factor_code">Mã xác thực hai lớp</label><span class="auth-optional">Nếu tài khoản đã bật 2FA</span></div>
                            <div class="auth-control">
                                <svg class="auth-control__leading" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M9 7h6M9 12h.01M12 12h.01M15 12h.01M9 16h.01M12 16h.01M15 16h.01"/></svg>
                                <input id="two_factor_code" name="two_factor_code" type="text" value="{{ old('two_factor_code') }}" placeholder="6 chữ số hoặc mã khôi phục" inputmode="numeric" autocomplete="one-time-code" maxlength="32" class="{{ $errors->has('two_factor_code') ? 'is-invalid' : '' }}" aria-describedby="two-factor-error">
                            </div>
                            <div id="two-factor-error" class="auth-field-error" data-field-error="two_factor_code">{{ $errors->first('two_factor_code') }}</div>
                        </div>

                        <div class="auth-row">
                            <label class="auth-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Duy trì đăng nhập</span></label>
                            <span class="auth-help">Thông tin được bảo vệ an toàn</span>
                        </div>

                        <button class="auth-button" type="submit">
                            <span>Đăng nhập vào hệ thống</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                        </button>
                    </form>

                    <p class="auth-register">Chưa có tài khoản?<a href="{{ route('customer.auth.register') }}">Đăng ký ngay</a></p>
                </div>
            </section>
        </main>
        <script>
            (() => {
                const form = document.querySelector('[data-customer-auth-form="login"]');
                const passwordInput = document.querySelector('#password');
                const passwordToggle = document.querySelector('[data-password-toggle]');

                passwordToggle?.addEventListener('click', () => {
                    const shouldShow = passwordInput?.type === 'password';
                    if (passwordInput) passwordInput.type = shouldShow ? 'text' : 'password';
                    passwordToggle.setAttribute('aria-pressed', String(shouldShow));
                    passwordToggle.setAttribute('aria-label', shouldShow ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
                });

                if (!form) return;

                const setFieldError = (field, message) => {
                    const input = form.querySelector(`[name="${field}"]`);
                    const error = form.querySelector(`[data-field-error="${field}"]`);
                    input?.classList.toggle('is-invalid', Boolean(message));
                    input?.setAttribute('aria-invalid', String(Boolean(message)));
                    if (error) error.textContent = message || '';
                };

                form.addEventListener('submit', (event) => {
                    const login = String(form.login.value || '').trim();
                    const password = String(form.password.value || '');
                    let hasError = false;

                    setFieldError('login', '');
                    setFieldError('password', '');

                    if (!login) {
                        setFieldError('login', 'Vui lòng nhập email hoặc tên đăng nhập.');
                        hasError = true;
                    }

                    if (!password) {
                        setFieldError('password', 'Vui lòng nhập mật khẩu.');
                        hasError = true;
                    }

                    if (hasError) event.preventDefault();
                });
            })();
        </script>
    </body>
</html>

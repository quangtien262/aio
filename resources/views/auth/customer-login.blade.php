<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'AIO Platform') }} Login</title>
        @vite('resources/css/app.css')
        <style>
            body { margin: 0; font-family: 'Segoe UI', sans-serif; background: linear-gradient(180deg, #f4fbf8 0%, #ffffff 100%); color: #16302b; }
            .shell { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
            .panel { width: min(460px, 100%); background: #fff; border: 1px solid #dbe7e4; border-radius: 24px; padding: 28px; box-shadow: 0 30px 90px rgba(22, 48, 43, 0.08); }
            .kicker { text-transform: uppercase; letter-spacing: .14em; font-size: 12px; color: #0f766e; margin-bottom: 10px; }
            h1 { margin: 0 0 8px; font-size: 32px; }
            p { color: #56736c; line-height: 1.6; }
            .field { display: grid; gap: 8px; margin-top: 16px; }
            label { font-weight: 600; font-size: 14px; }
            input { padding: 12px 14px; border: 1px solid #cbdad6; border-radius: 12px; font: inherit; }
            .button { width: 100%; margin-top: 20px; padding: 14px 18px; border: 0; border-radius: 14px; background: #0f766e; color: #fff; font-weight: 700; cursor: pointer; }
            .error { margin-top: 12px; padding: 12px 14px; border-radius: 12px; background: #fff2f2; color: #9f2d2d; }
            .links { display: flex; justify-content: space-between; gap: 12px; margin-top: 18px; font-size: 14px; }
            a { color: #0f766e; text-decoration: none; }
        </style>
    </head>
    <body>
        <main class="shell">
            <section class="panel">
                <div class="kicker">Customer Guard</div>
                <h1>Đăng nhập khách hàng</h1>
                <p>Khu vực dành cho khách đăng ký tài khoản trên website.</p>

                @if ($errors->any())
                    <div class="error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('customer.auth.store') }}">
                    @csrf
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', 'customer@aio.local') }}" required autofocus>
                    </div>
                    <div class="field">
                        <label for="password">Mật khẩu</label>
                        <input id="password" name="password" type="password" value="password" required>
                    </div>
                    <button class="button" type="submit">Đăng nhập</button>
                </form>

                <div class="links">
                    <a href="{{ route('customer.auth.register') }}">Đăng ký tài khoản</a>
                    <a href="{{ route('admin.auth.login') }}">Đăng nhập admin</a>
                </div>
            </section>
        </main>
    </body>
</html>

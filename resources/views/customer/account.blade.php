<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'AIO Platform') }} Account</title>
        @vite('resources/css/app.css')
        <style>
            body { margin: 0; font-family: 'Segoe UI', sans-serif; background: linear-gradient(180deg, #f4fbf8 0%, #ffffff 100%); color: #16302b; }
            .shell { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
            .panel { width: min(900px, 100%); background: #fff; border: 1px solid #dbe7e4; border-radius: 24px; padding: 28px; box-shadow: 0 30px 90px rgba(22, 48, 43, 0.08); }
            .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 22px; }
            .card { padding: 18px; border-radius: 18px; background: #f8fbfa; border: 1px solid #dbe7e4; }
            .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
            .button { display: inline-flex; align-items: center; justify-content: center; padding: 14px 18px; border: 0; border-radius: 14px; background: #0f766e; color: #fff; font-weight: 700; text-decoration: none; cursor: pointer; }
            .button-secondary { background: #edf6f3; color: #16302b; }
        </style>
    </head>
    <body>
        <main class="shell">
            <section class="panel">
                <div style="text-transform: uppercase; letter-spacing: .14em; font-size: 12px; color: #0f766e; margin-bottom: 10px;">Customer Portal</div>
                <h1 style="margin: 0 0 10px;">Xin chào, {{ auth('customer')->user()->name }}</h1>
                <p style="color: #56736c; line-height: 1.7; margin: 0;">Tài khoản customer đã được tách guard riêng khỏi khu vực admin. Về sau trang này sẽ là điểm vào cho đơn hàng, profile, loyalty và các module front-office.</p>

                <div class="grid">
                    <div class="card">
                        <strong>Email</strong>
                        <div>{{ auth('customer')->user()->email }}</div>
                    </div>
                    <div class="card">
                        <strong>Phone</strong>
                        <div>{{ auth('customer')->user()->phone ?: 'Chưa cập nhật' }}</div>
                    </div>
                    <div class="card">
                        <strong>Guard</strong>
                        <div>customer</div>
                    </div>
                </div>

                <div class="actions">
                    <a class="button button-secondary" href="{{ route('site.home') }}">Về website</a>
                    <form method="POST" action="{{ route('customer.auth.logout') }}">
                        @csrf
                        <button class="button" type="submit">Đăng xuất</button>
                    </form>
                </div>
            </section>
        </main>
    </body>
</html>

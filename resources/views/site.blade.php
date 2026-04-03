<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'AIO Platform') }}</title>
        @vite('resources/css/app.css')
        <style>
            body {
                margin: 0;
                font-family: 'Segoe UI', sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(15, 118, 110, 0.18), transparent 28%),
                    linear-gradient(180deg, #f4fbf8 0%, #ffffff 100%);
                color: #16302b;
            }

            .shell {
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 32px;
            }

            .panel {
                width: min(960px, 100%);
                background: rgba(255, 255, 255, 0.86);
                border: 1px solid #dbe7e4;
                border-radius: 24px;
                padding: 32px;
                box-shadow: 0 30px 90px rgba(22, 48, 43, 0.08);
                backdrop-filter: blur(14px);
            }

            .kicker {
                text-transform: uppercase;
                letter-spacing: 0.14em;
                font-size: 12px;
                color: #0f766e;
                margin-bottom: 10px;
            }

            h1 {
                font-size: clamp(32px, 5vw, 56px);
                line-height: 1.05;
                margin: 0 0 16px;
            }

            p {
                font-size: 18px;
                line-height: 1.7;
                color: #46635c;
            }

            .actions {
                display: flex;
                flex-wrap: wrap;
                gap: 14px;
                margin-top: 28px;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 14px 18px;
                border-radius: 14px;
                text-decoration: none;
                font-weight: 600;
            }

            .button-primary {
                background: #0f766e;
                color: #fff;
            }

            .button-secondary {
                background: #edf6f3;
                color: #16302b;
            }

            .grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 16px;
                margin-top: 28px;
            }

            .card {
                padding: 18px;
                border-radius: 18px;
                background: #f8fbfa;
                border: 1px solid #dbe7e4;
            }

            .card strong {
                display: block;
                margin-bottom: 8px;
            }
        </style>
    </head>
    <body>
        <main class="shell">
            <section class="panel">
                <div class="kicker">AIO Website Platform</div>
                <h1>Nền tảng Laravel 13 để bán nhiều website, nhiều module, nhiều theme.</h1>
                <p>
                    Base source đã được khởi tạo theo hướng modular monolith, có admin shell React + Ant Design,
                    sẵn chỗ cho module store, theme engine, setup wizard và phân quyền theo module.
                </p>

                <div class="actions">
                    <a class="button button-primary" href="{{ route('admin.index') }}">Vào Admin Shell</a>
                    <a class="button button-secondary" href="{{ route('customer.auth.login') }}">Đăng nhập khách hàng</a>
                    <a class="button button-secondary" href="{{ route('customer.auth.register') }}">Đăng ký tài khoản</a>
                    <a class="button button-secondary" href="/docs/architecture/aio-overall-architecture.svg">Xem sơ đồ kiến trúc</a>
                </div>

                <div class="grid">
                    <article class="card">
                        <strong>Modules</strong>
                        Cấu trúc dành cho CRM, kho, sale, kế toán, CMS và các module nghiệp vụ cài đặt theo store.
                    </article>
                    <article class="card">
                        <strong>Themes</strong>
                        Theme engine tách khỏi dữ liệu nghiệp vụ, hỗ trợ đổi giao diện theo từng loại website.
                    </article>
                    <article class="card">
                        <strong>Admin</strong>
                        React + Vite + Ant Design được mount riêng để phát triển dashboard và store quản trị.
                    </article>
                </div>
            </section>
        </main>
    </body>
</html>

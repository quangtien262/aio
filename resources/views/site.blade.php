@extends('layouts.site')
@section('content')
        <main class="shell">
            <section class="panel">
                <div class="kicker">AIO Website Platform</div>
                <h1>AI MANAGER BUSINESS</h1>
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
@endsection

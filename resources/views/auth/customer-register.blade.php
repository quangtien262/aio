<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'AIO Platform') }} Register</title>
        @vite('resources/css/app.css')
        <style>
            body { margin: 0; font-family: 'Segoe UI', sans-serif; background: linear-gradient(180deg, #f4fbf8 0%, #ffffff 100%); color: #16302b; }
            .shell { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
            .panel { width: min(520px, 100%); background: #fff; border: 1px solid #dbe7e4; border-radius: 24px; padding: 28px; box-shadow: 0 30px 90px rgba(22, 48, 43, 0.08); }
            .kicker { text-transform: uppercase; letter-spacing: .14em; font-size: 12px; color: #0f766e; margin-bottom: 10px; }
            h1 { margin: 0 0 8px; font-size: 32px; }
            .field { display: grid; gap: 8px; margin-top: 16px; }
            label { font-weight: 600; font-size: 14px; }
            input { padding: 12px 14px; border: 1px solid #cbdad6; border-radius: 12px; font: inherit; }
            input.is-invalid { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08); }
            .field-error { min-height: 18px; font-size: 13px; line-height: 1.4; color: #dc2626; }
            .button { width: 100%; margin-top: 20px; padding: 14px 18px; border: 0; border-radius: 14px; background: #0f766e; color: #fff; font-weight: 700; cursor: pointer; }
            .error { margin-top: 12px; padding: 12px 14px; border-radius: 12px; background: #fff2f2; color: #9f2d2d; }
            .links { display: flex; justify-content: space-between; gap: 12px; margin-top: 18px; font-size: 14px; }
            a, p { color: #56736c; text-decoration: none; }
        </style>
    </head>
    <body>
        <main class="shell">
            <section class="panel">
                <div class="kicker">Customer Registration</div>
                <h1>Tạo tài khoản khách hàng</h1>
                <p>Tài khoản này dùng cho khu vực website và các module hướng khách hàng.</p>

                @if ($errors->any())
                    <div class="error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('customer.auth.register.store') }}" novalidate data-customer-auth-form="register">
                    @csrf
                    <div class="field">
                        <label for="name">Họ và tên</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required class="{{ $errors->has('name') ? 'is-invalid' : '' }}">
                        <div class="field-error" data-field-error="name">{{ $errors->first('name') }}</div>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required class="{{ $errors->has('email') ? 'is-invalid' : '' }}">
                        <div class="field-error" data-field-error="email">{{ $errors->first('email') }}</div>
                    </div>
                    <div class="field">
                        <label for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="{{ $errors->has('phone') ? 'is-invalid' : '' }}">
                        <div class="field-error" data-field-error="phone">{{ $errors->first('phone') }}</div>
                    </div>
                    <div class="field">
                        <label for="password">Mật khẩu</label>
                        <input id="password" name="password" type="password" required class="{{ $errors->has('password') ? 'is-invalid' : '' }}">
                        <div class="field-error" data-field-error="password">{{ $errors->first('password') }}</div>
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Xác nhận mật khẩu</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="{{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}">
                        <div class="field-error" data-field-error="password_confirmation">{{ $errors->first('password_confirmation') }}</div>
                    </div>
                    <button class="button" type="submit">Đăng ký</button>
                </form>

                <div class="links">
                    <a href="{{ route('customer.auth.login') }}">Đã có tài khoản?</a>
                    <a href="{{ route('admin.auth.login') }}">Khu vực admin</a>
                </div>
            </section>
        </main>
        <script>
            (() => {
                const form = document.querySelector('[data-customer-auth-form="register"]');

                if (!form) {
                    return;
                }

                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const setFieldError = (field, message) => {
                    const input = form.querySelector(`[name="${field}"]`);
                    const error = form.querySelector(`[data-field-error="${field}"]`);

                    input?.classList.toggle('is-invalid', Boolean(message));

                    if (error) {
                        error.textContent = message || '';
                    }
                };

                form.addEventListener('submit', (event) => {
                    const name = String(form.name.value || '').trim();
                    const email = String(form.email.value || '').trim();
                    const phone = String(form.phone.value || '').trim();
                    const password = String(form.password.value || '');
                    const passwordConfirmation = String(form.password_confirmation.value || '');
                    let hasError = false;

                    ['name', 'email', 'phone', 'password', 'password_confirmation'].forEach((field) => setFieldError(field, ''));

                    if (!name) {
                        setFieldError('name', 'Vui lòng nhập họ và tên.');
                        hasError = true;
                    }

                    if (!email) {
                        setFieldError('email', 'Vui lòng nhập email.');
                        hasError = true;
                    } else if (!emailPattern.test(email)) {
                        setFieldError('email', 'Email không đúng định dạng.');
                        hasError = true;
                    }

                    if (phone && phone.length > 30) {
                        setFieldError('phone', 'Số điện thoại không được quá 30 ký tự.');
                        hasError = true;
                    }

                    if (!password) {
                        setFieldError('password', 'Vui lòng nhập mật khẩu.');
                        hasError = true;
                    } else if (password.length < 8) {
                        setFieldError('password', 'Mật khẩu phải có ít nhất 8 ký tự.');
                        hasError = true;
                    }

                    if (!passwordConfirmation) {
                        setFieldError('password_confirmation', 'Vui lòng xác nhận mật khẩu.');
                        hasError = true;
                    } else if (passwordConfirmation !== password) {
                        setFieldError('password_confirmation', 'Xác nhận mật khẩu không khớp.');
                        hasError = true;
                    }

                    if (hasError) {
                        event.preventDefault();
                    }
                });
            })();
        </script>
    </body>
</html>

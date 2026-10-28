<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Đăng nhập nhà thuốc | AIO</title>
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #edf5f3; color: #182737; font: 16px/1.5 system-ui, sans-serif; }
            main { width: min(100%, 440px); padding: 32px; background: white; border: 1px solid #d7e0e9; border-radius: 16px; box-shadow: 0 12px 36px #1827370b; }
            h1 { margin: 0 0 8px; font-size: 25px; } p { margin: 0 0 24px; color: #536679; }
            label { display: block; margin: 18px 0 6px; font-weight: 600; }
            input:not([type="checkbox"]) { width: 100%; padding: 11px; border: 1px solid #b8c6d4; border-radius: 8px; font: inherit; }
            input:focus { outline: 3px solid #b9d9ff; border-color: #087e77; }
            .remember { display: flex; align-items: center; gap: 8px; font-weight: 400; }
            .errors { margin: 18px 0; padding: 12px 16px; border-radius: 8px; background: #fff0f0; color: #9d2020; }
            .errors ul { margin: 0; padding-left: 18px; }
            button { margin-top: 24px; width: 100%; border: 0; border-radius: 8px; padding: 12px; background: #087e77; color: white; font: inherit; font-weight: 600; cursor: pointer; }
            small { display: block; margin-top: 6px; color: #536679; }
        </style>
    </head>
    <body>
        <main>
            <h1>Đăng nhập nhà thuốc</h1>
            <p>Dùng tài khoản quản trị đã được cấp quyền nhà thuốc.</p>
            @if ($errors->any())
                <div class="errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="post" action="{{ route('admin.pharmacy.login.store') }}">
                @csrf
                <label for="login">Tên đăng nhập hoặc email</label>
                <input id="login" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus maxlength="255">
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <label for="two_factor_code">Mã xác thực hai lớp hoặc mã khôi phục</label>
                <input id="two_factor_code" name="two_factor_code" autocomplete="one-time-code" maxlength="32">
                <small>Điền khi tài khoản đã bật xác thực hai lớp.</small>
                <label class="remember"><input name="remember" type="checkbox" value="1"> Ghi nhớ đăng nhập</label>
                <button type="submit">Đăng nhập</button>
            </form>
        </main>
    </body>
</html>

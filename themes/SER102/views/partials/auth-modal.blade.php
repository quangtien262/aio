@unless (auth('customer')->check())
    <style>
        .xd-auth-modal[hidden] { display: none !important; }
        .xd-auth-modal { position: fixed; inset: 0; z-index: 9999; display: grid; place-items: center; padding: 24px; }
        .xd-auth-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .72); backdrop-filter: blur(4px); }
        .xd-auth-card { position: relative; width: min(560px, 100%); max-height: calc(100vh - 48px); overflow: auto; background: #fff; color: #20262d; border-radius: 8px; padding: 30px; box-shadow: 0 30px 90px rgba(0, 0, 0, .32); }
        .xd-auth-close { position: absolute; top: 16px; right: 16px; width: 38px; height: 38px; border: 0; border-radius: 999px; background: #f2f4f7; color: #20262d; font-size: 24px; line-height: 1; cursor: pointer; }
        .xd-auth-title { margin: 0; font-size: 30px; line-height: 1.15; color: #20262d; }
        .xd-auth-note { margin: 10px 0 18px; color: #66717d; line-height: 1.6; font-weight: 600; }
        .xd-auth-tabs { display: flex; gap: 8px; padding: 6px; border: 1px solid #e5e8ec; border-radius: 999px; margin-bottom: 18px; }
        .xd-auth-tab { flex: 1; border: 0; border-radius: 999px; padding: 12px 16px; background: transparent; color: #20262d; font-weight: 800; cursor: pointer; }
        .xd-auth-tab.is-active { background: #71458a; color: #fff; box-shadow: 0 12px 24px rgba(113, 69, 138, .24); }
        .xd-auth-panel { display: none; }
        .xd-auth-panel.is-active { display: block; }
        .xd-auth-form { display: grid; gap: 14px; }
        .xd-auth-field { display: grid; gap: 7px; font-weight: 750; color: #20262d; }
        .xd-auth-field input { width: 100%; border: 1px solid #dde3ea; border-radius: 8px; padding: 13px 14px; font: inherit; color: #20262d; background: #fff; }
        .xd-auth-check { display: flex; align-items: center; gap: 9px; color: #66717d; font-weight: 700; }
        .xd-auth-submit { border: 0; border-radius: 8px; padding: 14px 18px; background: #71458a; color: #fff; font-weight: 900; text-transform: uppercase; cursor: pointer; box-shadow: 0 12px 26px rgba(113, 69, 138, .22); }
        .xd-auth-submit:disabled { opacity: .65; cursor: progress; }
        .xd-auth-feedback { margin: 16px 0 0; padding: 12px 14px; border-radius: 8px; background: #fff1ef; color: #bd1f2d; font-weight: 750; }
        .xd-auth-feedback.is-success { background: #eff9e8; color: #3f7a1f; }
        button.xd-login-button, button.xd-button, button.xd-btn { font: inherit; cursor: pointer; }
        @media (max-width: 640px) {
            .xd-auth-card { padding: 24px 18px; }
            .xd-auth-title { font-size: 24px; }
        }
    </style>

    <div class="xd-auth-modal" data-xd-auth-modal hidden>
        <div class="xd-auth-backdrop" data-xd-auth-close></div>
        <section class="xd-auth-card" role="dialog" aria-modal="true" aria-labelledby="xd-auth-title">
            <button type="button" class="xd-auth-close" aria-label="Đóng" data-xd-auth-close>&times;</button>
            <h2 id="xd-auth-title" class="xd-auth-title">Tài khoản khách hàng</h2>
            <p class="xd-auth-note">Đăng nhập để lưu thông tin tư vấn, hoặc đăng ký nhanh nếu bạn chưa có tài khoản.</p>

            <div class="xd-auth-tabs" role="tablist" aria-label="Tài khoản khách hàng">
                <button type="button" class="xd-auth-tab is-active" role="tab" aria-selected="true" data-xd-auth-tab="login">Đăng nhập</button>
                <button type="button" class="xd-auth-tab" role="tab" aria-selected="false" data-xd-auth-tab="register">Đăng ký</button>
            </div>

            <div class="xd-auth-panel is-active" data-xd-auth-panel="login">
                <form class="xd-auth-form" action="{{ route('customer.auth.store') }}" method="post" data-xd-auth-form="login">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    <label class="xd-auth-field">
                        <span>Email khách hàng / Username admin</span>
                        <input type="text" name="login" autocomplete="username" required>
                    </label>
                    <label class="xd-auth-field">
                        <span>Mật khẩu</span>
                        <input type="password" name="password" autocomplete="current-password" required>
                    </label>
                    <label class="xd-auth-check">
                        <input type="checkbox" name="remember" value="1">
                        <span>Ghi nhớ đăng nhập</span>
                    </label>
                    <button type="submit" class="xd-auth-submit">Đăng nhập</button>
                </form>
            </div>

            <div class="xd-auth-panel" data-xd-auth-panel="register">
                <form class="xd-auth-form" action="{{ route('customer.auth.register.store') }}" method="post" data-xd-auth-form="register">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    <label class="xd-auth-field">
                        <span>Họ tên</span>
                        <input type="text" name="name" autocomplete="name" required>
                    </label>
                    <label class="xd-auth-field">
                        <span>Email</span>
                        <input type="email" name="email" autocomplete="email" required>
                    </label>
                    <label class="xd-auth-field">
                        <span>Số điện thoại</span>
                        <input type="tel" name="phone" autocomplete="tel">
                    </label>
                    <label class="xd-auth-field">
                        <span>Mật khẩu</span>
                        <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                    </label>
                    <label class="xd-auth-field">
                        <span>Nhập lại mật khẩu</span>
                        <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                    </label>
                    <button type="submit" class="xd-auth-submit">Tạo tài khoản</button>
                </form>
            </div>

            <p class="xd-auth-feedback" data-xd-auth-feedback hidden></p>
        </section>
    </div>

    <script>
        (() => {
            if (window.__ser102AuthModalReady) return;
            window.__ser102AuthModalReady = true;

            const modal = document.querySelector('[data-xd-auth-modal]');
            const feedback = document.querySelector('[data-xd-auth-feedback]');
            const tabs = Array.from(document.querySelectorAll('[data-xd-auth-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-xd-auth-panel]'));

            const setFeedback = (message = '', isSuccess = false) => {
                if (!feedback) return;
                feedback.textContent = message;
                feedback.hidden = message === '';
                feedback.classList.toggle('is-success', isSuccess);
            };

            const setTab = (tab) => {
                tabs.forEach((button) => {
                    const active = button.dataset.xdAuthTab === tab;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.xdAuthPanel === tab));
                setFeedback();
            };

            const openModal = (tab = 'login') => {
                if (!modal) return;
                setTab(tab);
                modal.hidden = false;
                document.body.style.overflow = 'hidden';
                window.setTimeout(() => modal.querySelector('input:not([type="hidden"])')?.focus(), 30);
            };

            const closeModal = () => {
                if (!modal) return;
                modal.hidden = true;
                document.body.style.overflow = '';
                setFeedback();
            };

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-xd-auth-open]');
                if (trigger) {
                    event.preventDefault();
                    openModal(trigger.dataset.xdAuthOpen || 'login');
                    return;
                }

                if (event.target.closest('[data-xd-auth-close]')) {
                    event.preventDefault();
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal && !modal.hidden) closeModal();
            });

            tabs.forEach((button) => button.addEventListener('click', () => setTab(button.dataset.xdAuthTab || 'login')));

            document.querySelectorAll('[data-xd-auth-form]').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const submit = form.querySelector('[type="submit"]');
                    const original = submit?.textContent || '';
                    submit?.setAttribute('disabled', 'disabled');
                    if (submit) submit.textContent = 'Đang xử lý...';
                    setFeedback();

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            body: new FormData(form),
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || payload?.success === false) {
                            const message = payload?.message || Object.values(payload?.errors || {})?.flat()?.[0] || 'Không xử lý được yêu cầu.';
                            setFeedback(message);
                            return;
                        }

                        setFeedback(payload?.message || 'Thành công. Đang chuyển trang...', true);
                        window.setTimeout(() => {
                            window.location.href = payload?.redirect_to || form.querySelector('[name="redirect_to"]')?.value || window.location.href;
                        }, 500);
                    } catch (error) {
                        setFeedback('Không xử lý được yêu cầu.');
                    } finally {
                        submit?.removeAttribute('disabled');
                        if (submit) submit.textContent = original;
                    }
                });
            });
        })();
    </script>
@endunless

@unless (auth('customer')->check())
    <style>
        button.dr-account-link{border:0;background:transparent;padding:0;color:inherit;font:inherit;cursor:pointer}
        .dr-auth-modal[hidden]{display:none!important}
        .dr-auth-modal{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;padding:24px}
        .dr-auth-backdrop{position:absolute;inset:0;background:rgba(2,18,17,.78);backdrop-filter:blur(5px)}
        .dr-auth-card{position:relative;width:min(560px,100%);max-height:calc(100vh - 48px);overflow:auto;padding:30px;border:1px solid rgba(221,161,73,.32);border-radius:24px;background:#fff;color:#163a35;box-shadow:0 32px 90px rgba(0,0,0,.42)}
        .dr-auth-close{position:absolute;top:16px;right:16px;display:grid;place-items:center;width:40px;height:40px;border:0;border-radius:50%;background:#edf3f1;color:#163a35;font-size:25px;cursor:pointer}
        .dr-auth-title{margin:0 48px 8px 0;color:#0d3b35;font:700 34px/1.2 'Dancing Script',cursive}
        .dr-auth-note{margin:0 0 20px;color:#647773;font-size:14px;line-height:1.65}
        .dr-auth-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin-bottom:20px;padding:6px;border:1px solid #dce7e4;border-radius:999px;background:#f5f8f7}
        .dr-auth-tab{min-height:42px;border:0;border-radius:999px;background:transparent;color:#163a35;font:700 13px 'Be Vietnam Pro',sans-serif;cursor:pointer}
        .dr-auth-tab.is-active{background:var(--dr-gold);color:#fff;box-shadow:0 10px 24px rgba(221,161,73,.3)}
        .dr-auth-panel{display:none}.dr-auth-panel.is-active{display:block}
        .dr-auth-form{display:grid;gap:14px}
        .dr-auth-field{display:grid;gap:7px;color:#294b46;font-size:13px;font-weight:700}
        .dr-auth-field input{width:100%;min-height:48px;padding:0 14px;border:1px solid #d6e2df;border-radius:12px;background:#fff;color:#163a35;font:inherit;outline:0}
        .dr-auth-field input:focus{border-color:var(--dr-gold);box-shadow:0 0 0 4px rgba(221,161,73,.14)}
        .dr-auth-check{display:flex;align-items:center;gap:8px;color:#647773;font-size:14px}
        .dr-auth-submit{min-height:50px;border:0;border-radius:12px;background:var(--dr-green);color:#fff;font:700 14px 'Be Vietnam Pro',sans-serif;cursor:pointer}
        .dr-auth-submit:hover{background:var(--dr-gold)}.dr-auth-submit:disabled{opacity:.65;cursor:progress}
        .dr-auth-feedback{margin:16px 0 0;padding:12px 14px;border-radius:12px;background:#fff0f0;color:#b42318;font-size:14px;font-weight:600}
        .dr-auth-feedback.is-success{background:#edf8ef;color:#26733c}
        body.dr-auth-open{overflow:hidden}
        @media(max-width:640px){.dr-auth-modal{align-items:end;padding:0}.dr-auth-card{width:100%;max-height:90vh;padding:26px 18px;border-radius:22px 22px 0 0}.dr-auth-title{font-size:30px}}
    </style>

    <div class="dr-auth-modal" data-dr-auth-modal hidden>
        <div class="dr-auth-backdrop" data-dr-auth-close></div>
        <section class="dr-auth-card" role="dialog" aria-modal="true" aria-labelledby="dr-auth-title">
            <button type="button" class="dr-auth-close" aria-label="Đóng" data-dr-auth-close>&times;</button>
            <h2 id="dr-auth-title" class="dr-auth-title">Tài khoản</h2>
            <p class="dr-auth-note">Đăng nhập bằng email khách hàng hoặc username admin; đăng ký nhanh nếu bạn chưa có tài khoản.</p>

            <div class="dr-auth-tabs" role="tablist" aria-label="Đăng nhập hoặc đăng ký">
                <button type="button" class="dr-auth-tab is-active" role="tab" aria-selected="true" data-dr-auth-tab="login">Đăng nhập</button>
                <button type="button" class="dr-auth-tab" role="tab" aria-selected="false" data-dr-auth-tab="register">Đăng ký</button>
            </div>

            <div class="dr-auth-panel is-active" data-dr-auth-panel="login">
                <form class="dr-auth-form" action="{{ route('customer.auth.store') }}" method="post" data-dr-auth-form>
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    <label class="dr-auth-field"><span>Email khách hàng / Username admin</span><input type="text" name="login" autocomplete="username" required></label>
                    <label class="dr-auth-field"><span>Mật khẩu</span><input type="password" name="password" autocomplete="current-password" required></label>
                    <label class="dr-auth-check"><input type="checkbox" name="remember" value="1"><span>Ghi nhớ đăng nhập</span></label>
                    <button type="submit" class="dr-auth-submit">Đăng nhập</button>
                </form>
            </div>

            <div class="dr-auth-panel" data-dr-auth-panel="register">
                <form class="dr-auth-form" action="{{ route('customer.auth.register.store') }}" method="post" data-dr-auth-form>
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    <label class="dr-auth-field"><span>Họ tên</span><input type="text" name="name" autocomplete="name" required></label>
                    <label class="dr-auth-field"><span>Email</span><input type="email" name="email" autocomplete="email" required></label>
                    <label class="dr-auth-field"><span>Số điện thoại</span><input type="tel" name="phone" autocomplete="tel"></label>
                    <label class="dr-auth-field"><span>Mật khẩu</span><input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                    <label class="dr-auth-field"><span>Nhập lại mật khẩu</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
                    <button type="submit" class="dr-auth-submit">Tạo tài khoản</button>
                </form>
            </div>

            <p class="dr-auth-feedback" data-dr-auth-feedback hidden></p>
        </section>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-dr-auth-modal]');
            if (!modal || modal.dataset.ready === '1') return;
            modal.dataset.ready = '1';

            const tabs = [...modal.querySelectorAll('[data-dr-auth-tab]')];
            const panels = [...modal.querySelectorAll('[data-dr-auth-panel]')];
            const feedback = modal.querySelector('[data-dr-auth-feedback]');

            const setFeedback = (message = '', success = false) => {
                feedback.textContent = message;
                feedback.hidden = message === '';
                feedback.classList.toggle('is-success', success);
            };
            const selectTab = (name) => {
                tabs.forEach((tab) => {
                    const active = tab.dataset.drAuthTab === name;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.drAuthPanel === name));
                setFeedback();
            };
            const openModal = (name = 'login') => {
                selectTab(name);
                modal.hidden = false;
                document.body.classList.add('dr-auth-open');
                window.setTimeout(() => modal.querySelector('.dr-auth-panel.is-active input:not([type="hidden"])')?.focus(), 30);
            };
            const closeModal = () => {
                modal.hidden = true;
                document.body.classList.remove('dr-auth-open');
                setFeedback();
            };

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-dr-auth-open]');
                if (!trigger) return;
                event.preventDefault();
                openModal(trigger.dataset.drAuthOpen || 'login');
            });
            modal.querySelectorAll('[data-dr-auth-close]').forEach((button) => button.addEventListener('click', closeModal));
            tabs.forEach((tab) => tab.addEventListener('click', () => selectTab(tab.dataset.drAuthTab || 'login')));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.hidden) closeModal();
            });

            modal.querySelectorAll('[data-dr-auth-form]').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const submit = form.querySelector('[type="submit"]');
                    const originalLabel = submit.textContent;
                    setFeedback();
                    submit.disabled = true;
                    submit.textContent = 'Đang xử lý...';
                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: new FormData(form),
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const firstError = Object.values(payload.errors || {}).flat()[0];
                            throw new Error(firstError || payload.message || 'Không xử lý được yêu cầu.');
                        }
                        setFeedback(payload.message || 'Thành công. Đang chuyển trang...', true);
                        window.location.href = payload.data?.redirect_to || window.location.href;
                    } catch (error) {
                        setFeedback(error.message || 'Không xử lý được yêu cầu.');
                    } finally {
                        submit.disabled = false;
                        submit.textContent = originalLabel;
                    }
                });
            });
        })();
    </script>
@endunless

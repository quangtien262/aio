@if(!auth('customer')->check() && !auth('admin')->check())
    <style>
        .dn350-auth-modal[hidden]{display:none!important}.dn350-auth-modal{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;padding:24px}.dn350-auth-backdrop{position:absolute;inset:0;background:rgba(30,42,70,.82);backdrop-filter:blur(6px)}.dn350-auth-card{position:relative;width:min(560px,100%);max-height:calc(100vh - 48px);overflow:auto;padding:32px;border:1px solid rgba(237,207,148,.55);background:#fff;color:var(--dn-navy);box-shadow:0 34px 90px rgba(18,27,48,.42)}.dn350-auth-close{position:absolute;top:16px;right:16px;width:40px;height:40px;border:0;border-radius:50%;background:#f4ead3;color:var(--dn-navy);font-size:26px;cursor:pointer}.dn350-auth-title{margin:0 48px 8px 0;font:700 32px/1.2 var(--dn-display);text-transform:uppercase}.dn350-auth-note{margin:0 0 20px;color:#68738c;line-height:1.65}.dn350-auth-tabs{display:grid;grid-template-columns:repeat(2,1fr);gap:7px;margin-bottom:20px;padding:6px;background:#f5f2eb}.dn350-auth-tab{min-height:44px;border:0;background:transparent;color:var(--dn-navy);font:700 14px var(--dn-body);cursor:pointer}.dn350-auth-tab.is-active{background:var(--dn-navy);color:#fff}.dn350-auth-panel{display:none}.dn350-auth-panel.is-active{display:block}.dn350-auth-form{display:grid;gap:14px}.dn350-auth-field{display:grid;gap:7px;font-size:13px;font-weight:700}.dn350-auth-field input{width:100%;min-height:50px;padding:0 14px;border:1px solid #dce0e8;background:#fff;color:var(--dn-navy);font:inherit;outline:0}.dn350-auth-field input:focus{border-color:var(--dn-navy);box-shadow:0 0 0 4px rgba(70,84,116,.12)}.dn350-auth-check{display:flex;align-items:center;gap:8px;color:#68738c;font-size:14px}.dn350-auth-submit{min-height:52px;border:0;background:var(--dn-navy);color:#fff;font:700 15px var(--dn-body);text-transform:uppercase;cursor:pointer}.dn350-auth-submit:hover{background:#2f3f65}.dn350-auth-submit:disabled{opacity:.6;cursor:progress}.dn350-auth-feedback{margin:16px 0 0;padding:12px 14px;background:#fff0f0;color:#a82828;font-weight:700}.dn350-auth-feedback.is-success{background:#edf8ef;color:#26733c}body.dn350-auth-open{overflow:hidden}@media(max-width:640px){.dn350-auth-modal{align-items:end;padding:0}.dn350-auth-card{width:100%;max-height:90vh;padding:28px 18px}.dn350-auth-title{font-size:27px}}
    </style>

    <div class="dn350-auth-modal" data-dn350-auth-modal hidden>
        <div class="dn350-auth-backdrop" data-dn350-auth-close></div>
        <section class="dn350-auth-card" role="dialog" aria-modal="true" aria-labelledby="dn350-auth-title">
            <button type="button" class="dn350-auth-close" aria-label="Đóng" data-dn350-auth-close>&times;</button>
            <h2 id="dn350-auth-title" class="dn350-auth-title">Tài khoản</h2>
            <p class="dn350-auth-note">Đăng nhập bằng email khách hàng hoặc username admin; đăng ký nhanh nếu bạn chưa có tài khoản.</p>
            <div class="dn350-auth-tabs" role="tablist" aria-label="Đăng nhập hoặc đăng ký">
                <button type="button" class="dn350-auth-tab is-active" role="tab" aria-selected="true" data-dn350-auth-tab="login">Đăng nhập</button>
                <button type="button" class="dn350-auth-tab" role="tab" aria-selected="false" data-dn350-auth-tab="register">Đăng ký</button>
            </div>
            <div class="dn350-auth-panel is-active" data-dn350-auth-panel="login">
                <form class="dn350-auth-form" action="{{ route('customer.auth.store') }}" method="post" data-dn350-auth-form>
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    <label class="dn350-auth-field"><span>Email khách hàng / Username admin</span><input type="text" name="login" autocomplete="username" required></label>
                    <label class="dn350-auth-field"><span>Mật khẩu</span><input type="password" name="password" autocomplete="current-password" required></label>
                    <label class="dn350-auth-field"><span>Mã xác thực hai lớp (nếu đã bật)</span><input type="text" name="two_factor_code" inputmode="numeric" autocomplete="one-time-code" maxlength="32"></label>
                    <label class="dn350-auth-check"><input type="checkbox" name="remember" value="1"><span>Ghi nhớ đăng nhập</span></label>
                    <button type="submit" class="dn350-auth-submit">Đăng nhập</button>
                </form>
            </div>
            <div class="dn350-auth-panel" data-dn350-auth-panel="register">
                <form class="dn350-auth-form" action="{{ route('customer.auth.register.store') }}" method="post" data-dn350-auth-form>
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    <label class="dn350-auth-field"><span>Họ và tên</span><input type="text" name="name" autocomplete="name" required></label>
                    <label class="dn350-auth-field"><span>Email</span><input type="email" name="email" autocomplete="email" required></label>
                    <label class="dn350-auth-field"><span>Số điện thoại</span><input type="tel" name="phone" autocomplete="tel"></label>
                    <label class="dn350-auth-field"><span>Mật khẩu</span><input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                    <label class="dn350-auth-field"><span>Nhập lại mật khẩu</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
                    <button type="submit" class="dn350-auth-submit">Tạo tài khoản</button>
                </form>
            </div>
            <p class="dn350-auth-feedback" data-dn350-auth-feedback hidden></p>
        </section>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-dn350-auth-modal]');
            if (!modal || modal.dataset.ready === '1') return;
            modal.dataset.ready = '1';
            const tabs = [...modal.querySelectorAll('[data-dn350-auth-tab]')];
            const panels = [...modal.querySelectorAll('[data-dn350-auth-panel]')];
            const feedback = modal.querySelector('[data-dn350-auth-feedback]');
            const setFeedback = (message = '', success = false) => { feedback.textContent = message; feedback.hidden = message === ''; feedback.classList.toggle('is-success', success); };
            const selectTab = (name) => {
                tabs.forEach((tab) => { const active = tab.dataset.dnAuthTab === name; tab.classList.toggle('is-active', active); tab.setAttribute('aria-selected', active ? 'true' : 'false'); });
                panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.dnAuthPanel === name));
                setFeedback();
            };
            const openModal = (name = 'login') => { selectTab(name); modal.hidden = false; document.body.classList.add('dn350-auth-open'); window.setTimeout(() => modal.querySelector('.dn350-auth-panel.is-active input:not([type="hidden"])')?.focus(), 30); };
            const closeModal = () => { modal.hidden = true; document.body.classList.remove('dn350-auth-open'); setFeedback(); };
            document.addEventListener('click', (event) => { const trigger = event.target.closest('[data-xd-auth-open]'); if (!trigger) return; event.preventDefault(); openModal(trigger.dataset.xdAuthOpen || 'login'); });
            modal.querySelectorAll('[data-dn350-auth-close]').forEach((button) => button.addEventListener('click', closeModal));
            tabs.forEach((tab) => tab.addEventListener('click', () => selectTab(tab.dataset.dnAuthTab || 'login')));
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) closeModal(); });
            modal.querySelectorAll('[data-dn350-auth-form]').forEach((form) => form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const submit = form.querySelector('[type="submit"]');
                const label = submit.textContent;
                setFeedback(); submit.disabled = true; submit.textContent = 'Đang xử lý...';
                try {
                    const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'Không xử lý được yêu cầu.');
                    setFeedback(payload.message || 'Thành công. Đang chuyển trang...', true);
                    window.location.href = payload.data?.redirect_to || window.location.href;
                } catch (error) { setFeedback(error.message || 'Không xử lý được yêu cầu.'); }
                finally { submit.disabled = false; submit.textContent = label; }
            }));
        })();
    </script>
@endif

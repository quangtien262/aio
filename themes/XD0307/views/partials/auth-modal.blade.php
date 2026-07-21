@unless (auth('customer')->check())
    <style>
        .xd-auth-modal[hidden] { display: none !important; }
        .xd-auth-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .xd-auth-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(9, 21, 32, .72);
            backdrop-filter: blur(4px);
        }
        .xd-auth-card {
            position: relative;
            width: min(560px, 100%);
            max-height: calc(100vh - 48px);
            overflow: auto;
            background: #fff;
            color: #203247;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 30px 90px rgba(9, 21, 32, .35);
        }
        .xd-auth-close {
            position: absolute;
            top: 18px;
            right: 18px;
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 999px;
            background: #f1f5f0;
            color: #203247;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
        }
        .xd-auth-title {
            margin: 0;
            font-size: 30px;
            line-height: 1.15;
            color: #203247;
            letter-spacing: -.035em;
        }
        .xd-auth-note {
            margin: 10px 0 18px;
            color: #6c7782;
            line-height: 1.6;
            font-weight: 650;
        }
        .xd-auth-tabs {
            display: flex;
            gap: 8px;
            padding: 6px;
            border: 1px solid #e1e7e0;
            border-radius: 999px;
            margin-bottom: 18px;
        }
        .xd-auth-tab {
            flex: 1;
            border: 0;
            border-radius: 999px;
            padding: 12px 16px;
            background: transparent;
            color: #203247;
            font-weight: 900;
            cursor: pointer;
        }
        .xd-auth-tab.is-active {
            background: #b6d900;
            color: #fff;
            box-shadow: 0 12px 24px rgba(182, 217, 0, .28);
        }
        .xd-auth-panel { display: none; }
        .xd-auth-panel.is-active { display: block; }
        .xd-auth-form {
            display: grid;
            gap: 14px;
        }
        .xd-auth-field {
            display: grid;
            gap: 7px;
            font-weight: 850;
            color: #203247;
        }
        .xd-auth-field input {
            width: 100%;
            border: 1px solid #dfe7df;
            border-radius: 14px;
            padding: 13px 14px;
            font: inherit;
            color: #203247;
            background: #fff;
        }
        .xd-auth-check {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #60707c;
            font-weight: 750;
        }
        .xd-auth-submit {
            border: 0;
            border-radius: 14px;
            padding: 14px 18px;
            background: #b6d900;
            color: #fff;
            font-weight: 950;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(182, 217, 0, .28);
        }
        .xd-auth-submit:disabled {
            opacity: .65;
            cursor: progress;
        }
        .xd-auth-feedback {
            margin: 16px 0 0;
            padding: 12px 14px;
            border-radius: 14px;
            background: #fff2f2;
            color: #bd1f2d;
            font-weight: 850;
        }
        .xd-auth-feedback.is-success {
            background: #eef9dc;
            color: #6d8700;
        }
        button.xd-login-button,
        button.xd-button,
        button.xd-btn {
            font: inherit;
            cursor: pointer;
        }
        @media (max-width: 640px) {
            .xd-auth-card {
                padding: 24px 18px;
                border-radius: 18px;
            }
            .xd-auth-title { font-size: 24px; }
        }
    </style>

    <div class="xd-auth-modal" data-xd-auth-modal hidden>
        <div class="xd-auth-backdrop" data-xd-auth-close></div>
        <section class="xd-auth-card" role="dialog" aria-modal="true" aria-labelledby="xd-auth-title">
            <button type="button" class="xd-auth-close" aria-label="ÄÃ³ng" data-xd-auth-close>&times;</button>
            <h2 id="xd-auth-title" class="xd-auth-title">TÃ i khoáº£n khÃ¡ch hÃ ng</h2>
            <p class="xd-auth-note">ÄÄƒng nháº­p Ä‘á»ƒ lÆ°u thÃ´ng tin tÆ° váº¥n, hoáº·c Ä‘Äƒng kÃ½ nhanh náº¿u báº¡n chÆ°a cÃ³ tÃ i khoáº£n.</p>

            <div class="xd-auth-tabs" role="tablist" aria-label="TÃ i khoáº£n khÃ¡ch hÃ ng">
                <button type="button" class="xd-auth-tab is-active" role="tab" aria-selected="true" data-xd-auth-tab="login">ÄÄƒng nháº­p</button>
                <button type="button" class="xd-auth-tab" role="tab" aria-selected="false" data-xd-auth-tab="register">ÄÄƒng kÃ½</button>
            </div>

            <div class="xd-auth-panel is-active" data-xd-auth-panel="login">
                <form class="xd-auth-form" action="{{ route('customer.auth.store') }}" method="post" data-xd-auth-form="login">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    <label class="xd-auth-field">
                        <span>Email khÃ¡ch hÃ ng / Username admin</span>
                        <input type="text" name="login" autocomplete="username" required>
                    </label>
                    <label class="xd-auth-field">
                        <span>Máº­t kháº©u</span>
                        <input type="password" name="password" autocomplete="current-password" required>
                        <input type="text" name="two_factor_code" inputmode="numeric" autocomplete="one-time-code" maxlength="32" placeholder="Mã xác thực 2 lớp (nếu đã bật)">
                    </label>
                    <label class="xd-auth-check">
                        <input type="checkbox" name="remember" value="1">
                        <span>Ghi nhá»› Ä‘Äƒng nháº­p</span>
                    </label>
                    <button type="submit" class="xd-auth-submit">ÄÄƒng nháº­p</button>
                </form>
            </div>

            <div class="xd-auth-panel" data-xd-auth-panel="register">
                <form class="xd-auth-form" action="{{ route('customer.auth.register.store') }}" method="post" data-xd-auth-form="register">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    <label class="xd-auth-field">
                        <span>Há» tÃªn</span>
                        <input type="text" name="name" autocomplete="name" required>
                    </label>
                    <label class="xd-auth-field">
                        <span>Email</span>
                        <input type="email" name="email" autocomplete="email" required>
                    </label>
                    <label class="xd-auth-field">
                        <span>Sá»‘ Ä‘iá»‡n thoáº¡i</span>
                        <input type="tel" name="phone" autocomplete="tel">
                    </label>
                    <label class="xd-auth-field">
                        <span>Máº­t kháº©u</span>
                        <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                    </label>
                    <label class="xd-auth-field">
                        <span>Nháº­p láº¡i máº­t kháº©u</span>
                        <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                    </label>
                    <button type="submit" class="xd-auth-submit">Táº¡o tÃ i khoáº£n</button>
                </form>
            </div>

            <p class="xd-auth-feedback" data-xd-auth-feedback hidden></p>
        </section>
    </div>

    <script>
        (() => {
            if (window.__xdAuthModalReady) {
                return;
            }
            window.__xdAuthModalReady = true;

            const authModal = document.querySelector('[data-xd-auth-modal]');
            const authFeedback = document.querySelector('[data-xd-auth-feedback]');
            const authTabs = Array.from(document.querySelectorAll('[data-xd-auth-tab]'));
            const authPanels = Array.from(document.querySelectorAll('[data-xd-auth-panel]'));

            const setAuthFeedback = (message = '', isSuccess = false) => {
                if (!authFeedback) return;
                authFeedback.textContent = message;
                authFeedback.hidden = message === '';
                authFeedback.classList.toggle('is-success', isSuccess);
            };

            const setAuthTab = (tab) => {
                authTabs.forEach((button) => {
                    const isActive = button.dataset.xdAuthTab === tab;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                authPanels.forEach((panel) => {
                    panel.classList.toggle('is-active', panel.dataset.xdAuthPanel === tab);
                });
                setAuthFeedback();
            };

            const openAuthModal = (tab = 'login') => {
                if (!authModal) return;
                setAuthTab(tab);
                authModal.hidden = false;
                document.body.style.overflow = 'hidden';
                window.setTimeout(() => authModal.querySelector('input:not([type="hidden"])')?.focus(), 30);
            };

            const closeAuthModal = () => {
                if (!authModal) return;
                authModal.hidden = true;
                document.body.style.overflow = '';
                setAuthFeedback();
            };

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-xd-auth-open]');
                if (!trigger) return;
                event.preventDefault();
                openAuthModal(trigger.dataset.xdAuthOpen || 'login');
            });

            document.querySelectorAll('[data-xd-auth-close]').forEach((button) => {
                button.addEventListener('click', closeAuthModal);
            });

            authTabs.forEach((button) => {
                button.addEventListener('click', () => setAuthTab(button.dataset.xdAuthTab || 'login'));
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && authModal && !authModal.hidden) {
                    closeAuthModal();
                }
            });

            document.querySelectorAll('[data-xd-auth-form]').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const submit = form.querySelector('[type="submit"]');
                    const originalLabel = submit?.textContent || '';
                    setAuthFeedback();

                    if (submit) {
                        submit.disabled = true;
                        submit.textContent = 'Äang xá»­ lÃ½...';
                    }

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: new FormData(form),
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const firstError = Object.values(payload.errors || {}).flat()[0];
                            throw new Error(firstError || payload.message || 'KhÃ´ng xá»­ lÃ½ Ä‘Æ°á»£c yÃªu cáº§u.');
                        }
                        setAuthFeedback(payload.message || 'ThÃ nh cÃ´ng. Äang chuyá»ƒn trang...', true);
                        window.location.href = payload.data?.redirect_to || window.location.href;
                    } catch (error) {
                        setAuthFeedback(error.message || 'KhÃ´ng xá»­ lÃ½ Ä‘Æ°á»£c yÃªu cáº§u.');
                    } finally {
                        if (submit) {
                            submit.disabled = false;
                            submit.textContent = originalLabel;
                        }
                    }
                });
            });
        })();
    </script>
@endunless

@php
    $customerAuth = $customerAuth ?? ['is_authenticated' => false];
    $newsletterState = $newsletterState ?? ['is_subscribed' => false];
    $postLoginRedirect = $postLoginRedirect ?? request()->fullUrl();
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $themeText = static fn (string $key, string $default): string => $themeTranslator->bladeText('SER0101', app()->getLocale(), $key, $default);
@endphp
<div id="ser-modal-root"
    data-authenticated="{{ !empty($customerAuth['is_authenticated']) ? '1' : '0' }}"
    data-login-url="{{ route('customer.auth.store') }}"
    data-register-url="{{ route('customer.auth.register.store') }}"
    data-newsletter-url="{{ route('site.newsletter.subscribe') }}"
    data-contact-url="{{ route('site.contact.submit') }}"
    data-default-redirect="{{ $postLoginRedirect }}"
    style="display:none;">
</div>

<div class="ser-modal-overlay" data-ser-modal-overlay hidden>
    <div class="ser-modal-card" role="dialog" aria-modal="true">
        <button type="button" class="ser-modal-close" data-ser-modal-close aria-label="{{ $themeText('modal.close', 'Đóng') }}">×</button>
        <section class="ser-modal-panel" data-ser-modal-panel="login" hidden>
            <h3>{{ $themeText('modal.login_title', 'Đăng nhập để tiếp tục') }}</h3>
            <p>{{ $themeText('modal.login_summary', 'Đăng nhập để lưu yêu cầu và theo dõi thông tin.') }}</p>
            <form data-ser-auth-form="login" novalidate>
                <input type="hidden" name="redirect_to" value="{{ $postLoginRedirect }}">
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.email', 'Email') }}</span>
                    <input type="email" name="email" required>
                    <small data-ser-field-error="email"></small>
                </label>
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.password', 'Mật khẩu') }}</span>
                    <input type="password" name="password" required>
                    <small data-ser-field-error="password"></small>
                </label>
                <button type="submit" class="ser-modal-submit">{{ $themeText('modal.login_submit', 'Đăng nhập') }}</button>
            </form>
            <button type="button" class="ser-modal-switch" data-ser-modal-switch="register">{{ $themeText('modal.switch_to_register', 'Chưa có tài khoản? Đăng ký ngay') }}</button>
        </section>
        <section class="ser-modal-panel" data-ser-modal-panel="register" hidden>
            <h3>{{ $themeText('modal.register_title', 'Đăng ký tài khoản') }}</h3>
            <p>{{ $themeText('modal.register_summary', 'Tạo tài khoản để gửi thông tin và lưu yêu cầu trên mọi theme.') }}</p>
            <form data-ser-auth-form="register" novalidate>
                <input type="hidden" name="redirect_to" value="{{ $postLoginRedirect }}">
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.full_name', 'Họ và tên') }}</span>
                    <input type="text" name="name" required>
                    <small data-ser-field-error="name"></small>
                </label>
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.email', 'Email') }}</span>
                    <input type="email" name="email" required>
                    <small data-ser-field-error="email"></small>
                </label>
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.phone', 'Số điện thoại') }}</span>
                    <input type="text" name="phone">
                    <small data-ser-field-error="phone"></small>
                </label>
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.password', 'Mật khẩu') }}</span>
                    <input type="password" name="password" required>
                    <small data-ser-field-error="password"></small>
                </label>
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.password_confirmation', 'Xác nhận mật khẩu') }}</span>
                    <input type="password" name="password_confirmation" required>
                    <small data-ser-field-error="password_confirmation"></small>
                </label>
                <button type="submit" class="ser-modal-submit">{{ $themeText('modal.register_submit', 'Đăng ký') }}</button>
            </form>
            <button type="button" class="ser-modal-switch" data-ser-modal-switch="login">{{ $themeText('modal.switch_to_login', 'Đã có tài khoản? Đăng nhập') }}</button>
        </section>
        <section class="ser-modal-panel" data-ser-modal-panel="newsletter" hidden>
            <h3>{{ $themeText('modal.newsletter_title', 'Đăng ký nhận bản tin') }}</h3>
            <p>{{ !empty($newsletterState['is_subscribed']) ? $themeText('modal.newsletter_summary_subscribed', 'Email của bạn đã đăng ký nhận bản tin.') : $themeText('modal.newsletter_summary_unsubscribed', 'Nhập email để nhận cập nhật bài viết và chương trình mới.') }}</p>
            <form data-ser-newsletter-form novalidate>
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.email', 'Email') }}</span>
                    <input type="email" name="email" value="{{ $customerAuth['customer']['email'] ?? '' }}" {{ !empty($customerAuth['is_authenticated']) ? 'readonly' : '' }} required>
                    <small data-ser-field-error="email"></small>
                </label>
                <button type="submit" class="ser-modal-submit">{{ $themeText('modal.newsletter_submit', 'Xác nhận') }}</button>
            </form>
        </section>
        <section class="ser-modal-panel" data-ser-modal-panel="quote" hidden>
            <h3>{{ $themeText('modal.quote_title', 'Gửi yêu cầu báo giá') }}</h3>
            <p>{{ $themeText('modal.quote_summary', 'Để lại thông tin để điều phối viên gọi lại, gửi báo giá và lưu vào hệ thống admin.') }}</p>
            <form data-ser-quote-form class="ser-modal-quote-form" novalidate>
                <input type="hidden" name="subject" value="Yeu cau bao gia nhanh tu menu">
                <input type="hidden" name="source" value="quote_modal">
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.full_name', 'Họ và tên') }}</span>
                    <input type="text" name="name" value="{{ $customerAuth['customer']['name'] ?? '' }}" required>
                    <small data-ser-field-error="name"></small>
                </label>
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.phone', 'Số điện thoại') }}</span>
                    <input type="text" name="phone" value="{{ $customerAuth['customer']['phone'] ?? '' }}" required>
                    <small data-ser-field-error="phone"></small>
                </label>
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.email', 'Email') }}</span>
                    <input type="email" name="email" value="{{ $customerAuth['customer']['email'] ?? '' }}" required>
                    <small data-ser-field-error="email"></small>
                </label>
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.quote_route', 'Điểm đón / lộ trình dự kiến') }}</span>
                    <input type="text" name="route_summary" placeholder="{{ $themeText('modal.quote_route_placeholder', 'Ví dụ: Đón sân bay Tân Sơn Nhất đi Quận 1') }}" required>
                    <small data-ser-field-error="route_summary"></small>
                </label>
                <label class="ser-modal-field">
                    <span>{{ $themeText('modal.quote_message', 'Thông tin thêm') }}</span>
                    <textarea name="message" rows="4" placeholder="Số khách, thời gian đi, loại xe mong muốn, ghi chú khác..." required></textarea>
                    <small data-ser-field-error="message"></small>
                </label>
                <button type="submit" class="ser-modal-submit">{{ $themeText('modal.quote_submit', 'Lưu yêu cầu') }}</button>
            </form>
        </section>
        <div class="ser-modal-message" data-ser-modal-message hidden></div>
    </div>
</div>
<style>
    .ser-modal-overlay[hidden] { display:none; }
    .ser-modal-overlay { position:fixed; inset:0; z-index:80; display:grid; place-items:center; padding:16px; background:rgba(11,18,32,.56); }
    .ser-modal-card { width:min(440px,100%); padding:28px; border-radius:24px; background:#fff; box-shadow:0 24px 80px rgba(7,14,28,.28); position:relative; }
    .ser-modal-close { position:absolute; top:12px; right:12px; width:38px; height:38px; border:0; border-radius:999px; background:#eef3f7; color:#102a43; font-size:24px; cursor:pointer; }
    .ser-modal-panel h3 { margin:0 0 10px; font-size:28px; color:#102a43; }
    .ser-modal-panel p { margin:0 0 18px; color:#486581; line-height:1.7; }
    .ser-modal-field { display:grid; gap:8px; margin-bottom:14px; }
    .ser-modal-quote-form { display:grid; gap:14px; }
    .ser-modal-quote-form .ser-modal-field { gap:6px; margin-bottom:0; }
    .ser-modal-quote-form .ser-modal-submit { margin-top:4px; }
    .ser-modal-field span { font-size:14px; font-weight:700; color:#102a43; }
    .ser-modal-field input,
    .ser-modal-field textarea { min-height:46px; border:1px solid #d9e2ec; border-radius:14px; padding:0 14px; font:inherit; }
    .ser-modal-field textarea { min-height:88px; padding:12px 14px; resize:vertical; }
    .ser-modal-field small { min-height:0; color:#c53030; font-size:13px; }
    .ser-modal-field small:empty { display:none; }
    .ser-modal-submit,.ser-modal-switch { width:100%; min-height:46px; border-radius:14px; font-weight:700; cursor:pointer; }
    .ser-modal-submit { border:0; background:#c2410c; color:#fff; }
    .ser-modal-switch { margin-top:12px; border:1px solid #d9e2ec; background:#fff; color:#102a43; }
    .ser-modal-message { margin-top:14px; padding:12px 14px; border-radius:14px; background:#eefcf4; color:#166534; line-height:1.6; }
</style>
<script>
    (() => {
        const root = document.getElementById('ser-modal-root');
        if (!root) {
            return;
        }

        const overlay = document.querySelector('[data-ser-modal-overlay]');
        const panels = [...document.querySelectorAll('[data-ser-modal-panel]')];
        const messageNode = document.querySelector('[data-ser-modal-message]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        let activeRedirect = root.dataset.defaultRedirect || window.location.href;

        const showPanel = (name) => {
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.serModalPanel !== name;
            });
            messageNode.hidden = true;
            messageNode.textContent = '';
            overlay.hidden = false;
            document.body.style.overflow = 'hidden';
        };

        const hidePanel = () => {
            overlay.hidden = true;
            document.body.style.overflow = '';
        };

        const showMessage = (text, isError = false) => {
            messageNode.hidden = false;
            messageNode.textContent = text;
            messageNode.style.background = isError ? '#fff1f2' : '#eefcf4';
            messageNode.style.color = isError ? '#9b1c1c' : '#166534';
        };

        const clearErrors = (form) => {
            form.querySelectorAll('[data-ser-field-error]').forEach((node) => {
                node.textContent = '';
            });
        };

        const setFieldError = (form, field, message) => {
            const node = form.querySelector(`[data-ser-field-error="${field}"]`);
            if (node) {
                node.textContent = message;
            }
        };

        const validateForm = (form, mode) => {
            clearErrors(form);
            const data = new FormData(form);
            let valid = true;
            const email = String(data.get('email') || '').trim();
            if (!email || !emailPattern.test(email)) {
                setFieldError(form, 'email', 'Email không hợp lệ.');
                valid = false;
            }
            if (mode !== 'newsletter') {
                const password = String(data.get('password') || '');
                if (password.length < 8) {
                    setFieldError(form, 'password', 'Mật khẩu tối thiểu 8 ký tự.');
                    valid = false;
                }
            }
            if (mode === 'register') {
                if (!String(data.get('name') || '').trim()) {
                    setFieldError(form, 'name', 'Vui lòng nhập họ và tên.');
                    valid = false;
                }
                if (String(data.get('password_confirmation') || '') !== String(data.get('password') || '')) {
                    setFieldError(form, 'password_confirmation', 'Xác nhận mật khẩu không khớp.');
                    valid = false;
                }
            }
            return valid;
        };

        const submitJson = async (url, payload) => {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw data;
            }
            return data;
        };

        document.addEventListener('click', (event) => {
            const authButton = event.target.closest('[data-open-auth-modal]');
            const newsletterButton = event.target.closest('[data-open-newsletter-modal]');
            const quoteButton = event.target.closest('[data-open-quote-modal]');
            const switchButton = event.target.closest('[data-ser-modal-switch]');
            const closeButton = event.target.closest('[data-ser-modal-close]');

            if (authButton) {
                activeRedirect = authButton.dataset.authRedirect || root.dataset.defaultRedirect || window.location.href;
                showPanel(authButton.dataset.openAuthModal || 'login');
                return;
            }

            if (newsletterButton) {
                showPanel('newsletter');
                return;
            }

            if (quoteButton) {
                showPanel('quote');
                return;
            }

            if (switchButton) {
                showPanel(switchButton.dataset.serModalSwitch || 'login');
                return;
            }

            if (closeButton) {
                hidePanel();
            }
        });

        document.querySelectorAll('[data-ser-auth-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const mode = form.dataset.serAuthForm;
                if (!validateForm(form, mode)) {
                    return;
                }
                const payload = Object.fromEntries(new FormData(form).entries());
                payload.redirect_to = activeRedirect;
                try {
                    await submitJson(mode === 'login' ? root.dataset.loginUrl : root.dataset.registerUrl, payload);
                    window.location.assign(activeRedirect);
                } catch (error) {
                    const errors = error?.errors || {};
                    Object.entries(errors).forEach(([field, messages]) => setFieldError(form, field, Array.isArray(messages) ? messages[0] : messages));
                    showMessage(error?.message || 'Không thực hiện được thao tác.', true);
                }
            });
        });

        const newsletterForm = document.querySelector('[data-ser-newsletter-form]');
        newsletterForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!validateForm(newsletterForm, 'newsletter')) {
                return;
            }
            try {
                await submitJson(root.dataset.newsletterUrl, Object.fromEntries(new FormData(newsletterForm).entries()));
                showMessage('Đăng ký nhận bản tin thành công.');
            } catch (error) {
                const errors = error?.errors || {};
                Object.entries(errors).forEach(([field, messages]) => setFieldError(newsletterForm, field, Array.isArray(messages) ? messages[0] : messages));
                showMessage(error?.message || 'Không thể đăng ký bản tin.', true);
            }
        });

        const quoteForm = document.querySelector('[data-ser-quote-form]');
        quoteForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors(quoteForm);
            const formData = new FormData(quoteForm);
            let valid = true;
            const name = String(formData.get('name') || '').trim();
            const phone = String(formData.get('phone') || '').trim();
            const email = String(formData.get('email') || '').trim();
            const routeSummary = String(formData.get('route_summary') || '').trim();
            const message = String(formData.get('message') || '').trim();

            if (!name) {
                setFieldError(quoteForm, 'name', 'Vui lòng nhập họ và tên.');
                valid = false;
            }
            if (!phone) {
                setFieldError(quoteForm, 'phone', 'Vui lòng nhập số điện thoại.');
                valid = false;
            }
            if (!email || !emailPattern.test(email)) {
                setFieldError(quoteForm, 'email', 'Email không hợp lệ.');
                valid = false;
            }
            if (!routeSummary) {
                setFieldError(quoteForm, 'route_summary', 'Vui lòng nhập điểm đón hoặc lộ trình.');
                valid = false;
            }
            if (message.length < 10) {
                setFieldError(quoteForm, 'message', 'Vui lòng nhập tối thiểu 10 ký tự.');
                valid = false;
            }

            if (!valid) {
                return;
            }

            try {
                const payload = Object.fromEntries(formData.entries());
                await submitJson(root.dataset.contactUrl, payload);
                quoteForm.reset();
                showMessage('Yêu cầu báo giá đã được gửi và lưu vào hệ thống.');
            } catch (error) {
                const errors = error?.errors || {};
                Object.entries(errors).forEach(([field, messages]) => setFieldError(quoteForm, field, Array.isArray(messages) ? messages[0] : messages));
                showMessage(error?.message || 'Không thể gửi yêu cầu báo giá.', true);
            }
        });
    })();
</script>

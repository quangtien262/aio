@php
    $customerAuth = $customerAuth ?? ['is_authenticated' => false];
    $newsletterState = $newsletterState ?? ['is_subscribed' => false];
    $postLoginRedirect = $postLoginRedirect ?? request()->fullUrl();
@endphp
<div id="th-modal-root"
    data-authenticated="{{ !empty($customerAuth['is_authenticated']) ? '1' : '0' }}"
    data-login-url="{{ route('customer.auth.store') }}"
    data-register-url="{{ route('customer.auth.register.store') }}"
    data-newsletter-url="{{ route('site.newsletter.subscribe') }}"
    data-default-redirect="{{ $postLoginRedirect }}"
    data-open-modal="{{ session('open_auth_modal', '') }}"
    style="display: none;">
</div>

<div class="th-modal-overlay" data-th-modal-overlay hidden>
    <div class="th-modal-card" role="dialog" aria-modal="true" aria-labelledby="th-modal-title">
        <button type="button" class="th-modal-close" data-th-modal-close aria-label="Đóng">×</button>

        <section class="th-modal-panel" data-th-modal-panel="login" hidden>
            <h3 id="th-modal-title">Đăng nhập để tiếp tục mua hàng</h3>
            <p>Đăng nhập nhanh để thanh toán, theo dõi đơn hàng và lưu sản phẩm yêu thích.</p>
            <form data-th-auth-form="login" novalidate>
                <input type="hidden" name="redirect_to" value="{{ $postLoginRedirect }}">
                <label class="th-modal-field">
                    <span>Email</span>
                    <input type="email" name="email" required>
                    <small class="th-modal-field-error" data-th-field-error="email"></small>
                </label>
                <label class="th-modal-field">
                    <span>Mật khẩu</span>
                    <input type="password" name="password" required>
                    <small class="th-modal-field-error" data-th-field-error="password"></small>
                </label>
                <button type="submit" class="th-modal-submit">Đăng nhập</button>
            </form>
            <button type="button" class="th-modal-switch" data-th-modal-switch="register">Chưa có tài khoản? Đăng ký ngay</button>
        </section>

        <section class="th-modal-panel" data-th-modal-panel="register" hidden>
            <h3>Đăng ký tài khoản khách hàng</h3>
            <p>Tạo tài khoản để thanh toán nhanh hơn và dùng chung cho mọi theme của website.</p>
            <form data-th-auth-form="register" novalidate>
                <input type="hidden" name="redirect_to" value="{{ $postLoginRedirect }}">
                <label class="th-modal-field">
                    <span>Họ và tên</span>
                    <input type="text" name="name" required>
                    <small class="th-modal-field-error" data-th-field-error="name"></small>
                </label>
                <label class="th-modal-field">
                    <span>Email</span>
                    <input type="email" name="email" required>
                    <small class="th-modal-field-error" data-th-field-error="email"></small>
                </label>
                <label class="th-modal-field">
                    <span>Số điện thoại</span>
                    <input type="text" name="phone">
                    <small class="th-modal-field-error" data-th-field-error="phone"></small>
                </label>
                <label class="th-modal-field">
                    <span>Mật khẩu</span>
                    <input type="password" name="password" required>
                    <small class="th-modal-field-error" data-th-field-error="password"></small>
                </label>
                <label class="th-modal-field">
                    <span>Xác nhận mật khẩu</span>
                    <input type="password" name="password_confirmation" required>
                    <small class="th-modal-field-error" data-th-field-error="password_confirmation"></small>
                </label>
                <button type="submit" class="th-modal-submit">Đăng ký</button>
            </form>
            <button type="button" class="th-modal-switch" data-th-modal-switch="login">Đã có tài khoản? Đăng nhập</button>
        </section>

        <section class="th-modal-panel" data-th-modal-panel="newsletter" hidden>
            <div class="th-modal-kicker">Newsletter</div>
            <h3>Đăng ký nhận bản tin</h3>
            <p>{{ !empty($newsletterState['is_subscribed']) ? 'Email của bạn đã đăng ký nhận bản tin. Có thể nhập email khác nếu muốn đổi.' : 'Nhập email để nhận cập nhật ưu đãi, bài viết và sản phẩm mới.' }}</p>
            <form data-th-newsletter-form novalidate>
                <label class="th-modal-field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ $customerAuth['customer']['email'] ?? '' }}" {{ !empty($customerAuth['is_authenticated']) ? 'readonly' : '' }} required>
                    <small class="th-modal-field-error" data-th-field-error="email"></small>
                </label>
                <button type="submit" class="th-modal-submit">Xác nhận đăng ký</button>
            </form>
        </section>

        <div class="th-modal-message" data-th-modal-message hidden></div>
    </div>
</div>

<style>
    .th-modal-overlay[hidden] { display: none; }
    .th-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: grid;
        place-items: center;
        padding: 16px;
        background: rgba(22, 22, 22, 0.52);
    }
    .th-modal-card {
        position: relative;
        width: min(460px, 100%);
        border-radius: 24px;
        padding: 28px;
        background: linear-gradient(180deg, #ffffff 0%, #fff7f7 100%);
        box-shadow: 0 32px 90px rgba(0, 0, 0, 0.18);
    }
    .th-modal-close {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 40px;
        height: 40px;
        border: 0;
        border-radius: 999px;
        background: #fff2f2;
        color: #a61b1b;
        font-size: 24px;
        cursor: pointer;
    }
    .th-modal-kicker {
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: .12em;
        font-size: 12px;
        color: #ef2b2d;
        font-weight: 700;
    }
    .th-modal-panel h3 {
        margin: 0 0 10px;
        font-size: 28px;
        color: #1f1f1f;
    }
    .th-modal-panel p {
        margin: 0 0 18px;
        color: #666;
        line-height: 1.7;
    }
    .th-modal-field {
        display: grid;
        gap: 8px;
        margin-bottom: 14px;
    }
    .th-modal-field span {
        font-size: 14px;
        color: #444;
        font-weight: 700;
    }
    .th-modal-field input {
        min-height: 46px;
        border: 1px solid #e4d7d7;
        border-radius: 14px;
        padding: 0 14px;
        font: inherit;
    }
    .th-modal-field input.has-error {
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08);
    }
    .th-modal-field-error {
        min-height: 18px;
        font-size: 13px;
        line-height: 1.4;
        color: #dc2626;
    }
    .th-modal-submit,
    .th-modal-switch {
        width: 100%;
        min-height: 46px;
        border-radius: 14px;
        font-weight: 700;
        cursor: pointer;
    }
    .th-modal-submit {
        border: 0;
        background: #ef2b2d;
        color: #fff;
    }
    .th-modal-switch {
        margin-top: 12px;
        border: 1px solid #eed0d0;
        background: #fff;
        color: #b42318;
    }
    .th-modal-message {
        margin-top: 14px;
        padding: 12px 14px;
        border-radius: 14px;
        background: #fff1d8;
        color: #8a5a00;
        line-height: 1.6;
    }
</style>

<script>
    (() => {
        const root = document.getElementById('th-modal-root');

        if (!root) {
            return;
        }

        const overlay = document.querySelector('[data-th-modal-overlay]');
        const panels = [...document.querySelectorAll('[data-th-modal-panel]')];
        const messageNode = document.querySelector('[data-th-modal-message]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        let activeRedirect = root.dataset.defaultRedirect || window.location.href;
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        const clearFieldErrors = (form) => {
            form.querySelectorAll('[data-th-field-error]').forEach((node) => {
                node.textContent = '';
            });

            form.querySelectorAll('input').forEach((input) => {
                input.classList.remove('has-error');
            });
        };

        const setFieldError = (form, field, message) => {
            const errorNode = form.querySelector(`[data-th-field-error="${field}"]`);
            const inputNode = form.querySelector(`[name="${field}"]`);

            if (errorNode) {
                errorNode.textContent = message;
            }

            if (inputNode) {
                inputNode.classList.add('has-error');
            }
        };

        const normalizeServerErrors = (errors) => {
            return Object.entries(errors || {}).reduce((carry, [field, messages]) => {
                carry[field] = Array.isArray(messages) ? messages[0] : messages;
                return carry;
            }, {});
        };

        const validateAuthForm = (form, mode) => {
            const payload = Object.fromEntries(new FormData(form).entries());
            const errors = {};

            if (mode === 'login') {
                if (!String(payload.email || '').trim()) {
                    errors.email = 'Vui lòng nhập email.';
                } else if (!emailPattern.test(String(payload.email).trim())) {
                    errors.email = 'Email không đúng định dạng.';
                }

                if (!String(payload.password || '').trim()) {
                    errors.password = 'Vui lòng nhập mật khẩu.';
                }
            }

            if (mode === 'register') {
                if (!String(payload.name || '').trim()) {
                    errors.name = 'Vui lòng nhập họ và tên.';
                }

                if (!String(payload.email || '').trim()) {
                    errors.email = 'Vui lòng nhập email.';
                } else if (!emailPattern.test(String(payload.email).trim())) {
                    errors.email = 'Email không đúng định dạng.';
                }

                if (String(payload.phone || '').trim() && String(payload.phone || '').trim().length > 30) {
                    errors.phone = 'Số điện thoại không được quá 30 ký tự.';
                }

                if (!String(payload.password || '').trim()) {
                    errors.password = 'Vui lòng nhập mật khẩu.';
                } else if (String(payload.password).length < 8) {
                    errors.password = 'Mật khẩu phải có ít nhất 8 ký tự.';
                }

                if (!String(payload.password_confirmation || '').trim()) {
                    errors.password_confirmation = 'Vui lòng xác nhận mật khẩu.';
                } else if (payload.password !== payload.password_confirmation) {
                    errors.password_confirmation = 'Xác nhận mật khẩu không khớp.';
                }
            }

            return { payload, errors };
        };

        const validateNewsletterForm = (form) => {
            const payload = Object.fromEntries(new FormData(form).entries());
            const errors = {};

            if (!String(payload.email || '').trim()) {
                errors.email = 'Vui lòng nhập email.';
            } else if (!emailPattern.test(String(payload.email).trim())) {
                errors.email = 'Email không đúng định dạng.';
            }

            return { payload, errors };
        };

        const showMessage = (message) => {
            if (!messageNode) {
                return;
            }

            if (!message) {
                messageNode.hidden = true;
                messageNode.textContent = '';
                return;
            }

            messageNode.hidden = false;
            messageNode.textContent = message;
        };

        const openPanel = (panelKey, redirectTo = null) => {
            activeRedirect = redirectTo || root.dataset.defaultRedirect || window.location.href;
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.thModalPanel !== panelKey;
                panel.querySelectorAll('input[name="redirect_to"]').forEach((field) => {
                    field.value = activeRedirect;
                });
                panel.querySelectorAll('form').forEach((form) => clearFieldErrors(form));
            });
            showMessage('');
            overlay.hidden = false;
        };

        const closeModal = () => {
            overlay.hidden = true;
            showMessage('');
        };

        document.querySelectorAll('[data-th-modal-close]').forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        overlay?.addEventListener('click', (event) => {
            if (event.target === overlay) {
                closeModal();
            }
        });

        document.querySelectorAll('[data-th-modal-switch]').forEach((button) => {
            button.addEventListener('click', () => openPanel(button.dataset.thModalSwitch, activeRedirect));
        });

        document.querySelectorAll('[data-open-auth-modal]').forEach((button) => {
            button.addEventListener('click', () => openPanel(button.dataset.openAuthModal || 'login', button.dataset.authRedirect || activeRedirect));
        });

        document.querySelectorAll('[data-open-newsletter-modal]').forEach((button) => {
            button.addEventListener('click', async () => {
                if (root.dataset.authenticated === '1') {
                    try {
                        const response = await fetch(root.dataset.newsletterUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({}),
                        });
                        const payload = await response.json();

                        if (!response.ok) {
                            throw new Error(payload.message || 'Không thể đăng ký bản tin.');
                        }

                        alert(payload.message || 'Đăng ký nhận bản tin thành công.');
                        window.location.reload();
                    } catch (error) {
                        alert(error.message || 'Không thể đăng ký bản tin.');
                    }

                    return;
                }

                openPanel('newsletter');
            });
        });

        document.querySelectorAll('[data-th-auth-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                const mode = form.dataset.thAuthForm;
                const targetUrl = mode === 'register' ? root.dataset.registerUrl : root.dataset.loginUrl;
                const { payload, errors } = validateAuthForm(form, mode);

                clearFieldErrors(form);
                showMessage('');

                if (Object.keys(errors).length > 0) {
                    Object.entries(errors).forEach(([field, message]) => setFieldError(form, field, message));
                    return;
                }

                try {
                    const response = await fetch(targetUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const body = await response.json();

                    if (!response.ok) {
                        const serverErrors = normalizeServerErrors(body.errors);

                        if (Object.keys(serverErrors).length > 0) {
                            Object.entries(serverErrors).forEach(([field, message]) => setFieldError(form, field, message));
                            showMessage(body.message || 'Vui lòng kiểm tra lại thông tin đã nhập.');
                            return;
                        }

                        throw new Error(body.message || 'Không thực hiện được thao tác.');
                    }

                    window.location.href = body.data?.redirect_to || activeRedirect || window.location.href;
                } catch (error) {
                    showMessage(error.message || 'Không thực hiện được thao tác.');
                }
            });
        });

        const newsletterForm = document.querySelector('[data-th-newsletter-form]');

        newsletterForm?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const { payload, errors } = validateNewsletterForm(newsletterForm);

            clearFieldErrors(newsletterForm);
            showMessage('');

            if (Object.keys(errors).length > 0) {
                Object.entries(errors).forEach(([field, message]) => setFieldError(newsletterForm, field, message));
                return;
            }

            try {
                const response = await fetch(root.dataset.newsletterUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const body = await response.json();

                if (!response.ok) {
                    const serverErrors = normalizeServerErrors(body.errors);

                    if (Object.keys(serverErrors).length > 0) {
                        Object.entries(serverErrors).forEach(([field, message]) => setFieldError(newsletterForm, field, message));
                        showMessage(body.message || 'Vui lòng kiểm tra lại email đã nhập.');
                        return;
                    }

                    throw new Error(body.message || 'Không thể đăng ký bản tin.');
                }

                showMessage(body.message || 'Đăng ký nhận bản tin thành công.');
                window.setTimeout(() => window.location.reload(), 900);
            } catch (error) {
                showMessage(error.message || 'Không thể đăng ký bản tin.');
            }
        });

        if (root.dataset.openModal) {
            openPanel(root.dataset.openModal, root.dataset.defaultRedirect || window.location.href);
        }
    })();
</script>

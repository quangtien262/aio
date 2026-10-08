(() => {
    'use strict';
    const script = document.currentScript;
    if (!script || window.aioAuthClientLoaded) return;
    const messages = JSON.parse(script.dataset.messages);
    const login = new URL(script.dataset.login, location.href);
    const register = new URL(script.dataset.register, location.href);
    const states = new WeakMap();
    const errors = new WeakMap();
    let sequence = 0;
    const fields = (form) => Array.from(form.elements).filter((field) => field.matches('input') && !field.disabled
        && !['hidden', 'submit', 'button', 'checkbox', 'radio'].includes(field.type));

    function initialize(form) {
        if (!(form instanceof HTMLFormElement) || states.has(form) || form.method.toLowerCase() !== 'post') return;
        const action = new URL(form.action, location.href);
        if (action.origin !== login.origin || ![login.pathname, register.pathname].includes(action.pathname)) return;
        states.set(form, {register: action.pathname === register.pathname, pending: false, attempted: false, touched: new WeakSet()});
        form.noValidate = true;
        form.setAttribute('data-auth-client-form', '');
    }
    function scan(root) {
        if (root instanceof HTMLFormElement) initialize(root);
        else if (root instanceof Element) initialize(root.closest('form'));
        root.querySelectorAll?.('form').forEach(initialize);
    }
    function validate(field, state) {
        const value = field.value;
        const key = field.name;
        const required = field.required || ['password', ...(state.register ? ['name', 'email', 'password_confirmation'] : [])].includes(key);
        if ((required || (!state.register && ['login', 'email'].includes(key))) && !value.trim()) {
            return messages.required.replace(':field', messages.labels[key] || key);
        }
        if (!value) return '';
        if (state.register && key === 'email' && (!/^[^\s@]+@[^\s@]+$/.test(value.trim()) || field.validity.typeMismatch)) return messages.email;
        const maximum = {login: 255, email: 255, name: 255, phone: 30, two_factor_code: 32}[key];
        if (maximum && Array.from(value.trim()).length > maximum) return messages.max.replace(':max', maximum);
        if (state.register && key === 'password' && Array.from(value).length < 8) return messages.password_min;
        if (key === 'password_confirmation' && value !== field.form.elements.namedItem('password')?.value) return messages.confirmation;
        return '';
    }
    function showError(field, text) {
        // Clear the existing server-rendered field error when the field is checked again.
        field.form.querySelectorAll('[data-field-error], [data-ser-field-error]').forEach((node) => {
            if ((node.dataset.fieldError || node.dataset.serFieldError) === field.name) node.textContent = '';
        });
        let error = errors.get(field);
        if (!text) {
            if (error) {
                error.remove();
                const ids = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== error.id);
                if (ids.length) field.setAttribute('aria-describedby', ids.join(' '));
                else field.removeAttribute('aria-describedby');
            }
            field.removeAttribute('aria-invalid');
            field.removeAttribute('data-auth-invalid');
            return;
        }
        if (!error) {
            error = document.createElement('span');
            do { error.id = `auth-client-error-${++sequence}`; } while (document.getElementById(error.id));
            error.setAttribute('data-auth-field-error', '');
            error.setAttribute('aria-live', 'polite');
            errors.set(field, error);
        }
        field.after(error);
        error.textContent = text;
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('data-auth-invalid', '');
        const ids = new Set((field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
        ids.add(error.id);
        field.setAttribute('aria-describedby', Array.from(ids).join(' '));
    }
    function focusError(field) {
        field.focus({preventScroll: true});
        field.scrollIntoView({block: 'nearest', behavior: 'auto'});
    }
    function feedback(form, text, status = 'error') {
        let node = form.querySelector('[data-auth-feedback]');
        if (!node) {
            node = document.createElement('div');
            node.setAttribute('data-auth-feedback', '');
            form.prepend(node);
        }
        node.hidden = !text;
        node.dataset.status = status;
        node.setAttribute('role', status === 'error' ? 'alert' : 'status');
        node.textContent = text;
    }
    async function send(form, state) {
        state.pending = true;
        const data = new FormData(form);
        const inputs = fields(form);
        const buttons = Array.from(form.querySelectorAll('[type="submit"]'));
        const original = buttons.map((button) => ({button, disabled: button.disabled, content: button.tagName === 'INPUT' ? button.value : button.innerHTML}));
        inputs.forEach((field) => { field.disabled = true; });
        buttons.forEach((button) => {
            button.disabled = true;
            if (button.tagName === 'INPUT') button.value = messages.loading;
            else button.textContent = messages.loading;
        });
        form.setAttribute('aria-busy', 'true');
        feedback(form, messages.loading, 'pending');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 30000);
        let redirecting = false;
        try {
            const response = await fetch(form.action, {method: 'POST', body: data, credentials: 'same-origin', signal: controller.signal,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            const body = await response.json().catch(() => null);
            if (response.status === 422) {
                inputs.forEach((field) => { field.disabled = false; });
                let firstInvalid;
                inputs.forEach((field) => {
                    const list = body?.errors?.[field.name];
                    const text = Array.isArray(list) ? list[0] : typeof list === 'string' ? list : '';
                    showError(field, text);
                    if (text && !firstInvalid) firstInvalid = field;
                });
                feedback(form, firstInvalid ? '' : body?.message || messages.failed);
                if (firstInvalid) focusError(firstInvalid);
                return;
            }
            if (!response.ok || response.redirected || typeof body?.data?.redirect_to !== 'string') {
                feedback(form, response.status === 419 ? messages.expired : response.status === 429 ? messages.rate_limited : messages.failed);
                return;
            }
            const destination = new URL(body.data.redirect_to, location.href);
            if (destination.origin !== location.origin) throw new Error('Invalid redirect');
            redirecting = true;
            feedback(form, messages.redirecting, 'success');
            buttons.forEach((button) => {
                if (button.tagName === 'INPUT') button.value = messages.redirecting;
                else button.textContent = messages.redirecting;
            });
            location.assign(destination.href);
        } catch {
            feedback(form, messages.failed);
        } finally {
            clearTimeout(timeout);
            if (!redirecting) {
                state.pending = false;
                form.removeAttribute('aria-busy');
                inputs.forEach((field) => { field.disabled = false; });
                original.forEach(({button, disabled, content}) => {
                    button.disabled = disabled;
                    if (button.tagName === 'INPUT') button.value = content;
                    else button.innerHTML = content;
                });
            }
        }
    }
    scan(document);
    window.aioAuthClientLoaded = true;
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node instanceof Element) scan(node);
    }))).observe(document.body, {childList: true, subtree: true});
    document.addEventListener('submit', (event) => {
        const form = event.target;
        initialize(form);
        const state = states.get(form);
        if (!state) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (state.pending) return;
        state.attempted = true;
        feedback(form, '');
        let firstInvalid;
        fields(form).forEach((field) => {
            const text = validate(field, state);
            showError(field, text);
            if (text && !firstInvalid) firstInvalid = field;
        });
        if (firstInvalid) focusError(firstInvalid);
        else void send(form, state);
    }, true);
    function update(event) {
        const field = event.target;
        const state = states.get(field.form);
        if (!state || state.pending || !fields(field.form).includes(field)) return;
        if (event.type === 'focusout') state.touched.add(field);
        if (state.attempted || state.touched.has(field)) showError(field, validate(field, state));
        if (field.name === 'password') {
            const confirmation = field.form.elements.namedItem('password_confirmation');
            if (confirmation && (state.attempted || state.touched.has(confirmation))) showError(confirmation, validate(confirmation, state));
        }
    }
    ['input', 'change', 'focusout'].forEach((type) => document.addEventListener(type, update));
})();

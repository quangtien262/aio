(() => {
    'use strict';

    const script = document.currentScript;
    if (!script || window.aioContactValidationLoaded) return;
    const messages = JSON.parse(script.dataset.messages);
    const labels = JSON.parse(script.dataset.labels);
    const endpoint = new URL(script.dataset.endpoint, location.href);
    const forms = new WeakMap();
    const errors = new WeakMap();
    const limits = {name: 120, email: 150, phone: 30, subject: 150, route_summary: 255, message: 5000};
    let sequence = 0;

    const controls = (form) => Array.from(form.elements).filter((field) =>
        field.matches('input, textarea, select') && !field.disabled && !field.readOnly
        && !['hidden', 'submit', 'button', 'reset', 'image'].includes(field.type));

    function isContactForm(form) {
        if (!(form instanceof HTMLFormElement)) return false;
        const fields = controls(form);
        // Email-only newsletter forms must keep their existing behavior.
        if (!fields.some((field) => field.name === 'name' || field.tagName === 'TEXTAREA')) return false;
        const action = new URL(form.action, location.href);
        return (action.origin === endpoint.origin && action.pathname === endpoint.pathname)
            || form.matches('[data-contact-form], [class*="contact"]')
            || Boolean(form.closest('[data-block-type*="contact"], section[id="lien-he"], section[id="contact"], [class*="contact"]'));
    }

    function initialize(form) {
        if (forms.has(form) || !isContactForm(form)) return;
        forms.set(form, {attempted: false, touched: new WeakSet(), pending: false});
        form.noValidate = true;
        form.setAttribute('data-contact-validation-form', '');
    }

    function scan(root) {
        if (root instanceof HTMLFormElement) initialize(root);
        else if (root instanceof Element && root.closest('form')) initialize(root.closest('form'));
        root.querySelectorAll?.('form').forEach(initialize);
    }

    function fieldKey(field) {
        return field.name || (field.tagName === 'TEXTAREA' ? 'message' : field.type === 'email' ? 'email' : '');
    }

    function fieldLabel(field) {
        const key = fieldKey(field);
        return labels[key] || field.getAttribute('aria-label') || field.getAttribute('placeholder')?.replace(/^\s*\*\s*/, '')
            || field.labels?.[0]?.textContent.trim().replace(/\s*\*\s*$/, '') || key;
    }

    function message(key, values = {}) {
        return Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value)), messages[key]);
    }

    function validate(field) {
        const key = fieldKey(field);
        const value = field.value.trim();
        const length = Array.from(value).length;
        const required = field.required || ['name', 'email', 'message'].includes(key);
        if (required && (!value || field.validity.valueMissing)) return message('required', {field: fieldLabel(field)});
        if (!value) return '';
        if (key === 'email' && (!/^[^\s@]+@[^\s@]+$/.test(value) || field.validity.typeMismatch)) return message('email');
        const maximum = Math.min(limits[key] ?? Infinity, field.maxLength >= 0 ? field.maxLength : Infinity);
        if (length > maximum) return message('max', {max: maximum});
        const minimum = Math.max(key === 'message' ? 10 : 0, field.minLength > 0 ? field.minLength : 0);
        if (length < minimum) return message('min', {min: minimum});
        if (field.validity.patternMismatch || field.validity.badInput || field.validity.rangeUnderflow
            || field.validity.rangeOverflow || field.validity.stepMismatch || field.validity.typeMismatch) {
            return message('invalid', {field: fieldLabel(field)});
        }
        return '';
    }

    function showError(field, text) {
        let error = errors.get(field);
        if (!text) {
            if (error) {
                const describedBy = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== error.id);
                if (describedBy.length) field.setAttribute('aria-describedby', describedBy.join(' '));
                else field.removeAttribute('aria-describedby');
                error.remove();
            }
            field.removeAttribute('data-contact-invalid');
            field.removeAttribute('aria-invalid');
            return;
        }
        if (!error) {
            error = document.createElement('span');
            do { error.id = `contact-field-error-${++sequence}`; } while (document.getElementById(error.id));
            error.setAttribute('data-contact-field-error', '');
            error.setAttribute('aria-live', 'polite');
            errors.set(field, error);
        }
        if (!error.isConnected) {
            const parent = field.parentElement;
            const directControls = Array.from(parent.children).filter((child) => child.matches('input:not([type="hidden"]), textarea, select'));
            if (parent === field.form || directControls.length > 1) {
                // Wrap the whole row so error text does not stretch adjacent inputs.
                const placements = directControls.map((control) => ({control, column: getComputedStyle(control).gridColumn}));
                placements.forEach(({control, column}) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'contact-validation-control';
                    wrapper.style.gridColumn = column;
                    control.before(wrapper);
                    wrapper.append(control);
                });
            }
            field.after(error);
        }
        if (error.textContent !== text) error.textContent = text;
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('data-contact-invalid', 'true');
        const describedBy = new Set((field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
        describedBy.add(error.id);
        field.setAttribute('aria-describedby', Array.from(describedBy).join(' '));
    }

    function focusError(field) {
        field.focus({preventScroll: true});
        field.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'center'});
    }

    function feedback(form, text, status) {
        let notice = form.querySelector('[data-contact-feedback]');
        if (!notice) {
            notice = document.createElement('div');
            notice.setAttribute('data-contact-feedback', '');
            form.prepend(notice);
        }
        notice.hidden = !text;
        notice.dataset.status = status;
        notice.setAttribute('role', status === 'error' ? 'alert' : 'status');
        notice.textContent = text;
        return notice;
    }

    function canSendAjax(form) {
        const action = new URL(form.action, location.href);
        return form.method.toLowerCase() === 'post' && action.origin === endpoint.origin && action.pathname === endpoint.pathname
            && form.elements.namedItem('source')?.value !== 'quote_modal'
            && ['name', 'email', 'message'].every((name) => controls(form).some((field) => field.name === name));
    }

    async function send(form, state, submitter) {
        state.pending = true;
        const data = new FormData(form);
        const fields = controls(form);
        fields.forEach((field) => { field.disabled = true; });
        if (submitter?.name) data.append(submitter.name, submitter.value);
        const buttons = Array.from(form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]'));
        const original = buttons.map((button) => ({button, disabled: button.disabled, content: button.tagName === 'INPUT' ? button.value : button.innerHTML}));
        buttons.forEach((button) => {
            button.disabled = true;
            if (button.tagName === 'INPUT') button.value = messages.sending;
            else button.textContent = messages.sending;
        });
        form.setAttribute('aria-busy', 'true');
        feedback(form, messages.sending, 'pending');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: data, credentials: 'same-origin', signal: controller.signal,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            });
            const body = await response.json().catch(() => null);
            if (response.status === 422) {
                fields.forEach((field) => { field.disabled = false; });
                let firstInvalid;
                fields.forEach((field) => {
                    const errors = body?.errors?.[field.name];
                    const text = Array.isArray(errors) ? errors[0] : typeof errors === 'string' ? errors : '';
                    showError(field, text);
                    if (text && !firstInvalid) firstInvalid = field;
                });
                feedback(form, messages.validation_failed, 'error');
                if (firstInvalid) focusError(firstInvalid);
                return;
            }
            if (!response.ok || !body || typeof body.message !== 'string' || response.redirected) {
                feedback(form, response.status === 419 ? messages.expired : response.status === 429 ? messages.rate_limited : messages.failed, 'error');
                return;
            }
            form.reset();
            fields.forEach((field) => showError(field, ''));
            state.attempted = false;
            state.touched = new WeakSet();
            const notice = feedback(form, body.message || messages.success, 'success');
            notice.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'nearest'});
        } catch {
            feedback(form, messages.failed, 'error');
        } finally {
            clearTimeout(timeout);
            state.pending = false;
            fields.forEach((field) => { field.disabled = false; });
            form.removeAttribute('aria-busy');
            original.forEach(({button, disabled, content}) => {
                button.disabled = disabled;
                if (button.tagName === 'INPUT') button.value = content;
                else button.innerHTML = content;
            });
        }
    }

    scan(document);
    window.aioContactValidationLoaded = true;
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node.nodeType === Node.ELEMENT_NODE) scan(node);
    }))).observe(document.body, {childList: true, subtree: true});

    document.addEventListener('submit', (event) => {
        const form = event.target;
        initialize(form);
        const state = forms.get(form);
        if (!state) return;
        if (state.pending) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        state.attempted = true;
        let firstInvalid;
        controls(form).forEach((field) => {
            const text = validate(field);
            showError(field, text);
            if (text && !firstInvalid) firstInvalid = field;
        });
        if (!firstInvalid) {
            if (canSendAjax(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                void send(form, state, event.submitter);
            }
            return;
        }
        feedback(form, '', 'error');
        event.preventDefault();
        event.stopImmediatePropagation();
        focusError(firstInvalid);
    }, true);

    document.addEventListener('focusout', (event) => {
        const field = event.target;
        const state = forms.get(field.form);
        if (!state || !controls(field.form).includes(field)) return;
        state.touched.add(field);
        showError(field, validate(field));
    });

    document.addEventListener('input', (event) => {
        const field = event.target;
        const state = forms.get(field.form);
        if (state && (state.attempted || state.touched.has(field))) showError(field, validate(field));
    });
    document.addEventListener('change', (event) => {
        const field = event.target;
        const state = forms.get(field.form);
        if (state && (state.attempted || state.touched.has(field))) showError(field, validate(field));
    });
})();

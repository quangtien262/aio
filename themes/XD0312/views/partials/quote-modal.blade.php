<dialog id="xd12-quote-dialog" class="xd12-q" aria-labelledby="xd12-q-title">
    <button type="button" class="xd12-q-close" data-quote-close aria-label="{{ __('xd0312_quote.close') }}">×</button>
    <div class="xd12-q-grid">
        <aside class="xd12-q-intro">
            <span class="xd12-q-eyebrow">{{ __('xd0312_quote.eyebrow') }}</span>
            <h2>{{ __('xd0312_quote.title') }}</h2>
            <p>{{ __('xd0312_quote.intro') }}</p>
            <ol>@foreach(['step1', 'step2', 'step3'] as $step)<li>{{ __('xd0312_quote.'.$step) }}</li>@endforeach</ol>
        </aside>
        <section class="xd12-q-main">
            <h2 id="xd12-q-title">{{ __('xd0312_quote.form_title') }}</h2>
            <p class="xd12-q-note">{{ __('xd0312_quote.required') }}</p>
            <form action="{{ route('site.contact.submit') }}" method="post" data-quote-form data-contact-validation-custom novalidate>
                @csrf
                <input type="hidden" name="source" value="quote_modal">
                <div class="xd12-q-fields">
                    @foreach(['name' => ['name', 'text', 120, 'name'], 'email' => ['email', 'email', 150, 'email'], 'phone' => ['phone', 'tel', 30, 'tel'], 'subject' => ['service', 'text', 150, 'off'], 'route_summary' => ['address', 'text', 255, 'street-address']] as $field => $options)
                        <label class="{{ $field === 'route_summary' ? 'xd12-q-wide' : '' }}">{{ __('xd0312_quote.'.$options[0]) }}{{ in_array($field, ['name', 'email']) ? ' *' : '' }}
                            <input name="{{ $field }}" type="{{ $options[1] }}" maxlength="{{ $options[2] }}" autocomplete="{{ $options[3] }}" @required(in_array($field, ['name', 'email'])) aria-describedby="xd12-q-error-{{ $field }}">
                            <small class="xd12-q-error" id="xd12-q-error-{{ $field }}" data-error-for="{{ $field }}"></small>
                        </label>
                    @endforeach
                    <label class="xd12-q-wide">{{ __('xd0312_quote.message') }} *
                        <textarea name="message" rows="3" required minlength="10" maxlength="5000" placeholder="{{ __('xd0312_quote.placeholder') }}" aria-describedby="xd12-q-error-message"></textarea>
                        <small class="xd12-q-error" id="xd12-q-error-message" data-error-for="message"></small>
                    </label>
                </div>
                <p class="xd12-q-feedback" role="alert" hidden></p>
                <p class="xd12-q-note">{{ __('xd0312_quote.privacy') }}</p>
                <button class="xd12-q-submit" type="submit">{{ __('xd0312_quote.send') }} <span aria-hidden="true">→</span></button>
            </form>
            <div class="xd12-q-success" tabindex="-1" role="status" hidden>
                <span aria-hidden="true">✓</span><h3>{{ __('xd0312_quote.success_title') }}</h3><p>{{ __('xd0312_quote.success') }}</p>
                <button type="button" class="xd12-q-submit" data-quote-close>{{ __('xd0312_quote.close') }}</button>
            </div>
        </section>
    </div>
</dialog>
<style>
:root{--xd12-quote-font:"Segoe UI",Arial,sans-serif}
.xd12-q{width:min(920px,calc(100% - 32px));max-height:calc(100dvh - 32px);padding:0;border:0;border-radius:22px;overflow:auto;color:#171a2c;background:#fff;font-family:var(--xd12-quote-font);box-shadow:0 30px 100px #0005}.xd12-q *{box-sizing:border-box}.xd12-q::backdrop{background:#060d2abc;backdrop-filter:blur(5px)}.xd12-q-grid{display:grid;grid-template-columns:.8fr 1.3fr}.xd12-q-intro{padding:44px 32px;background:radial-gradient(circle at 0 100%,#293761,#060d2a 75%);color:#fff}.xd12-q-eyebrow{color:#ffb843;font-size:11px;font-weight:800;letter-spacing:.14em}.xd12-q-intro h2{font:700 32px/1.25 var(--xd12-quote-font);margin:22px 0}.xd12-q-intro p{font-size:14px;line-height:1.8;color:#cbd2e2}.xd12-q-intro ol{padding:0;list-style:none;counter-reset:quote;margin:34px 0 0}.xd12-q-intro li{counter-increment:quote;display:flex;align-items:center;gap:12px;margin:20px 0;font-size:14px}.xd12-q-intro li:before{content:counter(quote);display:grid;place-items:center;width:28px;height:28px;flex-shrink:0;border:1px solid #ffb84370;border-radius:50%;color:#ffb843}.xd12-q-main{padding:38px 32px}.xd12-q-main h2{font:750 25px/1.3 var(--xd12-quote-font);margin:0 28px 8px 0}.xd12-q-note{font-size:12px;line-height:1.6;color:#667085;margin:8px 0 18px}.xd12-q-close{position:absolute;right:12px;top:12px;border:0;width:34px;height:34px;border-radius:50%;background:#edf0f5;color:#171a2c;font-size:25px;cursor:pointer}.xd12-q-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px}.xd12-q-fields label{display:grid;gap:6px;font-size:12px;font-weight:700;min-width:0}.xd12-q-wide{grid-column:1/-1}.xd12-q-fields input,.xd12-q-fields textarea{width:100%;min-width:0;border:1px solid #dce1e9;border-radius:9px;padding:11px 12px;font:14px/1.5 var(--xd12-quote-font);background:#fbfcfe;color:#171a2c}.xd12-q-fields textarea{resize:vertical}.xd12-q-fields input:focus,.xd12-q-fields textarea:focus{outline:2px solid #ff9d1070;border-color:#d47b00;outline-offset:1px}.xd12-q-submit{width:100%;display:flex;justify-content:center;gap:14px;padding:14px 20px;border:0;border-radius:10px;background:var(--xd12-orange,#ffb51b);color:#060d2a;font:750 14px var(--xd12-quote-font);cursor:pointer}.xd12-q-submit:disabled{opacity:.65;cursor:wait}.xd12-q-error{color:#b42318;font-weight:500}.xd12-q-error:empty{display:none}.xd12-q [aria-invalid=true]{border-color:#b42318}.xd12-q-feedback{padding:10px;background:#fff1f0;color:#b42318;border-radius:8px;font-size:13px}.xd12-q-success{text-align:center;padding:38px 0}.xd12-q-success>span{display:inline-grid;place-items:center;width:64px;height:64px;border-radius:50%;background:#e6f6ed;color:#18794e;font-size:32px}.xd12-q-success p{color:#667085;line-height:1.7}.xd12-q [hidden]{display:none!important}body.xd12-quote-open{overflow:hidden}
@media(max-width:700px){.xd12-q-grid{grid-template-columns:1fr}.xd12-q-intro{padding:26px 24px}.xd12-q-intro h2{font-size:24px;margin:12px 24px 8px 0}.xd12-q-intro p{margin:0}.xd12-q-intro ol{display:none}.xd12-q-main{padding:24px}.xd12-q-close{background:#ffffffea}.xd12-q-fields{grid-template-columns:1fr}.xd12-q{border-radius:16px}}
</style>
<script>
(() => {
    const dialog = document.getElementById('xd12-quote-dialog');
    const form = dialog.querySelector('form');
    const submit = form.querySelector('[type=submit]');
    const feedback = dialog.querySelector('.xd12-q-feedback');
    const success = dialog.querySelector('.xd12-q-success');
    const initialLabel = submit.innerHTML;
    let busy = false, opener;
    const clearErrors = () => {
        feedback.hidden = true;
        form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
        form.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
    };
    document.querySelectorAll('[data-xd12-quote-open]').forEach(button => button.addEventListener('click', event => {
        event.preventDefault();
        opener = button;
        if (success.hidden === false) { success.hidden = true; form.hidden = false; }
        dialog.showModal();
        document.body.classList.add('xd12-quote-open');
    }));
    dialog.querySelectorAll('[data-quote-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { const r = dialog.getBoundingClientRect(); if (event.target === dialog && (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom)) dialog.close(); });
    dialog.addEventListener('close', () => { document.body.classList.remove('xd12-quote-open'); opener?.focus(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        clearErrors();
        let invalid = false;
        for (const field of form.querySelectorAll('input:not([type=hidden]), textarea')) {
            field.value = field.value.trim();
            if (!field.checkValidity() || (field.minLength > 0 && field.value.length < field.minLength)) {
                invalid = true;
                field.setAttribute('aria-invalid', 'true');
                const error = form.querySelector('[data-error-for="' + field.name + '"]');
                error.textContent = field.validity.valueMissing ? @json(__('xd0312_quote.missing')) : (field.type === 'email' ? @json(__('xd0312_quote.email_invalid')) : @json(__('xd0312_quote.message_short')));
            }
        }
        if (invalid) { form.querySelector('[aria-invalid=true]')?.focus(); return; }
        busy = true; submit.disabled = true;
        submit.textContent = @json(__('xd0312_quote.sending'));
        try {
            const response = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}, credentials:'same-origin'});
            const data = await response.json();
            if (!response.ok) {
                feedback.textContent = response.status === 422 ? @json(__('xd0312_quote.invalid')) : @json(__('xd0312_quote.error'));
                feedback.hidden = false;
                for (const [name, messages] of Object.entries(data.errors || {})) {
                    const field = form.elements.namedItem(name);
                    const error = [...form.querySelectorAll('[data-error-for]')].find(el => el.dataset.errorFor === name);
                    if (field && error) { field.setAttribute('aria-invalid','true'); error.textContent = messages[0]; }
                }
                form.querySelector('[aria-invalid=true]')?.focus();
                return;
            }
            form.reset(); form.hidden = true; success.hidden = false; success.focus();
        } catch (_) { feedback.textContent = @json(__('xd0312_quote.error')); feedback.hidden = false; }
        finally { busy = false; submit.disabled = false; submit.innerHTML = initialLabel; }
    });
})();
</script>

<dialog id="dn350-quote-dialog" class="dn350-q" aria-labelledby="dn350-q-title">
    <button type="button" class="dn350-q-close" data-quote-close aria-label="{{ __('dn350_quote.close') }}">×</button>
    <div class="dn350-q-grid">
        <aside class="dn350-q-intro">
            <span class="dn350-q-eyebrow">{{ __('dn350_quote.eyebrow') }}</span>
            <h2>{{ __('dn350_quote.title') }}</h2>
            <p>{{ __('dn350_quote.intro') }}</p>
            <ol>@foreach(['step1', 'step2', 'step3'] as $step)<li>{{ __('dn350_quote.'.$step) }}</li>@endforeach</ol>
        </aside>
        <section class="dn350-q-main">
            <h2 id="dn350-q-title">{{ __('dn350_quote.form_title') }}</h2>
            <p class="dn350-q-note">{{ __('dn350_quote.required') }}</p>
            <form action="{{ route('site.contact.submit') }}" method="post" data-quote-form>
                @csrf
                <input type="hidden" name="source" value="quote_modal">
                <div class="dn350-q-fields">
                    @foreach(['name' => ['name', 'text', 120, 'name'], 'email' => ['email', 'email', 150, 'email'], 'phone' => ['phone', 'tel', 30, 'tel'], 'subject' => ['service', 'text', 150, 'off'], 'route_summary' => ['address', 'text', 255, 'street-address']] as $field => $options)
                        <label class="{{ $field === 'route_summary' ? 'dn350-q-wide' : '' }}">{{ __('dn350_quote.'.$options[0]) }}{{ in_array($field, ['name', 'email']) ? ' *' : '' }}
                            <input name="{{ $field }}" type="{{ $options[1] }}" maxlength="{{ $options[2] }}" autocomplete="{{ $options[3] }}" @required(in_array($field, ['name', 'email'])) aria-describedby="dn350-q-error-{{ $field }}">
                            <small class="dn350-q-error" id="dn350-q-error-{{ $field }}" data-error-for="{{ $field }}"></small>
                        </label>
                    @endforeach
                    <label class="dn350-q-wide">{{ __('dn350_quote.message') }} *
                        <textarea name="message" rows="3" required minlength="10" maxlength="5000" placeholder="{{ __('dn350_quote.placeholder') }}" aria-describedby="dn350-q-error-message"></textarea>
                        <small class="dn350-q-error" id="dn350-q-error-message" data-error-for="message"></small>
                    </label>
                </div>
                <p class="dn350-q-feedback" role="alert" hidden></p>
                <p class="dn350-q-note">{{ __('dn350_quote.privacy') }}</p>
                <button class="dn350-q-submit" type="submit">{{ __('dn350_quote.send') }} <span aria-hidden="true">→</span></button>
            </form>
            <div class="dn350-q-success" tabindex="-1" role="status" hidden>
                <span aria-hidden="true">✓</span><h3>{{ __('dn350_quote.success_title') }}</h3><p>{{ __('dn350_quote.success') }}</p>
                <button type="button" class="dn350-q-submit" data-quote-close>{{ __('dn350_quote.close') }}</button>
            </div>
        </section>
    </div>
</dialog>
<style>
.dn350-top__actions .dn350-quote{border:0;font-family:inherit;cursor:pointer}.dn350-q{width:min(920px,calc(100% - 32px));max-height:calc(100dvh - 32px);padding:0;border:0;border-radius:22px;overflow:auto;color:#171a2c;background:#fff;font-family:var(--dn350-body);box-shadow:0 30px 100px #0005}.dn350-q *{box-sizing:border-box}.dn350-q::backdrop{background:#060d2abc;backdrop-filter:blur(5px)}.dn350-q-grid{display:grid;grid-template-columns:.8fr 1.3fr}.dn350-q-intro{padding:44px 32px;background:radial-gradient(circle at 0 100%,#293761,#060d2a 75%);color:#fff}.dn350-q-eyebrow{color:#ffb843;font-size:11px;font-weight:800;letter-spacing:.14em}.dn350-q-intro h2{font:700 32px/1.25 var(--dn350-body);margin:22px 0}.dn350-q-intro p{font-size:14px;line-height:1.8;color:#cbd2e2}.dn350-q-intro ol{padding:0;list-style:none;counter-reset:quote;margin:34px 0 0}.dn350-q-intro li{counter-increment:quote;display:flex;align-items:center;gap:12px;margin:20px 0;font-size:14px}.dn350-q-intro li:before{content:counter(quote);display:grid;place-items:center;width:28px;height:28px;flex-shrink:0;border:1px solid #ffb84370;border-radius:50%;color:#ffb843}.dn350-q-main{padding:38px 32px}.dn350-q-main h2{font:750 25px/1.3 var(--dn350-body);margin:0 28px 8px 0}.dn350-q-note{font-size:12px;line-height:1.6;color:#667085;margin:8px 0 18px}.dn350-q-close{position:absolute;right:12px;top:12px;border:0;width:34px;height:34px;border-radius:50%;background:#edf0f5;color:#171a2c;font-size:25px;cursor:pointer}.dn350-q-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px}.dn350-q-fields label{display:grid;gap:6px;font-size:12px;font-weight:700;min-width:0}.dn350-q-wide{grid-column:1/-1}.dn350-q-fields input,.dn350-q-fields textarea{width:100%;min-width:0;border:1px solid #dce1e9;border-radius:9px;padding:11px 12px;font:14px/1.5 var(--dn350-body);background:#fbfcfe;color:#171a2c}.dn350-q-fields textarea{resize:vertical}.dn350-q-fields input:focus,.dn350-q-fields textarea:focus{outline:2px solid #ff9d1070;border-color:#d47b00;outline-offset:1px}.dn350-q-submit{width:100%;display:flex;justify-content:center;gap:14px;padding:14px 20px;border:0;border-radius:10px;background:#ff9d10;color:#060d2a;font:750 14px var(--dn350-body);cursor:pointer}.dn350-q-submit:disabled{opacity:.65;cursor:wait}.dn350-q-error{color:#b42318;font-weight:500}.dn350-q-error:empty{display:none}.dn350-q [aria-invalid=true]{border-color:#b42318}.dn350-q-feedback{padding:10px;background:#fff1f0;color:#b42318;border-radius:8px;font-size:13px}.dn350-q-success{text-align:center;padding:38px 0}.dn350-q-success>span{display:inline-grid;place-items:center;width:64px;height:64px;border-radius:50%;background:#e6f6ed;color:#18794e;font-size:32px}.dn350-q-success p{color:#667085;line-height:1.7}.dn350-q [hidden]{display:none!important}body.dn350-quote-open{overflow:hidden}
@media(max-width:700px){.dn350-q-grid{grid-template-columns:1fr}.dn350-q-intro{padding:26px 24px}.dn350-q-intro h2{font-size:24px;margin:12px 24px 8px 0}.dn350-q-intro p{margin:0}.dn350-q-intro ol{display:none}.dn350-q-main{padding:24px}.dn350-q-close{background:#ffffffea}.dn350-q-fields{grid-template-columns:1fr}.dn350-q{border-radius:16px}}
</style>
<script>
(() => {
    const dialog = document.getElementById('dn350-quote-dialog');
    const form = dialog.querySelector('form');
    const submit = form.querySelector('[type=submit]');
    const feedback = dialog.querySelector('.dn350-q-feedback');
    const success = dialog.querySelector('.dn350-q-success');
    const initialLabel = submit.innerHTML;
    let busy = false, opener;
    const clearErrors = () => {
        feedback.hidden = true;
        form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
        form.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
    };
    document.querySelectorAll('[data-dn350-quote-open]').forEach(button => button.addEventListener('click', () => {
        opener = button;
        if (success.hidden === false) { success.hidden = true; form.hidden = false; }
        dialog.showModal();
        document.body.classList.add('dn350-quote-open');
    }));
    dialog.querySelectorAll('[data-quote-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { const r = dialog.getBoundingClientRect(); if (event.target === dialog && (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom)) dialog.close(); });
    dialog.addEventListener('close', () => { document.body.classList.remove('dn350-quote-open'); opener?.focus(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        busy = true; submit.disabled = true; clearErrors();
        submit.textContent = @json(__('dn350_quote.sending'));
        try {
            const response = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}, credentials:'same-origin'});
            const data = await response.json();
            if (!response.ok) {
                feedback.textContent = response.status === 422 ? @json(__('dn350_quote.invalid')) : @json(__('dn350_quote.error'));
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
        } catch (_) { feedback.textContent = @json(__('dn350_quote.error')); feedback.hidden = false; }
        finally { busy = false; submit.disabled = false; submit.innerHTML = initialLabel; }
    });
})();
</script>

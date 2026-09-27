<dialog id="ec915-question-dialog" class="ec915-q" aria-labelledby="ec915-q-title">
    <button type="button" class="ec915-q-close" data-quote-close aria-label="{{ __('ec915_question.close') }}">×</button>
    <div class="ec915-q-grid">
        <aside class="ec915-q-intro">
            <span class="ec915-q-eyebrow">{{ __('ec915_question.eyebrow') }}</span>
            <h2>{{ __('ec915_question.title') }}</h2>
            <p>{{ __('ec915_question.intro') }}</p>
            <ol>@foreach(['step1', 'step2', 'step3'] as $step)<li>{{ __('ec915_question.'.$step) }}</li>@endforeach</ol>
        </aside>
        <section class="ec915-q-main">
            <h2 id="ec915-q-title">{{ __('ec915_question.form_title') }}</h2>
            <p class="ec915-q-note">{{ __('ec915_question.required') }}</p>
            <form action="{{ route('site.contact.submit') }}" method="post" data-quote-form>
                @csrf
                <input type="hidden" name="source" value="contact">
                <div class="ec915-q-fields">
                    @foreach(['name' => ['name', 'text', 120, 'name'], 'email' => ['email', 'email', 150, 'email'], 'phone' => ['phone', 'tel', 30, 'tel'], 'subject' => ['service', 'text', 150, 'off']] as $field => $options)
                        <label class="{{ $field === 'route_summary' ? 'ec915-q-wide' : '' }}">{{ __('ec915_question.'.$options[0]) }}{{ in_array($field, ['name', 'email']) ? ' *' : '' }}
                            <input name="{{ $field }}" type="{{ $options[1] }}" maxlength="{{ $options[2] }}" autocomplete="{{ $options[3] }}" @required(in_array($field, ['name', 'email'])) aria-describedby="ec915-q-error-{{ $field }}">
                            <small class="ec915-q-error" id="ec915-q-error-{{ $field }}" data-error-for="{{ $field }}"></small>
                        </label>
                    @endforeach
                    <label class="ec915-q-wide">{{ __('ec915_question.message') }} *
                        <textarea name="message" rows="3" required minlength="10" maxlength="5000" placeholder="{{ __('ec915_question.placeholder') }}" aria-describedby="ec915-q-error-message"></textarea>
                        <small class="ec915-q-error" id="ec915-q-error-message" data-error-for="message"></small>
                    </label>
                </div>
                <p class="ec915-q-feedback" role="alert" hidden></p>
                <p class="ec915-q-note">{{ __('ec915_question.privacy') }}</p>
                <button class="ec915-q-submit" type="submit">{{ __('ec915_question.send') }} <span aria-hidden="true">→</span></button>
            </form>
            <div class="ec915-q-success" tabindex="-1" role="status" hidden>
                <span aria-hidden="true">✓</span><h3>{{ __('ec915_question.success_title') }}</h3><p>{{ __('ec915_question.success') }}</p>
                <button type="button" class="ec915-q-submit" data-quote-close>{{ __('ec915_question.close') }}</button>
            </div>
        </section>
    </div>
</dialog>
<style>
.ec15-question-open{border:0;border-radius:4px;padding:15px 26px;background:var(--ec15-copper);color:#fff;font:700 14px "EC915 Sans",Arial,sans-serif;text-transform:uppercase;cursor:pointer}.ec915-q{width:min(920px,calc(100% - 32px));max-height:calc(100dvh - 32px);padding:0;border:0;border-radius:22px;overflow:auto;color:#171a2c;background:#fff;font-family:"EC915 Sans",Arial,sans-serif;box-shadow:0 30px 100px #0005}.ec915-q *{box-sizing:border-box}.ec915-q::backdrop{background:#24201dbc;backdrop-filter:blur(5px)}.ec915-q-grid{display:grid;grid-template-columns:.8fr 1.3fr}.ec915-q-intro{padding:44px 32px;background:radial-gradient(circle at 0 100%,#594035,#24201d 75%);color:#fff}.ec915-q-eyebrow{color:#edc3a7;font-size:11px;font-weight:800;letter-spacing:.14em}.ec915-q-intro h2{font:700 32px/1.25 "EC915 Sans",Arial,sans-serif;margin:22px 0}.ec915-q-intro p{font-size:14px;line-height:1.8;color:#cbd2e2}.ec915-q-intro ol{padding:0;list-style:none;counter-reset:quote;margin:34px 0 0}.ec915-q-intro li{counter-increment:quote;display:flex;align-items:center;gap:12px;margin:20px 0;font-size:14px}.ec915-q-intro li:before{content:counter(quote);display:grid;place-items:center;width:28px;height:28px;flex-shrink:0;border:1px solid #edc3a770;border-radius:50%;color:#edc3a7}.ec915-q-main{padding:38px 32px}.ec915-q-main h2{font:750 25px/1.3 "EC915 Sans",Arial,sans-serif;margin:0 28px 8px 0}.ec915-q-note{font-size:12px;line-height:1.6;color:#667085;margin:8px 0 18px}.ec915-q-close{position:absolute;right:12px;top:12px;border:0;width:34px;height:34px;border-radius:50%;background:#edf0f5;color:#171a2c;font-size:25px;cursor:pointer}.ec915-q-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px}.ec915-q-fields label{display:grid;gap:6px;font-size:12px;font-weight:700;min-width:0}.ec915-q-wide{grid-column:1/-1}.ec915-q-fields input,.ec915-q-fields textarea{width:100%;min-width:0;border:1px solid #dce1e9;border-radius:9px;padding:11px 12px;font:14px/1.5 "EC915 Sans",Arial,sans-serif;background:#fbfcfe;color:#171a2c}.ec915-q-fields textarea{resize:vertical}.ec915-q-fields input:focus,.ec915-q-fields textarea:focus{outline:2px solid #bb7d5870;border-color:#99603f;outline-offset:1px}.ec915-q-submit{width:100%;display:flex;justify-content:center;gap:14px;padding:14px 20px;border:0;border-radius:10px;background:#bb7d58;color:#fff;font:750 14px "EC915 Sans",Arial,sans-serif;cursor:pointer}.ec915-q-submit:disabled{opacity:.65;cursor:wait}.ec915-q-error{color:#b42318;font-weight:500}.ec915-q-error:empty{display:none}.ec915-q [aria-invalid=true]{border-color:#b42318}.ec915-q-feedback{padding:10px;background:#fff1f0;color:#b42318;border-radius:8px;font-size:13px}.ec915-q-success{text-align:center;padding:38px 0}.ec915-q-success>span{display:inline-grid;place-items:center;width:64px;height:64px;border-radius:50%;background:#e6f6ed;color:#18794e;font-size:32px}.ec915-q-success p{color:#667085;line-height:1.7}.ec915-q [hidden]{display:none!important}body.ec915-quote-open{overflow:hidden}
@media(max-width:700px){.ec915-q-grid{grid-template-columns:1fr}.ec915-q-intro{padding:26px 24px}.ec915-q-intro h2{font-size:24px;margin:12px 24px 8px 0}.ec915-q-intro p{margin:0}.ec915-q-intro ol{display:none}.ec915-q-main{padding:24px}.ec915-q-close{background:#ffffffea}.ec915-q-fields{grid-template-columns:1fr}.ec915-q{border-radius:16px}}
</style>
<script>
(() => {
    const dialog = document.getElementById('ec915-question-dialog');
    const form = dialog.querySelector('form');
    const submit = form.querySelector('[type=submit]');
    const feedback = dialog.querySelector('.ec915-q-feedback');
    const success = dialog.querySelector('.ec915-q-success');
    const initialLabel = submit.innerHTML;
    let busy = false, opener;
    const clearErrors = () => {
        feedback.hidden = true;
        form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
        form.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
    };
    document.querySelectorAll('[data-ec915-question-open]').forEach(button => button.addEventListener('click', () => {
        opener = button;
        if (success.hidden === false) { success.hidden = true; form.hidden = false; }
        dialog.showModal();
        document.body.classList.add('ec915-quote-open');
    }));
    dialog.querySelectorAll('[data-quote-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { const r = dialog.getBoundingClientRect(); if (event.target === dialog && (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom)) dialog.close(); });
    dialog.addEventListener('close', () => { document.body.classList.remove('ec915-quote-open'); opener?.focus(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        busy = true; submit.disabled = true; clearErrors();
        submit.textContent = @json(__('ec915_question.sending'));
        try {
            const response = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}, credentials:'same-origin'});
            const data = await response.json();
            if (!response.ok) {
                feedback.textContent = response.status === 422 ? @json(__('ec915_question.invalid')) : @json(__('ec915_question.error'));
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
        } catch (_) { feedback.textContent = @json(__('ec915_question.error')); feedback.hidden = false; }
        finally { busy = false; submit.disabled = false; submit.innerHTML = initialLabel; }
    });
})();
</script>

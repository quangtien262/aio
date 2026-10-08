<style>
body.xd6-consultation-open{overflow:hidden}
.xd6-consultation{width:min(600px,calc(100% - 32px));max-height:calc(100dvh - 32px);overflow:auto;margin:auto;padding:36px;border:0;border-top:4px solid #d32b23;border-radius:12px;background:#fff;color:#202126;box-shadow:0 24px 80px #0006;font:400 15px/1.6 "Segoe UI",Arial,sans-serif}
.xd6-consultation::backdrop{background:#09090bc9;backdrop-filter:blur(5px)}
.xd6-consultation h2{color:#202126;font-size:28px;line-height:1.3;font-weight:700;margin:0 40px 12px 0}
.xd6-consultation>p{color:#5c6069;font-size:14px;margin:0 0 24px}
.xd6-consultation__close{position:absolute;right:16px;top:16px;display:grid;place-items:center;width:36px;height:36px;border:1px solid #d7d8dc;border-radius:50%;background:#f6f6f7;color:#202126;font-size:24px;line-height:1;cursor:pointer}
.xd6-consultation__fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.xd6-consultation__fields>div{min-width:0}.xd6-consultation__wide{grid-column:1/-1}
.xd6-consultation label{display:block;margin-bottom:8px;color:#292b32;font-size:13px;font-weight:600}
.xd6-consultation input,.xd6-consultation textarea{width:100%;min-width:0;max-width:100%;padding:12px 14px;border:1px solid #c9cbd2;border-radius:6px;background:#fafafa;color:#202126;font:inherit}
.xd6-consultation textarea{display:block;resize:vertical;min-height:110px}
.xd6-consultation small{display:block;margin-top:8px;color:#5c6069;font-size:12px}
.xd6-consultation__submit{width:100%;min-height:48px;padding:12px 24px;margin-top:24px;border:0;border-radius:6px;background:#d32b23;color:#fff;font:700 15px/1.5 "Segoe UI",Arial,sans-serif;cursor:pointer}
.xd6-consultation__submit:hover{background:#b8221c}.xd6-consultation__submit:disabled{opacity:.7;cursor:wait}
.xd6-consultation :focus-visible{outline:3px solid #d32b23;outline-offset:3px}
@media(max-width:480px){.xd6-consultation{padding:28px 20px}.xd6-consultation h2{font-size:24px}.xd6-consultation__fields{grid-template-columns:minmax(0,1fr);gap:16px}}
</style>
<dialog id="xd6-consultation" class="xd6-consultation" aria-labelledby="xd6-consultation-title">
    <button type="button" class="xd6-consultation__close" data-xd6-consultation-close aria-label="{{ __('theme_contact_page.close') }}">×</button>
    <h2 id="xd6-consultation-title">{{ __('theme_contact_page.consultation_title') }}</h2>
    <p>{{ __('theme_contact_page.required_hint') }}</p>
    <form data-contact-form method="POST" action="{{ route('site.contact.submit', ['locale' => app()->getLocale()]) }}">
        @csrf
        <input type="hidden" name="source" value="contact">
        <div class="xd6-consultation__fields">
            @foreach(['name' => [__('theme_contact_page.name'), 'text', 'name', 120, true], 'phone' => [__('theme_contact_page.phone_number'), 'tel', 'tel', 30, false], 'email' => [__('theme_contact_page.email'), 'email', 'email', 150, true], 'subject' => [__('theme_contact_page.subject'), 'text', 'off', 150, false]] as $field => [$label, $type, $autocomplete, $max, $required])
                <div><label for="xd6-consultation-{{ $field }}">{{ $label }}{{ $required ? ' *' : '' }}</label><input id="xd6-consultation-{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $max }}" @required($required)></div>
            @endforeach
            <div class="xd6-consultation__wide"><label for="xd6-consultation-message">{{ __('theme_contact_page.message') }} *</label><textarea id="xd6-consultation-message" name="message" rows="4" minlength="10" maxlength="5000" required aria-describedby="xd6-consultation-hint"></textarea><small id="xd6-consultation-hint">{{ __('theme_contact_page.message_hint') }}</small></div>
        </div>
        <button type="submit" class="xd6-consultation__submit">{{ __('theme_contact_page.send') }} →</button>
    </form>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('xd6-consultation');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    let opener;
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-xd6-consultation-open]');
        if (!trigger) return;
        event.preventDefault();
        if (dialog.open) return;
        opener = trigger;
        dialog.showModal();
        document.body.classList.add('xd6-consultation-open');
        dialog.querySelector('input[name="name"]')?.focus();
    });
    dialog.querySelector('[data-xd6-consultation-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => {
        document.body.classList.remove('xd6-consultation-open');
        opener?.focus({preventScroll:true});
    });
})();
</script>

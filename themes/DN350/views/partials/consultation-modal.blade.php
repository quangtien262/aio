@php
    $consultBranding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $consultCompany = trim((string) ($consultBranding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Chúng tôi')));
    $consultHotline = trim((string) ($consultBranding['support_hotline'] ?? ''));
    $consultEmail = trim((string) ($consultBranding['support_email'] ?? ''));
@endphp

<style>
    .dn-consult-modal[hidden]{display:none!important}.dn-consult-modal{position:fixed;inset:0;z-index:10020;display:grid;place-items:center;padding:24px}.dn-consult-modal__backdrop{position:absolute;inset:0;background:rgba(25,35,58,.84);backdrop-filter:blur(8px)}.dn-consult-dialog{position:relative;width:min(980px,100%);max-height:calc(100vh - 48px);display:grid;grid-template-columns:minmax(300px,.82fr) minmax(460px,1.18fr);overflow:auto;border:1px solid rgba(231,204,152,.48);background:#fff;box-shadow:0 40px 110px rgba(13,21,40,.5)}.dn-consult-dialog__close{position:absolute;z-index:3;top:18px;right:18px;width:42px;height:42px;display:grid;place-items:center;border:1px solid #e1e5eb;border-radius:50%;background:#fff;color:var(--dn-navy);font-size:25px;line-height:1;cursor:pointer;transition:.2s}.dn-consult-dialog__close:hover{border-color:var(--dn-champagne);background:var(--dn-champagne);transform:rotate(8deg)}.dn-consult-story{position:relative;isolation:isolate;display:flex;flex-direction:column;justify-content:space-between;min-height:620px;padding:42px 40px;overflow:hidden;background:linear-gradient(155deg,var(--dn-navy-deep),var(--dn-navy));color:#fff}.dn-consult-story::before{content:"";position:absolute;z-index:-1;width:390px;height:390px;right:-210px;top:-190px;border:1px solid rgba(255,255,255,.12);border-radius:50%;box-shadow:0 0 0 55px rgba(255,255,255,.025),0 0 0 115px rgba(255,255,255,.018)}.dn-consult-story__eyebrow{display:flex;align-items:center;gap:10px;margin:0 0 22px;color:var(--dn-champagne);font:700 12px var(--dn-body);letter-spacing:.17em;text-transform:uppercase}.dn-consult-story__eyebrow::before{content:"";width:28px;height:2px;background:var(--dn-champagne)}.dn-consult-story h2{margin:0;font:700 clamp(34px,4vw,52px)/1.05 var(--dn-display);letter-spacing:-.035em;text-transform:uppercase}.dn-consult-story__lead{margin:24px 0 0;color:rgba(255,255,255,.72);font-size:15px;line-height:1.8}.dn-consult-benefits{display:grid;gap:12px;margin:28px 0 0;padding:0;list-style:none}.dn-consult-benefits li{display:flex;align-items:center;gap:11px;color:rgba(255,255,255,.88);font-size:14px}.dn-consult-benefits i{width:28px;height:28px;display:grid;place-items:center;border-radius:50%;background:rgba(231,204,152,.16);color:var(--dn-champagne);font-size:12px}.dn-consult-contact{display:grid;gap:12px;padding-top:24px;border-top:1px solid rgba(255,255,255,.13)}.dn-consult-contact a,.dn-consult-contact span{display:flex;align-items:center;gap:12px;color:#fff;font-size:14px;font-weight:700}.dn-consult-contact i{color:var(--dn-champagne)}.dn-consult-content{padding:38px 46px 32px}.dn-consult-content__head{padding-right:48px}.dn-consult-content__head span{color:var(--dn-champagne);font-size:12px;font-weight:800;letter-spacing:.15em;text-transform:uppercase}.dn-consult-content__head h3{margin:8px 0 8px;color:var(--dn-navy);font:700 31px/1.15 var(--dn-display);text-transform:uppercase}.dn-consult-content__head p{margin:0;color:#737e94;font-size:14px;line-height:1.65}.dn-consult-form{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;margin-top:22px}.dn-consult-field{display:grid;gap:5px}.dn-consult-field--full{grid-column:1/-1}.dn-consult-field span{color:var(--dn-navy);font-size:12px;font-weight:800}.dn-consult-field input,.dn-consult-field select,.dn-consult-field textarea{width:100%;min-height:48px;padding:0 14px;border:1px solid #dce1e9;border-radius:0;background:#fbfcfd;color:var(--dn-navy);font:500 14px var(--dn-body);outline:0;transition:.2s}.dn-consult-field textarea{min-height:88px;padding-top:13px;resize:vertical}.dn-consult-field input:focus,.dn-consult-field select:focus,.dn-consult-field textarea:focus{border-color:var(--dn-navy);background:#fff;box-shadow:0 0 0 4px rgba(70,84,116,.1)}.dn-consult-field.is-invalid input,.dn-consult-field.is-invalid select,.dn-consult-field.is-invalid textarea{border-color:#c63f3f;background:#fff8f8}.dn-consult-field__error{min-height:14px;color:#b62f2f;font-size:11px;line-height:1.2}.dn-consult-agreement{grid-column:1/-1;display:flex;align-items:flex-start;gap:9px;color:#69758c;font-size:12px;line-height:1.55;cursor:pointer}.dn-consult-agreement input{margin-top:3px;accent-color:var(--dn-navy)}.dn-consult-submit{grid-column:1/-1;min-height:54px;display:flex;align-items:center;justify-content:center;gap:12px;border:0;background:var(--dn-champagne);color:var(--dn-navy);font:800 14px var(--dn-body);letter-spacing:.04em;text-transform:uppercase;cursor:pointer;transition:.25s}.dn-consult-submit:hover{background:#f0dbaa;transform:translateY(-2px)}.dn-consult-submit:disabled{opacity:.62;transform:none;cursor:progress}.dn-consult-feedback{grid-column:1/-1;margin:0;padding:12px 14px;background:#fff2f2;color:#a62e2e;font-size:13px;font-weight:700}.dn-consult-success{min-height:470px;display:grid;place-items:center;text-align:center}.dn-consult-success[hidden]{display:none!important}.dn-consult-success__icon{width:78px;height:78px;display:grid;place-items:center;margin:0 auto 22px;border-radius:50%;background:#eef7ef;color:#2b7a42;font-size:30px}.dn-consult-success h3{margin:0;color:var(--dn-navy);font:700 32px var(--dn-display);text-transform:uppercase}.dn-consult-success p{max-width:410px;margin:14px auto 25px;color:#718097;line-height:1.7}.dn-consult-success button{min-height:48px;padding:0 28px;border:0;background:var(--dn-navy);color:#fff;font-weight:800;cursor:pointer}body.dn-consult-open{overflow:hidden}@media(max-width:800px){.dn-consult-modal{align-items:end;padding:0}.dn-consult-dialog{width:100%;max-height:94vh;grid-template-columns:1fr}.dn-consult-story{display:none}.dn-consult-content{padding:38px 22px 26px}.dn-consult-content__head{padding-right:42px}.dn-consult-content__head h3{font-size:27px}}@media(max-width:520px){.dn-consult-form{grid-template-columns:1fr}.dn-consult-field--full,.dn-consult-agreement,.dn-consult-submit,.dn-consult-feedback{grid-column:auto}}
</style>

<div class="dn-consult-modal" data-dn-consult-modal hidden>
    <div class="dn-consult-modal__backdrop" data-dn-consult-close></div>
    <section class="dn-consult-dialog" role="dialog" aria-modal="true" aria-labelledby="dn-consult-title">
        <button type="button" class="dn-consult-dialog__close" aria-label="Đóng form tư vấn" data-dn-consult-close>&times;</button>
        <aside class="dn-consult-story">
            <div>
                <p class="dn-consult-story__eyebrow">Đồng hành cùng bạn</p>
                <h2>Giải pháp phù hợp bắt đầu từ một cuộc trao đổi.</h2>
                <p class="dn-consult-story__lead">Đội ngũ {{ $consultCompany }} sẽ lắng nghe nhu cầu, khảo sát thực tế và đề xuất phương án tối ưu cho công trình của bạn.</p>
                <ul class="dn-consult-benefits">
                    <li><i class="fa-solid fa-check"></i>Tư vấn rõ ràng, đúng nhu cầu</li>
                    <li><i class="fa-solid fa-check"></i>Phản hồi trong giờ làm việc</li>
                    <li><i class="fa-solid fa-check"></i>Thông tin được bảo mật</li>
                </ul>
            </div>
            <div class="dn-consult-contact">
                @if($consultHotline !== '')<a href="tel:{{ preg_replace('/[^0-9+]/', '', $consultHotline) }}"><i class="fa-solid fa-phone"></i>{{ $consultHotline }}</a>@endif
                @if($consultEmail !== '')<a href="mailto:{{ $consultEmail }}"><i class="fa-solid fa-envelope"></i>{{ $consultEmail }}</a>@endif
            </div>
        </aside>

        <div class="dn-consult-content">
            <div data-dn-consult-form-panel>
                <header class="dn-consult-content__head">
                    <span>Đăng ký tư vấn</span>
                    <h3 id="dn-consult-title">Chúng tôi có thể hỗ trợ gì cho bạn?</h3>
                    <p>Điền thông tin bên dưới, chuyên viên sẽ chủ động liên hệ trong thời gian sớm nhất.</p>
                </header>
                <form class="dn-consult-form" action="{{ route('site.contact.submit') }}" method="post" data-dn-consult-form novalidate>
                    @csrf
                    <input type="hidden" name="source" value="contact">
                    <label class="dn-consult-field"><span>Họ và tên *</span><input type="text" name="name" autocomplete="name" placeholder="Nguyễn Văn A" required maxlength="120"><small class="dn-consult-field__error" data-error-for="name"></small></label>
                    <label class="dn-consult-field"><span>Số điện thoại</span><input type="tel" name="phone" autocomplete="tel" placeholder="0901 234 567" maxlength="30"><small class="dn-consult-field__error" data-error-for="phone"></small></label>
                    <label class="dn-consult-field"><span>Email *</span><input type="email" name="email" autocomplete="email" placeholder="email@domain.com" required maxlength="150"><small class="dn-consult-field__error" data-error-for="email"></small></label>
                    <label class="dn-consult-field"><span>Nhu cầu tư vấn</span><select name="subject"><option value="Tư vấn giải pháp phù hợp">Tư vấn giải pháp phù hợp</option><option value="Khảo sát và báo giá công trình">Khảo sát và báo giá công trình</option><option value="Tư vấn sản phẩm và vật liệu">Tư vấn sản phẩm và vật liệu</option><option value="Bảo trì và hỗ trợ kỹ thuật">Bảo trì và hỗ trợ kỹ thuật</option></select><small class="dn-consult-field__error" data-error-for="subject"></small></label>
                    <label class="dn-consult-field dn-consult-field--full"><span>Nội dung cần hỗ trợ *</span><textarea name="message" required minlength="10" maxlength="5000" placeholder="Mô tả ngắn nhu cầu, loại công trình hoặc thời gian bạn muốn được liên hệ..."></textarea><small class="dn-consult-field__error" data-error-for="message"></small></label>
                    <label class="dn-consult-agreement"><input type="checkbox" required><span>Tôi đồng ý để {{ $consultCompany }} liên hệ và sử dụng thông tin trên cho mục đích tư vấn.</span></label>
                    <p class="dn-consult-feedback" data-dn-consult-feedback hidden></p>
                    <button class="dn-consult-submit" type="submit"><span>Gửi yêu cầu tư vấn</span><i class="fa-solid fa-arrow-right-long"></i></button>
                </form>
            </div>

            <div class="dn-consult-success" data-dn-consult-success hidden>
                <div><div class="dn-consult-success__icon"><i class="fa-solid fa-check"></i></div><h3>Đã gửi thành công</h3><p>Cảm ơn bạn đã để lại thông tin. Chuyên viên của chúng tôi sẽ liên hệ trong thời gian sớm nhất.</p><button type="button" data-dn-consult-close>Hoàn tất</button></div>
            </div>
        </div>
    </section>
</div>

<script>
    (() => {
        const modal = document.querySelector('[data-dn-consult-modal]');
        if (!modal || modal.dataset.ready === '1') return;
        modal.dataset.ready = '1';
        const form = modal.querySelector('[data-dn-consult-form]');
        const formPanel = modal.querySelector('[data-dn-consult-form-panel]');
        const successPanel = modal.querySelector('[data-dn-consult-success]');
        const feedback = modal.querySelector('[data-dn-consult-feedback]');
        let lastFocused = null;

        const setFeedback = (message = '') => { feedback.textContent = message; feedback.hidden = message === ''; };
        const clearErrors = () => modal.querySelectorAll('[data-error-for]').forEach((element) => { element.textContent = ''; element.closest('.dn-consult-field')?.classList.remove('is-invalid'); });
        const showErrors = (errors = {}) => Object.entries(errors).forEach(([field, messages]) => { const target = modal.querySelector(`[data-error-for="${field}"]`); if (!target) return; target.textContent = Array.isArray(messages) ? messages[0] : messages; target.closest('.dn-consult-field')?.classList.add('is-invalid'); });
        const resetView = () => { formPanel.hidden = false; successPanel.hidden = true; setFeedback(); clearErrors(); };
        const openModal = () => { lastFocused = document.activeElement; resetView(); modal.hidden = false; document.body.classList.add('dn-consult-open'); window.setTimeout(() => form.querySelector('[name="name"]')?.focus(), 30); };
        const closeModal = () => { modal.hidden = true; document.body.classList.remove('dn-consult-open'); lastFocused?.focus?.(); };

        document.addEventListener('click', (event) => { const trigger = event.target.closest('[data-dn-consult-open]'); if (!trigger) return; event.preventDefault(); openModal(); });
        modal.querySelectorAll('[data-dn-consult-close]').forEach((button) => button.addEventListener('click', closeModal));
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) closeModal(); });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors(); setFeedback();
            if (!form.reportValidity()) return;
            const submit = form.querySelector('[type="submit"]');
            const originalLabel = submit.innerHTML;
            submit.disabled = true; submit.textContent = 'Đang gửi yêu cầu...';
            try {
                const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) { showErrors(payload.errors || {}); throw new Error(payload.message || 'Vui lòng kiểm tra lại thông tin.'); }
                form.reset(); formPanel.hidden = true; successPanel.hidden = false;
            } catch (error) { setFeedback(error.message || 'Chưa thể gửi yêu cầu. Vui lòng thử lại.'); }
            finally { submit.disabled = false; submit.innerHTML = originalLabel; }
        });
    })();
</script>

<style>
    .th-landing-editor[hidden] { display: none !important; }
    .th-landing-editor { position: fixed; inset: 0; z-index: 1200; display: grid; place-items: center; padding: 18px; background: rgba(15,23,42,.62); }
    .th-landing-editor-dialog { width: min(760px, 100%); max-height: calc(100vh - 36px); overflow: auto; border-radius: 18px; background: #fff; box-shadow: 0 30px 80px rgba(0,0,0,.3); }
    .th-landing-editor-head { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 22px; border-bottom: 1px solid #e5e7eb; background: #fff; }
    .th-landing-editor-head strong { font-size: 18px; }
    .th-landing-editor-close { border: 0; background: transparent; font-size: 24px; cursor: pointer; }
    .th-landing-editor-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; padding: 20px 22px 24px; }
    .th-landing-editor-form label { display: grid; gap: 7px; color: #374151; font-size: 13px; font-weight: 700; }
    .th-landing-editor-form label.is-wide { grid-column: 1 / -1; }
    .th-landing-editor-form input, .th-landing-editor-form select, .th-landing-editor-form textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 9px; padding: 10px 11px; background: #fff; color: #111827; font: 400 14px/1.45 'Segoe UI', sans-serif; }
    .th-landing-editor-form textarea { min-height: 96px; resize: vertical; }
    .th-landing-editor-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 10px; padding-top: 4px; }
    .th-landing-editor-actions button { border: 1px solid #d1d5db; border-radius: 9px; padding: 10px 16px; background: #fff; font-weight: 700; cursor: pointer; }
    .th-landing-editor-actions button[type="submit"] { border-color: var(--th-red); background: var(--th-red); color: #fff; }
    @media (max-width: 640px) { .th-landing-editor-form { grid-template-columns: 1fr; } .th-landing-editor-form label.is-wide, .th-landing-editor-actions { grid-column: 1; } }
</style>

<div class="th-landing-editor" data-th-landing-editor hidden>
    <div class="th-landing-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="th-landing-editor-title">
        <div class="th-landing-editor-head">
            <strong id="th-landing-editor-title">Sửa khối landing page</strong>
            <button type="button" class="th-landing-editor-close" data-th-editor-close aria-label="Đóng">×</button>
        </div>
        <form class="th-landing-editor-form" data-th-editor-form>
            <input type="hidden" name="block_id">
            <label>Ngôn ngữ
                <select name="locale">
                    @foreach ($editorLocales as $locale)
                        <option value="{{ $locale['code'] }}">{{ $locale['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>Anchor
                <input name="anchor_id" maxlength="120">
            </label>
            <label class="is-wide">Tiêu đề
                <input name="title" maxlength="255">
            </label>
            <label class="is-wide">Tiêu đề phụ
                <input name="subtitle" maxlength="255">
            </label>
            <label class="is-wide">Mô tả
                <textarea name="description"></textarea>
            </label>
            <label class="is-wide">Nhãn nút
                <input name="button_label" maxlength="255">
            </label>
            <label class="is-wide">Content JSON
                <textarea name="content" rows="8"></textarea>
            </label>
            <label class="is-wide">Settings JSON
                <textarea name="settings" rows="6"></textarea>
            </label>
            <label class="is-wide">Media JSON
                <textarea name="media" rows="5"></textarea>
            </label>
            <label><span>Hiển thị</span><input type="checkbox" name="is_visible" style="width:auto"></label>
            <div class="th-landing-editor-actions">
                <button type="button" data-th-editor-close>Đóng</button>
                <button type="submit">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<script>
    (() => {
        const editor = document.querySelector('[data-th-landing-editor]');
        const form = document.querySelector('[data-th-editor-form]');
        const blocks = @json($blockPayload);
        const updateUrlTemplate = @json($blockUpdateUrlTemplate);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const field = (name) => form?.elements.namedItem(name);
        const pretty = (value) => JSON.stringify(value || {}, null, 2);
        let activeBlock = null;

        const localeData = (block, locale) => block?.data_by_locale?.[locale] || block?.data || {};
        const loadLocale = (locale) => {
            if (!activeBlock) return;
            const data = localeData(activeBlock, locale);
            field('title').value = data.title || '';
            field('subtitle').value = data.subtitle || '';
            field('description').value = data.description || '';
            field('button_label').value = data.button_label || '';
            field('content').value = pretty(data.content || {});
        };
        const close = () => { if (editor) editor.hidden = true; };

        document.querySelectorAll('[data-th-edit-block]').forEach((button) => {
            button.addEventListener('click', () => {
                activeBlock = blocks[button.dataset.thEditBlock];
                if (!activeBlock || !editor || !form) return;
                field('block_id').value = activeBlock.id;
                field('anchor_id').value = activeBlock.anchor_id || '';
                field('settings').value = pretty(activeBlock.settings || {});
                field('media').value = pretty(activeBlock.media || {});
                field('is_visible').checked = Boolean(activeBlock.is_visible);
                const locale = activeBlock.data?.locale || field('locale').value;
                field('locale').value = locale;
                loadLocale(locale);
                editor.hidden = false;
            });
        });
        field('locale')?.addEventListener('change', (event) => loadLocale(event.target.value));
        document.querySelectorAll('[data-th-editor-close]').forEach((button) => button.addEventListener('click', close));
        editor?.addEventListener('click', (event) => { if (event.target === editor) close(); });

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const parseObject = (name) => {
                try { return JSON.parse(field(name).value || '{}'); }
                catch { throw new Error(`${name} phải là JSON hợp lệ.`); }
            };
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            try {
                const response = await fetch(updateUrlTemplate.replace('__BLOCK_ID__', field('block_id').value), {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({
                        locale: field('locale').value,
                        anchor_id: field('anchor_id').value,
                        is_visible: field('is_visible').checked,
                        settings: parseObject('settings'),
                        media: parseObject('media'),
                        data: {
                            title: field('title').value,
                            subtitle: field('subtitle').value,
                            description: field('description').value,
                            button_label: field('button_label').value,
                            content: parseObject('content'),
                        },
                    }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || 'Không lưu được khối landing page.');
                window.location.reload();
            } catch (error) {
                window.alert(error.message || 'Không lưu được khối landing page.');
            } finally {
                submitButton.disabled = false;
            }
        });
    })();
</script>

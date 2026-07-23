@php
    $editorId = $editorId ?? 'storefront-inline-editor';
    $themeKey = $themeKey ?? null;
    $currentLocale = $currentLocale ?? app()->getLocale();
    $supportedLocales = $supportedLocales ?? [];
    $localeOptions = $localeOptions ?? [];
    $themeKeyPlaceholder = '__THEME_KEY__';
    $localePlaceholder = '__LOCALE__';
    $editorConfigJson = json_encode([
        'themeKey' => $themeKey,
        'currentLocale' => $currentLocale,
        'supportedLocales' => $supportedLocales,
        'localeOptions' => $localeOptions,
        'translationIndexUrlTemplate' => route('admin.api.themes.translations.index', [
            'key' => $themeKeyPlaceholder,
        ]),
        'translationUpdateUrlTemplate' => route('admin.api.themes.translations.update', [
            'key' => $themeKeyPlaceholder,
            'locale' => $localePlaceholder,
        ]),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

@if ($themeKey && $supportedLocales !== [])
    <div
        id="{{ $editorId }}"
        data-storefront-inline-editor-root
        data-config='{{ $editorConfigJson }}'
        hidden
    ></div>
    <div class="sf-inline-edit-modal" data-sf-inline-edit-modal hidden>
        <div class="sf-inline-edit-card" role="dialog" aria-modal="true" aria-labelledby="sf-inline-edit-title">
            <div class="sf-inline-edit-topbar">
                <div>
                    <h3 id="sf-inline-edit-title">Sửa nhanh nội dung</h3>
                    <p data-sf-inline-edit-summary>Chỉnh trực tiếp các bản dịch ngay trên storefront cho block đang chọn.</p>
                </div>
                <button type="button" class="sf-inline-edit-close" data-sf-inline-edit-close aria-label="Đóng">×</button>
            </div>
            <div class="sf-inline-edit-error" data-sf-inline-edit-error hidden></div>
            <div class="sf-inline-edit-stack" data-sf-inline-edit-body></div>
            <div class="sf-inline-edit-actions">
                <button type="button" class="sf-inline-edit-secondary" data-sf-inline-edit-close>Đóng</button>
                <button type="button" class="sf-inline-edit-primary" data-sf-inline-edit-save disabled>Lưu thay đổi</button>
            </div>
        </div>
    </div>

    <style>
        .sf-inline-edit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 32px;
            padding: 0 12px;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.88);
            color: var(--sf-inline-edit-ink, #0f172a);
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            backdrop-filter: blur(10px);
        }
        .sf-inline-edit-btn:hover { border-color: rgba(14, 116, 144, 0.3); color: var(--sf-inline-edit-accent, #0f766e); }
        .sf-inline-edit-modal[hidden] { display: none; }
        .sf-inline-edit-modal {
            position: fixed;
            inset: 0;
            z-index: 90;
            display: grid;
            place-items: center;
            padding: 18px;
            background: rgba(15, 23, 42, 0.5);
        }
        .sf-inline-edit-card {
            width: min(860px, 100%);
            max-height: calc(100vh - 40px);
            overflow: auto;
            padding: 24px;
            border-radius: 28px;
            background: #fff;
            box-shadow: 0 28px 90px rgba(7, 20, 33, 0.24);
        }
        .sf-inline-edit-topbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 12px; }
        .sf-inline-edit-topbar h3 { margin: 0; color: var(--sf-inline-edit-ink, #0f172a); font-size: 28px; }
        .sf-inline-edit-topbar p { margin: 6px 0 0; color: #64748b; line-height: 1.7; }
        .sf-inline-edit-close {
            width: 40px;
            height: 40px;
            border: 0;
            border-radius: 999px;
            background: #eef2f7;
            color: var(--sf-inline-edit-ink, #0f172a);
            font-size: 24px;
            cursor: pointer;
        }
        .sf-inline-edit-error {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #fff1f2;
            color: #9f1239;
            line-height: 1.6;
        }
        .sf-inline-edit-stack { display: grid; gap: 16px; }
        .sf-inline-edit-field { padding: 18px; border: 1px solid #d9e2ec; border-radius: 22px; background: #fbfdfe; }
        .sf-inline-edit-field-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
        .sf-inline-edit-field-head strong { color: var(--sf-inline-edit-ink, #0f172a); font-size: 16px; }
        .sf-inline-edit-field-key { color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
        .sf-inline-edit-locales { display: grid; gap: 12px; }
        .sf-inline-edit-locale { padding: 14px; border: 1px solid #e6edf3; border-radius: 18px; background: #fff; }
        .sf-inline-edit-locale.is-current { border-color: rgba(14, 116, 144, 0.36); background: #f0fdfa; }
        .sf-inline-edit-locale-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
        .sf-inline-edit-locale-head span { font-size: 13px; font-weight: 800; color: var(--sf-inline-edit-ink, #0f172a); }
        .sf-inline-edit-locale-head small { color: #64748b; }
        .sf-inline-edit-locale textarea {
            width: 100%;
            min-height: 90px;
            padding: 12px 14px;
            border: 1px solid #d9e2ec;
            border-radius: 16px;
            font: inherit;
            resize: vertical;
            color: #334155;
        }
        .sf-inline-edit-actions { display: flex; align-items: center; justify-content: flex-end; gap: 12px; margin-top: 18px; }
        .sf-inline-edit-secondary,
        .sf-inline-edit-primary {
            min-height: 44px;
            padding: 0 18px;
            border-radius: 999px;
            font-weight: 800;
            cursor: pointer;
        }
        .sf-inline-edit-secondary { border: 1px solid #d9e2ec; background: #fff; color: var(--sf-inline-edit-ink, #0f172a); }
        .sf-inline-edit-primary { border: 0; background: linear-gradient(135deg, var(--sf-inline-edit-accent, #0f766e), var(--sf-inline-edit-accent-deep, #155e75)); color: #fff; }
        .sf-inline-edit-primary:disabled { opacity: 0.55; cursor: not-allowed; }
    </style>

    <script>
        (() => {
            const root = document.getElementById(@js($editorId));
            if (!root || root.dataset.bound === '1') {
                return;
            }

            root.dataset.bound = '1';

            const config = JSON.parse(root.dataset.config || '{}');
            const overlay = document.querySelector('[data-sf-inline-edit-modal]');
            const body = document.querySelector('[data-sf-inline-edit-body]');
            const errorNode = document.querySelector('[data-sf-inline-edit-error]');
            const titleNode = document.getElementById('sf-inline-edit-title');
            const summaryNode = document.querySelector('[data-sf-inline-edit-summary]');
            const saveButton = document.querySelector('[data-sf-inline-edit-save]');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const localeLabel = (locale) => (config.localeOptions || []).find((item) => item.code === locale)?.name || locale.toUpperCase();

            let activeFields = [];
            let loadedEntries = {};
            let draftValues = {};
            let isSaving = false;

            const setError = (message = '') => {
                errorNode.hidden = message === '';
                errorNode.textContent = message;
            };

            const setSavingState = (saving) => {
                isSaving = saving;
                saveButton.disabled = saving || Object.keys(draftValues).length === 0;
                saveButton.textContent = saving ? 'Đang lưu...' : (Object.keys(draftValues).length > 0 ? `Lưu ${Object.keys(draftValues).length} thay đổi` : 'Lưu thay đổi');
            };

            const requestJson = async (url, options = {}) => {
                const response = await fetch(url, {
                    ...options,
                    headers: {
                        Accept: 'application/json',
                        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                        ...(options.headers || {}),
                    },
                    credentials: 'same-origin',
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || 'Không thể xử lý yêu cầu sửa nhanh.');
                }

                return data;
            };

            const escapeHtml = (value) => String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const render = () => {
                const locales = config.supportedLocales || [];

                body.innerHTML = activeFields.map((field) => {
                    const localeHtml = locales.map((locale) => {
                        const entry = loadedEntries[locale]?.[field.key] || {};
                        const draftKey = `${locale}::${field.group || 'content'}::${field.key}`;
                        const value = Object.prototype.hasOwnProperty.call(draftValues, draftKey)
                            ? draftValues[draftKey]
                            : (entry.override_value ?? entry.effective_value ?? '');

                        return `
                            <div class="sf-inline-edit-locale ${locale === config.currentLocale ? 'is-current' : ''}">
                                <div class="sf-inline-edit-locale-head">
                                    <span>Bản dịch ${escapeHtml(localeLabel(locale))}</span>
                                    <small>${locale === config.currentLocale ? 'Đang hiển thị trên trang' : escapeHtml(locale.toUpperCase())}</small>
                                </div>
                                <textarea data-sf-inline-input data-locale="${escapeHtml(locale)}" data-key="${escapeHtml(field.key)}" placeholder="${escapeHtml(entry.default_value || '')}">${escapeHtml(value)}</textarea>
                            </div>`;
                    }).join('');

                    return `
                        <section class="sf-inline-edit-field">
                            <div class="sf-inline-edit-field-head">
                                <strong>${escapeHtml(field.label)}</strong>
                                <span class="sf-inline-edit-field-key">${escapeHtml(field.key)}</span>
                            </div>
                            <div class="sf-inline-edit-locales">${localeHtml}</div>
                        </section>`;
                }).join('');

                setSavingState(isSaving);
            };

            const updateDisplays = () => {
                const currentLocaleEntries = loadedEntries[config.currentLocale] || {};
                document.querySelectorAll('[data-translation-display]').forEach((node) => {
                    const key = node.getAttribute('data-translation-display');
                    const entry = currentLocaleEntries[key];

                    if (entry) {
                        node.textContent = entry.override_value ?? entry.effective_value ?? entry.default_value ?? '';
                    }
                });
            };

            const loadEntries = async () => {
                setError('');
                body.innerHTML = '<div style="padding:24px 0;text-align:center;color:#64748b;">Đang tải bản dịch...</div>';

                const localePayloads = await Promise.all((config.supportedLocales || []).map(async (locale) => {
                    const fieldPayloads = await Promise.all(activeFields.map(async (field) => {
                        const params = new URLSearchParams({
                            locale,
                            group: field.group || 'content',
                            page: '1',
                            per_page: '100',
                            keyword: field.key,
                        });

                        if ((field.group || 'content') === 'content' && field.entity) {
                            params.set('entity', field.entity);
                        }

                        const translationIndexUrl = config.translationIndexUrlTemplate
                            .replace('__THEME_KEY__', encodeURIComponent(config.themeKey));
                        const payload = await requestJson(`${translationIndexUrl}?${params.toString()}`);
                        const entries = payload.data?.entries || [];
                        const matchedEntry = entries.find((entry) => entry.key === field.key) || {
                            key: field.key,
                            default_value: '',
                            effective_value: '',
                            override_value: '',
                        };

                        return [field.key, matchedEntry];
                    }));

                    return [locale, Object.fromEntries(fieldPayloads)];
                }));

                loadedEntries = Object.fromEntries(localePayloads);
                draftValues = {};
                render();
            };

            const openEditor = async (fields, title) => {
                activeFields = fields;
                titleNode.textContent = title || 'Sửa nhanh nội dung';
                summaryNode.textContent = 'Modal này cho phép sửa cùng lúc nhiều locale cho đúng item đang chọn ngay trên storefront.';
                overlay.hidden = false;
                document.body.style.overflow = 'hidden';

                try {
                    await loadEntries();
                } catch (error) {
                    setError(error.message || 'Không tải được dữ liệu bản dịch.');
                    body.innerHTML = '';
                }
            };

            const closeEditor = () => {
                overlay.hidden = true;
                document.body.style.overflow = '';
                activeFields = [];
                loadedEntries = {};
                draftValues = {};
                setError('');
                render();
            };

            const saveEntries = async () => {
                if (Object.keys(draftValues).length === 0 || isSaving) {
                    return;
                }

                setError('');
                setSavingState(true);

                try {
                    const groupedByLocale = {};
                    Object.entries(draftValues).forEach(([draftKey, value]) => {
                        const [locale, group, key] = draftKey.split('::');
                        const scopeKey = `${locale}::${group}`;
                        groupedByLocale[scopeKey] = groupedByLocale[scopeKey] || {
                            locale,
                            group,
                            entries: [],
                        };
                        groupedByLocale[scopeKey].entries.push({ key, value });
                    });

                    for (const scope of Object.values(groupedByLocale)) {
                        const translationUpdateUrl = config.translationUpdateUrlTemplate
                            .replace('__THEME_KEY__', encodeURIComponent(config.themeKey))
                            .replace('__LOCALE__', encodeURIComponent(scope.locale));
                        await requestJson(translationUpdateUrl, {
                            method: 'PUT',
                            headers: { 'X-CSRF-TOKEN': csrfToken },
                            body: JSON.stringify({
                                locale: scope.locale,
                                group: scope.group,
                                entries: scope.entries,
                            }),
                        });
                    }

                    await loadEntries();
                    updateDisplays();
                } catch (error) {
                    setError(error.message || 'Không lưu được bản dịch.');
                } finally {
                    setSavingState(false);
                }
            };

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-sf-inline-edit-trigger]');
                const close = event.target.closest('[data-sf-inline-edit-close]');

                if (trigger) {
                    const fields = JSON.parse(trigger.getAttribute('data-edit-fields') || '[]');
                    void openEditor(fields, trigger.getAttribute('data-edit-title'));
                    return;
                }

                if (close || event.target === overlay) {
                    closeEditor();
                }
            });

            document.addEventListener('input', (event) => {
                const input = event.target.closest('[data-sf-inline-input]');
                if (!input) {
                    return;
                }

                const locale = input.getAttribute('data-locale');
                const key = input.getAttribute('data-key');
                const field = activeFields.find((item) => item.key === key);
                const draftKey = `${locale}::${field?.group || 'content'}::${key}`;
                const original = loadedEntries[locale]?.[key]?.override_value ?? loadedEntries[locale]?.[key]?.effective_value ?? '';

                if (input.value === original) {
                    delete draftValues[draftKey];
                } else {
                    draftValues[draftKey] = input.value;
                }

                setSavingState(false);
            });

            saveButton.addEventListener('click', () => {
                void saveEntries();
            });
        })();
    </script>
@endif

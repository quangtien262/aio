    <script>
        (() => {
            const slides = Array.from(document.querySelectorAll('.xd-slide'));
            const dots = Array.from(document.querySelectorAll('.xd-dot'));
            const copy = @json($heroSlides);
            const title = document.querySelector('[data-hero-title]');
            const kicker = document.querySelector('[data-hero-kicker]');
            const summary = document.querySelector('[data-hero-summary]');
            const heroLink = document.querySelector('[data-hero-link]');
            const heroCard = document.querySelector('.xd-hero-card');
            let index = 0;
            let timer = null;
            const hasMeaningfulHeroText = (item = {}) => {
                return ['kicker', 'title', 'summary', 'button_label'].some((key) => {
                    const value = String(item?.[key] || '').trim();
                    return value !== '' && !/^[\d\s.,:;!?\-+_#]+$/u.test(value);
                });
            };
            document.querySelectorAll('.xd-service-image img').forEach((image) => {
                if (!image.currentSrc && !image.getAttribute('src')) image.classList.add('is-broken');
                image.addEventListener('error', () => image.classList.add('is-broken'), {once: true});
            });
            const show = (next) => {
                if (!slides.length) return;
                index = (next + slides.length) % slides.length;
                slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === index));
                dots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === index));
                if (heroCard) heroCard.hidden = !hasMeaningfulHeroText(copy[index] || {});
                if (title) title.textContent = copy[index]?.title || '';
                if (kicker) kicker.textContent = copy[index]?.kicker || '';
                if (summary) summary.textContent = copy[index]?.summary || '';
                if (heroLink) {
                    heroLink.href = copy[index]?.link_url || '#du-an';
                    heroLink.textContent = copy[index]?.button_label || heroLink.textContent;
                }
            };
            const restart = () => { window.clearInterval(timer); timer = window.setInterval(() => show(index + 1), Number(@json(data_get($hero, 'settings.autoplay_ms', 5200)))); };
            document.querySelector('[data-slide-prev]')?.addEventListener('click', () => { show(index - 1); restart(); });
            document.querySelector('[data-slide-next]')?.addEventListener('click', () => { show(index + 1); restart(); });
            dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.slideDot || 0)); restart(); }));
            restart();

            document.querySelectorAll('[data-service-slider]').forEach((slider) => {
                const track = slider.querySelector('[data-service-track]');
                const cards = Array.from(slider.querySelectorAll('.xd-service-card'));
                const prev = slider.querySelector('[data-service-prev]');
                const next = slider.querySelector('[data-service-next]');
                const serviceDots = Array.from(slider.querySelectorAll('[data-service-dot]'));
                if (!track || cards.length <= 1) return;

                let serviceTimer = null;
                const cardStep = () => {
                    const first = cards[0];
                    const second = cards[1];
                    if (!first) return track.clientWidth;
                    if (!second) return first.getBoundingClientRect().width;
                    return second.getBoundingClientRect().left - first.getBoundingClientRect().left;
                };
                const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);
                const isScrollable = () => maxScroll() > 6;
                const setServiceControls = () => {
                    const visible = isScrollable();
                    [prev, next, slider.querySelector('[data-service-dots]')].forEach((element) => {
                        if (element) element.style.display = visible ? '' : 'none';
                    });
                };
                const activeIndex = () => Math.round(track.scrollLeft / Math.max(1, cardStep()));
                const updateServiceDots = () => {
                    const current = activeIndex();
                    serviceDots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === current));
                };
                const goService = (direction = 1) => {
                    if (!isScrollable()) return;
                    const nextLeft = track.scrollLeft + (cardStep() * direction);
                    track.scrollTo({left: nextLeft > maxScroll() + 2 ? 0 : Math.max(0, nextLeft), behavior: 'smooth'});
                };
                const restartService = () => {
                    window.clearInterval(serviceTimer);
                    if (isScrollable()) serviceTimer = window.setInterval(() => goService(1), 4200);
                };

                prev?.addEventListener('click', () => { goService(-1); restartService(); });
                next?.addEventListener('click', () => { goService(1); restartService(); });
                serviceDots.forEach((dot) => dot.addEventListener('click', () => {
                    track.scrollTo({left: cardStep() * Number(dot.dataset.serviceDot || 0), behavior: 'smooth'});
                    restartService();
                }));
                track.addEventListener('scroll', () => window.requestAnimationFrame(updateServiceDots), {passive: true});
                window.addEventListener('resize', () => { setServiceControls(); updateServiceDots(); restartService(); });
                setServiceControls();
                updateServiceDots();
                restartService();
            });

            document.querySelectorAll('[data-row-slider]').forEach((slider) => {
                const track = slider.querySelector('[data-row-track]');
                const cards = Array.from(track?.children || []);
                const prev = slider.querySelector('[data-row-prev]');
                const next = slider.querySelector('[data-row-next]');
                const rowDots = Array.from(slider.querySelectorAll('[data-row-dot]'));
                const dotsWrap = slider.querySelector('[data-row-dots]');
                if (!track || cards.length <= 1) return;

                let rowTimer = null;
                const cardStep = () => {
                    const first = cards[0];
                    const second = cards[1];
                    if (!first) return track.clientWidth;
                    if (!second) return first.getBoundingClientRect().width;
                    return second.getBoundingClientRect().left - first.getBoundingClientRect().left;
                };
                const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);
                const isScrollable = () => maxScroll() > 6;
                const setControls = () => {
                    const visible = isScrollable();
                    [prev, next, dotsWrap].forEach((element) => {
                        if (element) element.style.display = visible ? '' : 'none';
                    });
                };
                const activeIndex = () => Math.round(track.scrollLeft / Math.max(1, cardStep()));
                const updateDots = () => {
                    const current = activeIndex();
                    rowDots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === current));
                };
                const go = (direction = 1) => {
                    if (!isScrollable()) return;
                    const nextLeft = track.scrollLeft + (cardStep() * direction);
                    track.scrollTo({left: nextLeft > maxScroll() + 2 ? 0 : Math.max(0, nextLeft), behavior: 'smooth'});
                };
                const restartRow = () => {
                    window.clearInterval(rowTimer);
                    if (isScrollable()) rowTimer = window.setInterval(() => go(1), 4600);
                };

                prev?.addEventListener('click', () => { go(-1); restartRow(); });
                next?.addEventListener('click', () => { go(1); restartRow(); });
                rowDots.forEach((dot) => dot.addEventListener('click', () => {
                    track.scrollTo({left: cardStep() * Number(dot.dataset.rowDot || 0), behavior: 'smooth'});
                    restartRow();
                }));
                track.addEventListener('scroll', () => window.requestAnimationFrame(updateDots), {passive: true});
                window.addEventListener('resize', () => { setControls(); updateDots(); restartRow(); });
                setControls();
                updateDots();
                restartRow();
            });

            const canEdit = @json($canEditLanding);
            if (!canEdit) return;
            const blocks = @json($blockPayload);
            const editorLocales = @json($editorLocales);
            const editorOptions = @json($landingEditorOptions ?? []);
            const updateUrlTemplate = @json($blockUpdateUrlTemplate);
            const sourcePreviewUrlTemplate = @json($blockSourcePreviewUrlTemplate);
            const mediaUploadUrl = '/admin/api/cms/media';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const editor = document.querySelector('[data-xd-editor]');
            const form = document.querySelector('[data-xd-editor-form]');
            const field = (name) => form?.querySelector(`[data-xd-field="${name}"]`);
            const pretty = (value) => JSON.stringify(value || {}, null, 2);
            const parseJson = (value, fallback) => {
                try { return value.trim() ? JSON.parse(value) : fallback; } catch (error) { throw new Error('JSON khÃ´ng há»£p lá»‡: ' + error.message); }
            };
            const itemsEditor = document.querySelector('[data-xd-items-editor]');
            const itemList = document.querySelector('[data-xd-item-list]');
            const itemHelp = document.querySelector('[data-xd-items-help]');
            const addItemButton = document.querySelector('[data-xd-add-item]');
            const itemModal = document.querySelector('[data-xd-item-modal]');
            const itemForm = document.querySelector('[data-xd-item-form]');
            const itemFormFields = document.querySelector('[data-xd-item-form-fields]');
            const itemModalTitle = document.querySelector('[data-xd-item-title]');
            const itemIndexInput = document.querySelector('[data-xd-item-index]');
            const sourceEditor = document.querySelector('[data-xd-source-editor]');
            const contactEditor = document.querySelector('[data-xd-contact-editor]');
            const contactContentFields = Array.from(document.querySelectorAll('[data-xd-content-field]'));
            const manageSourceLink = document.querySelector('[data-xd-manage-source]');
            const sourceSettingFields = Array.from(document.querySelectorAll('[data-xd-setting-field]'));
            const localeTabs = Array.from(document.querySelectorAll('[data-xd-locale-tab]'));
            let activeItemKey = '';
            let activeBlock = null;
            let activeEditorLocale = @json(app()->getLocale());
            let localeDrafts = {};
            let sourcePreviewController = null;
            let sourcePreviewTimer = null;
            const sourceLabels = {
                custom: 'Ná»™i dung tÃ¹y chá»‰nh',
                catalog_categories: 'Danh má»¥c sáº£n pháº©m',
                cms_service_categories: 'Danh má»¥c dá»‹ch vá»¥',
                cms_categories: 'Danh má»¥c tin tá»©c',
                cms_services: 'Dá»‹ch vá»¥',
                cms_products: 'Sáº£n pháº©m',
                catalog_products: 'Sáº£n pháº©m',
                featured_products: 'Sáº£n pháº©m ná»•i báº­t',
                cms_posts: 'Tin tá»©c',
                latest_posts: 'Tin má»›i nháº¥t',
                cms_projects: 'Dá»± Ã¡n',
                cms_team_members: 'Äá»™i ngÅ©',
                cms_testimonials: 'ÄÃ¡nh giÃ¡',
            };
            const sourceManageUrls = {
                cms_services: '/admin/cms/services',
                catalog_categories: '/admin/cms/products',
                cms_service_categories: '/admin/cms/services',
                cms_categories: '/admin/cms/posts',
                cms_posts: '/admin/cms/posts',
                latest_posts: '/admin/cms/posts',
                cms_products: '/admin/cms/products',
                catalog_products: '/admin/cms/products',
                featured_products: '/admin/cms/products',
                cms_projects: '/admin/cms/projects',
                cms_team_members: '/admin/cms/team',
                cms_testimonials: '/admin/cms/testimonials',
            };
            const defaultBlockLimits = {
                hero_slider: 3,
                featured_categories: 6,
                content_mosaic: 5,
                content_showcase: 5,
                featured_services: 3,
                latest_posts: 3,
                project_gallery: 4,
                team_members: 4,
                testimonials: 2,
                partner_logos: 6,
            };
            const categoryOptionsBySource = editorOptions.categories_by_source || {};

            const escapeHtml = (value = '') => String(value).replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));
            const uploadItemImage = async (file, targetInput, statusNode, triggerButton) => {
                if (!file || !targetInput) return;

                const payload = new FormData();
                payload.append('file', file);
                payload.append('title', file.name.replace(/\.[^.]+$/, ''));
                payload.append('alt_text', targetInput.closest('form')?.querySelector('[data-xd-item-modal-field="title"], [data-xd-item-modal-field="name"]')?.value || file.name);

                if (statusNode) statusNode.textContent = 'Äang upload...';
                if (triggerButton) triggerButton.disabled = true;

                try {
                    const response = await fetch(mediaUploadUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrf ? {'X-CSRF-TOKEN': csrf} : {}),
                        },
                        body: payload,
                    });

                    if (!response.ok) {
                        const errorPayload = await response.json().catch(() => ({}));
                        throw new Error(errorPayload.message || 'Upload áº£nh khÃ´ng thÃ nh cÃ´ng.');
                    }

                    const result = await response.json();
                    targetInput.value = result?.data?.file_url || '';
                    targetInput.dispatchEvent(new Event('input', {bubbles: true}));
                    if (statusNode) statusNode.textContent = 'ÄÃ£ upload áº£nh.';
                } catch (error) {
                    if (statusNode) statusNode.textContent = error.message || 'KhÃ´ng upload Ä‘Æ°á»£c áº£nh.';
                } finally {
                    if (triggerButton) triggerButton.disabled = false;
                }
            };
            const parseItemData = (row) => {
                try { return JSON.parse(row?.dataset.xdItem || '{}'); } catch (error) { return {}; }
            };
            const normalizeContentObject = (value) => {
                return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
            };
            const syncContactEditorVisibility = (block) => {
                if (!contactEditor) return;
                contactEditor.hidden = (block?.block_type || '') !== 'landing_contact';
            };
            const loadContactContentFields = (content = {}) => {
                contactContentFields.forEach((input) => {
                    input.value = content?.[input.dataset.xdContentField] ?? '';
                });
            };
            const mergeContactContentFields = (content = {}) => {
                if (!contactEditor || contactEditor.hidden) return content;

                const next = {...normalizeContentObject(content)};
                contactContentFields.forEach((input) => {
                    next[input.dataset.xdContentField] = input.value.trim();
                });

                return next;
            };
            const settingField = (name) => sourceSettingFields.find((input) => input.dataset.xdSettingField === name);
            const sourceControlWrap = (name) => settingField(name)?.closest('label');
            const currentSourceValue = () => settingField('source')?.value || activeBlock?.settings?.source || '';
            const isCustomSource = () => !sourceEditor || sourceEditor.hidden || currentSourceValue() === 'custom' || currentSourceValue() === '';
            const syncSourceModeUi = () => {
                const customMode = isCustomSource();
                ['limit', 'category_id', 'featured_only'].forEach((name) => {
                    const wrap = sourceControlWrap(name);
                    const input = settingField(name);
                    if (wrap) wrap.hidden = customMode;
                    if (input) input.disabled = customMode;
                });
                if (addItemButton) addItemButton.hidden = !customMode;
                if (manageSourceLink) {
                    const source = currentSourceValue();
                    const url = sourceManageUrls[source] || '';
                    manageSourceLink.hidden = customMode || url === '';
                    manageSourceLink.href = url || '#';
                    manageSourceLink.textContent = `Quáº£n lÃ½ ${sourceLabels[source] || 'ná»™i dung'}`;
                }
            };
            const normalizeSourceOptions = (options = []) => options
                .map((option) => typeof option === 'string' ? {value: option, label: sourceLabels[option] || option} : {
                    value: option.value || option.key || '',
                    label: option.label || sourceLabels[option.value || option.key] || option.value || option.key || '',
                })
                .filter((option) => option.value !== '');
            const renderCategorySelect = (source, selectedValue = '') => {
                const categorySelect = settingField('category_id');
                if (!categorySelect) return;

                const options = categoryOptionsBySource[source] || [];
                categorySelect.innerHTML = [
                    '<option value="">Táº¥t cáº£ danh má»¥c</option>',
                    ...options.map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`),
                ].join('');
                categorySelect.value = selectedValue ? String(selectedValue) : '';
                categorySelect.disabled = options.length === 0;
                categorySelect.onchange = () => scheduleSourcePreview();
            };
            const renderSourceEditor = (block) => {
                if (!sourceEditor) return;
                const schema = block?.settings_schema || {};
                const sourceSchema = schema.source || null;
                const options = normalizeSourceOptions(sourceSchema?.options || []);

                if (!sourceSchema || options.length === 0) {
                    sourceEditor.hidden = true;
                    return;
                }

                const settings = parseJson(field('settings').value, {});
                const sourceSelect = settingField('source');
                if (sourceSelect) {
                    sourceSelect.innerHTML = options
                        .map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`)
                        .join('');
                    sourceSelect.value = settings.source || options[0]?.value || '';
                    sourceSelect.onchange = () => {
                        renderCategorySelect(sourceSelect.value, '');
                        syncSourceModeUi();
                        if (sourceSelect.value === 'custom') {
                            renderItemsEditor(activeBlock, parseJson(field('content').value, {}));
                        } else {
                            scheduleSourcePreview();
                        }
                    };
                }

                const limitInput = settingField('limit');
                if (limitInput) {
                    limitInput.value = settings.limit ?? schema.limit?.default ?? defaultBlockLimits[block.block_type] ?? 3;
                    limitInput.oninput = () => scheduleSourcePreview();
                }

                renderCategorySelect(sourceSelect?.value || settings.source || options[0]?.value || '', settings.category_id ?? '');

                const featuredInput = settingField('featured_only');
                if (featuredInput) {
                    featuredInput.checked = settings.featured_only !== false;
                    featuredInput.onchange = () => scheduleSourcePreview();
                }

                sourceEditor.hidden = false;
                syncSourceModeUi();
            };
            const collectSourceSettings = (settings) => {
                if (!sourceEditor || sourceEditor.hidden) return settings;

                const next = {...settings};
                const source = settingField('source')?.value || '';
                const limit = Number(settingField('limit')?.value || 0);
                const categoryId = Number(settingField('category_id')?.value || 0);
                const featuredOnly = settingField('featured_only');

                if (source !== '') next.source = source;
                if (source === 'custom') {
                    delete next.limit;
                    delete next.category_id;
                    delete next.featured_only;
                    return next;
                }
                if (limit > 0) next.limit = Math.max(1, Math.min(12, limit));
                else delete next.limit;
                if (categoryId > 0) next.category_id = categoryId;
                else delete next.category_id;
                if (featuredOnly) next.featured_only = Boolean(featuredOnly.checked);

                return next;
            };

            const editorItemKey = (blockType) => blockType === 'hero_slider' ? 'slides' : 'items';
            const editorItemFields = (blockType) => {
                if (blockType === 'hero_slider') {
                    return [
                        ['kicker', 'NhÃ£n nhá»'],
                        ['title', 'TiÃªu Ä‘á»'],
                        ['summary', 'MÃ´ táº£', 'textarea'],
                        ['image', 'áº¢nh'],
                        ['link_url', 'Link'],
                        ['button_label', 'NÃºt báº¥m'],
                    ];
                }

                if (blockType === 'featured_categories') {
                    return [
                        ['title', 'TiÃªu Ä‘á»'],
                        ['summary', 'MÃ´ táº£ / nhÃ£n phá»¥', 'textarea'],
                        ['image', 'áº¢nh'],
                        ['icon', 'Icon / kÃ½ tá»±'],
                        ['url', 'Link'],
                        ['count_label', 'NhÃ£n sá»‘ lÆ°á»£ng'],
                    ];
                }

                if (blockType === 'content_mosaic') {
                    return [
                        ['title', 'TiÃƒÂªu Ã„â€˜Ã¡Â»Â'],
                        ['summary', 'MÃƒÂ´ tÃ¡ÂºÂ£', 'textarea'],
                        ['image', 'Ã¡ÂºÂ¢nh'],
                        ['url', 'Link'],
                    ];
                }

                if (blockType === 'faq_showcase') {
                    return [
                        ['question', 'CÃ¢u há»i'],
                        ['answer', 'CÃ¢u tráº£ lá»i', 'textarea'],
                    ];
                }

                                if (blockType === 'process_steps') {
                    return [
                        ['title', 'Tiêu đề bước'],
                        ['description', 'Mô tả', 'textarea'],
                    ];
                }

if (['content_showcase', 'latest_posts'].includes(blockType)) {
                    return [
                        ['title', 'TiÃªu Ä‘á»'],
                        ['summary', 'MÃ´ táº£', 'textarea'],
                        ['image', 'áº¢nh'],
                        ['url', 'Link'],
                    ];
                }

                if (blockType === 'testimonials') {
                    return [
                        ['name', 'TÃªn khÃ¡ch hÃ ng'],
                        ['company', 'CÃ´ng ty / vai trÃ²'],
                        ['quote', 'Nháº­n xÃ©t', 'textarea'],
                        ['image', 'áº¢nh Ä‘áº¡i diá»‡n'],
                        ['url', 'Link'],
                    ];
                }

                if (blockType === 'team_members') {
                    return [
                        ['name', 'TÃªn nhÃ¢n sá»±'],
                        ['role', 'Chá»©c vá»¥'],
                        ['image', 'áº¢nh'],
                        ['url', 'Link'],
                    ];
                }

                if (blockType === 'partner_logos') {
                    return [
                        ['name', 'TÃªn Ä‘á»‘i tÃ¡c'],
                        ['image', 'Logo / áº£nh'],
                        ['url', 'Link'],
                        ['alt', 'Alt áº£nh'],
                    ];
                }

                return [
                    ['title', 'TiÃªu Ä‘á»'],
                    ['summary', 'MÃ´ táº£', 'textarea'],
                    ['image', 'áº¢nh'],
                    ['url', 'Link'],
                    ['button_label', 'NÃºt báº¥m'],
                ];
            };
            const renderEditorItem = (item = {}, index = 0, blockType = '', canEditItem = true) => {
                const row = document.createElement('article');
                row.className = 'xd-editor-item';
                row.dataset.xdItemRow = '1';
                row.dataset.xdItem = JSON.stringify(item || {});
                const title = item.title || item.name || item.kicker || `Má»¥c ${index + 1}`;
                const summary = item.summary || item.description || item.quote || item.role || item.company || item.url || item.link_url || 'ChÆ°a cÃ³ mÃ´ táº£.';
                const image = item.image || item.image_url || item.thumbnail || item.logo || item.avatar || '';
                const imageAlt = item.alt || title;
                const thumb = image
                    ? `<span class="xd-editor-item-thumb"><img src="${escapeHtml(image)}" alt="${escapeHtml(imageAlt)}"></span>`
                    : '<span class="xd-editor-item-thumb is-empty">No img</span>';
                row.innerHTML = `
                    <div class="xd-editor-item-main">
                        ${thumb}
                        <div class="xd-editor-item-summary">
                            <small>Má»¥c ${index + 1}</small>
                            <strong>${escapeHtml(title)}</strong>
                            <span>${escapeHtml(summary)}</span>
                        </div>
                    </div>
                    ${canEditItem ? `<div class="xd-editor-item-actions">
                        <button type="button" class="xd-editor-edit" data-xd-edit-item>Sá»­a</button>
                        <button type="button" class="xd-editor-remove" data-xd-remove-item>XÃ³a</button>
                    </div>` : ''}
                `;
                if (canEditItem) {
                    row.querySelector('[data-xd-edit-item]')?.addEventListener('click', () => {
                        const currentIndex = Array.from(itemList.querySelectorAll('[data-xd-item-row]')).indexOf(row);
                        openItemModal(currentIndex, parseItemData(row));
                    });
                    row.querySelector('[data-xd-remove-item]')?.addEventListener('click', () => {
                        row.remove();
                        syncItemNumbers();
                    });
                }
                return row;
            };
            const syncItemNumbers = () => {
                itemList?.querySelectorAll('[data-xd-item-row]').forEach((row, index) => {
                    const badge = row.querySelector('.xd-editor-item-summary small');
                    if (badge) badge.textContent = `Má»¥c ${index + 1}`;
                });
            };
            const closeItemModal = () => {
                if (!itemModal) return;
                itemModal.hidden = true;
                if (itemFormFields) itemFormFields.innerHTML = '';
                if (itemIndexInput) itemIndexInput.value = '';
            };
            const openItemModal = (index = null, item = {}) => {
                if (!itemModal || !itemFormFields || !activeBlock) return;
                const blockType = activeBlock.block_type || addItemButton?.dataset.xdBlockType || '';
                itemModalTitle.textContent = index === null ? 'ThÃªm má»¥c' : `Sá»­a má»¥c ${index + 1}`;
                itemIndexInput.value = index === null ? '' : String(index);
                itemFormFields.innerHTML = '';
                editorItemFields(blockType).forEach(([key, label, type]) => {
                    const control = document.createElement('label');
                    control.className = type === 'textarea' ? 'is-wide' : '';
                    if (key === 'image') control.className = `${control.className} xd-item-image-field`.trim();
                    control.innerHTML = type === 'textarea'
                        ? `<span>${escapeHtml(label)}</span><textarea data-xd-item-modal-field="${escapeHtml(key)}"></textarea>`
                        : `<span>${escapeHtml(label)}</span><input data-xd-item-modal-field="${escapeHtml(key)}">`;
                    const input = control.querySelector('[data-xd-item-modal-field]');
                    input.value = item?.[key] ?? '';
                    if (key === 'image') {
                        const modeWrap = document.createElement('div');
                        modeWrap.className = 'xd-image-mode';
                        modeWrap.innerHTML = `
                            <label><input type="radio" name="xd_item_image_mode" value="url" checked> Nháº­p liÃªn káº¿t hÃ¬nh áº£nh</label>
                            <label><input type="radio" name="xd_item_image_mode" value="upload"> Upload áº£nh</label>
                        `;
                        const uploadWrap = document.createElement('div');
                        uploadWrap.className = 'xd-item-upload';
                        uploadWrap.hidden = true;
                        uploadWrap.innerHTML = '<input type="file" accept="image/*" data-xd-item-upload hidden><button type="button" data-xd-item-upload-trigger>Upload áº£nh</button><small data-xd-item-upload-status></small>';
                        const fileInput = uploadWrap.querySelector('[data-xd-item-upload]');
                        const triggerButton = uploadWrap.querySelector('[data-xd-item-upload-trigger]');
                        const statusNode = uploadWrap.querySelector('[data-xd-item-upload-status]');
                        const syncImageMode = () => {
                            const mode = modeWrap.querySelector('input[name="xd_item_image_mode"]:checked')?.value || 'url';
                            uploadWrap.hidden = mode !== 'upload';
                            control.classList.toggle('is-upload-mode', mode === 'upload');
                        };
                        modeWrap.querySelectorAll('input[name="xd_item_image_mode"]').forEach((radio) => radio.addEventListener('change', syncImageMode));
                        triggerButton?.addEventListener('click', () => fileInput?.click());
                        fileInput?.addEventListener('change', () => {
                            const file = fileInput.files?.[0];
                            if (file) uploadItemImage(file, input, statusNode, triggerButton);
                        });
                        control.insertBefore(modeWrap, input);
                        control.appendChild(uploadWrap);
                        syncImageMode();
                    }
                    itemFormFields.appendChild(control);
                });
                itemModal.hidden = false;
            };
            const blockHasDynamicItems = (block) => Array.isArray(block?.dynamic_items) && block.dynamic_items.length > 0;
            const renderItemsEditor = (block, contentOverride = null) => {
                if (!itemsEditor || !itemList) return;
                const blockType = block.block_type || '';
                activeItemKey = editorItemKey(blockType);
                const content = normalizeContentObject(contentOverride || block.data?.content || {});
                let items = Array.isArray(content[activeItemKey]) ? content[activeItemKey] : [];
                const canEditList = ['hero_slider', 'featured_categories', 'content_mosaic', 'content_showcase', 'latest_posts', 'featured_services', 'project_gallery', 'faq_showcase', 'process_steps', 'team_members', 'testimonials', 'partner_logos'].includes(blockType);

                itemList.innerHTML = '';
                if (!canEditList) {
                    itemsEditor.hidden = true;
                    return;
                }

                if (!items.length && blockHasDynamicItems(block)) {
                    items = block.dynamic_items;
                }

                itemsEditor.hidden = false;
                const customMode = isCustomSource();
                syncSourceModeUi();
                itemHelp.textContent = customMode
                    ? 'Ná»™i dung tÃ¹y chá»‰nh: cÃ³ thá»ƒ thÃªm, sá»­a hoáº·c xÃ³a tá»«ng má»¥c ngay táº¡i Ä‘Ã¢y.'
                    : `Danh sÃ¡ch Ä‘ang láº¥y tá»± Ä‘á»™ng tá»« ${sourceLabels[currentSourceValue()] || 'CMS'}. Muá»‘n sá»­a tá»«ng item, má»Ÿ trang quáº£n lÃ½ tÆ°Æ¡ng á»©ng.`;

                items.forEach((item, index) => itemList.appendChild(renderEditorItem(item, index, blockType, customMode)));
                if (!items.length && customMode) itemList.appendChild(renderEditorItem({}, 0, blockType, true));
                addItemButton.dataset.xdBlockType = blockType;
            };
            const alwaysCollectItemBlocks = ['hero_slider', 'partner_logos'];
            const shouldCollectEditorItems = () => {
                const blockType = activeBlock?.block_type || addItemButton?.dataset.xdBlockType || '';
                return alwaysCollectItemBlocks.includes(blockType) || isCustomSource();
            };
            const previewSourceItems = async () => {
                if (!activeBlock || !sourcePreviewUrlTemplate || !sourceEditor || sourceEditor.hidden) return;
                if (isCustomSource()) {
                    renderItemsEditor(activeBlock, parseJson(field('content').value, {}));
                    return;
                }

                const settingsPayload = collectSourceSettings(parseJson(field('settings').value, {}));
                field('settings').value = pretty(settingsPayload);
                if (itemHelp) itemHelp.textContent = 'Äang táº£i láº¡i danh sÃ¡ch theo nguá»“n ná»™i dung...';

                sourcePreviewController?.abort();
                sourcePreviewController = new AbortController();

                const params = new URLSearchParams({locale: activeEditorLocale});
                Object.entries(settingsPayload).forEach(([key, value]) => {
                    if (value !== null && value !== '' && value !== undefined) {
                        params.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
                    }
                });

                try {
                    const response = await fetch(`${sourcePreviewUrlTemplate.replace('__BLOCK_ID__', activeBlock.id)}?${params.toString()}`, {
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                        signal: sourcePreviewController.signal,
                    });
                    if (!response.ok) throw new Error('KhÃ´ng táº£i Ä‘Æ°á»£c danh sÃ¡ch ná»™i dung.');

                    const payload = await response.json();
                    const items = payload.data?.items || [];
                    activeBlock = {...activeBlock, settings: settingsPayload, dynamic_items: items};
                    blocks[activeBlock.id] = activeBlock;

                    const previewContent = {};
                    previewContent[activeItemKey || editorItemKey(activeBlock.block_type)] = items;
                    renderItemsEditor(activeBlock, previewContent);
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    if (itemHelp) itemHelp.textContent = error.message || 'KhÃ´ng táº£i Ä‘Æ°á»£c danh sÃ¡ch ná»™i dung.';
                }
            };
            const scheduleSourcePreview = () => {
                window.clearTimeout(sourcePreviewTimer);
                sourcePreviewTimer = window.setTimeout(() => {
                    previewSourceItems();
                }, 250);
            };
            const collectEditorItems = () => {
                if (!shouldCollectEditorItems()) return null;
                if (!itemsEditor || itemsEditor.hidden || !activeItemKey) return null;
                return Array.from(itemList.querySelectorAll('[data-xd-item-row]'))
                    .map(parseItemData)
                    .filter((item) => Object.keys(item).length > 0);
            };
            const syncEditorItemsIntoContentField = () => {
                const editorItems = collectEditorItems();
                if (!editorItems) return;

                const content = normalizeContentObject(parseJson(field('content').value, {}));
                content[activeItemKey] = editorItems;
                field('content').value = pretty(content);

                if (localeDrafts[activeEditorLocale]) {
                    localeDrafts[activeEditorLocale].content = content;
                }
            };
            const collectCurrentLocaleDraft = () => {
                let content = normalizeContentObject(parseJson(field('content').value, {}));
                const editorItems = collectEditorItems();
                if (editorItems) content[activeItemKey] = editorItems;
                content = mergeContactContentFields(content);

                return {
                    locale: activeEditorLocale,
                    title: field('title').value,
                    subtitle: field('subtitle').value,
                    description: field('description').value,
                    button_label: field('button_label').value,
                    content,
                };
            };
            const loadLocaleDraft = (locale) => {
                if (!activeBlock) return;
                const draft = localeDrafts[locale] || {locale, content: {}};
                activeEditorLocale = locale;
                field('locale').value = locale;
                field('title').value = draft.title || '';
                field('subtitle').value = draft.subtitle || '';
                field('description').value = draft.description || '';
                field('button_label').value = draft.button_label || '';
                field('content').value = pretty(normalizeContentObject(draft.content || {}));
                loadContactContentFields(normalizeContentObject(draft.content || {}));
                renderItemsEditor(activeBlock, normalizeContentObject(draft.content || {}));
                if (!isCustomSource()) scheduleSourcePreview();
                localeTabs.forEach((button) => button.classList.toggle('is-active', button.dataset.xdLocaleTab === locale));
            };
            const switchEditorLocale = (locale) => {
                if (!activeBlock || locale === activeEditorLocale) return;
                localeDrafts[activeEditorLocale] = collectCurrentLocaleDraft();
                loadLocaleDraft(locale);
            };
            addItemButton?.addEventListener('click', () => {
                openItemModal(null, {});
            });
            itemForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                if (!itemList || !activeBlock) return;
                const item = {};
                itemForm.querySelectorAll('[data-xd-item-modal-field]').forEach((input) => {
                    const value = input.value.trim();
                    if (value !== '') item[input.dataset.xdItemModalField] = value;
                });
                const indexValue = itemIndexInput?.value ?? '';
                const rows = Array.from(itemList.querySelectorAll('[data-xd-item-row]'));
                const blockType = activeBlock.block_type || addItemButton?.dataset.xdBlockType || '';
                if (indexValue === '') {
                    itemList.appendChild(renderEditorItem(item, rows.length, blockType, true));
                } else {
                    const index = Number(indexValue);
                    rows[index]?.replaceWith(renderEditorItem(item, index, blockType, true));
                }
                syncItemNumbers();
                syncEditorItemsIntoContentField();
                closeItemModal();
            });
            document.querySelectorAll('[data-xd-item-close]').forEach((button) => button.addEventListener('click', closeItemModal));
            localeTabs.forEach((button) => button.addEventListener('click', () => switchEditorLocale(button.dataset.xdLocaleTab || activeEditorLocale)));

            document.querySelectorAll('[data-xd-edit-block]').forEach((button) => {
                button.addEventListener('click', () => {
                    const block = blocks[button.dataset.xdEditBlock];
                    if (!block || !editor) return;
                    activeBlock = block;
                    localeDrafts = {};
                    const availableLocales = editorLocales.length ? editorLocales.map((locale) => locale.code) : [block.data?.locale || activeEditorLocale];
                    availableLocales.forEach((locale) => {
                        const localeContent = block.data_by_locale?.[locale]?.content || block.data?.content || {};
                        localeDrafts[locale] = {
                            locale,
                            ...(block.data_by_locale?.[locale] || block.data || {}),
                            content: normalizeContentObject(localeContent),
                        };
                    });
                    field('block_id').value = block.id;
                    field('anchor_id').value = block.anchor_id || '';
                    field('is_visible').checked = Boolean(block.is_visible);
                    field('settings').value = pretty(block.settings || {});
                    if (field('cta_url')) field('cta_url').value = block.settings?.cta_url || '';
                    field('media').value = pretty(block.media || {});
                    syncContactEditorVisibility(block);
                    renderSourceEditor(block);
                    loadLocaleDraft(block.data?.locale || activeEditorLocale);
                    editor.hidden = false;
                });
            });

            document.querySelectorAll('[data-xd-editor-close]').forEach((button) => button.addEventListener('click', () => { editor.hidden = true; closeItemModal(); }));
            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                const blockId = field('block_id').value;
                try {
                    localeDrafts[activeEditorLocale] = collectCurrentLocaleDraft();
                    const localePayloads = Object.values(localeDrafts);
                    const settingsPayload = collectSourceSettings(parseJson(field('settings').value, {}));
                    const ctaUrl = field('cta_url')?.value.trim() || '';
                    if (ctaUrl !== '') settingsPayload.cta_url = ctaUrl;
                    else delete settingsPayload.cta_url;
                    const mediaPayload = parseJson(field('media').value, {});

                    for (const draft of localePayloads) {
                        const payload = {
                            locale: draft.locale,
                            anchor_id: field('anchor_id').value,
                            is_visible: field('is_visible').checked,
                            settings: settingsPayload,
                            media: mediaPayload,
                            data: {
                                title: draft.title || '',
                                subtitle: draft.subtitle || '',
                                description: draft.description || '',
                                button_label: draft.button_label || '',
                                content: draft.content || {},
                            },
                        };
                        const response = await fetch(updateUrlTemplate.replace('__BLOCK_ID__', blockId), {
                            method: 'PUT',
                            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                            body: JSON.stringify(payload),
                        });
                        if (!response.ok) throw new Error('KhÃ´ng lÆ°u Ä‘Æ°á»£c khá»‘i landing.');
                    }
                    window.location.reload();
                } catch (error) {
                    alert(error.message);
                }
            });
        })();
    </script>
    <script>
        (() => {
            document.querySelectorAll('[data-xd2-hero]').forEach((hero) => {
                const slides = [...hero.querySelectorAll('.xd2-hero__slide')];
                const dots = [...hero.querySelectorAll('[data-xd2-hero-dot]')];
                if (slides.length < 2) return;
                let current = 0;
                const show = (index) => {
                    current = (index + slides.length) % slides.length;
                    slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
                    dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
                };
                hero.querySelector('[data-xd2-hero-prev]')?.addEventListener('click', () => show(current - 1));
                hero.querySelector('[data-xd2-hero-next]')?.addEventListener('click', () => show(current + 1));
                dots.forEach((dot, index) => dot.addEventListener('click', () => show(index)));
                window.setInterval(() => show(current + 1), Math.max(2500, Number(hero.dataset.autoplay || 6000)));
            });
        })();
    </script>

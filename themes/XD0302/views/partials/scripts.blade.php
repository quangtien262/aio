    <script>
        (() => {
            const slides = Array.from(document.querySelectorAll('.xd-slide'));
            const dots = Array.from(document.querySelectorAll('.xd-dot'));
            const copy = @json($heroSlides ?? []);
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
            const restart = () => { window.clearInterval(timer); timer = window.setInterval(() => show(index + 1), Number(@json(data_get($hero ?? [], 'settings.autoplay_ms', 5200)))); };
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

            document.querySelectorAll('[data-xd2-tabs]').forEach((tabList) => {
                const tabButtons = Array.from(tabList.querySelectorAll('[data-xd2-tab]'));
                const tabRoot = tabList.closest('[data-block-type="about_experience"]');
                const tabPanels = Array.from(tabRoot?.querySelectorAll('[data-xd2-tab-panel]') || []);
                const activateTab = (nextIndex, focus = false) => {
                    const normalizedIndex = (nextIndex + tabButtons.length) % tabButtons.length;
                    tabButtons.forEach((button, index) => {
                        const active = index === normalizedIndex;
                        button.classList.toggle('is-active', active);
                        button.setAttribute('aria-selected', active ? 'true' : 'false');
                        button.tabIndex = active ? 0 : -1;
                        if (active && focus) button.focus();
                    });
                    tabPanels.forEach((panel, index) => { panel.hidden = index !== normalizedIndex; });
                };

                tabButtons.forEach((button, index) => {
                    button.addEventListener('click', () => activateTab(index));
                    button.addEventListener('keydown', (event) => {
                        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                        event.preventDefault();
                        if (event.key === 'Home') activateTab(0, true);
                        else if (event.key === 'End') activateTab(tabButtons.length - 1, true);
                        else activateTab(index + (event.key === 'ArrowRight' ? 1 : -1), true);
                    });
                });
            });

            const canEdit = @json($canEditLanding);
            if (!canEdit) return;
            const blocks = @json($blockPayload);
            const editorLocales = @json($editorLocales);
            const editorOptions = @json($landingEditorOptions ?? []);
            const updateUrlTemplate = @json($blockUpdateUrlTemplate);
            const sourcePreviewUrlTemplate = @json($blockSourcePreviewUrlTemplate);
            const mediaUploadUrl = @json(route('admin.api.cms.media.store'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const editor = document.querySelector('[data-xd-editor]');
            const form = document.querySelector('[data-xd-editor-form]');
            const field = (name) => form?.querySelector(`[data-xd-field="${name}"]`);
            const blockCtaFields = Array.from(form?.querySelectorAll('[data-xd-block-cta]') || []);
            const heroUsesBlockCta = window.xdHeroUsesBlockCta === true;
            const aboutUsesValueButtons = window.xdAboutUsesValueButtons === true;
            const syncBlockCtaVisibility = (blockType) => {
                blockCtaFields.forEach((element) => {
                    const shouldHide = blockType === 'faq_showcase' || (blockType === 'hero_slider' && !heroUsesBlockCta);
                    element.hidden = shouldHide;
                    element.style.display = shouldHide ? 'none' : '';
                });
            };
            const pretty = (value) => JSON.stringify(value || {}, null, 2);
            const parseJson = (value, fallback) => {
                try { return value.trim() ? JSON.parse(value) : fallback; } catch (error) { throw new Error('JSON không hợp lệ: ' + error.message); }
            };
            const itemsEditor = document.querySelector('[data-xd-items-editor]');
            const itemList = document.querySelector('[data-xd-item-list]');
            const itemsTitle = document.querySelector('[data-xd-items-title]');
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
            const faqEditor = document.querySelector('[data-xd-faq-editor]');
            const faqContentFields = Array.from(document.querySelectorAll('[data-xd-faq-content-field]'));
            const faqMediaFields = Array.from(document.querySelectorAll('[data-xd-faq-media-field]'));
            const mediaEditor = document.querySelector('[data-xd-media-editor]');
            const mediaFields = Array.from(document.querySelectorAll('[data-xd-media-field]'));
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
                custom: 'Nội dung tùy chỉnh',
                catalog_categories: 'Danh mục sản phẩm',
                cms_service_categories: 'Danh mục dịch vụ',
                cms_categories: 'Danh mục tin tức',
                cms_services: 'Dịch vụ',
                cms_products: 'Sản phẩm',
                catalog_products: 'Sản phẩm',
                featured_products: 'Sản phẩm nổi bật',
                cms_posts: 'Tin tức',
                latest_posts: 'Tin mới nhất',
                cms_projects: 'Dự án',
                cms_menus: 'Menu website',
                cms_team_members: 'Đội ngũ',
                cms_testimonials: 'Đánh giá',
            };
            const sourceManageUrls = {
                cms_services: @json(route('admin.index', ['any' => 'cms/services'])),
                catalog_categories: @json(route('admin.index', ['any' => 'cms/products'])),
                cms_service_categories: @json(route('admin.index', ['any' => 'cms/services'])),
                cms_categories: @json(route('admin.index', ['any' => 'cms/posts'])),
                cms_posts: @json(route('admin.index', ['any' => 'cms/posts'])),
                latest_posts: @json(route('admin.index', ['any' => 'cms/posts'])),
                cms_products: @json(route('admin.index', ['any' => 'cms/products'])),
                catalog_products: @json(route('admin.index', ['any' => 'cms/products'])),
                featured_products: @json(route('admin.index', ['any' => 'cms/products'])),
                cms_projects: @json(route('admin.index', ['any' => 'cms/projects'])),
                cms_menus: @json(route('admin.index', ['any' => 'cms/menus'])),
                cms_team_members: @json(route('admin.index', ['any' => 'cms/team'])),
                cms_testimonials: @json(route('admin.index', ['any' => 'cms/testimonials'])),
            };
            const defaultBlockLimits = {
                hero_slider: 3,
                featured_categories: 6,
                content_mosaic: 5,
                content_showcase: 5,
                featured_services: 3,
                featured_service_list: 3,
                completed_projects_list: 5,
                testimonial_showcase: 3,
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

                if (statusNode) statusNode.textContent = 'Đang upload...';
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
                        throw new Error(errorPayload.message || 'Upload ảnh không thành công.');
                    }

                    const result = await response.json();
                    targetInput.value = result?.data?.file_url || '';
                    targetInput.dispatchEvent(new Event('input', {bubbles: true}));
                    if (statusNode) statusNode.textContent = 'Đã upload ảnh.';
                } catch (error) {
                    if (statusNode) statusNode.textContent = error.message || 'Không upload được ảnh.';
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
            const syncMediaEditorVisibility = (block) => {
                if (!mediaEditor) return;
                const blockType = block?.block_type || '';
                mediaEditor.hidden = !['about_experience', 'featured_categories', 'landing_contact'].includes(blockType);
                if (mediaEditor.hidden) return;

                const isContact = blockType === 'landing_contact';
                const isFeaturedCategories = blockType === 'featured_categories';
                const title = mediaEditor.querySelector('[data-xd-media-title]');
                const note = mediaEditor.querySelector('[data-xd-media-note]');
                const label = mediaEditor.querySelector('[data-xd-media-label]');
                const imageInput = mediaEditor.querySelector('[data-xd-media-field="image"]');
                const uploadTrigger = mediaEditor.querySelector('[data-xd-media-upload-trigger]');
                const imageLabel = isContact
                    ? 'Ảnh nền liên hệ'
                    : (isFeaturedCategories ? 'Ảnh danh mục nổi bật' : 'Ảnh giới thiệu');
                if (title) title.textContent = isContact
                    ? 'Ảnh nền khu vực liên hệ'
                    : (isFeaturedCategories ? 'Ảnh khối danh mục nổi bật' : 'Ảnh tổng hợp khối giới thiệu');
                if (note) note.textContent = isContact
                    ? 'Ảnh hiển thị bên trái form liên hệ. Có thể nhập liên kết hoặc upload ảnh mới.'
                    : (isFeaturedCategories
                        ? 'Ảnh lớn hiển thị bên trái danh sách danh mục. Có thể nhập liên kết hoặc upload ảnh mới.'
                        : 'Cột trái dùng duy nhất một ảnh hoàn chỉnh, bao gồm cả hình ảnh và nội dung kinh nghiệm nếu cần.');
                if (label) label.textContent = imageLabel;
                if (imageInput) imageInput.setAttribute('aria-label', imageLabel);
                if (uploadTrigger) uploadTrigger.textContent = `Upload ${imageLabel.toLowerCase()}`;
            };
            const loadMediaFields = (block) => {
                const media = normalizeContentObject(block?.media);
                mediaFields.forEach((input) => {
                    const key = input.dataset.xdMediaField;
                    input.value = media[key] ?? '';
                });
            };
            const mergeMediaFields = (media = {}) => {
                if (!mediaEditor || mediaEditor.hidden) return media;

                const next = {...normalizeContentObject(media)};
                delete next.image_secondary;
                mediaFields.forEach((input) => {
                    const key = input.dataset.xdMediaField;
                    const value = input.value.trim();
                    if (value !== '') next[key] = value;
                    else delete next[key];
                });

                return next;
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
            const faqContentDefaults = {
                aside_title: 'Giải pháp được triển khai thực tế',
                aside_description: 'Đội ngũ tư vấn cùng doanh nghiệp xác định quy mô, mục tiêu tiết kiệm và lộ trình đầu tư phù hợp.',
                aside_button_label: 'Nhận tư vấn',
                aside_button_url: '#lien-he',
            };
            const faqMediaDefaults = {
                aside_image: 'https://images.unsplash.com/photo-1509391366360-2e959784a276?auto=format&fit=crop&w=800&q=85',
            };
            const syncFaqEditorVisibility = (block) => {
                if (!faqEditor) return;
                faqEditor.hidden = (block?.block_type || '') !== 'faq_showcase';
            };
            const loadFaqFields = (block, content = {}) => {
                const media = normalizeContentObject(block?.media);
                faqContentFields.forEach((input) => {
                    const key = input.dataset.xdFaqContentField;
                    input.value = content?.[key] ?? faqContentDefaults[key] ?? '';
                });
                faqMediaFields.forEach((input) => {
                    const key = input.dataset.xdFaqMediaField;
                    input.value = media[key] ?? faqMediaDefaults[key] ?? '';
                });
            };
            const mergeFaqContentFields = (content = {}) => {
                if (!faqEditor || faqEditor.hidden) return content;

                const next = {...normalizeContentObject(content)};
                faqContentFields.forEach((input) => {
                    next[input.dataset.xdFaqContentField] = input.value.trim();
                });
                return next;
            };
            const mergeFaqMediaFields = (media = {}) => {
                if (!faqEditor || faqEditor.hidden) return media;

                const next = {...normalizeContentObject(media)};
                faqMediaFields.forEach((input) => {
                    const key = input.dataset.xdFaqMediaField;
                    const value = input.value.trim();
                    if (value !== '') next[key] = value;
                    else delete next[key];
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
                const menuLocationWrap = sourceControlWrap('menu_location');
                const menuLocationInput = settingField('menu_location');
                const isMenuSource = currentSourceValue() === 'cms_menus';
                if (menuLocationWrap) menuLocationWrap.hidden = customMode || !isMenuSource;
                if (menuLocationInput) menuLocationInput.disabled = customMode || !isMenuSource;
                if (addItemButton) addItemButton.hidden = !customMode;
                if (manageSourceLink) {
                    const source = currentSourceValue();
                    const url = sourceManageUrls[source] || '';
                    manageSourceLink.hidden = customMode || url === '';
                    manageSourceLink.href = url || '#';
                    manageSourceLink.textContent = `Quản lý ${sourceLabels[source] || 'nội dung'}`;
                }
            };
            const normalizeSourceOptions = (options = []) => options
                .map((option) => typeof option === 'string' ? {value: option, label: sourceLabels[option] || option} : {
                    value: option.value || option.key || '',
                    label: sourceLabels[option.value || option.key] || option.label || option.value || option.key || '',
                })
                .filter((option) => option.value !== '');
            const renderCategorySelect = (source, selectedValue = '') => {
                const categorySelect = settingField('category_id');
                if (!categorySelect) return;

                const options = categoryOptionsBySource[source] || [];
                categorySelect.innerHTML = [
                    '<option value="">Tất cả danh mục</option>',
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
                    const storedItems = block?.data?.content?.items;
                    const inferredSource = block?.block_type === 'testimonial_showcase' && Array.isArray(storedItems) && storedItems.length
                        ? 'custom'
                        : '';
                    sourceSelect.value = settings.source || inferredSource || options[0]?.value || '';
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

                const menuLocationInput = settingField('menu_location');
                if (menuLocationInput) {
                    menuLocationInput.value = settings.menu_location || '';
                    menuLocationInput.onchange = () => scheduleSourcePreview();
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
                const menuLocation = settingField('menu_location')?.value?.trim() || '';

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
                if (source === 'cms_menus' && menuLocation !== '') next.menu_location = menuLocation;
                else delete next.menu_location;

                return next;
            };

            const editorItemKey = (blockType) => {
                if (blockType === 'hero_slider') return 'slides';
                if (blockType === 'about_experience') return aboutUsesValueButtons ? 'items' : 'tabs';
                return 'items';
            };
            const editorItemFields = (blockType) => {
                if (blockType === 'hero_slider') {
                    return [
                        ['kicker', 'Nhãn nhỏ'],
                        ['title', 'Tiêu đề'],
                        ['summary', 'Mô tả', 'textarea'],
                        ['image', 'Ảnh'],
                        ['link_url', 'Link'],
                        ['button_label', 'Nút bấm'],
                    ];
                }

                if (blockType === 'featured_categories') {
                    return [
                        ['title', 'Tiêu đề'],
                        ['summary', 'Mô tả / nhãn phụ', 'textarea'],
                        ['image', 'Ảnh'],
                        ['icon', 'Biểu tượng'],
                        ['url', 'Link'],
                        ['count_label', 'Nhãn số lượng'],
                    ];
                }

                if (blockType === 'content_mosaic') {
                    return [
                        ['title', 'TiÃªu Ä‘á»'],
                        ['summary', 'MÃ´ táº£', 'textarea'],
                        ['image', 'áº¢nh'],
                        ['url', 'Link'],
                    ];
                }

                if (blockType === 'faq_showcase') {
                    return [
                        ['question', 'Câu hỏi'],
                        ['answer', 'Câu trả lời', 'textarea'],
                    ];
                }

                if (blockType === 'about_experience') {
                    if (aboutUsesValueButtons) {
                        return [
                            ['title', 'Tên nút'],
                            ['url', 'Link khi click'],
                        ];
                    }

                    return [
                        ['label', 'Tên tab'],
                        ['description', 'Nội dung tab', 'textarea'],
                    ];
                }

                if (blockType === 'content_showcase') {
                    return [
                        ['title', 'Tiêu đề'],
                        ['summary', 'Mô tả', 'textarea'],
                        ['image', 'Ảnh'],
                        ['icon', 'Biểu tượng'],
                        ['url', 'Link'],
                    ];
                }

                if (['latest_posts', 'featured_service_list', 'completed_projects_list'].includes(blockType)) {
                    return [
                        ['title', 'Tiêu đề'],
                        ['summary', 'Mô tả', 'textarea'],
                        ['image', 'Ảnh'],
                        ['url', 'Link'],
                    ];
                }

                if (['testimonials', 'testimonial_showcase'].includes(blockType)) {
                    return [
                        ['name', 'Tên khách hàng'],
                        ['company', 'Công ty / vai trò'],
                        ['quote', 'Nhận xét', 'textarea'],
                        ['image', 'Ảnh đại diện'],
                        ['url', 'Link'],
                    ];
                }

                if (blockType === 'team_members') {
                    return [
                        ['name', 'Tên nhân sự'],
                        ['role', 'Chức vụ'],
                        ['image', 'Ảnh'],
                        ['url', 'Link'],
                    ];
                }

                if (blockType === 'partner_logos') {
                    return [
                        ['name', 'Tên đối tác'],
                        ['image', 'Logo / ảnh'],
                        ['url', 'Link'],
                        ['alt', 'Alt ảnh'],
                    ];
                }

                return [
                    ['title', 'Tiêu đề'],
                    ['summary', 'Mô tả', 'textarea'],
                    ['image', 'Ảnh'],
                    ['url', 'Link'],
                    ['button_label', 'Nút bấm'],
                ];
            };
            const renderEditorItem = (item = {}, index = 0, blockType = '', canEditItem = true) => {
                const row = document.createElement('article');
                row.className = 'xd-editor-item';
                row.dataset.xdItemRow = '1';
                row.dataset.xdItem = JSON.stringify(item || {});
                const title = item.title || item.question || item.label || item.name || item.kicker || `Mục ${index + 1}`;
                const summary = item.summary || item.answer || item.description || item.quote || item.role || item.company || item.url || item.link_url || 'Chưa có mô tả.';
                const image = item.image || item.image_url || item.thumbnail || item.logo || item.avatar || '';
                const imageAlt = item.alt || title;
                const thumb = image
                    ? `<span class="xd-editor-item-thumb"><img src="${escapeHtml(image)}" alt="${escapeHtml(imageAlt)}"></span>`
                    : '<span class="xd-editor-item-thumb is-empty">No img</span>';
                row.innerHTML = `
                    <div class="xd-editor-item-main">
                        ${thumb}
                        <div class="xd-editor-item-summary">
                            <small>Mục ${index + 1}</small>
                            <strong>${escapeHtml(title)}</strong>
                            <span>${escapeHtml(summary)}</span>
                        </div>
                    </div>
                    ${canEditItem ? `<div class="xd-editor-item-actions">
                        <button type="button" class="xd-editor-edit" data-xd-edit-item>Sửa</button>
                        <button type="button" class="xd-editor-remove" data-xd-remove-item>Xóa</button>
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
                    if (badge) badge.textContent = `Mục ${index + 1}`;
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
                itemModalTitle.textContent = index === null ? 'Thêm mục' : `Sửa mục ${index + 1}`;
                itemIndexInput.value = index === null ? '' : String(index);
                itemFormFields.innerHTML = '';
                editorItemFields(blockType).forEach(([key, label, type]) => {
                    const control = document.createElement('label');
                    control.className = type === 'textarea' ? 'is-wide' : '';
                    if (key === 'image') control.className = `${control.className} xd-item-image-field`.trim();
                    if (key === 'icon') control.className = `${control.className} xd-icon-picker-field`.trim();
                    control.innerHTML = key === 'icon'
                        ? `<span>${escapeHtml(label)}</span><input type="hidden" data-xd-item-modal-field="${escapeHtml(key)}"><button type="button" class="xd-icon-picker-trigger"><i class="fa-solid fa-icons" aria-hidden="true"></i><span>Chọn icon</span><small>Font Awesome Free</small></button>`
                        : type === 'textarea'
                        ? `<span>${escapeHtml(label)}</span><textarea data-xd-item-modal-field="${escapeHtml(key)}"></textarea>`
                        : `<span>${escapeHtml(label)}</span><input data-xd-item-modal-field="${escapeHtml(key)}">`;
                    const input = control.querySelector('[data-xd-item-modal-field]');
                    input.value = item?.[key] ?? '';
                    if (key === 'icon') {
                        const trigger = control.querySelector('.xd-icon-picker-trigger');
                        const syncIconPreview = () => {
                            const value = input.value.trim();
                            trigger.querySelector('i').className = value || 'fa-solid fa-icons';
                            trigger.querySelector('span').textContent = value ? 'Đổi icon' : 'Chọn icon';
                            trigger.classList.toggle('has-value', value !== '');
                        };
                        trigger?.addEventListener('click', () => window.AioFontAwesomeIconPicker?.open({
                            value: input.value,
                            trigger,
                            onSelect: (value) => {
                                input.value = value;
                                syncIconPreview();
                            },
                        }));
                        syncIconPreview();
                    }
                    if (key === 'image') {
                        const modeWrap = document.createElement('div');
                        modeWrap.className = 'xd-image-mode';
                        modeWrap.innerHTML = `
                            <label><input type="radio" name="xd_item_image_mode" value="url" checked> Nhập liên kết hình ảnh</label>
                            <label><input type="radio" name="xd_item_image_mode" value="upload"> Upload ảnh</label>
                        `;
                        const uploadWrap = document.createElement('div');
                        uploadWrap.className = 'xd-item-upload';
                        uploadWrap.hidden = true;
                        uploadWrap.innerHTML = '<input type="file" accept="image/*" data-xd-item-upload hidden><button type="button" data-xd-item-upload-trigger>Upload ảnh</button><small data-xd-item-upload-status></small>';
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
                if (blockType === 'about_experience' && !aboutUsesValueButtons) {
                    items = items.map((item) => typeof item === 'string'
                        ? {label: item, description: field('description')?.value || block.data?.description || ''}
                        : normalizeContentObject(item));
                }
                if (blockType === 'testimonial_showcase') {
                    items = items.map((item) => ({
                        ...normalizeContentObject(item),
                        name: item?.name ?? item?.title ?? '',
                        quote: item?.quote ?? item?.summary ?? item?.description ?? '',
                        company: item?.company ?? item?.role ?? '',
                    }));
                }
                const canEditList = ['hero_slider', 'about_experience', 'featured_categories', 'content_mosaic', 'content_showcase', 'latest_posts', 'featured_services', 'featured_service_list', 'completed_projects_list', 'project_gallery', 'faq_showcase', 'team_members', 'testimonials', 'testimonial_showcase', 'partner_logos'].includes(blockType);

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
                if (itemsTitle) itemsTitle.textContent = blockType === 'about_experience'
                    ? (aboutUsesValueButtons ? 'Danh sách nút giá trị' : 'Danh sách tab giới thiệu')
                    : 'Danh sách nội dung';
                itemHelp.textContent = blockType === 'about_experience' && aboutUsesValueButtons
                    ? 'Mỗi mục là một nút gồm tên hiển thị và liên kết khi click.'
                    : blockType === 'about_experience'
                    ? 'Mỗi mục là một tab. Có thể thêm, sửa hoặc xóa tab cho từng ngôn ngữ.'
                    : customMode
                    ? 'Nội dung tùy chỉnh: có thể thêm, sửa hoặc xóa từng mục ngay tại đây.'
                    : `Danh sách đang lấy tự động từ ${sourceLabels[currentSourceValue()] || 'CMS'}. Muốn sửa từng item, mở trang quản lý tương ứng.`;

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
                if (itemHelp) itemHelp.textContent = 'Đang tải lại danh sách theo nguồn nội dung...';

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
                    if (!response.ok) throw new Error('Không tải được danh sách nội dung.');

                    const payload = await response.json();
                    const items = payload.data?.items || [];
                    activeBlock = {...activeBlock, settings: settingsPayload, dynamic_items: items};
                    blocks[activeBlock.id] = activeBlock;

                    const previewContent = {};
                    previewContent[activeItemKey || editorItemKey(activeBlock.block_type)] = items;
                    renderItemsEditor(activeBlock, previewContent);
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    if (itemHelp) itemHelp.textContent = error.message || 'Không tải được danh sách nội dung.';
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
                content = mergeFaqContentFields(content);

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
                field('button_label').value = activeBlock?.block_type === 'hero_slider' && !heroUsesBlockCta ? '' : (draft.button_label || '');
                field('content').value = pretty(normalizeContentObject(draft.content || {}));
                loadContactContentFields(normalizeContentObject(draft.content || {}));
                loadFaqFields(activeBlock, normalizeContentObject(draft.content || {}));
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
            document.querySelectorAll('[data-xd-media-upload]').forEach((fileInput) => {
                const row = fileInput.closest('[data-xd-media-row]');
                const targetInput = mediaFields.find((input) => input.dataset.xdMediaField === fileInput.dataset.xdMediaUpload);
                const triggerButton = row?.querySelector('[data-xd-media-upload-trigger]');
                const statusNode = row?.querySelector('[data-xd-media-upload-status]');
                triggerButton?.addEventListener('click', () => fileInput.click());
                fileInput.addEventListener('change', () => {
                    const file = fileInput.files?.[0];
                    if (file) uploadItemImage(file, targetInput, statusNode, triggerButton);
                });
            });
            document.querySelectorAll('[data-xd-faq-media-upload]').forEach((fileInput) => {
                const row = fileInput.closest('[data-xd-faq-media-row]');
                const targetInput = faqMediaFields.find((input) => input.dataset.xdFaqMediaField === fileInput.dataset.xdFaqMediaUpload);
                const triggerButton = row?.querySelector('[data-xd-faq-media-upload-trigger]');
                const statusNode = row?.querySelector('[data-xd-faq-media-upload-status]');
                triggerButton?.addEventListener('click', () => fileInput.click());
                fileInput.addEventListener('change', () => {
                    const file = fileInput.files?.[0];
                    if (file) uploadItemImage(file, targetInput, statusNode, triggerButton);
                });
            });

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
                    syncBlockCtaVisibility(block.block_type);
                    if (field('cta_url')) field('cta_url').value = block.block_type === 'hero_slider' && !heroUsesBlockCta ? '' : (block.settings?.cta_url || '');
                    field('media').value = pretty(block.media || {});
                    syncMediaEditorVisibility(block);
                    loadMediaFields(block);
                    syncContactEditorVisibility(block);
                    syncFaqEditorVisibility(block);
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
                    if (activeBlock?.block_type === 'hero_slider' && !heroUsesBlockCta) {
                        delete settingsPayload.cta_url;
                    } else {
                        const ctaUrl = field('cta_url')?.value.trim() || '';
                        if (ctaUrl !== '') settingsPayload.cta_url = ctaUrl;
                        else delete settingsPayload.cta_url;
                    }
                    let mediaPayload = mergeMediaFields(parseJson(field('media').value, {}));
                    mediaPayload = mergeFaqMediaFields(mediaPayload);
                    field('media').value = pretty(mediaPayload);

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
                        if (!response.ok) throw new Error('Không lưu được khối landing.');
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

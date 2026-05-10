import { useEffect, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import List from 'antd/es/list';
import Modal from 'antd/es/modal';
import Pagination from 'antd/es/pagination';
import Select from 'antd/es/select';
import Segmented from 'antd/es/segmented';
import Space from 'antd/es/space';
import Spin from 'antd/es/spin';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Search, TextArea } = Input;
const { Paragraph, Text } = Typography;
const DEFAULT_PAGE_SIZE = 25;
const ENTITY_LABELS = {
    all: 'Tất cả nhóm dữ liệu',
    'site-profile': 'Site profile',
    theme: 'Theme section',
    menu: 'Menu',
    banner: 'Banner',
    catalog: 'Catalog',
    'catalog-category': 'Catalog category',
    'catalog-product': 'Catalog product',
    cms: 'CMS',
    'cms-page': 'CMS page',
    'cms-category': 'CMS category',
    'cms-post': 'CMS post',
};

function applyDrafts(entries, drafts) {
    return entries.map((entry) => ({
        ...entry,
        value: Object.prototype.hasOwnProperty.call(drafts, entry.key)
            ? drafts[entry.key]
            : (entry.override_value ?? entry.effective_value ?? ''),
    }));
}

function entryEntity(key) {
    if (key.startsWith('site_profile.') || key.startsWith('branding.')) {
        return 'site-profile';
    }

    if (key.startsWith('cms_menu.')) {
        return 'menu';
    }

    if (key.startsWith('site_banner.')) {
        return 'banner';
    }

    if (key.startsWith('theme_block.') || key.startsWith('theme_metric.') || key.startsWith('theme_section.')) {
        return 'theme';
    }

    if (key.startsWith('catalog_category.')) {
        return 'catalog-category';
    }

    if (key.startsWith('catalog_product.')) {
        return 'catalog-product';
    }

    if (key.startsWith('cms_page.')) {
        return 'cms-page';
    }

    if (key.startsWith('cms_category.')) {
        return 'cms-category';
    }

    if (key.startsWith('cms_post.')) {
        return 'cms-post';
    }

    return 'all';
}

function buildScopeKey(themeKey, locale, group) {
    return `${themeKey ?? 'unknown'}:${locale}:${group}`;
}

function resolveLocaleLabel(locale, localeOptions) {
    return localeOptions.find((option) => option.code === locale)?.name ?? locale.toUpperCase();
}

export default function ThemeTranslationDrawer({
    open,
    theme,
    locale,
    canManageTranslations,
    callAdminApi,
    runAdminAction,
    onClose,
    initialGroup = 'static',
    initialEntity = 'all',
    title,
    description,
}) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [entries, setEntries] = useState([]);
    const [supportedLocales, setSupportedLocales] = useState(locale ? [locale] : ['vi']);
    const [localeOptions, setLocaleOptions] = useState([]);
    const [availableEntities, setAvailableEntities] = useState(['all']);
    const [keywordInput, setKeywordInput] = useState('');
    const [keyword, setKeyword] = useState('');
    const [group, setGroup] = useState('static');
    const [activeLocale, setActiveLocale] = useState(locale);
    const [entity, setEntity] = useState('all');
    const [pagination, setPagination] = useState({
        page: 1,
        perPage: DEFAULT_PAGE_SIZE,
        total: 0,
        lastPage: 1,
    });
    const [draftsByScope, setDraftsByScope] = useState({});
    const [editingKey, setEditingKey] = useState(null);
    const [editingEntriesByLocale, setEditingEntriesByLocale] = useState({});
    const [editingLocalesLoading, setEditingLocalesLoading] = useState(false);
    const [editingLocalesError, setEditingLocalesError] = useState(null);

    const scopeKey = buildScopeKey(theme?.key, activeLocale, group);
    const currentDrafts = draftsByScope[scopeKey] ?? {};
    const visibleEntries = useMemo(() => applyDrafts(entries, currentDrafts), [entries, currentDrafts]);
    const editingEntry = useMemo(
        () => visibleEntries.find((entry) => entry.key === editingKey) ?? null,
        [editingKey, visibleEntries],
    );
    const isEditing = Boolean(editingKey);
    const pendingChangesCount = Object.keys(currentDrafts).length;
    const editingLocaleEntries = useMemo(
        () => supportedLocales
            .map((supportedLocale) => {
                const entry = editingEntriesByLocale[supportedLocale];

                if (!entry) {
                    return null;
                }

                const localeScopeKey = buildScopeKey(theme?.key, supportedLocale, group);
                const localeDrafts = draftsByScope[localeScopeKey] ?? {};

                return {
                    ...entry,
                    locale: supportedLocale,
                    localeLabel: resolveLocaleLabel(supportedLocale, localeOptions),
                    value: Object.prototype.hasOwnProperty.call(localeDrafts, entry.key)
                        ? localeDrafts[entry.key]
                        : (entry.override_value ?? entry.effective_value ?? ''),
                    isDirty: Object.prototype.hasOwnProperty.call(localeDrafts, entry.key),
                };
            })
            .filter(Boolean),
        [draftsByScope, editingEntriesByLocale, group, localeOptions, supportedLocales, theme?.key],
    );
    const editingPendingChangesCount = useMemo(
        () => supportedLocales.reduce((count, supportedLocale) => {
            const localeScopeDrafts = draftsByScope[buildScopeKey(theme?.key, supportedLocale, group)] ?? {};

            return count + (editingKey && Object.prototype.hasOwnProperty.call(localeScopeDrafts, editingKey) ? 1 : 0);
        }, 0),
        [draftsByScope, editingKey, group, supportedLocales, theme?.key],
    );

    const updateDraft = (translationKey, value, targetLocale = activeLocale) => {
        const targetScopeKey = buildScopeKey(theme?.key, targetLocale, group);

        setDraftsByScope((current) => ({
            ...current,
            [targetScopeKey]: {
                ...(current[targetScopeKey] ?? {}),
                [translationKey]: value,
            },
        }));
    };

    const clearDraft = (translationKey, fallbackValue, targetLocale = activeLocale) => {
        const targetScopeKey = buildScopeKey(theme?.key, targetLocale, group);

        setDraftsByScope((current) => {
            const nextScopeDrafts = { ...(current[targetScopeKey] ?? {}) };

            if (fallbackValue === undefined) {
                delete nextScopeDrafts[translationKey];
            } else {
                nextScopeDrafts[translationKey] = fallbackValue;
            }

            return {
                ...current,
                [targetScopeKey]: nextScopeDrafts,
            };
        });
    };

    const replaceScopeDrafts = (nextDrafts) => {
        setDraftsByScope((current) => ({
            ...current,
            [scopeKey]: nextDrafts,
        }));
    };

    const loadTranslations = async (nextPage = pagination.page, nextPerPage = pagination.perPage, nextKeyword = keyword, nextEntity = entity) => {
        if (!open || !theme?.key) {
            return;
        }

        try {
            setLoading(true);
            setError(null);
            const params = new URLSearchParams({
                locale: activeLocale,
                group,
                page: String(nextPage),
                per_page: String(nextPerPage),
            });

            if (group === 'content' && nextEntity !== 'all') {
                params.set('entity', nextEntity);
            }

            if (nextKeyword.trim() !== '') {
                params.set('keyword', nextKeyword.trim());
            }

            const payload = await callAdminApi(`/admin/api/themes/${theme.key}/translations?${params.toString()}`);

            setSupportedLocales(payload.data?.supported_locales ?? (locale ? [locale] : ['vi']));
            setLocaleOptions(payload.data?.locale_options ?? []);
            setAvailableEntities(payload.data?.available_entities ?? ['all']);
            setEntries(payload.data?.entries ?? []);
            setPagination({
                page: payload.data?.pagination?.page ?? nextPage,
                perPage: payload.data?.pagination?.per_page ?? nextPerPage,
                total: payload.data?.pagination?.total ?? 0,
                lastPage: payload.data?.pagination?.last_page ?? 1,
            });
        } catch (nextError) {
            setError(nextError instanceof Error ? nextError.message : 'Không tải được bản dịch theme.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        setActiveLocale(locale);
        setGroup(initialGroup);
        setEntity(initialGroup === 'content' ? initialEntity : 'all');
        setKeywordInput('');
        setKeyword('');
        setPagination((current) => ({ ...current, page: 1, perPage: DEFAULT_PAGE_SIZE }));
        setEditingKey(null);
        setEditingEntriesByLocale({});
        setEditingLocalesError(null);
    }, [initialEntity, initialGroup, locale, open, theme?.key]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            const normalizedKeyword = keywordInput.trim();
            setKeyword(normalizedKeyword);
            setPagination((current) => ({ ...current, page: 1 }));
        }, 250);

        return () => window.clearTimeout(timeoutId);
    }, [keywordInput, open]);

    useEffect(() => {
        setPagination((current) => ({ ...current, page: 1 }));
        setEditingKey(null);
        setEditingEntriesByLocale({});
        setEditingLocalesError(null);
        setEntity('all');
    }, [activeLocale, group]);

    useEffect(() => {
        void loadTranslations(pagination.page, pagination.perPage, keyword, entity);
    }, [activeLocale, entity, group, keyword, open, pagination.page, pagination.perPage, theme?.key]);

    useEffect(() => {
        if (!open || !theme?.key || !editingKey) {
            setEditingEntriesByLocale({});
            setEditingLocalesError(null);
            setEditingLocalesLoading(false);
            return;
        }

        let cancelled = false;

        const loadEditingLocales = async () => {
            try {
                setEditingLocalesLoading(true);
                setEditingLocalesError(null);

                const localePayloads = await Promise.all(
                    supportedLocales.map(async (supportedLocale) => {
                        const params = new URLSearchParams({
                            locale: supportedLocale,
                            group,
                            page: '1',
                            per_page: '100',
                            keyword: editingKey,
                        });

                        if (group === 'content' && entity !== 'all') {
                            params.set('entity', entity);
                        }

                        const payload = await callAdminApi(`/admin/api/themes/${theme.key}/translations?${params.toString()}`);
                        const matchedEntry = (payload.data?.entries ?? []).find((entry) => entry.key === editingKey);

                        return [supportedLocale, matchedEntry ?? null];
                    }),
                );

                if (cancelled) {
                    return;
                }

                setEditingEntriesByLocale(Object.fromEntries(localePayloads.filter(([, entry]) => entry)));
            } catch (nextError) {
                if (!cancelled) {
                    setEditingLocalesError(nextError instanceof Error ? nextError.message : 'Không tải được bản dịch cho các ngôn ngữ khác.');
                }
            } finally {
                if (!cancelled) {
                    setEditingLocalesLoading(false);
                }
            }
        };

        void loadEditingLocales();

        return () => {
            cancelled = true;
        };
    }, [callAdminApi, editingKey, entity, group, open, supportedLocales, theme?.key]);

    const handleSave = async () => {
        const draftEntries = Object.entries(currentDrafts).map(([entryKey, value]) => ({
            key: entryKey,
            value,
        }));

        if (draftEntries.length === 0) {
            return;
        }

        await runAdminAction(
            () => callAdminApi(`/admin/api/themes/${theme.key}/translations/${activeLocale}`, {
                method: 'PUT',
                body: JSON.stringify({
                    locale: activeLocale,
                    group,
                    entries: draftEntries,
                }),
            }),
            group === 'content' ? 'Đã lưu bản dịch nội dung storefront.' : 'Đã lưu bản dịch frontend của theme.',
            async () => {
                replaceScopeDrafts({});
                await loadTranslations(pagination.page, pagination.perPage, keyword, entity);
            },
        );
    };

    const handleSaveEditingLocales = async () => {
        if (!editingKey) {
            return;
        }

        const localeDrafts = supportedLocales
            .map((supportedLocale) => {
                const localeScopeDrafts = draftsByScope[buildScopeKey(theme?.key, supportedLocale, group)] ?? {};

                if (!Object.prototype.hasOwnProperty.call(localeScopeDrafts, editingKey)) {
                    return null;
                }

                return {
                    locale: supportedLocale,
                    entries: [{
                        key: editingKey,
                        value: localeScopeDrafts[editingKey],
                    }],
                };
            })
            .filter(Boolean);

        if (!localeDrafts.length) {
            return;
        }

        await runAdminAction(
            async () => {
                for (const localeDraft of localeDrafts) {
                    await callAdminApi(`/admin/api/themes/${theme.key}/translations/${localeDraft.locale}`, {
                        method: 'PUT',
                        body: JSON.stringify({
                            locale: localeDraft.locale,
                            group,
                            entries: localeDraft.entries,
                        }),
                    });
                }
            },
            localeDrafts.length > 1
                ? 'Đã lưu bản dịch cho các ngôn ngữ đã chỉnh.'
                : 'Đã lưu bản dịch cho ngôn ngữ đã chỉnh.',
            async () => {
                setDraftsByScope((current) => {
                    const nextState = { ...current };

                    localeDrafts.forEach((localeDraft) => {
                        const localeScopeKey = buildScopeKey(theme?.key, localeDraft.locale, group);
                        const nextScopeDrafts = { ...(nextState[localeScopeKey] ?? {}) };
                        delete nextScopeDrafts[editingKey];
                        nextState[localeScopeKey] = nextScopeDrafts;
                    });

                    return nextState;
                });

                await Promise.all([
                    loadTranslations(pagination.page, pagination.perPage, keyword, entity),
                    (async () => {
                        const localePayloads = await Promise.all(
                            supportedLocales.map(async (supportedLocale) => {
                                const params = new URLSearchParams({
                                    locale: supportedLocale,
                                    group,
                                    page: '1',
                                    per_page: '100',
                                    keyword: editingKey,
                                });

                                if (group === 'content' && entity !== 'all') {
                                    params.set('entity', entity);
                                }

                                const payload = await callAdminApi(`/admin/api/themes/${theme.key}/translations?${params.toString()}`);
                                const matchedEntry = (payload.data?.entries ?? []).find((entry) => entry.key === editingKey);

                                return [supportedLocale, matchedEntry ?? null];
                            }),
                        );

                        setEditingEntriesByLocale(Object.fromEntries(localePayloads.filter(([, entry]) => entry)));
                    })(),
                ]);
            },
        );
    };

    const entityOptions = availableEntities.map((value) => ({
        value,
        label: ENTITY_LABELS[value] ?? value,
    }));

    const drawerTitle = title || (theme ? `Frontend translations: ${theme.name} (${activeLocale.toUpperCase()})` : 'Frontend translations');
    const drawerDescription = description || (group === 'content'
        ? 'Tab này chỉnh business content đang render ra storefront: menu, CMS page/post/category, catalog category/product, banner, site profile và các block riêng của đúng theme đang mở. Text hệ thống admin không nằm trong phạm vi editor này.'
        : 'Tab này chỉnh text tĩnh của theme storefront. Text hệ thống admin không nằm trong phạm vi editor này.');

    return (
        <>
            <Drawer
                title={drawerTitle}
                open={open}
                onClose={onClose}
                width={760}
                maskClosable={false}
                destroyOnHidden
                extra={(
                    <Button type="primary" disabled={!canManageTranslations || pendingChangesCount === 0} onClick={handleSave}>
                        {pendingChangesCount > 0 ? `Lưu ${pendingChangesCount} thay đổi` : 'Lưu bản dịch'}
                    </Button>
                )}
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Paragraph style={{ marginBottom: 0 }}>{drawerDescription}</Paragraph>

                    <Segmented
                        block
                        value={activeLocale}
                        onChange={setActiveLocale}
                        options={supportedLocales.map((supportedLocale) => ({
                            label: localeOptions.find((option) => option.code === supportedLocale)?.name ?? supportedLocale.toUpperCase(),
                            value: supportedLocale,
                        }))}
                    />

                    <Segmented
                        block
                        value={group}
                        onChange={setGroup}
                        options={[
                            { label: 'Static theme copy', value: 'static' },
                            { label: 'Business content', value: 'content' },
                        ]}
                    />

                    <Search
                        placeholder={group === 'content' ? 'Tìm theo key, label hoặc nội dung storefront' : 'Tìm theo key hoặc nội dung mặc định'}
                        allowClear
                        value={keywordInput}
                        onChange={(event) => setKeywordInput(event.target.value)}
                        onSearch={(value) => setKeywordInput(value)}
                    />

                    {group === 'content' ? (
                        <Select
                            value={entity}
                            options={entityOptions}
                            onChange={(value) => {
                                setEntity(value);
                                setPagination((current) => ({ ...current, page: 1 }));
                            }}
                        />
                    ) : null}

                    {error ? <Alert type="error" showIcon message={error} /> : null}

                    {loading ? (
                        <div style={{ padding: '48px 0', textAlign: 'center' }}>
                            <Spin />
                        </div>
                    ) : visibleEntries.length ? (
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                            <List
                                itemLayout="horizontal"
                                dataSource={visibleEntries}
                                renderItem={(entry) => {
                                    const isDirty = Object.prototype.hasOwnProperty.call(currentDrafts, entry.key);
                                    const previewValue = String(entry.value || entry.default_value || 'Chưa có nội dung.').trim();
                                    const resolvedEntity = entryEntity(entry.key);

                                    return (
                                        <List.Item
                                            key={entry.key}
                                            actions={[
                                                <Button key="edit" type="link" onClick={() => setEditingKey(entry.key)}>
                                                    Edit
                                                </Button>,
                                                isDirty ? (
                                                    <Button
                                                        key="reset"
                                                        type="link"
                                                        onClick={() => clearDraft(entry.key, entry.override_value ?? entry.effective_value ?? '')}
                                                    >
                                                        Reset
                                                    </Button>
                                                ) : null,
                                            ].filter(Boolean)}
                                        >
                                            <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                                <Space size={8} wrap>
                                                    <Text className="card-label">{entry.key}</Text>
                                                    {group === 'content' ? <Tag>{ENTITY_LABELS[resolvedEntity] ?? 'Content'}</Tag> : null}
                                                    {entry.override_value ? <Tag color="blue">Override</Tag> : null}
                                                    {isDirty ? <Tag color="orange">Chưa lưu</Tag> : null}
                                                </Space>
                                                {entry.label ? <Text strong>{entry.label}</Text> : null}
                                                {group === 'content' && entry.source_value ? (
                                                    <Paragraph style={{ marginBottom: 0 }} type="secondary" ellipsis={{ rows: 1, tooltip: entry.source_value }}>
                                                        Nguồn storefront: {entry.source_value}
                                                    </Paragraph>
                                                ) : null}
                                                <Paragraph style={{ marginBottom: 0 }} type="secondary" ellipsis={{ rows: 2, tooltip: previewValue }}>
                                                    {previewValue}
                                                </Paragraph>
                                            </Space>
                                        </List.Item>
                                    );
                                }}
                            />

                            <Pagination
                                align="end"
                                current={pagination.page}
                                pageSize={pagination.perPage}
                                total={pagination.total}
                                showSizeChanger
                                pageSizeOptions={['10', '25', '50', '100']}
                                onChange={(page, pageSize) => {
                                    setPagination({
                                        page,
                                        perPage: pageSize,
                                        total: pagination.total,
                                        lastPage: pagination.lastPage,
                                    });
                                }}
                                showTotal={(total, range) => `${range[0]}-${range[1]} / ${total} items`}
                            />
                        </Space>
                    ) : (
                        <Empty description={keyword ? 'Không tìm thấy key phù hợp với từ khóa hiện tại.' : 'Chưa có key dịch nào cho theme này.'} />
                    )}
                </Space>
            </Drawer>

            <Modal
                title={editingEntry?.label || editingEntry?.key || 'Chỉnh bản dịch'}
                open={isEditing}
                onCancel={() => setEditingKey(null)}
                destroyOnHidden
                width={720}
                footer={[
                    <Button key="close" onClick={() => setEditingKey(null)}>
                        Đóng
                    </Button>,
                    <Button
                        key="save"
                        type="primary"
                        onClick={handleSaveEditingLocales}
                        disabled={!canManageTranslations || editingPendingChangesCount === 0}
                    >
                        {editingPendingChangesCount > 0 ? `Lưu ${editingPendingChangesCount} ngôn ngữ` : 'Lưu bản dịch'}
                    </Button>,
                ].filter(Boolean)}
            >
                {isEditing ? (
                    <Space direction="vertical" size={12} style={{ width: '100%' }}>
                        <Text className="card-label">{editingEntry?.key}</Text>
                        {group === 'content' && editingEntry?.source_value ? (
                            <Paragraph style={{ marginBottom: 0 }} type="secondary">
                                Nguồn storefront: {editingEntry.source_value}
                            </Paragraph>
                        ) : null}
                        <Paragraph style={{ marginBottom: 0 }} type="secondary">
                            Mặc định: {editingEntry?.default_value || 'Chưa có text mặc định cho key này.'}
                        </Paragraph>
                        <Paragraph style={{ marginBottom: 0 }} type="secondary">
                            Form này hiển thị cùng lúc tất cả ngôn ngữ đang cấu hình cho đúng key đang mở để sửa nhanh trong một modal.
                        </Paragraph>

                        {editingLocalesError ? <Alert type="error" showIcon message={editingLocalesError} /> : null}

                        {editingLocalesLoading ? (
                            <div style={{ padding: '24px 0', textAlign: 'center' }}>
                                <Spin />
                            </div>
                        ) : (
                            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                {editingLocaleEntries.map((entry) => (
                                    <div
                                        key={`${entry.locale}:${entry.key}`}
                                        style={{
                                            padding: 16,
                                            border: entry.locale === activeLocale ? '1px solid #1677ff' : '1px solid #f0f0f0',
                                            borderRadius: 12,
                                            background: entry.locale === activeLocale ? '#f6fbff' : '#fff',
                                        }}
                                    >
                                        <Space direction="vertical" size={8} style={{ width: '100%' }}>
                                            <Space size={8} wrap>
                                                <Text strong>{`Bản dịch ${entry.localeLabel}`}</Text>
                                                {entry.override_value ? <Tag color="blue">Override</Tag> : null}
                                                {entry.isDirty ? <Tag color="orange">Chưa lưu</Tag> : null}
                                                {entry.locale === activeLocale ? <Tag color="geekblue">Đang xem</Tag> : null}
                                            </Space>
                                            <TextArea
                                                autoSize={{ minRows: 4, maxRows: 10 }}
                                                disabled={!canManageTranslations}
                                                value={entry.value}
                                                onChange={(event) => updateDraft(entry.key, event.target.value, entry.locale)}
                                                placeholder={entry.default_value || `Nhập nội dung cho ${entry.localeLabel}`}
                                            />
                                            <Space>
                                                <Button
                                                    onClick={() => clearDraft(entry.key, entry.default_value || '', entry.locale)}
                                                    disabled={!canManageTranslations}
                                                >
                                                    Trả về mặc định
                                                </Button>
                                                <Button
                                                    type="link"
                                                    onClick={() => clearDraft(entry.key, entry.override_value ?? entry.effective_value ?? '', entry.locale)}
                                                    disabled={!canManageTranslations}
                                                >
                                                    Hoàn tác về giá trị hiện tại
                                                </Button>
                                            </Space>
                                        </Space>
                                    </div>
                                ))}

                                {!editingLocaleEntries.length ? (
                                    <Empty description="Không tải được dữ liệu bản dịch cho key này ở các ngôn ngữ đã cấu hình." />
                                ) : null}
                            </Space>
                        )}
                    </Space>
                ) : null}
            </Modal>
        </>
    );
}

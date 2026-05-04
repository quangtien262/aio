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

export default function ThemeTranslationDrawer({ open, theme, locale, canManageTranslations, callAdminApi, runAdminAction, onClose }) {
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

    const scopeKey = `${theme?.key ?? 'unknown'}:${activeLocale}:${group}`;
    const currentDrafts = draftsByScope[scopeKey] ?? {};
    const visibleEntries = useMemo(() => applyDrafts(entries, currentDrafts), [entries, currentDrafts]);
    const editingEntry = useMemo(
        () => visibleEntries.find((entry) => entry.key === editingKey) ?? null,
        [editingKey, visibleEntries],
    );
    const pendingChangesCount = Object.keys(currentDrafts).length;

    const updateDraft = (translationKey, value) => {
        setDraftsByScope((current) => ({
            ...current,
            [scopeKey]: {
                ...(current[scopeKey] ?? {}),
                [translationKey]: value,
            },
        }));
    };

    const clearDraft = (translationKey, fallbackValue) => {
        setDraftsByScope((current) => {
            const nextScopeDrafts = { ...(current[scopeKey] ?? {}) };

            if (fallbackValue === undefined) {
                delete nextScopeDrafts[translationKey];
            } else {
                nextScopeDrafts[translationKey] = fallbackValue;
            }

            return {
                ...current,
                [scopeKey]: nextScopeDrafts,
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
        setGroup('static');
        setEntity('all');
        setKeywordInput('');
        setKeyword('');
        setPagination((current) => ({ ...current, page: 1, perPage: DEFAULT_PAGE_SIZE }));
        setEditingKey(null);
    }, [locale, open, theme?.key]);

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
        setEntity('all');
    }, [activeLocale, group]);

    useEffect(() => {
        void loadTranslations(pagination.page, pagination.perPage, keyword, entity);
    }, [activeLocale, entity, group, keyword, open, pagination.page, pagination.perPage, theme?.key]);

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

    const entityOptions = availableEntities.map((value) => ({
        value,
        label: ENTITY_LABELS[value] ?? value,
    }));

    return (
        <>
            <Drawer
                title={theme ? `Frontend translations: ${theme.name} (${activeLocale.toUpperCase()})` : 'Frontend translations'}
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
                    <Paragraph style={{ marginBottom: 0 }}>
                        {group === 'content'
                            ? 'Tab này chỉnh business content đang render ra storefront: menu, CMS page/post/category, catalog category/product, banner và site profile. Text hệ thống admin không nằm trong phạm vi editor này.'
                            : 'Tab này chỉnh text tĩnh của theme storefront. Text hệ thống admin không nằm trong phạm vi editor này.'}
                    </Paragraph>

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
                title={editingEntry?.label || editingEntry?.key || 'Edit translation'}
                open={Boolean(editingEntry)}
                onCancel={() => setEditingKey(null)}
                destroyOnHidden
                width={720}
                footer={[
                    <Button key="close" onClick={() => setEditingKey(null)}>
                        Đóng
                    </Button>,
                    editingEntry ? (
                        <Button
                            key="reset"
                            onClick={() => clearDraft(editingEntry.key, editingEntry.default_value || '')}
                            disabled={!canManageTranslations}
                        >
                            Trả về mặc định
                        </Button>
                    ) : null,
                ].filter(Boolean)}
            >
                {editingEntry ? (
                    <Space direction="vertical" size={12} style={{ width: '100%' }}>
                        <Text className="card-label">{editingEntry.key}</Text>
                        {group === 'content' && editingEntry.source_value ? (
                            <Paragraph style={{ marginBottom: 0 }} type="secondary">
                                Nguồn storefront: {editingEntry.source_value}
                            </Paragraph>
                        ) : null}
                        <Paragraph style={{ marginBottom: 0 }} type="secondary">
                            Mặc định: {editingEntry.default_value || 'Chưa có text mặc định cho key này.'}
                        </Paragraph>
                        <TextArea
                            autoSize={{ minRows: 6, maxRows: 16 }}
                            disabled={!canManageTranslations}
                            value={editingEntry.value}
                            onChange={(event) => updateDraft(editingEntry.key, event.target.value)}
                            placeholder={editingEntry.default_value || 'Nhập nội dung bản dịch'}
                        />
                    </Space>
                ) : null}
            </Modal>
        </>
    );
}

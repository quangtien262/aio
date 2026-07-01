import { useEffect, useMemo, useState } from 'react';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EyeOutlined from '@ant-design/icons/EyeOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Tag from 'antd/es/tag';
import Tabs from 'antd/es/tabs';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;

function blockTitle(block) {
    return block?.data?.title || block?.data?.subtitle || block?.block_type || 'Khối landing';
}

function localeLabel(localeCode) {
    return localeCode === 'vi' ? 'Tiếng Việt' : localeCode === 'en' ? 'English' : localeCode.toUpperCase();
}

function normalizeFormLocales(block) {
    return Object.fromEntries(
        Object.entries(block?.data_by_locale ?? { [block?.data?.locale ?? 'vi']: block?.data ?? {} }).map(([localeCode, data]) => [
            localeCode,
            {
                title: data?.title ?? '',
                subtitle: data?.subtitle ?? '',
                description: data?.description ?? '',
                button_label: data?.button_label ?? '',
                content: data?.content ?? {},
            },
        ]),
    );
}

function editorItemKey(blockType) {
    return blockType === 'hero_slider' ? 'slides' : 'items';
}

function editorItemFields(blockType) {
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

    if (blockType === 'testimonials') {
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

    if (blockType === 'featured_categories') {
        return [
            ['title', 'Tiêu đề'],
            ['summary', 'Mô tả / nhãn phụ', 'textarea'],
            ['image', 'Ảnh'],
            ['icon', 'Icon / ký tự'],
            ['url', 'Link'],
            ['count_label', 'Nhãn số lượng'],
        ];
    }

    return [
        ['title', 'Tiêu đề'],
        ['summary', 'Mô tả', 'textarea'],
        ['image', 'Ảnh'],
        ['url', 'Link'],
        ['button_label', 'Nút bấm'],
    ];
}

export default function LandingBlockManagerDrawer({
    open,
    page,
    locale = 'vi',
    canCreate,
    canUpdate,
    canDelete,
    callAdminApi,
    runAdminAction,
    onClose,
    onChanged,
}) {
    const [form] = Form.useForm();
    const [blocks, setBlocks] = useState([]);
    const [availableBlocks, setAvailableBlocks] = useState([]);
    const [selectedBlockType, setSelectedBlockType] = useState(null);
    const [loading, setLoading] = useState(false);
    const [draggingId, setDraggingId] = useState(null);
    const [addBlockModalOpen, setAddBlockModalOpen] = useState(false);
    const [editingBlock, setEditingBlock] = useState(null);
    const [savingBlock, setSavingBlock] = useState(false);
    const [activeEditLocale, setActiveEditLocale] = useState(locale);
    const [contentVersion, setContentVersion] = useState(0);
    const [editingItemIndex, setEditingItemIndex] = useState(null);
    const [itemModalOpen, setItemModalOpen] = useState(false);
    const [itemDraft, setItemDraft] = useState({});

    const editingLocales = useMemo(() => Object.keys(editingBlock?.data_by_locale ?? { [locale]: {} }), [editingBlock, locale]);
    const activeItemKey = editingBlock ? editorItemKey(editingBlock.block_type) : 'items';
    const activeContent = editingBlock ? (form.getFieldValue(['data_by_locale', activeEditLocale, 'content']) ?? {}) : {};
    const activeItems = Array.isArray(activeContent?.[activeItemKey]) ? activeContent[activeItemKey] : [];

    const loadBlocks = async () => {
        if (!page?.id) {
            return;
        }

        setLoading(true);

        try {
            const payload = await callAdminApi(`/admin/api/landing/pages/${page.id}/blocks?locale=${encodeURIComponent(locale)}`);
            setBlocks(payload.data ?? []);
            setAvailableBlocks(payload.available_blocks ?? []);
            setSelectedBlockType(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (open) {
            loadBlocks();
        }
    }, [open, page?.id, locale]);

    const openAddBlockModal = () => {
        setSelectedBlockType(null);
        setAddBlockModalOpen(true);
    };

    const handleAddBlock = async (blockType = selectedBlockType) => {
        if (!blockType || !page?.id) {
            return;
        }

        await runAdminAction(
            () => callAdminApi(`/admin/api/landing/pages/${page.id}/blocks`, {
                method: 'POST',
                body: JSON.stringify({ block_type: blockType, locale }),
            }),
            'Đã thêm khối landingpage.',
            async () => {
                setAddBlockModalOpen(false);
                await loadBlocks();
                await onChanged?.();
            },
        );
    };

    const reorderBlocks = async (nextBlocks) => {
        const previousBlocks = blocks;
        const normalizedBlocks = nextBlocks.map((block, index) => ({ ...block, sort_order: (index + 1) * 10 }));
        setBlocks(normalizedBlocks);

        try {
            await callAdminApi(`/admin/api/landing/pages/${page.id}/blocks/reorder`, {
                method: 'PUT',
                body: JSON.stringify({
                    blocks: normalizedBlocks.map((block) => ({ id: block.id, sort_order: block.sort_order })),
                }),
            });
        } catch {
            setBlocks(previousBlocks);
        }
    };

    const handleDrop = async (targetId) => {
        if (!draggingId || draggingId === targetId) {
            setDraggingId(null);
            return;
        }

        const currentIndex = blocks.findIndex((block) => block.id === draggingId);
        const targetIndex = blocks.findIndex((block) => block.id === targetId);

        if (currentIndex < 0 || targetIndex < 0) {
            setDraggingId(null);
            return;
        }

        const nextBlocks = [...blocks];
        const [movingBlock] = nextBlocks.splice(currentIndex, 1);
        nextBlocks.splice(targetIndex, 0, movingBlock);
        setDraggingId(null);
        await reorderBlocks(nextBlocks);
    };

    const handleToggleVisible = async (block, checked) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/landing/blocks/${block.id}`, {
                method: 'PUT',
                body: JSON.stringify({ is_visible: checked, locale }),
            }),
            checked ? 'Đã bật hiển thị khối.' : 'Đã ẩn khối.',
            async () => {
                await loadBlocks();
                await onChanged?.();
            },
        );
    };

    const handleDeleteBlock = async (block) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/landing/blocks/${block.id}`, { method: 'DELETE' }),
            'Đã xóa khối landingpage.',
            async () => {
                await loadBlocks();
                await onChanged?.();
            },
        );
    };

    const openPageVisualEditor = () => {
        if (page?.admin_url) {
            window.location.href = page.admin_url;
        }
    };

    const openBlockEditor = (block) => {
        const localeCodes = Object.keys(block.data_by_locale ?? { [locale]: block.data ?? {} });
        setActiveEditLocale(localeCodes.includes(locale) ? locale : (localeCodes[0] ?? locale));
        setEditingItemIndex(null);
        setItemModalOpen(false);
        setItemDraft({});
        setEditingBlock(block);
        form.setFieldsValue({
            anchor_id: block.anchor_id ?? '',
            is_visible: Boolean(block.is_visible),
            settings: block.settings ?? {},
            data_by_locale: normalizeFormLocales(block),
        });
        setContentVersion((version) => version + 1);
    };

    const handleSaveBlock = async () => {
        if (!editingBlock) {
            return;
        }

        const values = await form.validateFields();
        const locales = Object.keys(editingBlock.data_by_locale ?? { [locale]: editingBlock.data ?? {} });

        setSavingBlock(true);

        try {
            for (const localeCode of locales) {
                const localeData = values.data_by_locale?.[localeCode] ?? {};

                await callAdminApi(`/admin/api/landing/blocks/${editingBlock.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({
                        locale: localeCode,
                        anchor_id: values.anchor_id || null,
                        is_visible: Boolean(values.is_visible),
                        settings: values.settings ?? {},
                        data: {
                            title: localeData.title ?? '',
                            subtitle: localeData.subtitle ?? '',
                            description: localeData.description ?? '',
                            button_label: localeData.button_label ?? '',
                            content: localeData.content ?? {},
                        },
                    }),
                });
            }

            setEditingBlock(null);
            await loadBlocks();
        } finally {
            setSavingBlock(false);
        }
    };

    const setActiveLocaleItems = (nextItems) => {
        const currentContent = form.getFieldValue(['data_by_locale', activeEditLocale, 'content']) ?? {};
        form.setFieldValue(['data_by_locale', activeEditLocale, 'content'], {
            ...currentContent,
            [activeItemKey]: nextItems,
        });
        setContentVersion((version) => version + 1);
    };

    const openItemEditor = (index = null, item = {}) => {
        setEditingItemIndex(index);
        setItemDraft(item ?? {});
        setItemModalOpen(true);
    };

    const saveItemDraft = () => {
        const cleanItem = Object.fromEntries(
            Object.entries(itemDraft).filter(([, value]) => String(value ?? '').trim() !== ''),
        );
        const nextItems = [...activeItems];

        if (editingItemIndex === null) {
            nextItems.push(cleanItem);
        } else {
            nextItems[editingItemIndex] = cleanItem;
        }

        setActiveLocaleItems(nextItems);
        setItemModalOpen(false);
        setEditingItemIndex(null);
        setItemDraft({});
    };

    const removeItem = (index) => {
        setActiveLocaleItems(activeItems.filter((_, itemIndex) => itemIndex !== index));
    };

    const renderSettingField = ([key, schema]) => {
        const label = schema?.label || key;

        if (schema?.type === 'select') {
            return (
                <Form.Item key={key} name={['settings', key]} label={label}>
                    <Select
                        options={(schema.options ?? []).map((option) => ({ value: option, label: option }))}
                        placeholder="Chọn nguồn"
                        allowClear
                    />
                </Form.Item>
            );
        }

        if (schema?.type === 'boolean') {
            return (
                <Form.Item key={key} name={['settings', key]} label={label} valuePropName="checked">
                    <Switch />
                </Form.Item>
            );
        }

        return (
            <Form.Item key={key} name={['settings', key]} label={label}>
                <InputNumber min={0} style={{ width: '100%' }} />
            </Form.Item>
        );
    };

    return (
        <>
        <Drawer
            title={page ? `Quản lý khối: ${page.title || page.path}` : 'Quản lý khối landingpage'}
            open={open}
            onClose={onClose}
            width={760}
            destroyOnHidden
        >
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                {page ? (
                    <Card size="small">
                        <Space direction="vertical" size={4}>
                            <Space wrap>
                                <Tag color={page.is_home ? 'green' : 'blue'}>{page.is_home ? 'Trang chủ' : page.path}</Tag>
                                <Tag>{page.theme_key}</Tag>
                                <Tag color={page.status === 'published' ? 'green' : 'default'}>{page.status === 'published' ? 'Đã xuất bản' : 'Bản nháp'}</Tag>
                            </Space>
                            <Text type="secondary">Có thể sắp xếp bằng cách kéo từng khối lên/xuống. Nút sửa trực quan sẽ mở website với chế độ admin.</Text>
                        </Space>
                    </Card>
                ) : null}

                <Card
                    size="small"
                    title="Thêm khối"
                    extra={(
                        <Space>
                            <Button icon={<EyeOutlined />} disabled={!page?.admin_url} onClick={openPageVisualEditor}>Sửa trực quan</Button>
                            <Button type="primary" icon={<PlusOutlined />} disabled={!canCreate || !availableBlocks.length} onClick={openAddBlockModal}>Thêm khối</Button>
                        </Space>
                    )}
                >
                    <Paragraph type="secondary" style={{ marginBottom: 0 }}>
                        Bấm thêm khối để chọn từ danh sách block đã tích hợp sẵn cho theme này.
                    </Paragraph>
                </Card>

                {loading ? (
                    <Card loading />
                ) : blocks.length ? (
                    <Space direction="vertical" size={10} style={{ width: '100%' }}>
                        {blocks.map((block, index) => (
                            <Card
                                key={block.id}
                                size="small"
                                draggable={canUpdate}
                                onDragStart={() => setDraggingId(block.id)}
                                onDragOver={(event) => event.preventDefault()}
                                onDrop={() => handleDrop(block.id)}
                                style={{
                                    borderColor: draggingId === block.id ? '#bed600' : undefined,
                                    cursor: canUpdate ? 'grab' : 'default',
                                }}
                            >
                                <div style={{ display: 'grid', gridTemplateColumns: '56px 1fr auto', gap: 12, alignItems: 'center' }}>
                                    <div style={{ width: 44, height: 44, borderRadius: 14, display: 'grid', placeItems: 'center', background: '#eef6d1', color: '#6a8a00', fontWeight: 800 }}>
                                        {index + 1}
                                    </div>
                                    <Space direction="vertical" size={2}>
                                        <Space wrap>
                                            <Title level={5} style={{ margin: 0 }}>{blockTitle(block)}</Title>
                                            <Tag>{block.block_type}</Tag>
                                            {block.anchor_id ? <Tag color="blue">#{block.anchor_id}</Tag> : null}
                                        </Space>
                                        <Text type="secondary">{block.data?.description || block.data?.subtitle || 'Khối nội dung landingpage'}</Text>
                                    </Space>
                                    <Space>
                                        <Space size={6}>
                                            <Switch
                                                checked={Boolean(block.is_visible)}
                                                disabled={!canUpdate}
                                                onChange={(checked) => handleToggleVisible(block, checked)}
                                            />
                                            <Text type={block.is_visible ? 'success' : 'secondary'} strong>
                                                {block.is_visible ? 'Hiện' : 'Ẩn'}
                                            </Text>
                                        </Space>
                                        <Button icon={<EditOutlined />} disabled={!canUpdate} onClick={() => openBlockEditor(block)}>Sửa</Button>
                                        <Popconfirm title="Xóa khối này?" disabled={!canDelete} onConfirm={() => handleDeleteBlock(block)}>
                                            <Button danger icon={<DeleteOutlined />} disabled={!canDelete}>Xóa</Button>
                                        </Popconfirm>
                                    </Space>
                                </div>
                            </Card>
                        ))}
                    </Space>
                ) : (
                    <Empty description="Landingpage này chưa có khối nào." />
                )}

                {!canUpdate ? (
                    <Alert type="info" showIcon message="Tài khoản hiện tại chỉ có quyền xem, chưa thể sắp xếp hoặc sửa khối." />
                ) : null}
            </Space>
        </Drawer>

        <Modal
            title="Chọn khối muốn thêm"
            open={addBlockModalOpen}
            onCancel={() => setAddBlockModalOpen(false)}
            onOk={() => handleAddBlock()}
            okText="Thêm khối này"
            cancelText="Hủy"
            okButtonProps={{ disabled: !selectedBlockType }}
            width={760}
            destroyOnHidden
        >
            <Space direction="vertical" size={10} style={{ width: '100%' }}>
                {availableBlocks.map((block) => {
                    const active = selectedBlockType === block.block_type;

                    return (
                        <Card
                            key={block.block_type}
                            size="small"
                            hoverable
                            onClick={() => setSelectedBlockType(block.block_type)}
                            style={{
                                borderColor: active ? '#bed600' : undefined,
                                background: active ? '#fbfde8' : undefined,
                            }}
                        >
                            <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                {block.preview_image ? (
                                    <div style={{ width: '100%', overflow: 'hidden', borderRadius: 12, background: '#f2f6f0', border: '1px solid #edf1e8' }}>
                                        <img
                                            src={block.preview_image}
                                            alt={`Ảnh mẫu ${block.label || block.block_type}`}
                                            loading="lazy"
                                            style={{ width: '100%', height: 'auto', display: 'block' }}
                                        />
                                    </div>
                                ) : null}
                                <Space wrap>
                                    <Title level={5} style={{ margin: 0 }}>{block.label || block.block_type}</Title>
                                    <Tag color={active ? 'green' : 'default'}>{block.block_type}</Tag>
                                </Space>
                                <Text type="secondary">{block.description || 'Khối nội dung đã được tích hợp sẵn trong theme.'}</Text>
                            </Space>
                        </Card>
                    );
                })}
                {!availableBlocks.length ? <Empty description="Theme này chưa khai báo block có thể thêm." /> : null}
            </Space>
        </Modal>

        <Modal
            title={editingBlock ? `Sửa khối: ${blockTitle(editingBlock)}` : 'Sửa khối landing'}
            open={Boolean(editingBlock)}
            onCancel={() => setEditingBlock(null)}
            onOk={handleSaveBlock}
            okText="Lưu"
            cancelText="Hủy"
            confirmLoading={savingBlock}
            width={760}
            destroyOnHidden
        >
            {editingBlock ? (
                <Form form={form} layout="vertical">
                    <Card size="small" style={{ marginBottom: 16 }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 180px', gap: 16, alignItems: 'end' }}>
                            <Form.Item name="anchor_id" label="Anchor">
                                <Input placeholder="vd: dich-vu, du-an" />
                            </Form.Item>
                            <Form.Item name="is_visible" label="Hiển thị" valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </div>
                        {Object.keys(editingBlock.settings_schema ?? {}).length ? (
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: 16 }}>
                                {Object.entries(editingBlock.settings_schema ?? {}).map(renderSettingField)}
                            </div>
                        ) : null}
                        <Form.Item name={['settings', 'cta_url']} label="Link CTA">
                            <Input placeholder="/gioi-thieu hoac https://..." />
                        </Form.Item>
                    </Card>

                    <Tabs
                        items={editingLocales.map((localeCode) => ({
                            key: localeCode,
                            label: localeLabel(localeCode),
                            children: (
                                <div style={{ display: 'grid', gap: 12 }}>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                        <Form.Item name={['data_by_locale', localeCode, 'title']} label="Tiêu đề">
                                            <Input />
                                        </Form.Item>
                                        <Form.Item name={['data_by_locale', localeCode, 'subtitle']} label="Nhãn phụ">
                                            <Input />
                                        </Form.Item>
                                    </div>
                                    <Form.Item name={['data_by_locale', localeCode, 'description']} label="Mô tả">
                                        <Input.TextArea rows={4} />
                                    </Form.Item>
                                    <Form.Item name={['data_by_locale', localeCode, 'button_label']} label="Nút CTA">
                                        <Input />
                                    </Form.Item>
                                    {localeCode === activeEditLocale ? (
                                        <Card
                                            size="small"
                                            title="Danh sách nội dung"
                                            extra={<Button size="small" icon={<PlusOutlined />} onClick={() => openItemEditor(null, {})}>Thêm mục</Button>}
                                        >
                                            <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                                <Text type="secondary">Chỉnh từng mục bằng form, không cần nhập JSON.</Text>
                                                {activeItems.length ? activeItems.map((item, index) => {
                                                    const image = item.image || item.logo || item.avatar || '';
                                                    const title = item.title || item.name || item.kicker || `Mục ${index + 1}`;
                                                    const summary = item.summary || item.description || item.quote || item.role || item.company || item.url || item.link_url || 'Chưa có mô tả.';

                                                    return (
                                                        <Card key={`${contentVersion}-${index}`} size="small">
                                                            <div style={{ display: 'grid', gridTemplateColumns: '72px 1fr auto', gap: 12, alignItems: 'center' }}>
                                                                <div style={{ width: 72, height: 54, overflow: 'hidden', borderRadius: 10, background: '#eef3ee', display: 'grid', placeItems: 'center', color: '#94a3b8', fontSize: 11, fontWeight: 800 }}>
                                                                    {image ? <img src={image} alt={title} style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : 'No img'}
                                                                </div>
                                                                <Space direction="vertical" size={2}>
                                                                    <Text type="secondary" style={{ fontSize: 11, fontWeight: 800, textTransform: 'uppercase' }}>{`Mục ${index + 1}`}</Text>
                                                                    <Text strong>{title}</Text>
                                                                    <Paragraph type="secondary" ellipsis={{ rows: 2 }} style={{ marginBottom: 0 }}>{summary}</Paragraph>
                                                                </Space>
                                                                <Space>
                                                                    <Button size="small" onClick={() => openItemEditor(index, item)}>Sửa</Button>
                                                                    <Button size="small" danger onClick={() => removeItem(index)}>Xóa</Button>
                                                                </Space>
                                                            </div>
                                                        </Card>
                                                    );
                                                }) : (
                                                    <Empty description="Chưa có mục nội dung nào." />
                                                )}
                                            </Space>
                                        </Card>
                                    ) : null}
                                </div>
                            ),
                        }))}
                        activeKey={activeEditLocale}
                        onChange={setActiveEditLocale}
                    />
                </Form>
            ) : null}
        </Modal>

        <Modal
            title={editingItemIndex === null ? 'Thêm mục nội dung' : `Sửa mục ${editingItemIndex + 1}`}
            open={itemModalOpen}
            onCancel={() => {
                setItemModalOpen(false);
                setEditingItemIndex(null);
                setItemDraft({});
            }}
            onOk={saveItemDraft}
            okText="Lưu mục"
            cancelText="Hủy"
            destroyOnHidden
        >
            <div style={{ display: 'grid', gap: 12 }}>
                {editingBlock ? editorItemFields(editingBlock.block_type).map(([key, label, type]) => (
                    <label key={key} style={{ display: 'grid', gap: 6, fontWeight: 700 }}>
                        <span>{label}</span>
                        {type === 'textarea' ? (
                            <Input.TextArea
                                rows={4}
                                value={itemDraft[key] ?? ''}
                                onChange={(event) => setItemDraft((draft) => ({ ...draft, [key]: event.target.value }))}
                            />
                        ) : (
                            <Input
                                value={itemDraft[key] ?? ''}
                                onChange={(event) => setItemDraft((draft) => ({ ...draft, [key]: event.target.value }))}
                            />
                        )}
                    </label>
                )) : null}
            </div>
        </Modal>
        </>
    );
}

import { useEffect, useMemo, useState } from 'react';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EyeOutlined from '@ant-design/icons/EyeOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import HolderOutlined from '@ant-design/icons/HolderOutlined';
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
import SingleMediaPicker from '../../../shared/components/SingleMediaPicker';

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
    if (blockType === 'hero_slider') {
        return 'slides';
    }

    if (blockType === 'about_experience') {
        return 'tabs';
    }

    return 'items';
}

function FormValueBridge() {
    return null;
}

function isMediaItemField(key) {
    return ['image', 'logo', 'avatar'].includes(key);
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

    if (blockType === 'about_experience') {
        return [
            ['label', 'Tên tab'],
            ['description', 'Nội dung tab', 'textarea'],
        ];
    }

    if (['testimonials', 'testimonial_showcase', 'bizmax_testimonial_carousel'].includes(blockType)) {
        return [
            ['name', 'Tên khách hàng'],
            ['company', 'Công ty / vai trò'],
            ['quote', 'Nhận xét', 'textarea'],
            ['image', 'Ảnh đại diện'],
            ['url', 'Link'],
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

    if (blockType === 'process_steps') {
        return [
            ['title', 'Tiêu đề bước'],
            ['description', 'Mô tả', 'textarea'],
        ];
    }

    if (['content_showcase', 'latest_posts', 'featured_service_list', 'completed_projects_list', 'service_category_slider', 'solutions_split_list', 'collection_gallery', 'business_service_grid', 'bizmax_latest_posts'].includes(blockType)) {
        return [
            ['title', 'Tiêu đề'],
            ['summary', 'Mô tả', 'textarea'],
            ['image', 'Ảnh'],
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
    const [visibilityUpdatingIds, setVisibilityUpdatingIds] = useState(() => new Set());
    const [draggingId, setDraggingId] = useState(null);
    const [dragOverId, setDragOverId] = useState(null);
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

    const handleDragStart = (event, blockId) => {
        setDraggingId(blockId);
        setDragOverId(null);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(blockId));
    };

    const handleDragOver = (event, blockId) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        if (draggingId && draggingId !== blockId) {
            setDragOverId(blockId);
        }
    };

    const handleDragEnd = () => {
        setDraggingId(null);
        setDragOverId(null);
    };

    const handleDrop = async (targetId) => {
        if (!draggingId || draggingId === targetId) {
            handleDragEnd();
            return;
        }

        const currentIndex = blocks.findIndex((block) => block.id === draggingId);
        const targetIndex = blocks.findIndex((block) => block.id === targetId);

        if (currentIndex < 0 || targetIndex < 0) {
            handleDragEnd();
            return;
        }

        const nextBlocks = [...blocks];
        const [movingBlock] = nextBlocks.splice(currentIndex, 1);
        nextBlocks.splice(targetIndex, 0, movingBlock);
        handleDragEnd();
        await reorderBlocks(nextBlocks);
    };

    const handleToggleVisible = async (block, checked) => {
        const previousBlocks = blocks;

        setVisibilityUpdatingIds((currentIds) => new Set(currentIds).add(block.id));
        setBlocks((currentBlocks) => currentBlocks.map((currentBlock) => (
            currentBlock.id === block.id
                ? { ...currentBlock, is_visible: checked }
                : currentBlock
        )));

        const didUpdate = await runAdminAction(
            () => callAdminApi(`/admin/api/landing/blocks/${block.id}`, {
                method: 'PUT',
                body: JSON.stringify({ is_visible: checked, locale }),
            }),
            checked ? 'Đã bật hiển thị khối.' : 'Đã ẩn khối.',
            async () => {
                await onChanged?.();
            },
        );

        if (!didUpdate) {
            setBlocks(previousBlocks);
        }

        setVisibilityUpdatingIds((currentIds) => {
            const nextIds = new Set(currentIds);
            nextIds.delete(block.id);
            return nextIds;
        });
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
            media: block.block_type === 'about_experience'
                ? { image: block.media?.image ?? '' }
                : (block.media ?? {}),
            data_by_locale: normalizeFormLocales(block),
        });
        setContentVersion((version) => version + 1);
    };

    const handleSaveBlock = async () => {
        if (!editingBlock) {
            return;
        }

        await form.validateFields();
        const values = form.getFieldsValue(true);
        const locales = Object.keys(editingBlock.data_by_locale ?? { [locale]: editingBlock.data ?? {} });

        setSavingBlock(true);

        try {
            for (const localeCode of locales) {
                const localeData = values.data_by_locale?.[localeCode] ?? {};
                const existingLocaleData = editingBlock.data_by_locale?.[localeCode] ?? {};

                await callAdminApi(`/admin/api/landing/blocks/${editingBlock.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({
                        locale: localeCode,
                        anchor_id: values.anchor_id || null,
                        is_visible: Boolean(values.is_visible),
                        settings: values.settings ?? {},
                        media: editingBlock.block_type === 'about_experience'
                            ? { image: values.media?.image ?? '' }
                            : (editingBlock.media ?? {}),
                        data: {
                            title: localeData.title ?? '',
                            subtitle: localeData.subtitle ?? '',
                            description: localeData.description ?? '',
                            button_label: localeData.button_label ?? '',
                            content: localeData.content ?? existingLocaleData.content ?? {},
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
                        options={(schema.options ?? []).map((option) => typeof option === 'string'
                            ? { value: option, label: option }
                            : { value: option.value ?? option.key, label: option.label ?? option.value ?? option.key })}
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

        if (schema?.type === 'text') {
            return (
                <Form.Item key={key} name={['settings', key]} label={label}>
                    <Input />
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
                    <Space direction="vertical" size={8} style={{ width: '100%' }}>
                        {blocks.map((block, index) => {
                            const isDragging = draggingId === block.id;
                            const isDropTarget = dragOverId === block.id && draggingId !== block.id;

                            return (
                                <div
                                    key={block.id}
                                    onDragEnter={(event) => handleDragOver(event, block.id)}
                                    onDragOver={(event) => handleDragOver(event, block.id)}
                                    onDrop={() => handleDrop(block.id)}
                                    style={{
                                        position: 'relative',
                                        paddingTop: isDropTarget ? 12 : 0,
                                        transition: 'padding 160ms ease',
                                    }}
                                >
                                    <div
                                        aria-hidden="true"
                                        style={{
                                            position: 'absolute',
                                            top: 2,
                                            left: 16,
                                            right: 16,
                                            height: 4,
                                            borderRadius: 999,
                                            background: '#0f8f82',
                                            boxShadow: '0 0 0 4px rgba(15, 143, 130, 0.12)',
                                            opacity: isDropTarget ? 1 : 0,
                                            transform: isDropTarget ? 'scaleX(1)' : 'scaleX(0.92)',
                                            transition: 'opacity 140ms ease, transform 140ms ease',
                                            pointerEvents: 'none',
                                        }}
                                    />
                                    <Card
                                        size="small"
                                        draggable={canUpdate}
                                        onDragStart={(event) => handleDragStart(event, block.id)}
                                        onDragEnd={handleDragEnd}
                                        style={{
                                            borderColor: isDragging ? '#0f8f82' : isDropTarget ? '#8fd8d1' : undefined,
                                            cursor: canUpdate ? 'grab' : 'default',
                                            opacity: isDragging ? 0.62 : 1,
                                            transform: isDragging ? 'scale(0.985)' : isDropTarget ? 'translateY(2px)' : 'translateY(0)',
                                            boxShadow: isDragging ? '0 14px 30px rgba(15, 23, 42, 0.16)' : undefined,
                                            transition: 'transform 160ms ease, opacity 160ms ease, box-shadow 160ms ease, border-color 160ms ease',
                                            userSelect: isDragging ? 'none' : undefined,
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
                                                <Button
                                                    icon={<HolderOutlined />}
                                                    disabled={!canUpdate}
                                                    aria-label="Kéo để sắp xếp"
                                                    title="Kéo để sắp xếp"
                                                    style={{ cursor: canUpdate ? 'grab' : 'default' }}
                                                />
                                                <Space size={6}>
                                                    <Switch
                                                        checked={Boolean(block.is_visible)}
                                                        disabled={!canUpdate || visibilityUpdatingIds.has(block.id)}
                                                        loading={visibilityUpdatingIds.has(block.id)}
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
                                </div>
                            );
                        })}
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
                        {editingBlock.block_type === 'about_experience' ? (
                            <Form.Item
                                name={['media', 'image']}
                                label="Ảnh tổng hợp bên trái"
                                extra="Chỉ dùng một ảnh hoàn chỉnh; nếu cần hiển thị số năm kinh nghiệm, hãy thiết kế trực tiếp trong ảnh."
                            >
                                <SingleMediaPicker
                                    open={Boolean(editingBlock)}
                                    canManage={canUpdate}
                                    callAdminApi={callAdminApi}
                                    recordTitle={blockTitle(editingBlock)}
                                    previewTitle="Ảnh giới thiệu"
                                    uploadButtonLabel="Upload ảnh giới thiệu"
                                    uploadHint="Ảnh upload xong sẽ thay toàn bộ cột trái của khối giới thiệu."
                                    libraryButtonLabel="Chọn từ thư viện media"
                                    libraryHint="Chọn một ảnh tổng hợp đã có trong thư viện CMS."
                                    urlPlaceholder="https://example.com/about-composite.jpg"
                                    urlButtonLabel="Lưu URL và gắn ảnh"
                                    libraryModalTitle="Chọn ảnh tổng hợp khối giới thiệu"
                                />
                            </Form.Item>
                        ) : null}
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
                                    <Form.Item name={['data_by_locale', localeCode, 'content']} hidden>
                                        <FormValueBridge />
                                    </Form.Item>
                                    {localeCode === activeEditLocale ? (
                                        <Card
                                            size="small"
                                            title={editingBlock.block_type === 'about_experience' ? 'Danh sách tab giới thiệu' : 'Danh sách nội dung'}
                                            extra={<Button size="small" icon={<PlusOutlined />} onClick={() => openItemEditor(null, {})}>{editingBlock.block_type === 'about_experience' ? 'Thêm tab' : 'Thêm mục'}</Button>}
                                        >
                                            <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                                <Text type="secondary">{editingBlock.block_type === 'about_experience' ? 'Mỗi mục tương ứng một tab hiển thị ở cột nội dung bên phải.' : 'Chỉnh từng mục bằng form, không cần nhập JSON.'}</Text>
                                                {activeItems.length ? activeItems.map((item, index) => {
                                                    const image = item.image || item.logo || item.avatar || '';
                                                    const title = item.title || item.label || item.name || item.kicker || `Mục ${index + 1}`;
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
            width={680}
        >
            <div style={{ display: 'grid', gap: 12 }}>
                {editingBlock ? editorItemFields(editingBlock.block_type).map(([key, label, type]) => {
                    if (isMediaItemField(key)) {
                        return (
                            <div key={key} style={{ display: 'grid', gap: 6 }}>
                                <Text strong>{label}</Text>
                                <SingleMediaPicker
                                    open={itemModalOpen}
                                    canManage={canUpdate}
                                    callAdminApi={callAdminApi}
                                    value={itemDraft[key] ?? ''}
                                    onChange={(value) => setItemDraft((draft) => ({ ...draft, [key]: value }))}
                                    recordTitle={itemDraft.title || itemDraft.name || itemDraft.kicker || blockTitle(editingBlock)}
                                    previewTitle={label}
                                    uploadButtonLabel="Upload ảnh trực tiếp"
                                    uploadHint="Ảnh upload xong sẽ tự gắn vào mục nội dung này."
                                    libraryButtonLabel="Mở thư viện media"
                                    libraryHint="Chọn ảnh từ thư viện CMS đã có sẵn."
                                    urlPlaceholder="https://example.com/image.jpg"
                                    urlButtonLabel="Lưu URL và gắn ảnh"
                                    libraryModalTitle={`Chọn ${label.toLowerCase()} từ thư viện`}
                                />
                            </div>
                        );
                    }

                    return (
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
                    );
                }) : null}
            </div>
        </Modal>
        </>
    );
}

import {
    DeleteOutlined,
    EditOutlined,
    HolderOutlined,
    PlusOutlined,
} from '@ant-design/icons';
import { useEffect, useMemo, useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Tree from 'antd/es/tree';
import Typography from 'antd/es/typography';
import SingleMediaPicker from '../../../shared/components/SingleMediaPicker';

const LINK_TYPE_OPTIONS = [
    { label: 'Theo page', value: 'page' },
    { label: 'Theo danh mục sản phẩm', value: 'product-category' },
    { label: 'Theo danh mục tin tức', value: 'post-category' },
    { label: 'Liên kết khác', value: 'custom' },
];

const TARGET_OPTIONS = [
    { label: 'Cùng tab', value: '_self' },
    { label: 'Tab mới', value: '_blank' },
];

const { Paragraph, Text, Title } = Typography;

let sidePromoItemKeySeed = 0;

function createSidePromoItemKey() {
    sidePromoItemKeySeed += 1;

    return `cms-side-promo-item-${sidePromoItemKeySeed}`;
}

function createEmptySidePromoItem() {
    return {
        __itemKey: createSidePromoItemKey(),
        sort_order: 0,
        badge: '',
        title: '',
        subtitle: '',
        cta_label: '',
        image: '',
        url: '',
        target: '_self',
        link_type: 'custom',
        link_value: null,
        custom_url: '',
    };
}

function buildLinkLookups(linkOptions = {}) {
    return {
        page: new Map((linkOptions.pages ?? []).map((item) => [String(item.value), item])),
        'product-category': new Map((linkOptions.productCategories ?? []).map((item) => [String(item.value), item])),
        'post-category': new Map((linkOptions.postCategories ?? []).map((item) => [String(item.value), item])),
    };
}

function inferLinkMeta(item, linkLookups) {
    const normalized = { ...createEmptySidePromoItem(), ...(item ?? {}) };
    const url = typeof normalized.url === 'string' ? normalized.url : '';
    const legacyPostCategorySlug = url.match(/^\/tin-tuc\?category=([^&]+)/)?.[1] ?? '';

    if (normalized.link_type && normalized.link_type !== 'custom' && normalized.link_value) {
        return {
            ...normalized,
            link_value: String(normalized.link_value),
            custom_url: normalized.custom_url ?? '',
        };
    }

    for (const [linkType, lookup] of Object.entries(linkLookups)) {
        const matched = Array.from(lookup.values()).find((option) => {
            if (option.url === url) {
                return true;
            }

            return linkType === 'post-category' && legacyPostCategorySlug && option.url === `/c/${legacyPostCategorySlug}`;
        });

        if (matched) {
            return {
                ...normalized,
                link_type: linkType,
                link_value: String(matched.value),
                custom_url: '',
            };
        }
    }

    return {
        ...normalized,
        link_type: 'custom',
        link_value: null,
        custom_url: url === '#' ? '' : url,
    };
}

function normalizeItemsForForm(items, linkLookups) {
    if (!Array.isArray(items) || items.length === 0) {
        return [];
    }

    return items.map((item, index) => ({
        ...inferLinkMeta(item, linkLookups),
        sort_order: Number.isFinite(Number(item?.sort_order)) ? Number(item.sort_order) : index,
        __itemKey: item?.__itemKey ?? createSidePromoItemKey(),
    }));
}

function resolveItemUrl(item, linkLookups) {
    const linkType = item?.link_type ?? 'custom';

    if (linkType === 'custom') {
        return (item?.custom_url ?? '').trim() || '#';
    }

    const linkValue = item?.link_value;
    const matched = linkValue ? linkLookups[linkType]?.get(String(linkValue)) : null;

    return matched?.url ?? (item?.url ?? '#');
}

function normalizeItemsForSubmit(items, linkLookups) {
    return (items ?? [])
        .filter((item) => item?.title && item?.image)
        .map((item, index) => {
            const { __itemKey, ...rest } = item;

            return {
                ...rest,
                sort_order: index,
                badge: (item?.badge ?? '').trim(),
                cta_label: (item?.cta_label ?? '').trim(),
                link_value: item?.link_value ? String(item.link_value) : null,
                custom_url: item?.link_type === 'custom' ? (item?.custom_url ?? '').trim() : '',
                url: resolveItemUrl(item, linkLookups),
            };
        });
}

function normalizeSearchKeyword(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
        .trim();
}

function buildUrlSelectOptions(items = []) {
    return items.map((item) => ({
        label: item.label || item.url,
        value: item.value,
        title: item.label || item.url,
        url: item.url,
        searchText: normalizeSearchKeyword(`${item.label ?? ''} ${item.url ?? ''}`),
    }));
}

function removeItemAtIndex(items, targetIndex) {
    return (items ?? []).filter((_, index) => index !== targetIndex);
}

function findItemIndexByKey(items, itemKey) {
    return (items ?? []).findIndex((item) => item?.__itemKey === itemKey);
}

function insertItemAtIndex(items, item, index = null) {
    const nextItems = [...(items ?? [])];
    const targetIndex = index ?? nextItems.length;

    nextItems.splice(targetIndex, 0, item);

    return nextItems;
}

function linkTypeLabel(linkType) {
    return LINK_TYPE_OPTIONS.find((item) => item.value === linkType)?.label ?? 'Liên kết khác';
}

function renderPreviewUrl(item, linkLookups) {
    return resolveItemUrl(item, linkLookups);
}

export default function CmsSidePromoFormModal({ open, canManage, editingGroup, locationOptions = [], linkOptions = {}, mediaOptions = [], callAdminApi, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [itemForm] = Form.useForm();
    const [items, setItems] = useState([]);
    const [itemEditorOpen, setItemEditorOpen] = useState(false);
    const [editingIndex, setEditingIndex] = useState(null);
    const [itemLinkType, setItemLinkType] = useState('custom');
    const [manualExpandedKeys, setManualExpandedKeys] = useState([]);
    const [draggingKey, setDraggingKey] = useState(null);
    const [dragOverState, setDragOverState] = useState({ key: null, mode: null });
    const linkLookups = useMemo(() => buildLinkLookups(linkOptions), [linkOptions]);

    useEffect(() => {
        form.setFieldsValue({
            ...editingGroup,
            location: editingGroup?.location ?? locationOptions[0]?.value ?? 'home-hero-side-promos',
        });
        const normalizedItems = normalizeItemsForForm(editingGroup?.items, linkLookups);

        setItems(normalizedItems);
        setManualExpandedKeys(normalizedItems.map((item) => item.__itemKey));
        setDragOverState({ key: null, mode: null });
    }, [editingGroup, form, linkLookups, locationOptions]);

    const treeData = useMemo(() => (items ?? []).map((item, index) => ({
        key: item.__itemKey,
        item,
        index,
        children: [],
    })), [items]);

    const itemUrlOptions = itemLinkType === 'page'
        ? buildUrlSelectOptions(linkOptions.pages ?? [])
        : itemLinkType === 'product-category'
            ? buildUrlSelectOptions(linkOptions.productCategories ?? [])
            : itemLinkType === 'post-category'
                ? buildUrlSelectOptions(linkOptions.postCategories ?? [])
                : [];
    const itemImage = Form.useWatch('image', itemForm) ?? '';
    const itemBadge = Form.useWatch('badge', itemForm) ?? '';
    const itemTitle = Form.useWatch('title', itemForm) ?? '';
    const itemSubtitle = Form.useWatch('subtitle', itemForm) ?? '';
    const itemCtaLabel = Form.useWatch('cta_label', itemForm) ?? '';
    const itemPreviewData = itemForm.getFieldsValue(true);

    const openItemEditor = (index = null) => {
        const targetItem = index === null ? createEmptySidePromoItem() : items[index] ?? createEmptySidePromoItem();

        itemForm.setFieldsValue(targetItem);
        setItemLinkType(targetItem?.link_type ?? 'custom');
        setEditingIndex(index);
        setItemEditorOpen(true);
    };

    const closeItemEditor = () => {
        itemForm.resetFields();
        setItemLinkType('custom');
        setEditingIndex(null);
        setItemEditorOpen(false);
    };

    const handleSaveItem = async () => {
        const values = await itemForm.validateFields();
        const nextItem = {
            ...createEmptySidePromoItem(),
            ...values,
            __itemKey: editingIndex === null ? createSidePromoItemKey() : (items[editingIndex]?.__itemKey ?? createSidePromoItemKey()),
        };

        setItems((current) => {
            if (editingIndex === null) {
                return [...current, nextItem];
            }

            return current.map((item, index) => (index === editingIndex ? nextItem : item));
        });

        closeItemEditor();
    };

    const handleTreeDrop = (info) => {
        const dragKey = String(info.dragNode?.key ?? '');
        const dropKey = String(info.node?.key ?? '');

        setDraggingKey(null);
        setDragOverState({ key: null, mode: null });

        if (!dragKey || !dropKey || dragKey === dropKey) {
            return;
        }

        setItems((current) => {
            const dragIndex = findItemIndexByKey(current, dragKey);
            const dropIndex = findItemIndexByKey(current, dropKey);

            if (dragIndex < 0 || dropIndex < 0) {
                return current;
            }

            const draggedItem = current[dragIndex];
            const nextItems = removeItemAtIndex(current, dragIndex);
            const nextDropIndex = findItemIndexByKey(nextItems, dropKey);
            const rawDropPosition = info.dropPosition - Number(String(info.node?.pos ?? '0').split('-').at(-1) ?? 0);

            return insertItemAtIndex(nextItems, draggedItem, nextDropIndex + (rawDropPosition > 0 ? 1 : 0));
        });
    };

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            website_key: editingGroup?.website_key ?? '',
            owner_key: editingGroup?.owner_key ?? '',
            tenant_key: editingGroup?.tenant_key ?? '',
            items: normalizeItemsForSubmit(items, linkLookups),
        });

        form.resetFields();
        setItems([]);
    };

    const handleCancel = () => {
        form.resetFields();
        setItems([]);
        setDraggingKey(null);
        setDragOverState({ key: null, mode: null });
        setItemEditorOpen(false);
        onCancel?.();
    };

    return (
        <>
            <Drawer
                title={editingGroup?.id ? 'Side promo block' : 'Tạo side promo block'}
                open={open}
                onClose={handleCancel}
                maskClosable={false}
                width={980}
                destroyOnHidden
                extra={(
                    <Space>
                        <Button onClick={handleCancel}>Đóng</Button>
                        <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Lưu side promo</Button>
                    </Space>
                )}
            >
                <Form form={form} layout="vertical">
                    <Row gutter={16}>
                        <Col xs={24} md={14}>
                            <Form.Item name="name" label="Tên block" rules={[{ required: true, message: 'Nhập tên block.' }]}>
                                <Input placeholder="TH0001 Hero side promos" />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={10}>
                            <Form.Item name="location" label="Vị trí" rules={[{ required: true, message: 'Chọn vị trí hiển thị.' }]}>
                                <Select options={locationOptions} />
                            </Form.Item>
                        </Col>
                    </Row>
                </Form>

                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <div>
                            <Title level={5} style={{ margin: 0 }}>Danh sách promo</Title>
                            <Paragraph type="secondary" style={{ marginBottom: 0 }}>
                                Mỗi item hiển thị nhanh ảnh, badge, CTA, mô tả ngắn và liên kết đích.
                            </Paragraph>
                        </div>
                        <Button type="dashed" icon={<PlusOutlined />} disabled={!canManage} onClick={() => openItemEditor()}>
                            Thêm promo
                        </Button>
                    </Space>

                    {items.length ? (
                        <Tree
                            className="cms-menu-tree"
                            blockNode
                            expandedKeys={manualExpandedKeys}
                            draggable={canManage}
                            allowDrop={({ dropPosition }) => canManage && dropPosition !== 0}
                            onExpand={setManualExpandedKeys}
                            onDragStart={({ node }) => setDraggingKey(String(node?.key ?? ''))}
                            onDragEnter={(info) => {
                                const nextKey = String(info.node?.key ?? '');

                                setDragOverState({
                                    key: nextKey || null,
                                    mode: info.dropPosition < 0 ? 'before' : 'after',
                                });
                            }}
                            onDragEnd={() => {
                                setDraggingKey(null);
                                setDragOverState({ key: null, mode: null });
                            }}
                            onDrop={handleTreeDrop}
                            treeData={treeData}
                            titleRender={(node) => {
                                const item = node.item;
                                const index = node.index ?? 0;
                                const isDragging = draggingKey === node.key;
                                const isDragOver = dragOverState.key === node.key;
                                const dropMode = isDragOver ? dragOverState.mode : null;

                                return (
                                    <div className={`cms-menu-tree-node${isDragging ? ' is-dragging' : ''}${isDragOver ? ' is-drag-over' : ''}${dropMode ? ` drop-mode-${dropMode}` : ''}`}>
                                        <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 }}>
                                            <Space style={{ minWidth: 0, flex: 1 }} size={12} align="start">
                                                <HolderOutlined style={{ color: '#7c948d', marginTop: 4 }} />
                                                {item.image ? (
                                                    <img
                                                        src={item.image}
                                                        alt={item.title}
                                                        style={{ width: 64, height: 64, objectFit: 'cover', borderRadius: 10, border: '1px solid #dbe3ea', flexShrink: 0 }}
                                                    />
                                                ) : (
                                                    <div style={{ width: 64, height: 64, borderRadius: 10, border: '1px dashed #d9d9d9', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9ca3af', flexShrink: 0 }}>
                                                        No img
                                                    </div>
                                                )}
                                                <Space direction="vertical" size={4} style={{ minWidth: 0, flex: 1 }}>
                                                    <Space wrap>
                                                        <Text strong>{item.title || 'Chưa có tiêu đề'}</Text>
                                                        <Tag color="green">Promo {index + 1}</Tag>
                                                        <Text type="secondary" style={{ fontSize: 12 }}>{linkTypeLabel(item.link_type)}</Text>
                                                        {dropMode === 'before' ? <Tag color="gold">Chèn phía trên</Tag> : null}
                                                        {dropMode === 'after' ? <Tag color="purple">Chèn phía dưới</Tag> : null}
                                                    </Space>
                                                    {(item.badge || item.cta_label) ? (
                                                        <Space wrap size={[6, 6]}>
                                                            {item.badge ? <Tag color="gold">Badge: {item.badge}</Tag> : null}
                                                            {item.cta_label ? <Tag color="blue">CTA: {item.cta_label}</Tag> : null}
                                                        </Space>
                                                    ) : null}
                                                    <Text type="secondary">{item.subtitle || 'Chưa có mô tả ngắn'}</Text>
                                                    <Text type="secondary" style={{ fontSize: 12 }}>{resolveItemUrl(item, linkLookups)}</Text>
                                                </Space>
                                            </Space>

                                            <Space size={4} wrap>
                                                <Button type="text" icon={<EditOutlined />} style={{ color: '#2563eb' }} onClick={() => openItemEditor(index)}>Sửa</Button>
                                                <Popconfirm title="Xóa promo này?" onConfirm={() => setItems((current) => removeItemAtIndex(current, index))}>
                                                    <Button type="text" icon={<DeleteOutlined />} style={{ color: '#3f3f46' }}>Xóa</Button>
                                                </Popconfirm>
                                            </Space>
                                        </div>
                                    </div>
                                );
                            }}
                        />
                    ) : <Empty description="Chưa có promo nào" />}
                </Space>
            </Drawer>

            <Modal
                title={editingIndex === null ? 'Thêm promo' : 'Chỉnh promo'}
                open={itemEditorOpen}
                onCancel={closeItemEditor}
                onOk={handleSaveItem}
                okText="Lưu promo"
                cancelText="Hủy"
                width={760}
                destroyOnHidden
            >
                <Form form={itemForm} layout="vertical">
                    <Row gutter={16}>
                        <Col xs={24} md={8}>
                            <Form.Item name="badge" label="Badge nhỏ">
                                <Input placeholder="Ưu đãi mới" maxLength={80} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={14}>
                            <Form.Item name="title" label="Tiêu đề" rules={[{ required: true, message: 'Nhập tiêu đề promo.' }]}>
                                <Input placeholder="Voucher cuối tuần" />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={2}></Col>
                    </Row>

                    <Row gutter={16}>
                        <Col xs={24} md={14}>
                            <Form.Item name="cta_label" label="CTA nhỏ">
                                <Input placeholder="Xem deal" maxLength={80} />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={10}>
                            <Form.Item name="target" label="Kiểu mở link">
                                <Select options={TARGET_OPTIONS} />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Form.Item name="subtitle" label="Mô tả ngắn">
                        <Input placeholder="Ăn uống nhóm nhỏ giá tốt" />
                    </Form.Item>

                    <Form.Item name="image" hidden rules={[{ required: true, message: 'Chọn ảnh promo.' }]}>
                        <Input />
                    </Form.Item>

                    <Row gutter={16}>
                        <Col xs={24} md={14}>
                            <Form.Item label="Ảnh promo" required>
                                <SingleMediaPicker
                                    open={itemEditorOpen}
                                    value={itemImage}
                                    onChange={(nextValue) => itemForm.setFieldValue('image', nextValue)}
                                    canManage={canManage}
                                    callAdminApi={callAdminApi}
                                    mediaOptions={mediaOptions}
                                    recordTitle={itemTitle || 'Side promo image'}
                                    previewTitle="Ảnh promo"
                                    uploadButtonLabel="Upload ảnh promo"
                                    uploadHint="Ảnh upload xong sẽ tự được gán cho promo hiện tại."
                                    libraryModalTitle="Chọn ảnh promo từ thư viện"
                                    urlPlaceholder="https://example.com/side-promo.jpg"
                                    uploadSuccessMessage="Đã upload và gán ảnh promo."
                                    urlSuccessMessage="Đã lưu URL vào thư viện media và gán cho promo."
                                    uploadErrorMessage="Upload ảnh promo không thành công."
                                    urlErrorMessage="Không thể lưu ảnh promo từ URL."
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={10}>
                            <div style={{ border: '1px solid #e5e7eb', borderRadius: 16, overflow: 'hidden', background: '#0f172a', minHeight: 280 }}>
                                <div style={{ position: 'relative', minHeight: 280 }}>
                                    {itemImage ? (
                                        <img
                                            src={itemImage}
                                            alt={itemTitle || 'Promo preview'}
                                            style={{ width: '100%', height: 280, objectFit: 'cover' }}
                                        />
                                    ) : (
                                        <div style={{ width: '100%', height: 280, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'rgba(255,255,255,0.7)', background: 'linear-gradient(180deg, #1f2937 0%, #0f172a 100%)' }}>
                                            Chưa có ảnh preview
                                        </div>
                                    )}
                                    <div style={{ position: 'absolute', inset: 'auto 0 0 0', padding: 16, background: 'linear-gradient(180deg, rgba(15,23,42,0) 0%, rgba(15,23,42,0.92) 100%)', color: '#fff' }}>
                                        {itemBadge ? (
                                            <Text style={{ display: 'inline-flex', marginBottom: 10, padding: '5px 10px', borderRadius: 999, background: 'rgba(255,255,255,0.16)', color: '#fff', fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                                                {itemBadge}
                                            </Text>
                                        ) : null}
                                        <Text strong style={{ color: '#fff', display: 'block', fontSize: 20, lineHeight: 1.2 }}>
                                            {itemTitle || 'Tiêu đề promo preview'}
                                        </Text>
                                        <Text style={{ color: 'rgba(255,255,255,0.78)', display: 'block', marginTop: 8 }}>
                                            {itemSubtitle || 'Mô tả ngắn preview sẽ hiện ở đây'}
                                        </Text>
                                        {itemCtaLabel ? (
                                            <Text style={{ display: 'inline-flex', marginTop: 12, padding: '7px 12px', borderRadius: 999, background: '#ffffff', color: '#111827', fontSize: 12, fontWeight: 700 }}>
                                                {itemCtaLabel}
                                            </Text>
                                        ) : null}
                                        <Text style={{ color: 'rgba(255,255,255,0.62)', display: 'block', marginTop: 10, fontSize: 12 }}>
                                            {renderPreviewUrl(itemPreviewData, linkLookups)}
                                        </Text>
                                    </div>
                                </div>
                            </div>
                        </Col>
                    </Row>

                    <Row gutter={16}>
                        <Col xs={24} md={10}>
                            <Form.Item name="link_type" label="Loại liên kết" rules={[{ required: true, message: 'Chọn loại liên kết.' }]}>
                                <Select
                                    options={LINK_TYPE_OPTIONS}
                                    onChange={(value) => {
                                        setItemLinkType(value);
                                        itemForm.setFieldsValue({ link_value: null, custom_url: '' });
                                    }}
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={14}>
                            {itemLinkType === 'custom' ? (
                                <Form.Item name="custom_url" label="Liên kết đích">
                                    <Input placeholder="/vi#featured" />
                                </Form.Item>
                            ) : (
                                <Form.Item name="link_value" label="Chọn đích liên kết">
                                    <Select
                                        showSearch
                                        options={itemUrlOptions}
                                        optionFilterProp="searchText"
                                        filterOption={(input, option) => (option?.searchText ?? '').includes(normalizeSearchKeyword(input))}
                                    />
                                </Form.Item>
                            )}
                        </Col>
                    </Row>
                </Form>
            </Modal>
        </>
    );
}

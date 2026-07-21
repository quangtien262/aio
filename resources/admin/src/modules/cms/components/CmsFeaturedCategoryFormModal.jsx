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

const LINK_TYPE_OPTIONS = [
    { label: 'Theo page', value: 'page' },
    { label: 'Theo danh mục sản phẩm', value: 'product-category' },
    { label: 'Theo danh mục tin tức', value: 'post-category' },
    { label: 'Liên kết khác', value: 'custom' },
];

const { Paragraph, Text, Title } = Typography;

let featuredCategoryItemKeySeed = 0;

function createFeaturedCategoryItemKey() {
    featuredCategoryItemKeySeed += 1;

    return `cms-featured-category-item-${featuredCategoryItemKeySeed}`;
}

function createEmptyFeaturedCategoryItem() {
    return {
        __menuKey: createFeaturedCategoryItemKey(),
        label: '',
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
    const normalized = { ...createEmptyFeaturedCategoryItem(), ...(item ?? {}) };
    const url = typeof normalized.url === 'string' ? normalized.url : '';

    if (normalized.link_type && normalized.link_type !== 'custom' && normalized.link_value) {
        return {
            ...normalized,
            link_value: String(normalized.link_value),
            custom_url: normalized.custom_url ?? '',
        };
    }

    for (const [linkType, lookup] of Object.entries(linkLookups)) {
        const matched = Array.from(lookup.values()).find((option) => option.url === url);

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

function normalizeFeaturedCategoryItemsForForm(items, linkLookups) {
    if (!Array.isArray(items) || items.length === 0) {
        return [];
    }

    return items.map((item) => ({
        ...inferLinkMeta(item, linkLookups),
        __menuKey: item?.__menuKey ?? createFeaturedCategoryItemKey(),
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

function normalizeFeaturedCategoryItemsForSubmit(items, linkLookups) {
    return (items ?? [])
        .filter((item) => item?.label)
        .map((item) => {
            const { __menuKey, ...rest } = item;

            return {
                ...rest,
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

function updateItemAtIndex(items, targetIndex, updater) {
    return (items ?? []).map((item, index) => (index === targetIndex ? updater(item) : item));
}

function removeItemAtIndex(items, targetIndex) {
    return (items ?? []).filter((_, index) => index !== targetIndex);
}

function findItemIndexByKey(items, itemKey) {
    return (items ?? []).findIndex((item) => item?.__menuKey === itemKey);
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

export default function CmsFeaturedCategoryFormModal({ open, canManage, editingGroup, locationOptions = [], linkOptions = {}, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [itemForm] = Form.useForm();
    const [groupItems, setGroupItems] = useState([createEmptyFeaturedCategoryItem()]);
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
            location: editingGroup?.location ?? locationOptions[0]?.value ?? 'home-featured-categories',
        });

        const normalizedItems = normalizeFeaturedCategoryItemsForForm(editingGroup?.items, linkLookups);

        setGroupItems(normalizedItems);
        setManualExpandedKeys(normalizedItems.map((item) => item.__menuKey));
        setDragOverState({ key: null, mode: null });
    }, [editingGroup, form, linkLookups, locationOptions]);

    const treeData = useMemo(() => (groupItems ?? []).map((item, index) => ({
        key: item.__menuKey,
        item,
        index,
        children: [],
    })), [groupItems]);

    const itemUrlOptions = itemLinkType === 'page'
        ? buildUrlSelectOptions(linkOptions.pages ?? [])
        : itemLinkType === 'product-category'
            ? buildUrlSelectOptions(linkOptions.productCategories ?? [])
            : itemLinkType === 'post-category'
                ? buildUrlSelectOptions(linkOptions.postCategories ?? [])
                : [];

    const itemPreviewUrl = resolveItemUrl(itemForm.getFieldsValue(true), linkLookups);

    const openItemEditor = (index = null) => {
        const targetItem = index === null ? createEmptyFeaturedCategoryItem() : groupItems[index] ?? createEmptyFeaturedCategoryItem();

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
            ...createEmptyFeaturedCategoryItem(),
            ...values,
            __menuKey: editingIndex === null ? createFeaturedCategoryItemKey() : (groupItems[editingIndex]?.__menuKey ?? createFeaturedCategoryItemKey()),
        };

        if (editingIndex === null) {
            setGroupItems((current) => [...current, nextItem]);
        } else {
            setGroupItems((current) => updateItemAtIndex(current, editingIndex, () => nextItem));
        }

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

        setGroupItems((current) => {
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
            items: normalizeFeaturedCategoryItemsForSubmit(groupItems, linkLookups),
        });

        form.resetFields();
        setGroupItems([]);
    };

    const handleCancel = () => {
        form.resetFields();
        setGroupItems([]);
        setDraggingKey(null);
        setDragOverState({ key: null, mode: null });
        setItemEditorOpen(false);
        onCancel?.();
    };

    return (
        <>
            <Drawer
                title={editingGroup?.id ? 'Danh mục nổi bật' : 'Tạo danh mục nổi bật'}
                open={open}
                onClose={handleCancel}
                maskClosable={false}
                width={960}
                destroyOnHidden
                extra={(
                    <Space>
                        <Button onClick={handleCancel}>Đóng</Button>
                        <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Lưu danh mục nổi bật</Button>
                    </Space>
                )}
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Form form={form} layout="vertical" initialValues={editingGroup}>
                        <Card>
                            <Row gutter={16}>
                                <Col span={12}>
                                    <Form.Item name="name" label="Tên nhóm" rules={[{ required: true, message: 'Nhập tên nhóm' }]}>
                                        <Input placeholder="Home featured categories" />
                                    </Form.Item>
                                </Col>
                                <Col span={12}>
                                    <Form.Item name="location" label="Vị trí" rules={[{ required: true, message: 'Chọn vị trí hiển thị' }]}>
                                        <Select options={locationOptions} />
                                    </Form.Item>
                                </Col>
                            </Row>
                        </Card>
                    </Form>

                    <Card
                        title="Danh sách danh mục nổi bật"
                        extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => openItemEditor()}>Thêm mới</Button>}
                    >
                        <Space direction="vertical" size={16} style={{ width: '100%' }}>
                            <Row gutter={[12, 12]}>
                                <Col xs={24} md={12}>
                                    <Card size="small">
                                        <Text type="secondary">Tổng item</Text>
                                        <Title level={4} style={{ margin: '6px 0 0' }}>{groupItems.length}</Title>
                                    </Card>
                                </Col>
                                <Col xs={24} md={12}>
                                    <Card size="small">
                                        <Text type="secondary">Loại hiển thị</Text>
                                        <Title level={4} style={{ margin: '6px 0 0' }}>Một cấp</Title>
                                    </Card>
                                </Col>
                            </Row>

                            {groupItems.length ? (
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
                                            <div
                                                className={`cms-menu-tree-node${isDragging ? ' is-dragging' : ''}${isDragOver ? ' is-drag-over' : ''}${dropMode ? ` drop-mode-${dropMode}` : ''}`}
                                            >
                                                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 }}>
                                                    <Space direction="vertical" size={4} style={{ minWidth: 0, flex: 1 }}>
                                                        <Space wrap>
                                                            <Space size={6}>
                                                                <HolderOutlined style={{ color: '#7c948d' }} />
                                                                <Text strong>{item.label || 'Chưa có label'}</Text>
                                                            </Space>
                                                            <Tag color="green">Item {index + 1}</Tag>
                                                            <Text type="secondary" style={{ fontSize: 12 }}>
                                                                {linkTypeLabel(item.link_type)}
                                                            </Text>
                                                            {dropMode === 'before' ? <Tag color="gold">Chèn phía trên</Tag> : null}
                                                            {dropMode === 'after' ? <Tag color="purple">Chèn phía dưới</Tag> : null}
                                                        </Space>
                                                    </Space>

                                                    <Space size={4} wrap>
                                                        <Button type="text" icon={<EditOutlined />} style={{ color: '#2563eb' }} onClick={() => openItemEditor(index)}>Sửa</Button>
                                                        <Popconfirm title="Xóa item này?" onConfirm={() => setGroupItems((current) => removeItemAtIndex(current, index))}>
                                                            <Button type="text" icon={<DeleteOutlined />} style={{ color: '#3f3f46' }}>Xóa</Button>
                                                        </Popconfirm>
                                                    </Space>
                                                </div>
                                            </div>
                                        );
                                    }}
                                />
                            ) : <Empty description="Chưa có danh mục nổi bật nào." />}
                        </Space>
                    </Card>
                </Space>
            </Drawer>

            <Modal
                title={editingIndex === null ? 'Thêm danh mục nổi bật' : 'Sửa danh mục nổi bật'}
                open={itemEditorOpen}
                onCancel={closeItemEditor}
                onOk={handleSaveItem}
                width={760}
                destroyOnHidden
            >
                <Form form={itemForm} layout="vertical" initialValues={createEmptyFeaturedCategoryItem()}>
                    <Row gutter={16}>
                        <Col span={10}>
                            <Form.Item name="label" label="Label" rules={[{ required: true, message: 'Nhập label' }]}>
                                <Input placeholder="Buffet cuối tuần" />
                            </Form.Item>
                        </Col>
                        <Col span={8}>
                            <Form.Item name="link_type" label="Loại link" rules={[{ required: true, message: 'Chọn loại link' }]}>
                                <Select
                                    options={LINK_TYPE_OPTIONS}
                                    onChange={(value) => {
                                        setItemLinkType(value);
                                        itemForm.setFieldValue('link_value', null);
                                        itemForm.setFieldValue('custom_url', '');
                                    }}
                                />
                            </Form.Item>
                        </Col>
                        <Col span={6}>
                            <Form.Item name="target" label="Target">
                                <Select options={[{ label: 'Self', value: '_self' }, { label: 'Blank', value: '_blank' }]} />
                            </Form.Item>
                        </Col>
                    </Row>

                    {itemLinkType === 'custom' ? (
                        <Form.Item name="custom_url" label="URL" rules={[{ required: true, message: 'Nhập URL' }]}>
                            <Input placeholder="/danh-muc/am-thuc hoặc https://domain.com" />
                        </Form.Item>
                    ) : (
                        <Form.Item name="link_value" label="URL" rules={[{ required: true, message: 'Chọn URL' }]}>
                            <Select
                                showSearch
                                options={itemUrlOptions}
                                optionRender={(option) => (
                                    <Space direction="vertical" size={0} style={{ width: '100%', lineHeight: 1.35 }}>
                                        <Text strong>{option.data.title}</Text>
                                        <Text type="secondary">{option.data.url}</Text>
                                    </Space>
                                )}
                                filterOption={(input, option) => {
                                    const normalizedInput = normalizeSearchKeyword(input);

                                    if (!normalizedInput) {
                                        return true;
                                    }

                                    return String(option?.searchText ?? '').includes(normalizedInput);
                                }}
                                placeholder={`Chọn URL ${linkTypeLabel(itemLinkType).toLowerCase()}`}
                            />
                        </Form.Item>
                    )}

                    <Form.Item label="URL thực tế">
                        <Paragraph style={{ marginBottom: 0 }}>{itemPreviewUrl}</Paragraph>
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
}

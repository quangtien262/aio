import {
    DeleteOutlined,
    EditOutlined,
    HolderOutlined,
    PlusOutlined,
    SettingOutlined,
} from '@ant-design/icons';
import { useEffect, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Checkbox from 'antd/es/checkbox';
import Col from 'antd/es/col';
import Divider from 'antd/es/divider';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import List from 'antd/es/list';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Tree from 'antd/es/tree';
import Typography from 'antd/es/typography';

export const emptyCmsMenuForm = {
    id: null,
    name: '',
    location: 'primary',
    items: [{ label: '', url: '', target: '_self', link_type: 'custom', link_value: null, custom_url: '', children: [] }],
};

const emptyLocationForm = { label: '', value: '' };
const { Paragraph, Text, Title } = Typography;

const LINK_TYPE_OPTIONS = [
    { label: 'Theo page', value: 'page' },
    { label: 'Theo danh mục sản phẩm', value: 'product-category' },
    { label: 'Theo danh mục tin tức', value: 'post-category' },
    { label: 'Liên kết khác', value: 'custom' },
];

let menuItemKeySeed = 0;

function createMenuItemKey() {
    menuItemKeySeed += 1;

    return `cms-menu-item-${menuItemKeySeed}`;
}

function createEmptyMenuItem() {
    return {
        __menuKey: createMenuItemKey(),
        label: '',
        url: '',
        target: '_self',
        link_type: 'custom',
        link_value: null,
        custom_url: '',
        children: [],
    };
}

function hasMeaningfulMenuItem(item) {
    if (!item) {
        return false;
    }

    return Boolean(item.label || item.url || item.custom_url || item.link_value || (item.children ?? []).some((child) => hasMeaningfulMenuItem(child)));
}

function buildLinkLookups(linkOptions = {}) {
    return {
        page: new Map((linkOptions.pages ?? []).map((item) => [String(item.value), item])),
        'product-category': new Map((linkOptions.productCategories ?? []).map((item) => [String(item.value), item])),
        'post-category': new Map((linkOptions.postCategories ?? []).map((item) => [String(item.value), item])),
    };
}

function inferLinkMeta(item, linkLookups) {
    const normalized = { ...createEmptyMenuItem(), ...(item ?? {}) };
    const url = typeof normalized.url === 'string' ? normalized.url : '';

    if (normalized.link_type && normalized.link_type !== 'custom' && normalized.link_value) {
        return {
            ...normalized,
            link_value: String(normalized.link_value),
            custom_url: normalized.custom_url ?? '',
            children: Array.isArray(normalized.children) ? normalized.children : [],
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
                children: Array.isArray(normalized.children) ? normalized.children : [],
            };
        }
    }

    return {
        ...normalized,
        link_type: 'custom',
        link_value: null,
        custom_url: url === '#' ? '' : url,
        children: Array.isArray(normalized.children) ? normalized.children : [],
    };
}

function normalizeMenuItemForForm(item, linkLookups) {
    const normalized = inferLinkMeta(item, linkLookups);

    return {
        ...normalized,
        __menuKey: normalized.__menuKey ?? createMenuItemKey(),
        children: (normalized.children ?? []).map((child) => normalizeMenuItemForForm(child, linkLookups)),
    };
}

function normalizeMenuItemsForForm(items, linkLookups) {
    if (!Array.isArray(items) || items.length === 0) {
        return [];
    }

    const normalizedItems = items.map((item) => normalizeMenuItemForForm(item, linkLookups));

    return normalizedItems.some((item) => hasMeaningfulMenuItem(item)) ? normalizedItems : [];
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

function normalizeMenuItemsForSubmit(items, linkLookups) {
    return (items ?? [])
        .filter((item) => item?.label)
        .map((item) => {
            const { __menuKey, ...rest } = item;

            return {
                ...rest,
                link_value: item?.link_value ? String(item.link_value) : null,
                custom_url: item?.link_type === 'custom' ? (item?.custom_url ?? '').trim() : '',
                url: resolveItemUrl(item, linkLookups),
                children: normalizeMenuItemsForSubmit(item?.children ?? [], linkLookups),
            };
        });
}

function linkTypeLabel(linkType) {
    return LINK_TYPE_OPTIONS.find((item) => item.value === linkType)?.label ?? 'Liên kết khác';
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

function countMenuItems(items = []) {
    return (items ?? []).reduce((total, item) => total + 1 + countMenuItems(item?.children ?? []), 0);
}

function updateItemAtPath(items, path, updater) {
    if (!path.length) {
        return items;
    }

    const [currentIndex, childIndex] = path;

    return items.map((item, index) => {
        if (index !== currentIndex) {
            return item;
        }

        if (childIndex === undefined || childIndex === null) {
            return updater(item);
        }

        return {
            ...item,
            children: (item.children ?? []).map((child, nestedIndex) => (nestedIndex === childIndex ? updater(child) : child)),
        };
    });
}

function removeItemAtPath(items, path) {
    if (!path.length) {
        return items;
    }

    const [currentIndex, childIndex] = path;

    if (childIndex === undefined || childIndex === null) {
        return items.filter((_, index) => index !== currentIndex);
    }

    return items.map((item, index) => {
        if (index !== currentIndex) {
            return item;
        }

        return {
            ...item,
            children: (item.children ?? []).filter((_, nestedIndex) => nestedIndex !== childIndex),
        };
    });
}

function removeItemsByKeys(items, selectedKeys) {
    const selectedSet = new Set(selectedKeys ?? []);

    return (items ?? [])
        .filter((item) => !selectedSet.has(item?.__menuKey))
        .map((item) => ({
            ...item,
            children: (item.children ?? []).filter((child) => !selectedSet.has(child?.__menuKey)),
        }));
}

function collectMenuItemKeys(items = []) {
    return (items ?? []).flatMap((item) => [
        item.__menuKey,
        ...collectMenuItemKeys(item.children ?? []),
    ]).filter(Boolean);
}

function appendChildItem(items, parentIndex, nextChild) {
    return items.map((item, index) => {
        if (index !== parentIndex) {
            return item;
        }

        return {
            ...item,
            children: [...(item.children ?? []), nextChild],
        };
    });
}

function getItemAtPath(items, path) {
    const [currentIndex, childIndex] = path;
    const currentItem = items[currentIndex] ?? null;

    if (!currentItem) {
        return null;
    }

    if (childIndex === undefined || childIndex === null) {
        return currentItem;
    }

    return currentItem.children?.[childIndex] ?? null;
}

function findItemPathByKey(items, itemKey) {
    for (let index = 0; index < (items ?? []).length; index += 1) {
        const item = items[index];

        if (item?.__menuKey === itemKey) {
            return [index];
        }

        for (let childIndex = 0; childIndex < (item?.children ?? []).length; childIndex += 1) {
            if (item.children[childIndex]?.__menuKey === itemKey) {
                return [index, childIndex];
            }
        }
    }

    return null;
}

function insertItemAtPath(items, path, item, index = null) {
    if (!path?.length) {
        const nextItems = [...(items ?? [])];
        const targetIndex = index ?? nextItems.length;

        nextItems.splice(targetIndex, 0, item);

        return nextItems;
    }

    const [parentIndex] = path;

    return (items ?? []).map((entry, indexOfEntry) => {
        if (indexOfEntry !== parentIndex) {
            return entry;
        }

        const nextChildren = [...(entry.children ?? [])];
        const targetIndex = index ?? nextChildren.length;

        nextChildren.splice(targetIndex, 0, item);

        return {
            ...entry,
            children: nextChildren,
        };
    });
}

function buildMenuTreeData(items, parentPath = []) {
    return (items ?? []).map((item, index) => {
        const path = [...parentPath, index];

        return {
            key: item.__menuKey,
            item,
            path,
            isChild: path.length > 1,
            children: buildMenuTreeData(item.children ?? [], path),
        };
    });
}

function collectExpandableKeys(items) {
    return (items ?? []).flatMap((item) => {
        const nestedKeys = collectExpandableKeys(item.children ?? []);

        return (item.children ?? []).length ? [item.__menuKey, ...nestedKeys] : nestedKeys;
    });
}

function buildEditorTitle(mode, isChild) {
    if (mode === 'create') {
        return isChild ? 'Thêm menu cấp 2' : 'Thêm menu';
    }

    return isChild ? 'Sửa menu cấp 2' : 'Sửa menu';
}

export default function CmsMenuFormModal({ open, canManage, editingMenu, locationOptions = [], linkOptions = {}, callAdminApi, runAdminAction, onLocationsChanged, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [itemForm] = Form.useForm();
    const [locationModalOpen, setLocationModalOpen] = useState(false);
    const [locationForm] = Form.useForm();
    const [editingLocation, setEditingLocation] = useState(null);
    const [locationError, setLocationError] = useState('');
    const [menuItems, setMenuItems] = useState([createEmptyMenuItem()]);
    const [itemEditorOpen, setItemEditorOpen] = useState(false);
    const [itemEditorState, setItemEditorState] = useState({ mode: 'create', path: null, parentIndex: null, isChild: false });
    const [selectedLocation, setSelectedLocation] = useState(editingMenu?.location ?? 'primary');
    const [itemLinkType, setItemLinkType] = useState('custom');
    const [manualExpandedKeys, setManualExpandedKeys] = useState([]);
    const [selectedItemKeys, setSelectedItemKeys] = useState([]);
    const [draggingKey, setDraggingKey] = useState(null);
    const [dragOverState, setDragOverState] = useState({ key: null, mode: null });
    const linkLookups = useMemo(() => buildLinkLookups(linkOptions), [linkOptions]);
    const menuTreeData = useMemo(() => buildMenuTreeData(menuItems), [menuItems]);
    const expandableKeys = useMemo(() => collectExpandableKeys(menuItems), [menuItems]);
    const expandedKeys = useMemo(() => manualExpandedKeys.filter((key) => expandableKeys.includes(key)), [expandableKeys, manualExpandedKeys]);

    useEffect(() => {
        form.setFieldsValue({
            ...editingMenu,
            location: editingMenu?.location ?? 'primary',
        });
        const normalizedItems = normalizeMenuItemsForForm(editingMenu?.items, linkLookups);

        setMenuItems(normalizedItems);
        setManualExpandedKeys(collectExpandableKeys(normalizedItems));
        setSelectedItemKeys([]);
        setDragOverState({ key: null, mode: null });
        setSelectedLocation(editingMenu?.location ?? 'primary');
    }, [editingMenu, form, linkLookups]);

    useEffect(() => {
        setManualExpandedKeys((current) => current.filter((key) => expandableKeys.includes(key)));
    }, [expandableKeys]);

    const handleSubmit = async () => {
        const values = await form.validateFields();

        const didSave = await onSubmit?.({
            ...values,
            items: normalizeMenuItemsForSubmit(menuItems, linkLookups),
        });

        if (didSave === false) {
            return;
        }

        form.resetFields();
        setMenuItems([]);
        setManualExpandedKeys([]);
        setSelectedItemKeys([]);
    };

    const handleCancel = () => {
        form.resetFields();
        setMenuItems([]);
        setManualExpandedKeys([]);
        setSelectedItemKeys([]);
        setDraggingKey(null);
        setDragOverState({ key: null, mode: null });
        setItemEditorOpen(false);
        onCancel?.();
    };

    const openLocationModal = () => {
        setEditingLocation(null);
        setLocationError('');
        locationForm.setFieldsValue(emptyLocationForm);
        setLocationModalOpen(true);
    };

    const startEditLocation = (location) => {
        setEditingLocation(location);
        setLocationError('');
        locationForm.setFieldsValue(location);
        setLocationModalOpen(true);
    };

    const handleSaveLocation = async () => {
        const values = await locationForm.validateFields();
        setLocationError('');

        const method = editingLocation ? 'PUT' : 'POST';
        const endpoint = editingLocation ? `/admin/api/cms/menu-locations/${editingLocation.value}` : '/admin/api/cms/menu-locations';
        const didSave = await runAdminAction(
            () => callAdminApi(endpoint, { method, body: JSON.stringify(values) }),
            editingLocation ? 'Đã cập nhật vị trí menu.' : 'Đã tạo vị trí menu.',
            onLocationsChanged,
        );

        if (didSave) {
            setLocationModalOpen(false);
            setEditingLocation(null);
            locationForm.resetFields();
        }
    };

    const handleDeleteLocation = async (location) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/cms/menu-locations/${location.value}`, { method: 'DELETE' }),
            'Đã xóa vị trí menu.',
            onLocationsChanged,
        );
    };

    const showChildrenEditor = (selectedLocation ?? editingMenu?.location ?? 'primary') === 'product-navigation';

    const openItemEditor = ({ mode, path = null, parentIndex = null, isChild = false }) => {
        const targetItem = path ? getItemAtPath(menuItems, path) : createEmptyMenuItem();

        itemForm.setFieldsValue(targetItem ?? createEmptyMenuItem());
        setItemLinkType(targetItem?.link_type ?? 'custom');
        setItemEditorState({ mode, path, parentIndex, isChild });
        setItemEditorOpen(true);
    };

    const closeItemEditor = () => {
        itemForm.resetFields();
        setItemLinkType('custom');
        setItemEditorOpen(false);
        setItemEditorState({ mode: 'create', path: null, parentIndex: null, isChild: false });
    };

    const handleSaveItem = async () => {
        const values = await itemForm.validateFields();
        const nextItem = {
            ...createEmptyMenuItem(),
            ...values,
            children: itemEditorState.isChild ? [] : (values.children ?? []),
        };

        if (itemEditorState.mode === 'edit' && itemEditorState.path) {
            setMenuItems((current) => updateItemAtPath(current, itemEditorState.path, () => ({
                ...getItemAtPath(current, itemEditorState.path),
                ...nextItem,
                children: itemEditorState.isChild ? [] : (getItemAtPath(current, itemEditorState.path)?.children ?? nextItem.children ?? []),
            })));
        } else if (itemEditorState.isChild && itemEditorState.parentIndex !== null) {
            setMenuItems((current) => appendChildItem(current, itemEditorState.parentIndex, nextItem));
        } else {
            setMenuItems((current) => [...current, nextItem]);
        }

        closeItemEditor();
    };

    const persistMenuItems = async (nextItems, successMessage = 'Đã cập nhật menu.') => {
        if (!editingMenu?.id) {
            return true;
        }

        const values = await form.validateFields();

        return runAdminAction?.(
            () => callAdminApi(`/admin/api/cms/menus/${editingMenu.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    ...values,
                    items: normalizeMenuItemsForSubmit(nextItems, linkLookups),
                }),
            }),
            successMessage,
            onLocationsChanged,
        );
    };

    const handleDeleteItem = async (path) => {
        const currentItems = menuItems;
        const nextItems = removeItemAtPath(currentItems, path);

        setMenuItems(nextItems);
        setSelectedItemKeys((current) => {
            const nextKeys = collectMenuItemKeys(nextItems);

            return current.filter((key) => nextKeys.includes(key));
        });

        const didPersist = await persistMenuItems(nextItems, 'Đã xóa item menu.');

        if (didPersist === false) {
            setMenuItems(currentItems);
        }
    };

    const allMenuItemKeys = useMemo(() => collectMenuItemKeys(menuItems), [menuItems]);
    const selectedMenuItemKeys = useMemo(() => selectedItemKeys.filter((key) => allMenuItemKeys.includes(key)), [allMenuItemKeys, selectedItemKeys]);
    const isAllSelected = allMenuItemKeys.length > 0 && selectedMenuItemKeys.length === allMenuItemKeys.length;
    const isPartiallySelected = selectedMenuItemKeys.length > 0 && selectedMenuItemKeys.length < allMenuItemKeys.length;

    const toggleSelectAllItems = (checked) => {
        setSelectedItemKeys(checked ? allMenuItemKeys : []);
    };

    const toggleSelectItem = (itemKey, checked) => {
        setSelectedItemKeys((current) => {
            const next = new Set(current);

            if (checked) {
                next.add(itemKey);
            } else {
                next.delete(itemKey);
            }

            return Array.from(next);
        });
    };

    const handleDeleteSelectedItems = async () => {
        const currentItems = menuItems;
        const currentSelectedKeys = selectedItemKeys;
        const nextItems = removeItemsByKeys(currentItems, selectedMenuItemKeys);

        setMenuItems(nextItems);
        setSelectedItemKeys([]);

        const didPersist = await persistMenuItems(nextItems, 'Đã xóa các item menu đã chọn.');

        if (didPersist === false) {
            setMenuItems(currentItems);
            setSelectedItemKeys(currentSelectedKeys);
        }
    };

    const handleTreeDrop = (info) => {
        const dragKey = String(info.dragNode?.key ?? '');
        const dropKey = String(info.node?.key ?? '');

        setDraggingKey(null);
        setDragOverState({ key: null, mode: null });

        if (!dragKey || !dropKey || dragKey === dropKey) {
            return;
        }

        setMenuItems((current) => {
            const dragPath = findItemPathByKey(current, dragKey);

            if (!dragPath) {
                return current;
            }

            const draggedItem = getItemAtPath(current, dragPath);
            const draggedHasChildren = (draggedItem?.children ?? []).length > 0;
            const dropPath = findItemPathByKey(current, dropKey);

            if (!draggedItem || !dropPath) {
                return current;
            }

            const rawDropPosition = info.dropPosition - Number(String(info.node?.pos ?? '0').split('-').at(-1) ?? 0);
            const withoutDragged = removeItemAtPath(current, dragPath);
            const nextDropPath = findItemPathByKey(withoutDragged, dropKey);

            if (!nextDropPath) {
                return current;
            }

            if (!info.dropToGap) {
                if (!showChildrenEditor || nextDropPath.length !== 1 || draggedHasChildren) {
                    return current;
                }

                return insertItemAtPath(withoutDragged, nextDropPath, {
                    ...draggedItem,
                    children: [],
                });
            }

            if (nextDropPath.length === 1) {
                const targetIndex = nextDropPath[0] + (rawDropPosition > 0 ? 1 : 0);

                return insertItemAtPath(withoutDragged, [], draggedItem, targetIndex);
            }

            if (!showChildrenEditor || draggedHasChildren) {
                return current;
            }

            const targetIndex = nextDropPath[1] + (rawDropPosition > 0 ? 1 : 0);

            return insertItemAtPath(withoutDragged, [nextDropPath[0]], {
                ...draggedItem,
                children: [],
            }, targetIndex);
        });
    };

    const allowTreeDrop = ({ dragNode, dropNode, dropPosition }) => {
        if (!canManage) {
            return false;
        }

        const draggedHasChildren = (dragNode?.item?.children ?? []).length > 0;

        if (!showChildrenEditor) {
            return !dropNode?.isChild && dropPosition !== 0;
        }

        if (dropPosition === 0) {
            return !dropNode?.isChild && !draggedHasChildren;
        }

        if (dropNode?.isChild && draggedHasChildren) {
            return false;
        }

        return true;
    };

    const handleTreeDragStart = ({ node }) => {
        setDraggingKey(String(node?.key ?? ''));
    };

    const handleTreeDragEnter = (info) => {
        const nextKey = String(info.node?.key ?? '');

        setDragOverState({
            key: nextKey || null,
            mode: info.dropToGap ? (info.dropPosition < 0 ? 'before' : 'after') : 'inside',
        });
    };

    const handleTreeDragEnd = () => {
        setDraggingKey(null);
        setDragOverState({ key: null, mode: null });
    };

    const itemUrlOptions = itemLinkType === 'page'
        ? buildUrlSelectOptions(linkOptions.pages ?? [])
        : itemLinkType === 'product-category'
            ? buildUrlSelectOptions(linkOptions.productCategories ?? [])
            : itemLinkType === 'post-category'
                ? buildUrlSelectOptions(linkOptions.postCategories ?? [])
                : [];

    const itemPreviewUrl = resolveItemUrl(itemForm.getFieldsValue(true), linkLookups);

    return (
        <>
            <Drawer
                title={editingMenu?.id ? 'Chi tiết menu' : 'Tạo menu'}
                open={open}
                onClose={handleCancel}
                maskClosable={false}
                width={960}
                destroyOnHidden
                extra={(
                    <Space>
                        <Button onClick={handleCancel}>Đóng</Button>
                        <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Lưu menu</Button>
                    </Space>
                )}
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Form form={form} layout="vertical" initialValues={editingMenu}>
                        <Card>
                            <Row gutter={16}>
                                <Col span={12}>
                                    <Form.Item name="name" label="Tên menu" rules={[{ required: true, message: 'Nhập tên menu' }]}>
                                        <Input placeholder="Main Navigation" />
                                    </Form.Item>
                                </Col>
                                <Col span={12}>
                                    <Form.Item
                                        name="location"
                                        label={(
                                            <Space size={8}>
                                                <span>Vị trí</span>
                                                <Button type="text" size="small" icon={<SettingOutlined />} onClick={openLocationModal} />
                                            </Space>
                                        )}
                                        rules={[{ required: true, message: 'Chọn vị trí menu' }]}
                                    >
                                        <Select options={locationOptions} onChange={setSelectedLocation} />
                                    </Form.Item>
                                </Col>
                            </Row>
                        </Card>
                    </Form>

                    {showChildrenEditor ? (
                        <Alert
                            type="info"
                            showIcon
                            message="Vị trí product-navigation hỗ trợ menu cấp 1 và cấp 2 để khớp mega menu ngoài storefront."
                        />
                    ) : null}

                    <Card
                        title="Danh sách menu"
                        extra={(
                            <Space wrap>
                                <Popconfirm
                                    title={`Xóa ${selectedMenuItemKeys.length} item đã chọn?`}
                                    disabled={!canManage || !selectedMenuItemKeys.length}
                                    onConfirm={handleDeleteSelectedItems}
                                >
                                    <Button danger icon={<DeleteOutlined />} disabled={!canManage || !selectedMenuItemKeys.length}>
                                        Xóa đã chọn{selectedMenuItemKeys.length ? ` (${selectedMenuItemKeys.length})` : ''}
                                    </Button>
                                </Popconfirm>
                                <Button type="primary" icon={<PlusOutlined />} onClick={() => openItemEditor({ mode: 'create', isChild: false })}>Thêm mới</Button>
                            </Space>
                        )}
                    >
                        <Space direction="vertical" size={16} style={{ width: '100%' }}>
                            <Row gutter={[12, 12]}>
                                <Col xs={24} md={8}>
                                    <Card size="small">
                                        <Text type="secondary">Tổng item</Text>
                                        <Title level={4} style={{ margin: '6px 0 0' }}>{countMenuItems(menuItems)}</Title>
                                    </Card>
                                </Col>
                                <Col xs={24} md={8}>
                                    <Card size="small">
                                        <Text type="secondary">Menu cấp 1</Text>
                                        <Title level={4} style={{ margin: '6px 0 0' }}>{menuItems.length}</Title>
                                    </Card>
                                </Col>
                                <Col xs={24} md={8}>
                                    <Card size="small">
                                        <Text type="secondary">Loại hiển thị</Text>
                                        <Title level={4} style={{ margin: '6px 0 0' }}>{showChildrenEditor ? 'Đa cấp' : 'Một cấp'}</Title>
                                    </Card>
                                </Col>
                            </Row>

                            {menuItems.length ? (
                                <>
                                <Space wrap align="center">
                                    <Checkbox
                                        checked={isAllSelected}
                                        indeterminate={isPartiallySelected}
                                        disabled={!canManage}
                                        onChange={(event) => toggleSelectAllItems(event.target.checked)}
                                    >
                                        Chọn tất cả
                                    </Checkbox>
                                    <Text type="secondary">Đã chọn {selectedMenuItemKeys.length}/{allMenuItemKeys.length} item</Text>
                                </Space>
                                <Tree
                                    className="cms-menu-tree"
                                    blockNode
                                    expandedKeys={expandedKeys}
                                    draggable={canManage}
                                    allowDrop={allowTreeDrop}
                                    onExpand={setManualExpandedKeys}
                                    onDragStart={handleTreeDragStart}
                                    onDragEnter={handleTreeDragEnter}
                                    onDragEnd={handleTreeDragEnd}
                                    onDrop={handleTreeDrop}
                                    treeData={menuTreeData}
                                    titleRender={(node) => {
                                        const item = node.item;
                                        const path = node.path ?? [];
                                        const isChild = node.isChild;
                                        const isDragging = draggingKey === node.key;
                                        const isDragOver = dragOverState.key === node.key;
                                        const dropMode = isDragOver ? dragOverState.mode : null;

                                        return (
                                            <div
                                                className={`cms-menu-tree-node${isChild ? ' is-child' : ''}${isDragging ? ' is-dragging' : ''}${isDragOver ? ' is-drag-over' : ''}${dropMode ? ` drop-mode-${dropMode}` : ''}`}
                                            >
                                                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 }}>
                                                    <Space direction="vertical" size={4} style={{ minWidth: 0, flex: 1 }}>
                                                        <Space wrap>
                                                            <Checkbox
                                                                checked={selectedMenuItemKeys.includes(node.key)}
                                                                disabled={!canManage}
                                                                onChange={(event) => toggleSelectItem(node.key, event.target.checked)}
                                                                onClick={(event) => event.stopPropagation()}
                                                            />
                                                            <Space size={6}>
                                                                <HolderOutlined style={{ color: '#7c948d' }} />
                                                                <Text strong>{item.label || 'Chưa có label'}</Text>
                                                            </Space>
                                                            {isChild ? <Tag color="blue">Cấp 2</Tag> : <Tag color="green">Cấp 1</Tag>}
                                                            <Text type="secondary" style={{ fontSize: 12 }}>
                                                                {linkTypeLabel(item.link_type)}
                                                            </Text>
                                                            {dropMode === 'inside' ? <Tag color="processing">Thả vào trong</Tag> : null}
                                                            {dropMode === 'before' ? <Tag color="gold">Chèn phía trên</Tag> : null}
                                                            {dropMode === 'after' ? <Tag color="purple">Chèn phía dưới</Tag> : null}
                                                        </Space>
                                                    </Space>

                                                    <Space size={4} wrap>
                                                        {showChildrenEditor && !isChild ? (
                                                            <Button
                                                                type="text"
                                                                icon={<PlusOutlined />}
                                                                style={{ color: '#0f766e' }}
                                                                onClick={() => openItemEditor({ mode: 'create', isChild: true, parentIndex: path[0] })}
                                                            >
                                                                Thêm cấp 2
                                                            </Button>
                                                        ) : null}
                                                        <Button type="text" icon={<EditOutlined />} style={{ color: '#2563eb' }} onClick={() => openItemEditor({ mode: 'edit', path, isChild })}>Sửa</Button>
                                                        <Popconfirm title="Xóa item menu này?" onConfirm={() => handleDeleteItem(path)}>
                                                            <Button type="text" icon={<DeleteOutlined />} style={{ color: '#3f3f46' }}>Xóa</Button>
                                                        </Popconfirm>
                                                    </Space>
                                                </div>
                                            </div>
                                        );
                                    }}
                                />
                                </>
                            ) : <Empty description="Chưa có item menu nào." />}
                        </Space>
                    </Card>
                </Space>
            </Drawer>

            <Modal
                title={buildEditorTitle(itemEditorState.mode, itemEditorState.isChild)}
                open={itemEditorOpen}
                onCancel={closeItemEditor}
                onOk={handleSaveItem}
                width={760}
                destroyOnHidden
            >
                <Form form={itemForm} layout="vertical" initialValues={createEmptyMenuItem()}>
                    <Row gutter={16}>
                        <Col span={10}>
                            <Form.Item name="label" label="Label" rules={[{ required: true, message: 'Nhập label' }]}>
                                <Input placeholder="Giới thiệu" />
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
                            <Input placeholder="/gioi-thieu hoặc https://domain.com" />
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

            <Modal
                title="Quản lý vị trí menu"
                open={locationModalOpen}
                onCancel={() => {
                    setLocationModalOpen(false);
                    setEditingLocation(null);
                    setLocationError('');
                    locationForm.resetFields();
                }}
                onOk={handleSaveLocation}
                okText={editingLocation ? 'Cập nhật' : 'Tạo vị trí'}
                width={720}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Alert type="info" showIcon message="Vị trí menu là danh mục dùng chung cho toàn website. Khi đang có menu sử dụng một vị trí, hệ thống sẽ chặn xóa hoặc đổi mã vị trí đó." />

                    {locationError ? <Alert type="error" showIcon message={locationError} /> : null}

                    <Form form={locationForm} layout="vertical" initialValues={emptyLocationForm}>
                        <Row gutter={16}>
                            <Col span={12}>
                                <Form.Item name="label" label="Tên hiển thị" rules={[{ required: true, message: 'Nhập tên hiển thị vị trí' }]}>
                                    <Input placeholder="Primary Header" />
                                </Form.Item>
                            </Col>
                            <Col span={12}>
                                <Form.Item name="value" label="Mã vị trí" extra="Để trống nếu muốn hệ thống tự slug từ tên hiển thị.">
                                    <Input placeholder="primary-header" />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Form>

                    <Divider style={{ margin: 0 }} />

                    <List
                        size="small"
                        bordered
                        dataSource={locationOptions}
                        locale={{ emptyText: 'Chưa có vị trí menu nào.' }}
                        renderItem={(item) => (
                            <List.Item
                                actions={[
                                    <Button key={`edit-${item.value}`} type="text" icon={<EditOutlined />} onClick={() => startEditLocation(item)} />,
                                    <Popconfirm key={`delete-${item.value}`} title="Xóa vị trí menu này?" onConfirm={() => handleDeleteLocation(item)}>
                                        <Button danger type="text" icon={<PlusOutlined rotate={45} />} />
                                    </Popconfirm>,
                                ]}
                            >
                                <Space direction="vertical" size={0}>
                                    <Text strong>{item.label}</Text>
                                    <Text type="secondary">{item.value}</Text>
                                </Space>
                            </List.Item>
                        )}
                    />
                </Space>
            </Modal>
        </>
    );
}

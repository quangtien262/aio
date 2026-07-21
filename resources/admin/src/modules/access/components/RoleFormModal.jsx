import { useEffect, useMemo, useState } from 'react';
import Button from 'antd/es/button';
import Checkbox from 'antd/es/checkbox';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;

const MODULE_LABELS = {
    admin: 'Tài khoản quản trị',
    catalog: 'Danh mục & sản phẩm',
    cms: 'Nội dung website (CMS)',
    inventory: 'Kho hàng',
    module: 'Ứng dụng & phân hệ',
    platform: 'Nền tảng hệ thống',
    permission: 'Phân quyền hệ thống',
    project: 'Quản lý dự án',
    rbac: 'Quản lý vai trò và phân quyền',
    setup: 'Cấu hình hệ thống',
    store: 'Kho ứng dụng',
    theme: 'Giao diện website',
};

const RESOURCE_LABELS = {
    account: 'Tài khoản',
    category: 'Danh mục',
    dashboard: 'Tổng quan',
    media: 'Thư viện hình ảnh',
    menu: 'Menu website',
    module: 'Phân hệ',
    newsletter: 'Đăng ký nhận tin',
    order: 'Đơn hàng',
    page: 'Trang nội dung',
    partner: 'Đối tác',
    permission: 'Quyền truy cập',
    post: 'Bài viết',
    product: 'Sản phẩm',
    project: 'Dự án',
    role: 'Vai trò',
    scope: 'Phạm vi truy cập',
    service: 'Dịch vụ',
    settings: 'Thiết lập',
    setup: 'Cấu hình',
    team: 'Nhân sự',
    testimonial: 'Cảm nhận khách hàng',
    theme: 'Giao diện',
};

const ACTION_LABELS = {
    assign: 'Gán quyền',
    complete: 'Hoàn tất cấu hình',
    create: 'Thêm mới',
    delete: 'Xóa',
    disable: 'Tắt',
    enable: 'Bật',
    export: 'Xuất dữ liệu',
    install: 'Cài đặt',
    lock: 'Khóa',
    manage: 'Quản lý toàn bộ',
    publish: 'Xuất bản',
    reset_password: 'Đặt lại mật khẩu',
    uninstall: 'Gỡ cài đặt',
    unlock: 'Mở khóa',
    update: 'Chỉnh sửa',
    view: 'Xem',
};

const ACTION_COLUMNS = {
    view: 'view',
    create: 'create',
    update: 'update',
    delete: 'delete',
};

const titleCase = (value) => String(value ?? '')
    .replace(/[-_]+/g, ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase());

const roleKeyFromName = (value) => String(value ?? '')
    .trim()
    .replace(/[đĐ]/g, (character) => character === 'Đ' ? 'D' : 'd')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

const permissionMeta = (permission) => {
    const segments = String(permission.key ?? '').split('.').filter(Boolean);
    const moduleKey = permission.module_key || segments[0] || 'platform';
    const actionKey = segments.at(-1) || 'manage';
    const resourceSegments = segments.slice(1, -1);
    const resourceKey = resourceSegments.join('.') || 'dashboard';
    const simpleResourceKey = resourceSegments.at(-1) || 'dashboard';

    return {
        ...permission,
        moduleKey,
        moduleLabel: MODULE_LABELS[moduleKey] || titleCase(moduleKey),
        actionKey,
        actionLabel: ACTION_LABELS[actionKey] || titleCase(actionKey),
        actionColumn: ACTION_COLUMNS[actionKey] || 'other',
        resourceKey,
        resourceLabel: RESOURCE_LABELS[simpleResourceKey] || titleCase(resourceSegments.join(' ') || 'Tổng quan'),
    };
};

export const emptyRoleForm = {
    id: null,
    name: '',
    key: '',
    description: '',
    permission_ids: [],
};

export default function RoleFormModal({ open, canManageRoles, editingRole, permissions, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [search, setSearch] = useState('');
    const [autoKey, setAutoKey] = useState(!editingRole.id);
    const selectedPermissionIds = Form.useWatch('permission_ids', form) ?? [];

    useEffect(() => {
        form.setFieldsValue(editingRole);
        setSearch('');
        setAutoKey(!editingRole.id);
    }, [editingRole, form]);

    const handleFormValuesChange = (changedValues) => {
        if (autoKey && Object.prototype.hasOwnProperty.call(changedValues, 'name')) {
            form.setFieldValue('key', roleKeyFromName(changedValues.name));
        }
    };

    const handleAutoKeyChange = (checked) => {
        setAutoKey(checked);
        if (checked) form.setFieldValue('key', roleKeyFromName(form.getFieldValue('name')));
    };

    const permissionGroups = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('vi');
        const normalized = (permissions ?? []).map(permissionMeta).filter((permission) => {
            if (!query) return true;
            return [permission.moduleLabel, permission.resourceLabel, permission.actionLabel, permission.display_name]
                .some((value) => String(value ?? '').toLocaleLowerCase('vi').includes(query));
        });

        return Object.values(normalized.reduce((modules, permission) => {
            const module = modules[permission.moduleKey] ?? {
                key: permission.moduleKey,
                label: permission.moduleLabel,
                permissions: [],
                resources: {},
            };
            const resource = module.resources[permission.resourceKey] ?? {
                key: `${permission.moduleKey}:${permission.resourceKey}`,
                resourceLabel: permission.resourceLabel,
                view: [], create: [], update: [], delete: [], other: [],
            };

            resource[permission.actionColumn].push(permission);
            module.permissions.push(permission);
            module.resources[permission.resourceKey] = resource;
            modules[permission.moduleKey] = module;
            return modules;
        }, {})).map((module) => ({ ...module, rows: Object.values(module.resources) }));
    }, [permissions, search]);

    const selectedSet = useMemo(() => new Set(selectedPermissionIds), [selectedPermissionIds]);
    const updateSelection = (ids, checked) => {
        const next = new Set(selectedPermissionIds);
        ids.forEach((id) => checked ? next.add(id) : next.delete(id));
        form.setFieldValue('permission_ids', [...next]);
    };

    const renderPermissionCell = (items, compact = true) => {
        if (!items?.length) return <Text type="secondary">—</Text>;

        return (
            <Space direction="vertical" size={6} className={`role-permission-cell${compact ? '' : ' role-permission-cell--left'}`}>
                {items.map((permission) => (
                    <Checkbox
                        key={permission.id}
                        checked={selectedSet.has(permission.id)}
                        onChange={(event) => updateSelection([permission.id], event.target.checked)}
                    >
                        {compact ? <span className="role-permission-check-label">{permission.actionLabel}</span> : permission.actionLabel}
                        {permission.risk_level === 'high' ? <Tag color="red" bordered={false}>Nhạy cảm</Tag> : null}
                    </Checkbox>
                ))}
            </Space>
        );
    };

    const columns = [
        {
            title: 'Chức năng',
            dataIndex: 'resourceLabel',
            key: 'resourceLabel',
            width: 220,
            fixed: 'left',
            render: (value) => <Text strong>{value}</Text>,
        },
        ...[
            ['view', 'Xem'],
            ['create', 'Thêm mới'],
            ['update', 'Chỉnh sửa'],
            ['delete', 'Xóa'],
        ].map(([key, title]) => ({
            title,
            key,
            width: 130,
            align: 'center',
            render: (_, row) => renderPermissionCell(row[key]),
        })),
        {
            title: 'Quyền bổ sung',
            key: 'other',
            width: 220,
            align: 'left',
            render: (_, row) => renderPermissionCell(row.other, false),
        },
    ];

    const handleSubmit = async () => {
        const payload = await form.validateFields();
        await onSubmit?.(payload);
        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Drawer
            title={null}
            open={open}
            onClose={handleCancel}
            width="min(1180px, 96vw)"
            className="role-form-drawer"
            destroyOnHidden
            footer={(
                <div className="role-drawer-footer">
                    <Text type="secondary">Đã chọn <strong>{selectedPermissionIds.length}</strong> quyền</Text>
                    <Space>
                        <Button onClick={handleCancel}>Hủy</Button>
                        <Button type="primary" disabled={!canManageRoles} onClick={handleSubmit}>
                            {editingRole.id ? 'Lưu thay đổi' : 'Tạo vai trò'}
                        </Button>
                    </Space>
                </div>
            )}
        >
            <Form form={form} layout="vertical" initialValues={editingRole} className="role-form" onValuesChange={handleFormValuesChange}>
                <div className="role-drawer-heading">
                    <div>
                        <Text className="card-label">Vai trò & phân quyền</Text>
                        <Title level={3}>{editingRole.id ? 'Cập nhật vai trò' : 'Tạo vai trò mới'}</Title>
                        <Paragraph>Thiết lập thông tin vai trò và đánh dấu những công việc người dùng được phép thực hiện.</Paragraph>
                    </div>
                    <Tag color={editingRole.id ? 'blue' : 'green'}>{editingRole.id ? 'Đang chỉnh sửa' : 'Vai trò mới'}</Tag>
                </div>

                <div className="role-form-info-grid">
                    <Form.Item label="Tên vai trò" name="name" rules={[{ required: true, message: 'Vui lòng nhập tên vai trò.' }]}>
                        <Input size="large" placeholder="Ví dụ: Quản lý nội dung" />
                    </Form.Item>
                    <Form.Item
                        label={(
                            <span className="role-key-label">
                                <span>Mã vai trò</span>
                                <span className="role-key-auto"><Switch size="small" checked={autoKey} disabled={editingRole.key === 'super-admin'} onChange={handleAutoKeyChange} /><span>Tự động</span></span>
                            </span>
                        )}
                        name="key"
                        extra={autoKey ? 'Mã được tự động tạo từ tên vai trò.' : 'Mã dùng nội bộ, viết thường và không dấu.'}
                        rules={[{ required: true, message: 'Vui lòng nhập mã vai trò.' }]}
                    >
                        <Input size="large" placeholder="Ví dụ: content-manager" disabled={editingRole.key === 'super-admin' || autoKey} />
                    </Form.Item>
                    <Form.Item className="role-description-field" label="Mô tả trách nhiệm" name="description">
                        <Input.TextArea rows={3} placeholder="Mô tả ngắn gọn công việc và phạm vi sử dụng của vai trò này" />
                    </Form.Item>
                </div>

                <Form.Item name="permission_ids" hidden><Input /></Form.Item>

                <section className="role-permission-section">
                    <div className="role-permission-toolbar">
                        <div>
                            <Title level={4}>Bảng phân quyền</Title>
                            <Text type="secondary">Chọn quyền theo từng nhóm chức năng. Quyền nhạy cảm được đánh dấu riêng.</Text>
                        </div>
                        <Space wrap>
                            <Input.Search
                                allowClear
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Tìm chức năng..."
                                className="role-permission-search"
                            />
                            <Button onClick={() => updateSelection((permissions ?? []).map((item) => item.id), true)}>Chọn tất cả</Button>
                            <Button onClick={() => form.setFieldValue('permission_ids', [])}>Bỏ chọn</Button>
                        </Space>
                    </div>

                    <div className="role-permission-modules">
                        {permissionGroups.map((module) => {
                            const moduleIds = module.permissions.map((permission) => permission.id);
                            const selectedCount = moduleIds.filter((id) => selectedSet.has(id)).length;
                            const allSelected = moduleIds.length > 0 && selectedCount === moduleIds.length;

                            return (
                                <section className="role-permission-module" key={module.key}>
                                    <header className="role-permission-module__head">
                                        <div>
                                            <Title level={5}>{module.label}</Title>
                                            <Text type="secondary">Đã chọn {selectedCount}/{moduleIds.length} quyền</Text>
                                        </div>
                                        <Checkbox
                                            checked={allSelected}
                                            indeterminate={selectedCount > 0 && !allSelected}
                                            onChange={(event) => updateSelection(moduleIds, event.target.checked)}
                                        >
                                            Chọn cả nhóm
                                        </Checkbox>
                                    </header>
                                    <Table
                                        rowKey="key"
                                        columns={columns}
                                        dataSource={module.rows}
                                        pagination={false}
                                        size="small"
                                        scroll={{ x: 900 }}
                                    />
                                </section>
                            );
                        })}
                        {!permissionGroups.length ? <div className="role-permission-empty">Không tìm thấy chức năng phù hợp.</div> : null}
                    </div>
                </section>
            </Form>
        </Drawer>
    );
}

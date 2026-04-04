import { useMemo, useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Dropdown from 'antd/es/dropdown';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;

function escapeCsvCell(value) {
    const normalizedValue = String(value ?? '');

    if (/[",\n]/.test(normalizedValue)) {
        return `"${normalizedValue.replace(/"/g, '""')}"`;
    }

    return normalizedValue;
}

function formatLastLogin(value) {
    if (!value) {
        return 'Chưa đăng nhập';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('vi-VN');
}

export default function AdminAccountsTableCard({ adminAccounts, roles, scopeTypes, stats, currentAdmin, canManageAdmins, canResetPassword, canLockAdmins, onCreateAdmin, onOpenDetailsDrawer, onEditAdmin, onOpenPasswordModal, onOpenLockModal, onUnlockAdmin }) {
    const [keyword, setKeyword] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [roleFilter, setRoleFilter] = useState('all');
    const [scopeTypeFilter, setScopeTypeFilter] = useState('all');

    const roleOptions = useMemo(() => ([
        { label: 'Tất cả role', value: 'all' },
        ...((roles ?? []).map((role) => ({
            label: role.name,
            value: String(role.id),
        }))),
    ]), [roles]);

    const scopeTypeOptions = useMemo(() => ([
        { label: 'Tất cả scope type', value: 'all' },
        ...Object.entries(scopeTypes ?? {}).map(([value, label]) => ({
            label,
            value,
        })),
    ]), [scopeTypes]);

    const filteredAdmins = useMemo(() => {
        const normalizedKeyword = keyword.trim().toLowerCase();

        return (adminAccounts ?? []).filter((admin) => {
            const matchesKeyword = normalizedKeyword === ''
                || admin.name?.toLowerCase().includes(normalizedKeyword)
                || admin.email?.toLowerCase().includes(normalizedKeyword)
                || (admin.roles ?? []).some((role) => role.name?.toLowerCase().includes(normalizedKeyword))
                || (admin.scopes ?? []).some((scope) => `${scope.scope_type}:${scope.scope_value}`.toLowerCase().includes(normalizedKeyword));

            const matchesStatus = statusFilter === 'all'
                || (statusFilter === 'active' && admin.is_active && !admin.is_locked)
                || (statusFilter === 'inactive' && !admin.is_active && !admin.is_locked)
                || (statusFilter === 'locked' && admin.is_locked);

            const matchesRole = roleFilter === 'all'
                || (admin.role_ids ?? []).includes(Number(roleFilter));

            const matchesScopeType = scopeTypeFilter === 'all'
                || (admin.scopes ?? []).some((scope) => scope.scope_type === scopeTypeFilter);

            return matchesKeyword && matchesStatus && matchesRole && matchesScopeType;
        });
    }, [adminAccounts, keyword, roleFilter, scopeTypeFilter, statusFilter]);

    const handleExportCsv = () => {
        const rows = [
            ['ID', 'Tên admin', 'Email', 'Trạng thái', 'Khóa', 'Lý do khóa', 'Lần đăng nhập cuối', 'Roles', 'Scopes'],
            ...filteredAdmins.map((admin) => [
                admin.id,
                admin.name,
                admin.email,
                admin.is_active ? 'active' : 'inactive',
                admin.is_locked ? 'locked' : 'unlocked',
                admin.locked_reason ?? '',
                formatLastLogin(admin.last_login_at),
                (admin.roles ?? []).map((role) => role.name).join(' | '),
                (admin.scopes ?? []).map((scope) => `${scopeTypes?.[scope.scope_type] ?? scope.scope_type}:${scope.scope_value}`).join(' | '),
            ]),
        ];

        const csvContent = rows
            .map((row) => row.map((cell) => escapeCsvCell(cell)).join(','))
            .join('\n');

        const blob = new Blob([`\uFEFF${csvContent}`], { type: 'text/csv;charset=utf-8;' });
        const downloadUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = downloadUrl;
        link.download = 'admin-accounts.csv';
        link.click();

        URL.revokeObjectURL(downloadUrl);
    };

    const accountColumns = [
        {
            title: 'Admin',
            key: 'admin',
            render: (_, admin) => (
                <Space direction="vertical" size={0}>
                    <Button type="link" className="admin-name-link" onClick={() => onOpenDetailsDrawer?.(admin)}>
                        {admin.name}
                    </Button>
                    <Text type="secondary">{admin.email}</Text>
                </Space>
            ),
        },
        {
            title: 'Trạng thái',
            key: 'status',
            render: (_, admin) => (
                <Space direction="vertical" size={4}>
                    <Space wrap>
                        <Tag color={admin.is_active ? 'green' : 'default'}>{admin.is_active ? 'active' : 'inactive'}</Tag>
                        {admin.is_locked ? <Tag color="red">locked</Tag> : null}
                    </Space>
                    {admin.locked_reason ? <Text type="secondary">{admin.locked_reason}</Text> : null}
                </Space>
            ),
        },
        {
            title: 'Roles',
            key: 'roles',
            render: (_, admin) => (
                <Space wrap>
                    {(admin.roles ?? []).map((role) => (
                        <Tag key={role.id}>{role.name}</Tag>
                    ))}
                </Space>
            ),
        },
        {
            title: 'Scopes',
            key: 'scopes',
            render: (_, admin) => (
                <Space wrap>
                    {(admin.scopes ?? []).map((scope) => (
                        <Tag key={`${admin.id}-${scope.role_id}-${scope.scope_type}-${scope.scope_value}`} color="blue">
                            {scope.scope_type}:{scope.scope_value}
                        </Tag>
                    ))}
                </Space>
            ),
        },
        {
            title: 'Lần đăng nhập cuối',
            dataIndex: 'last_login_at',
            key: 'last_login_at',
            render: (value) => formatLastLogin(value),
        },
        {
            title: 'Tác vụ',
            key: 'actions',
            render: (_, admin) => {
                const isCurrentAdmin = currentAdmin?.id === admin.id;
                const actionItems = [
                    {
                        key: 'edit',
                        label: 'Sửa admin',
                        disabled: !canManageAdmins,
                    },
                    {
                        key: 'password',
                        label: 'Đặt lại mật khẩu',
                        disabled: !canResetPassword,
                    },
                    admin.is_locked
                        ? {
                            key: 'unlock',
                            label: 'Mở khóa tài khoản',
                            disabled: !canLockAdmins,
                        }
                        : {
                            key: 'lock',
                            label: isCurrentAdmin ? 'Không thể tự khóa tài khoản đang dùng' : 'Khóa tài khoản',
                            disabled: !canLockAdmins || isCurrentAdmin,
                            danger: true,
                        },
                ];

                const handleActionClick = ({ key }) => {
                    if (key === 'edit') {
                        onEditAdmin?.(admin);
                        return;
                    }

                    if (key === 'password') {
                        onOpenPasswordModal?.(admin);
                        return;
                    }

                    if (key === 'lock') {
                        onOpenLockModal?.(admin);
                        return;
                    }

                    if (key === 'unlock') {
                        onUnlockAdmin?.(admin.id);
                    }
                };

                return (
                    <Dropdown menu={{ items: actionItems, onClick: handleActionClick }} trigger={['click']}>
                        <Button size="small">
                            Tác vụ
                        </Button>
                    </Dropdown>
                );
            },
        },
    ];

    return (
        <Card
            className="admin-table-card"
            title="Admin Accounts"
            extra={(
                <Space wrap>
                    <Button onClick={handleExportCsv} disabled={filteredAdmins.length === 0}>
                        Export CSV
                    </Button>
                    <Button type="primary" disabled={!canManageAdmins} onClick={onCreateAdmin}>
                        Tạo admin
                    </Button>
                </Space>
            )}
        >
            <Space direction="vertical" size={4} style={{ marginBottom: 16 }}>
                <Text className="card-label">Admin Management</Text>
                <Title level={4}>Quản lý tài khoản admin, role, scope và trạng thái khóa/mở khóa</Title>
                <Paragraph>
                    Tài khoản admin có thể được gán role, data scope theo website/module/owner/tenant, đặt lại mật khẩu và khóa mở khóa trực tiếp.
                </Paragraph>
            </Space>

            <Row className="admin-table-stats" gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={6}>
                    <Card size="small">
                        <Text className="detail-label">Tổng admin</Text>
                        <Title level={4} style={{ margin: 0 }}>{stats?.total ?? 0}</Title>
                    </Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card size="small">
                        <Text className="detail-label">Đang hoạt động</Text>
                        <Title level={4} style={{ margin: 0 }}>{stats?.active ?? 0}</Title>
                    </Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card size="small">
                        <Text className="detail-label">Đang khóa</Text>
                        <Title level={4} style={{ margin: 0 }}>{stats?.locked ?? 0}</Title>
                    </Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card size="small">
                        <Text className="detail-label">Có scope riêng</Text>
                        <Title level={4} style={{ margin: 0 }}>{stats?.withScopes ?? 0}</Title>
                    </Card>
                </Col>
            </Row>

            <Row className="admin-table-filters" gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} md={12}>
                    <Input.Search
                        allowClear
                        value={keyword}
                        onChange={(event) => setKeyword(event.target.value)}
                        placeholder="Tìm theo tên, email, role hoặc scope"
                    />
                </Col>
                <Col xs={24} md={6}>
                    <Select
                        style={{ width: '100%' }}
                        value={statusFilter}
                        onChange={setStatusFilter}
                        options={[
                            { label: 'Tất cả trạng thái', value: 'all' },
                            { label: 'Đang hoạt động', value: 'active' },
                            { label: 'Ngưng hoạt động', value: 'inactive' },
                            { label: 'Đang khóa', value: 'locked' },
                        ]}
                    />
                </Col>
                <Col xs={24} md={6}>
                    <Select style={{ width: '100%' }} value={roleFilter} onChange={setRoleFilter} options={roleOptions} />
                </Col>
                <Col xs={24} md={6} lg={6}>
                    <Select style={{ width: '100%' }} value={scopeTypeFilter} onChange={setScopeTypeFilter} options={scopeTypeOptions} />
                </Col>
            </Row>

            <div className="admin-responsive-table">
                <Table
                    rowKey="id"
                    columns={accountColumns}
                    dataSource={filteredAdmins}
                    scroll={{ x: 980 }}
                    pagination={{ pageSize: 8, hideOnSinglePage: true, showSizeChanger: false }}
                    locale={{ emptyText: <Empty description="Không có admin phù hợp với bộ lọc hiện tại." /> }}
                />
            </div>
        </Card>
    );
}

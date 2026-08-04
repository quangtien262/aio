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
        return 'ChÆ°a Ä‘Äƒng nháº­p';
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
        { label: 'Táº¥t cáº£ role', value: 'all' },
        ...((roles ?? []).map((role) => ({
            label: role.name,
            value: String(role.id),
        }))),
    ]), [roles]);

    const scopeTypeOptions = useMemo(() => ([
        { label: 'Táº¥t cáº£ scope type', value: 'all' },
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
                || admin.username?.toLowerCase().includes(normalizedKeyword)
                || admin.email?.toLowerCase().includes(normalizedKeyword)
                || (admin.roles ?? []).some((role) => role.name?.toLowerCase().includes(normalizedKeyword))
                || (admin.assignments ?? []).some((assignment) => `${assignment.scope_type}:${assignment.scope_value ?? ''}`.toLowerCase().includes(normalizedKeyword));

            const matchesStatus = statusFilter === 'all'
                || (statusFilter === 'active' && admin.is_active && !admin.is_locked)
                || (statusFilter === 'inactive' && !admin.is_active && !admin.is_locked)
                || (statusFilter === 'locked' && admin.is_locked);

            const matchesRole = roleFilter === 'all'
                || (admin.role_ids ?? []).includes(Number(roleFilter));

            const matchesScopeType = scopeTypeFilter === 'all'
                || (admin.assignments ?? []).some((assignment) => assignment.scope_type === scopeTypeFilter);

            return matchesKeyword && matchesStatus && matchesRole && matchesScopeType;
        });
    }, [adminAccounts, keyword, roleFilter, scopeTypeFilter, statusFilter]);

    const handleExportCsv = () => {
        const rows = [
            ['ID', 'TÃªn admin', 'Username', 'Email', 'Tráº¡ng thÃ¡i', 'KhÃ³a', 'LÃ½ do khÃ³a', 'Láº§n Ä‘Äƒng nháº­p cuá»‘i', 'Roles', 'Scopes'],
            ...filteredAdmins.map((admin) => [
                admin.id,
                admin.name,
                admin.username,
                admin.email,
                admin.is_active ? 'active' : 'inactive',
                admin.is_locked ? 'locked' : 'unlocked',
                admin.locked_reason ?? '',
                formatLastLogin(admin.last_login_at),
                (admin.roles ?? []).map((role) => role.name).join(' | '),
                (admin.assignments ?? []).map((assignment) => `${scopeTypes?.[assignment.scope_type] ?? assignment.scope_type}:${assignment.scope_value ?? '*'}`).join(' | '),
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
                    <Text strong>Username: @{admin.username}</Text>
                    <Text type="secondary">Email: {admin.email}</Text>
                    {admin.is_system_owner ? <Tag color="gold">System Owner</Tag> : null}
                </Space>
            ),
        },
        {
            title: 'Tráº¡ng thÃ¡i',
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
                    {(admin.assignments ?? []).map((assignment) => (
                        <Tag key={`${admin.id}-${assignment.role_id}-${assignment.scope_type}-${assignment.scope_value ?? 'all'}`} color="blue">
                            {assignment.scope_type}:{assignment.scope_value ?? '*'}
                        </Tag>
                    ))}
                </Space>
            ),
        },
        {
            title: 'Láº§n Ä‘Äƒng nháº­p cuá»‘i',
            dataIndex: 'last_login_at',
            key: 'last_login_at',
            render: (value) => formatLastLogin(value),
        },
        {
            title: 'TÃ¡c vá»¥',
            key: 'actions',
            render: (_, admin) => {
                const isCurrentAdmin = currentAdmin?.id === admin.id;
                const actionItems = [
                    {
                        key: 'edit',
                        label: 'Sá»­a admin',
                        disabled: !canManageAdmins || admin.is_system_owner,
                    },
                    {
                        key: 'password',
                        label: 'Äáº·t láº¡i máº­t kháº©u',
                        disabled: !canResetPassword || admin.is_system_owner,
                    },
                    admin.is_locked
                        ? {
                            key: 'unlock',
                            label: 'Má»Ÿ khÃ³a tÃ i khoáº£n',
                            disabled: !canLockAdmins || admin.is_system_owner,
                        }
                        : {
                            key: 'lock',
                            label: isCurrentAdmin ? 'KhÃ´ng thá»ƒ tá»± khÃ³a tÃ i khoáº£n Ä‘ang dÃ¹ng' : 'KhÃ³a tÃ i khoáº£n',
                            disabled: !canLockAdmins || isCurrentAdmin || admin.is_system_owner,
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
                            TÃ¡c vá»¥
                        </Button>
                    </Dropdown>
                );
            },
        },
    ];

    return (
        <Card
            className="admin-table-card admin-accounts-card"
            title="Admin Accounts"
            extra={(
                <Space wrap>
                    <Button onClick={handleExportCsv} disabled={filteredAdmins.length === 0}>
                        Export CSV
                    </Button>
                    <Button type="primary" disabled={!canManageAdmins} onClick={onCreateAdmin}>
                        Táº¡o admin
                    </Button>
                </Space>
            )}
        >
            <Space direction="vertical" size={4} style={{ marginBottom: 16 }}>
                <Text className="card-label">Admin Management</Text>
                <Title level={4}>Quáº£n lÃ½ tÃ i khoáº£n, vai trÃ² vÃ  pháº¡m vi website</Title>
                <Paragraph>
                    Má»—i vai trÃ² Ä‘Æ°á»£c gÃ¡n á»Ÿ pháº¡m vi toÃ n há»‡ thá»‘ng hoáº·c theo website. System Owner luÃ´n toÃ n quyá»n vÃ  khÃ´ng thá»ƒ bá»‹ khÃ³a.
                </Paragraph>
            </Space>

            <Row className="admin-table-stats" gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={12} md={6}>
                    <Card size="small">
                        <Text className="detail-label">Tá»•ng admin</Text>
                        <Title level={4} style={{ margin: 0 }}>{stats?.total ?? 0}</Title>
                    </Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card size="small">
                        <Text className="detail-label">Äang hoáº¡t Ä‘á»™ng</Text>
                        <Title level={4} style={{ margin: 0 }}>{stats?.active ?? 0}</Title>
                    </Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card size="small">
                        <Text className="detail-label">Äang khÃ³a</Text>
                        <Title level={4} style={{ margin: 0 }}>{stats?.locked ?? 0}</Title>
                    </Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card size="small">
                        <Text className="detail-label">CÃ³ scope riÃªng</Text>
                        <Title level={4} style={{ margin: 0 }}>{stats?.withScopes ?? 0}</Title>
                    </Card>
                </Col>
            </Row>

            <Row className="admin-table-filters" gutter={[12, 12]} style={{ marginBottom: 16 }}>
                <Col xs={24} md={12}>
                    <Space direction="vertical" size={6} className="admin-filter-field">
                        <Text className="admin-filter-label">Từ khóa</Text>
                        <Input.Search
                            allowClear
                            value={keyword}
                            onChange={(event) => setKeyword(event.target.value)}
                            placeholder="Tìm theo tên, username, email..."
                        />
                    </Space>
                </Col>
                <Col xs={24} md={6}>
                    <Space direction="vertical" size={6} className="admin-filter-field">
                        <Text className="admin-filter-label">Trạng thái</Text>
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
                    </Space>
                </Col>
                <Col xs={24} md={6}>
                    <Space direction="vertical" size={6} className="admin-filter-field">
                        <Text className="admin-filter-label">Vai trò</Text>
                        <Select style={{ width: '100%' }} value={roleFilter} onChange={setRoleFilter} options={roleOptions} />
                    </Space>
                </Col>
                <Col xs={24} md={6} lg={6}>
                    <Space direction="vertical" size={6} className="admin-filter-field">
                        <Text className="admin-filter-label">Phạm vi</Text>
                        <Select style={{ width: '100%' }} value={scopeTypeFilter} onChange={setScopeTypeFilter} options={scopeTypeOptions} />
                    </Space>
                </Col>
            </Row>
            <div className="admin-responsive-table">
                <Table
                    rowKey="id"
                    columns={accountColumns}
                    dataSource={filteredAdmins}
                    scroll={{ x: 980 }}
                    pagination={{ pageSize: 8, hideOnSinglePage: true, showSizeChanger: false }}
                    locale={{ emptyText: <Empty description="KhÃ´ng cÃ³ admin phÃ¹ há»£p vá»›i bá»™ lá»c hiá»‡n táº¡i." /> }}
                />
            </div>
        </Card>
    );
}


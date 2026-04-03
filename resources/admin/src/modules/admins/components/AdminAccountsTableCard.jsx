import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Popconfirm from 'antd/es/popconfirm';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;

export default function AdminAccountsTableCard({ adminAccounts, currentAdmin, canManageAdmins, canResetPassword, canLockAdmins, onCreateAdmin, onEditAdmin, onOpenPasswordModal, onLockAdmin, onUnlockAdmin }) {
    const accountColumns = [
        {
            title: 'Admin',
            key: 'admin',
            render: (_, admin) => (
                <Space direction="vertical" size={0}>
                    <Text strong>{admin.name}</Text>
                    <Text type="secondary">{admin.email}</Text>
                </Space>
            ),
        },
        {
            title: 'Trạng thái',
            key: 'status',
            render: (_, admin) => (
                <Space wrap>
                    <Tag color={admin.is_active ? 'green' : 'default'}>{admin.is_active ? 'active' : 'inactive'}</Tag>
                    {admin.is_locked ? <Tag color="red">locked</Tag> : null}
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
            render: (value) => value || 'Chưa đăng nhập',
        },
        {
            title: 'Tác vụ',
            key: 'actions',
            render: (_, admin) => {
                const isCurrentAdmin = currentAdmin?.id === admin.id;

                return (
                    <Space wrap>
                        <Button size="small" disabled={!canManageAdmins} onClick={() => onEditAdmin?.(admin)}>
                            Sửa
                        </Button>
                        <Button size="small" disabled={!canResetPassword} onClick={() => onOpenPasswordModal?.(admin)}>
                            Đặt lại mật khẩu
                        </Button>
                        {!admin.is_locked ? (
                            <Popconfirm
                                title="Khóa tài khoản admin này?"
                                description={isCurrentAdmin ? 'Không thể tự khóa tài khoản đang sử dụng.' : undefined}
                                disabled={!canLockAdmins || isCurrentAdmin}
                                onConfirm={() => onLockAdmin?.(admin.id, { reason: 'Khóa bởi quản trị viên.' })}
                            >
                                <Button danger size="small" disabled={!canLockAdmins || isCurrentAdmin}>
                                    Khóa
                                </Button>
                            </Popconfirm>
                        ) : (
                            <Button size="small" disabled={!canLockAdmins} onClick={() => onUnlockAdmin?.(admin.id)}>
                                Mở khóa
                            </Button>
                        )}
                    </Space>
                );
            },
        },
    ];

    return (
        <Card
            title="Admin Accounts"
            extra={(
                <Button type="primary" disabled={!canManageAdmins} onClick={onCreateAdmin}>
                    Tạo admin
                </Button>
            )}
        >
            <Space direction="vertical" size={4} style={{ marginBottom: 16 }}>
                <Text className="card-label">Admin Management</Text>
                <Title level={4}>Quản lý tài khoản admin, role, scope và trạng thái khóa/mở khóa</Title>
                <Paragraph>
                    Tài khoản admin có thể được gán role, data scope theo website/module/owner/tenant, đặt lại mật khẩu và khóa mở khóa trực tiếp.
                </Paragraph>
            </Space>

            <Table rowKey="id" columns={accountColumns} dataSource={adminAccounts ?? []} pagination={false} />
        </Card>
    );
}

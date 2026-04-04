import Avatar from 'antd/es/avatar';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Divider from 'antd/es/divider';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;

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

function getInitials(name) {
    return (name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'AD';
}

export default function AdminAccountDetailsDrawer({ open, admin, scopeTypes, canManageAdmins, canResetPassword, canLockAdmins, isCurrentAdmin, onEditAdmin, onOpenPasswordModal, onOpenLockModal, onUnlockAdmin, onClose }) {
    return (
        <Drawer
            title="Chi tiết admin account"
            placement="right"
            width="min(560px, 100vw)"
            open={open}
            onClose={onClose}
            className="admin-account-drawer"
            destroyOnHidden
        >
            {!admin ? (
                <Empty description="Chưa có admin nào được chọn." />
            ) : (
                <Space direction="vertical" size={20} style={{ width: '100%' }}>
                    <div className="admin-account-drawer-hero">
                        <Avatar className="admin-account-drawer-avatar" size={72}>
                            {getInitials(admin.name)}
                        </Avatar>
                        <div className="admin-account-drawer-hero-copy">
                            <Text className="card-label">Admin Profile</Text>
                            <Title level={3} style={{ margin: '4px 0 6px' }}>{admin.name}</Title>
                            <Paragraph style={{ marginBottom: 10 }}>{admin.email}</Paragraph>
                            <Space wrap>
                                <Tag color={admin.is_active ? 'green' : 'default'}>{admin.is_active ? 'active' : 'inactive'}</Tag>
                                {admin.is_locked ? <Tag color="red">locked</Tag> : <Tag color="cyan">ready</Tag>}
                                <Tag color="blue">{(admin.roles ?? []).length} roles</Tag>
                                <Tag color="purple">{(admin.scopes ?? []).length} scopes</Tag>
                            </Space>
                        </div>
                    </div>

                    <Card className="admin-account-drawer-action-card" title="Thao tác nhanh">
                        <Space wrap>
                            <Button type="primary" disabled={!canManageAdmins} onClick={onEditAdmin}>
                                Sửa admin
                            </Button>
                            <Button disabled={!canResetPassword} onClick={onOpenPasswordModal}>
                                Reset password
                            </Button>
                            {!admin.is_locked ? (
                                <Button danger disabled={!canLockAdmins || isCurrentAdmin} onClick={onOpenLockModal}>
                                    Khóa tài khoản
                                </Button>
                            ) : (
                                <Button disabled={!canLockAdmins} onClick={onUnlockAdmin}>
                                    Mở khóa tài khoản
                                </Button>
                            )}
                        </Space>
                        {isCurrentAdmin ? (
                            <Paragraph type="secondary" style={{ marginBottom: 0, marginTop: 12 }}>
                                Tài khoản đang đăng nhập không thể tự khóa trực tiếp từ drawer này.
                            </Paragraph>
                        ) : null}
                    </Card>

                    <div className="detail-grid detail-grid-2">
                        <div className="detail-tile">
                            <Text className="detail-label">Lần đăng nhập cuối</Text>
                            <Text strong>{formatLastLogin(admin.last_login_at)}</Text>
                        </div>
                        <div className="detail-tile">
                            <Text className="detail-label">Khóa tài khoản</Text>
                            <Text strong>{admin.is_locked ? 'Đang khóa' : 'Không'}</Text>
                        </div>
                        <div className="detail-tile detail-tile-wide">
                            <Text className="detail-label">Lý do khóa</Text>
                            <Text strong>{admin.locked_reason || 'Không có'}</Text>
                        </div>
                    </div>

                    <Card className="hero-card" title="Vai trò được gán">
                        {(admin.roles ?? []).length ? (
                            <div className="support-tag-list">
                                {(admin.roles ?? []).map((role) => (
                                    <Tag key={role.id} color="blue">{role.name}</Tag>
                                ))}
                            </div>
                        ) : (
                            <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Admin này chưa có role nào." />
                        )}
                    </Card>

                    <Card title="Data scope matrix">
                        {(admin.scopes ?? []).length ? (
                            <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                {(admin.scopes ?? []).map((scope) => (
                                    <div className="admin-scope-row" key={`${admin.id}-${scope.role_id}-${scope.scope_type}-${scope.scope_value}`}>
                                        <div>
                                            <Text className="detail-label">{scopeTypes?.[scope.scope_type] ?? scope.scope_type}</Text>
                                            <Title level={5} style={{ margin: '4px 0 0' }}>{scope.scope_value}</Title>
                                        </div>
                                        <Tag>{`role #${scope.role_id}`}</Tag>
                                    </div>
                                ))}
                            </Space>
                        ) : (
                            <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Admin này chưa được gán scope dữ liệu riêng." />
                        )}
                    </Card>

                    <Divider style={{ margin: 0 }} />

                    <div>
                        <Text className="detail-label">Permission snapshot</Text>
                        {(admin.permissions ?? []).length ? (
                            <div className="support-tag-list" style={{ marginTop: 8 }}>
                                {(admin.permissions ?? []).map((permission) => (
                                    <Tag key={permission}>{permission}</Tag>
                                ))}
                            </div>
                        ) : (
                            <Paragraph style={{ marginTop: 8, marginBottom: 0 }}>
                                Admin này hiện chưa phát sinh permission hiệu lực từ role.
                            </Paragraph>
                        )}
                    </div>
                </Space>
            )}
        </Drawer>
    );
}

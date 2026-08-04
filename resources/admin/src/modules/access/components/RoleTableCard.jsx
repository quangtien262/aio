import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Popconfirm from 'antd/es/popconfirm';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tooltip from 'antd/es/tooltip';
import Typography from 'antd/es/typography';

const { Text } = Typography;

export default function RoleTableCard({ roles, canManageRoles, onCreateRole, onEditRole, onDeleteRole, onOpenPermissionCatalog }) {
    const roleColumns = [
        {
            title: 'Vai trò',
            dataIndex: 'name',
            key: 'name',
            render: (_, role) => (
                <Space direction="vertical" size={0}>
                    <Text strong>{role.name}</Text>
                    <Text type="secondary">Mã: {role.key}</Text>
                    {role.is_system ? <Text type="warning">Vai trò hệ thống · không thể chỉnh sửa</Text> : null}
                </Space>
            ),
        },
        {
            title: 'Mô tả trách nhiệm',
            dataIndex: 'description',
            key: 'description',
            render: (value) => value || 'Không có mô tả',
        },
        {
            title: 'Số quyền',
            dataIndex: 'permissions_count',
            key: 'permissions_count',
            width: 90,
        },
        {
            title: 'Tài khoản',
            dataIndex: 'admins_count',
            key: 'admins_count',
            width: 100,
        },
        {
            title: 'Tác vụ',
            key: 'actions',
            width: 130,
            render: (_, role) => (
                <Space size={8} className="role-action-buttons">
                    <Tooltip title="Sửa vai trò">
                        <Button
                            className="role-action-button role-action-button-edit"
                            shape="circle"
                            icon={<EditOutlined />}
                            disabled={!canManageRoles || role.is_system}
                            onClick={() => onEditRole?.(role)}
                        />
                    </Tooltip>
                    <Popconfirm
                        disabled={!canManageRoles || role.is_system}
                        title="Xóa vai trò này?"
                        okText="Xóa"
                        cancelText="Hủy"
                        onConfirm={() => onDeleteRole?.(role.id)}
                    >
                        <Tooltip title="Xóa vai trò">
                            <Button
                                danger
                                className="role-action-button role-action-button-delete"
                                shape="circle"
                                icon={<DeleteOutlined />}
                                disabled={!canManageRoles || role.is_system}
                            />
                        </Tooltip>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    return (
        <Card
            className="admin-table-card"
            title="Danh sách vai trò"
            extra={(
                <Space wrap>
                    <Button icon={<SafetyCertificateOutlined />} onClick={onOpenPermissionCatalog}>
                        Danh mục quyền
                    </Button>
                    <Button type="primary" disabled={!canManageRoles} onClick={onCreateRole}>
                        Tạo vai trò
                    </Button>
                </Space>
            )}
        >
            <Table rowKey="id" columns={roleColumns} dataSource={roles} pagination={false} scroll={{ x: 760 }} />
        </Card>
    );
}

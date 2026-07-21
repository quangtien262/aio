import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Popconfirm from 'antd/es/popconfirm';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Typography from 'antd/es/typography';

const { Text } = Typography;

export default function RoleTableCard({ roles, canManageRoles, onCreateRole, onEditRole, onDeleteRole }) {
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
        },
        {
            title: 'Tài khoản',
            dataIndex: 'admins_count',
            key: 'admins_count',
        },
        {
            title: 'Tác vụ',
            key: 'actions',
            render: (_, role) => (
                <Space>
                    <Button size="small" disabled={!canManageRoles || role.is_system} onClick={() => onEditRole?.(role)}>
                        Sửa
                    </Button>
                    <Popconfirm
                        disabled={!canManageRoles || role.is_system}
                        title="Xóa role này?"
                        onConfirm={() => onDeleteRole?.(role.id)}
                    >
                        <Button danger size="small" disabled={!canManageRoles || role.is_system}>
                            Xóa
                        </Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    return (
        <Card
            className="admin-table-card"
            title="Danh sách vai trò"
            extra={<Button type="primary" disabled={!canManageRoles} onClick={onCreateRole}>Tạo vai trò</Button>}
        >
            <Table rowKey="id" columns={roleColumns} dataSource={roles} pagination={false} scroll={{ x: 760 }} />
        </Card>
    );
}

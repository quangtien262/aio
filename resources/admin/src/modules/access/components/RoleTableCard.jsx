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
            title: 'Role',
            dataIndex: 'name',
            key: 'name',
            render: (_, role) => (
                <Space direction="vertical" size={0}>
                    <Text strong>{role.name}</Text>
                    <Text type="secondary">{role.key}</Text>
                </Space>
            ),
        },
        {
            title: 'Description',
            dataIndex: 'description',
            key: 'description',
            render: (value) => value || 'Không có mô tả',
        },
        {
            title: 'Permissions',
            dataIndex: 'permissions_count',
            key: 'permissions_count',
        },
        {
            title: 'Admins',
            dataIndex: 'admins_count',
            key: 'admins_count',
        },
        {
            title: 'Tác vụ',
            key: 'actions',
            render: (_, role) => (
                <Space>
                    <Button size="small" disabled={!canManageRoles} onClick={() => onEditRole?.(role)}>
                        Sửa
                    </Button>
                    <Popconfirm
                        disabled={!canManageRoles || role.key === 'super-admin'}
                        title="Xóa role này?"
                        onConfirm={() => onDeleteRole?.(role.id)}
                    >
                        <Button danger size="small" disabled={!canManageRoles || role.key === 'super-admin'}>
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
            title="Role Management"
            extra={<Button type="primary" disabled={!canManageRoles} onClick={onCreateRole}>Tạo role</Button>}
        >
            <Table rowKey="id" columns={roleColumns} dataSource={roles} pagination={false} scroll={{ x: 760 }} />
        </Card>
    );
}

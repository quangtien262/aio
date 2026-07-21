import Card from 'antd/es/card';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Text } = Typography;

const MODULE_LABELS = {
    admin: 'Tài khoản quản trị',
    catalog: 'Danh mục & sản phẩm',
    cms: 'Nội dung website',
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

export default function PermissionCatalogCard({ groupedPermissions }) {
    return (
        <Card title="Danh mục quyền">
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                {Object.entries(groupedPermissions).map(([groupKey, items]) => (
                    <div key={groupKey}>
                        <Text strong>{MODULE_LABELS[groupKey] || groupKey}</Text>
                        <Space direction="vertical" size={8} style={{ width: '100%', marginTop: 8 }}>
                            {items.map((permission) => (
                                <Space key={permission.id} size={8} wrap>
                                    <Tag>{permission.display_name ?? permission.name ?? permission.key}</Tag>
                                </Space>
                            ))}
                        </Space>
                    </div>
                ))}
            </Space>
        </Card>
    );
}

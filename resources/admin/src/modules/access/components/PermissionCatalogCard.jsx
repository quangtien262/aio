import Card from 'antd/es/card';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Text } = Typography;

export default function PermissionCatalogCard({ groupedPermissions }) {
    return (
        <Card title="Permission Catalog">
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                {Object.entries(groupedPermissions).map(([groupKey, items]) => (
                    <div key={groupKey}>
                        <Text strong>{groupKey}</Text>
                        <div style={{ marginTop: 8 }}>
                            {items.map((permission) => (
                                <Tag key={permission.id} style={{ marginBottom: 8 }}>
                                    {permission.key}
                                </Tag>
                            ))}
                        </div>
                    </div>
                ))}
            </Space>
        </Card>
    );
}

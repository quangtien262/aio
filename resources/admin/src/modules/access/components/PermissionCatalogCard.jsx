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
                        <Space direction="vertical" size={8} style={{ width: '100%', marginTop: 8 }}>
                            {items.map((permission) => (
                                <Space key={permission.id} size={8} wrap>
                                    <Tag>{permission.display_name ?? permission.name ?? permission.key}</Tag>
                                    <Text type="secondary">{permission.key}</Text>
                                </Space>
                            ))}
                        </Space>
                    </div>
                ))}
            </Space>
        </Card>
    );
}

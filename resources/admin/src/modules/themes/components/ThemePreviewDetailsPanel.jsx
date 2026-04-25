import Button from 'antd/es/button';
import Empty from 'antd/es/empty';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;

function renderSupportTags(supports) {
    const entries = Object.entries(supports ?? {});

    if (!entries.length) {
        return 'Không có';
    }

    return (
        <div className="support-tag-list">
            {entries.map(([key, value]) => (
                <Tag key={key} color={value ? 'green' : 'default'}>
                    {key}: {value ? 'on' : 'off'}
                </Tag>
            ))}
        </div>
    );
}

export default function ThemePreviewDetailsPanel({ theme, canActivate, onOpenActivateDialog }) {
    if (!theme) {
        return <Empty description="Chưa có theme nào để xem chi tiết." />;
    }

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <div>
                <Space wrap>
                    <Title level={4} style={{ margin: 0 }}>{theme.name}</Title>
                    <Tag color="gold">{theme.website_type}</Tag>
                    <Tag color={theme.is_active ? 'green' : 'default'}>{theme.is_active ? 'active' : theme.status}</Tag>
                </Space>
                <Paragraph style={{ marginBottom: 0 }}>{theme.description || 'Theme chưa có mô tả.'}</Paragraph>
            </div>

            {!theme.is_active ? (
                <Button type="primary" disabled={!canActivate} onClick={() => onOpenActivateDialog?.(theme)}>
                    Kích hoạt theme
                </Button>
            ) : (
                <Text type="success">Theme này đang được kích hoạt cho website hiện tại.</Text>
            )}

            <div>
                <Text strong style={{ display: 'block', marginBottom: 12 }}>Preview khối giao diện</Text>
                {(theme.blocks ?? []).length ? (
                    <Space direction="vertical" size={8} style={{ width: '100%' }}>
                        {(theme.blocks ?? []).map((blockName, index) => (
                            <div
                                key={`${theme.key}-${blockName}`}
                                style={{
                                    border: '1px solid #d9e6e2',
                                    borderRadius: 12,
                                    padding: '10px 12px',
                                    background: index % 2 === 0 ? '#f7fbfa' : '#eef7f4',
                                }}
                            >
                                <Text strong>{blockName}</Text>
                            </div>
                        ))}
                    </Space>
                ) : (
                    <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Theme chưa khai báo blocks preview." />
                )}
            </div>

            <div className="detail-grid">
                {[
                    ['Key', theme.key],
                    ['Version', theme.version],
                    ['Parent', theme.parent || 'Không có'],
                    ['Thumbnail asset', theme.preview?.thumbnail || 'Không có'],
                    ['Cover asset', theme.preview?.cover || 'Không có'],
                    ['Demo content', theme.demo?.content_path || 'Không có'],
                    ['Demo settings', theme.demo?.settings_path || 'Không có'],
                    ['Installed at', theme.installed_at || 'Chưa cài đặt'],
                    ['Activated at', theme.activated_at || 'Chưa kích hoạt'],
                ].map(([label, value]) => (
                    <div key={label} className="detail-tile">
                        <Text className="detail-label">{label}</Text>
                        <Text strong>{value}</Text>
                    </div>
                ))}
                <div className="detail-tile detail-tile-wide">
                    <Text className="detail-label">Supports</Text>
                    {renderSupportTags(theme.supports)}
                </div>
            </div>
        </Space>
    );
}

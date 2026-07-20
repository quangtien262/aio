import Button from 'antd/es/button';
import Empty from 'antd/es/empty';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { BgColorsOutlined, DatabaseOutlined } from '@ant-design/icons';

const { Paragraph, Text, Title } = Typography;

function formatAdminDateTime(value, emptyLabel) {
    if (!value) {
        return emptyLabel;
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(parsed);
}

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

export default function ThemePreviewDetailsPanel({ theme, canActivate, canOpenPalette = false, canGenerateDemoData = false, onOpenActivateDialog, onOpenPalette, onOpenDemoCreate }) {
    if (!theme) {
        return <Empty description="Chưa có theme nào để xem chi tiết." />;
    }

    const thumbnailUrl = theme.preview_urls?.thumbnail || null;
    const coverUrl = theme.preview_urls?.cover || null;

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

            {coverUrl || thumbnailUrl ? (
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    {coverUrl ? (
                        <img
                            src={coverUrl}
                            alt={`${theme.name} cover`}
                            style={{ width: '100%', height: 196, objectFit: 'cover', borderRadius: 18, border: '1px solid #d9e6e2', display: 'block' }}
                        />
                    ) : null}
                    {thumbnailUrl ? (
                        <div style={{ display: 'flex', justifyContent: 'flex-start' }}>
                            <img
                                src={thumbnailUrl}
                                alt={`${theme.name} thumbnail`}
                                style={{ width: 180, height: 120, objectFit: 'cover', borderRadius: 18, border: '1px solid #d9e6e2', display: 'block' }}
                            />
                        </div>
                    ) : null}
                </Space>
            ) : null}

            <Space wrap>
                {!theme.is_active ? (
                    <Button type="primary" disabled={!canActivate} onClick={() => onOpenActivateDialog?.(theme)}>
                        Kích hoạt theme
                    </Button>
                ) : (
                    <Text type="success">Theme này đang được kích hoạt cho website hiện tại.</Text>
                )}

                {canOpenPalette ? (
                    <Button icon={<BgColorsOutlined />} onClick={() => onOpenPalette?.(theme)}>
                        Palette theme
                    </Button>
                ) : null}

                {['XD321', 'XD0322', 'XD0323', 'SER102', 'TH0050', 'SHOP603'].includes(theme.key) ? (
                    <Button icon={<DatabaseOutlined />} disabled={!canGenerateDemoData} onClick={() => onOpenDemoCreate?.(theme)}>
                        Tạo data test
                    </Button>
                ) : null}
            </Space>

            <div className="detail-grid">
                {[
                    ['Key', theme.key],
                    ['Version', theme.version],
                    ['Installed at', formatAdminDateTime(theme.installed_at, 'Chưa cài đặt')],
                    ['Activated at', formatAdminDateTime(theme.activated_at, 'Chưa kích hoạt')],
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

import { adminApi } from '../../../shared/config/routes';
import Button from 'antd/es/button';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Text } = Typography;

const statusColorMap = {
    available: 'default',
    installed: 'blue',
    active: 'green',
};

export default function ThemeListTable({ themes, themesMeta = {}, selectedThemeKey, onSelectTheme, onOpenPreview, callAdminApi, runAdminAction }) {
    const canManageAvatar = Boolean(themesMeta?.can_manage_theme_avatar);

    const uploadAvatar = async (theme, file) => {
        if (!file) return;

        const formData = new FormData();
        formData.append('avatar', file);

        await runAdminAction(() => callAdminApi(adminApi(`themes/${encodeURIComponent(theme.key)}/avatar`), { method: 'POST', body: formData }), 'Đã cập nhật ảnh đại diện.', () => { window.location.reload(); });
    };

    const columns = [
        {
            title: 'Ảnh',
            key: 'avatar',
            width: 96,
            render: (_, theme) => (
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <img src={theme.avatar_url ?? theme.preview_urls?.thumbnail ?? ''} alt={theme.name} style={{ width: 56, height: 56, objectFit: 'cover', borderRadius: 8, background: '#f4f7f6', border: '1px solid #e6efec' }} />
                    {canManageAvatar ? (
                        <label style={{ cursor: 'pointer', fontSize: 12, color: '#1677ff' }}>
                            <input type="file" accept="image/*" style={{ display: 'none' }} onChange={(e) => uploadAvatar(theme, e.target.files?.[0])} />
                            Đổi
                        </label>
                    ) : null}
                </div>
            ),
        },
        {
            title: 'Theme',
            key: 'theme',
            render: (_, theme) => (
                <Space direction="vertical" size={0}>
                    <Button
                        type="link"
                        style={{ padding: 0, justifyContent: 'flex-start', height: 'auto', fontWeight: 600 }}
                        onClick={(event) => {
                            event.stopPropagation();
                            onOpenPreview?.(theme.key);
                        }}
                    >
                        {theme.name}
                    </Button>
                    <Text type="secondary">{theme.key}</Text>
                </Space>
            ),
        },
        {
            title: 'Loại website',
            dataIndex: 'website_type',
            key: 'website_type',
            render: (websiteType) => <Tag color="gold">{websiteType}</Tag>,
        },
        {
            title: 'Trạng thái',
            dataIndex: 'status',
            key: 'status',
            render: (status, theme) => (
                <Space>
                    <Tag color={statusColorMap[status] ?? 'default'}>{status}</Tag>
                    {theme.is_active ? <Tag color="green">active</Tag> : null}
                </Space>
            ),
        },
        {
            title: 'Version',
            dataIndex: 'version',
            key: 'version',
        },
        {
            title: 'Blocks',
            key: 'blocks',
            render: (_, theme) => (theme.blocks ?? []).length || 0,
        },
    ];

    return (
        <Table
            rowKey="key"
            columns={columns}
            dataSource={themes}
            pagination={false}
            scroll={{ x: 720 }}
            rowClassName={(record) => (record.key === selectedThemeKey ? 'ant-table-row-selected' : '')}
            onRow={(record) => ({
                onClick: () => onSelectTheme?.(record.key),
            })}
        />
    );
}

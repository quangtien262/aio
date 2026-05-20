import Card from 'antd/es/card';
import Menu from 'antd/es/menu';
import { GlobalOutlined, BgColorsOutlined, MessageOutlined, FileTextOutlined, SettingOutlined, ReloadOutlined, DeleteOutlined } from '@ant-design/icons';

export default function ThemeActionMenuCard({
    theme = null,
    canManageThemeActions = false,
    frontendLocale = 'vi',
    defaultFrontendLocale = 'vi',
    onOpenLocale,
    onOpenPalette,
    onOpenThemeTranslations,
    onOpenFrontendTranslations,
    onOpenDemoCreate,
    onOpenSetup,
    onOpenRebuild,
    onOpenDelete,
}) {
    const canOpenThemeActions = Boolean(theme) && canManageThemeActions;
    const canOpenPalette = canOpenThemeActions && theme?.key === 'TH0002';
    const canDeleteDemoData = canOpenThemeActions && Boolean(theme?.has_demo_data);

    return (
        <Card size="small" title="Actions">
            <Menu mode="vertical" selectable={false} style={{ borderRadius: 8, boxShadow: '0 6px 18px rgba(0,0,0,0.04)', padding: 8 }}>
                <Menu.Item key="lang" icon={<GlobalOutlined />} disabled={!canOpenThemeActions} onClick={() => onOpenLocale?.(theme)} style={{ marginBottom: 6 }}>
                    Quản lý ngôn ngữ
                </Menu.Item>
                <Menu.Item key="palette" icon={<BgColorsOutlined />} disabled={!canOpenPalette} onClick={() => onOpenPalette?.(theme)} style={{ marginBottom: 6 }}>
                    Palette theme
                </Menu.Item>
                <Menu.Item key="theme-translate" icon={<FileTextOutlined />} disabled={!canOpenThemeActions} onClick={() => onOpenThemeTranslations?.(theme)} style={{ marginBottom: 6 }}>
                    bản dịch của theme
                </Menu.Item>
                <Menu.Item key="frontend-translate" icon={<MessageOutlined />} disabled={!canOpenThemeActions} onClick={() => onOpenFrontendTranslations?.(theme)} style={{ marginBottom: 6 }}>
                    Bản dịch frontend (default {defaultFrontendLocale.toUpperCase()}, xem {frontendLocale.toUpperCase()})
                </Menu.Item>
                <Menu.Item key="demo-create" icon={<FileTextOutlined />} disabled={!canOpenThemeActions} onClick={() => onOpenDemoCreate?.(theme)} style={{ marginBottom: 6 }}>
                    Tạo data test
                </Menu.Item>
                <Menu.Item key="setup" icon={<SettingOutlined />} onClick={() => onOpenSetup?.()} style={{ marginBottom: 6, fontWeight: 600 }}>
                    Cài đặt website
                </Menu.Item>
                <Menu.Item key="rebuild" icon={<ReloadOutlined />} disabled={!canOpenThemeActions} onClick={() => onOpenRebuild?.(theme)} style={{ marginBottom: 6 }}>
                    Rebuild curated local demo
                </Menu.Item>
                <Menu.Item key="delete" icon={<DeleteOutlined />} disabled={!canDeleteDemoData} onClick={() => onOpenDelete?.(theme)} style={{ marginBottom: 6 }}>
                    <span style={{ color: 'var(--ant-danger-color)' }}>Xóa data test</span>
                </Menu.Item>
            </Menu>
        </Card>
    );
}
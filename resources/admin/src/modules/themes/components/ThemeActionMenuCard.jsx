import Card from 'antd/es/card';
import Menu from 'antd/es/menu';
import { AppstoreOutlined, GlobalOutlined, BgColorsOutlined, MessageOutlined, FileTextOutlined, SettingOutlined, ReloadOutlined, DeleteOutlined, LinkOutlined } from '@ant-design/icons';

export default function ThemeActionMenuCard({
    theme = null,
    canViewThemeManager = false,
    canManageThemeActions = false,
    frontendLocale = 'vi',
    defaultFrontendLocale = 'vi',
    onOpenThemeManager,
    onOpenLocale,
    onOpenPalette,
    onOpenThemeTranslations,
    onOpenFrontendTranslations,
    onOpenDemoCreate,
    onOpenSiteMappings,
    onOpenSetup,
    onOpenRebuild,
    onOpenDelete,
    isThemeManagerActive = false,
    isSiteMappingsActive = false,
    isSetupActive = false,
}) {
    const canOpenThemeActions = Boolean(theme) && canManageThemeActions;
    const canOpenPalette = canOpenThemeActions && Boolean(theme?.supports?.custom_css ?? true);
    const canDeleteDemoData = canOpenThemeActions && Boolean(theme?.has_demo_data);
    const items = [
        {
            key: 'theme-manager',
            icon: <AppstoreOutlined />,
            label: 'QL theme',
            disabled: !canViewThemeManager || isThemeManagerActive,
            onClick: () => onOpenThemeManager?.(),
            style: { marginBottom: 6, fontWeight: isThemeManagerActive ? 600 : 400 },
        },
        {
            key: 'setup',
            icon: <SettingOutlined />,
            label: 'Cài đặt website',
            disabled: isSetupActive,
            onClick: () => onOpenSetup?.(),
            style: { marginBottom: 6, fontWeight: isSetupActive ? 600 : 400 },
        },
        {
            key: 'site-mappings',
            icon: <LinkOutlined />,
            label: 'Cấu hình domain',
            disabled: isSiteMappingsActive,
            onClick: () => onOpenSiteMappings?.(),
            style: { marginBottom: 6, fontWeight: isSiteMappingsActive ? 600 : 400 },
        },
        {
            key: 'lang',
            icon: <GlobalOutlined />,
            label: 'Quản lý ngôn ngữ',
            disabled: !canOpenThemeActions,
            onClick: () => onOpenLocale?.(theme),
            style: { marginBottom: 6 },
        },
        {
            key: 'palette',
            icon: <BgColorsOutlined />,
            label: 'Palette theme',
            disabled: !canOpenPalette,
            onClick: () => onOpenPalette?.(theme),
            style: { marginBottom: 6 },
        },
        {
            key: 'theme-translate',
            icon: <FileTextOutlined />,
            label: 'bản dịch của theme',
            disabled: !canOpenThemeActions,
            onClick: () => onOpenThemeTranslations?.(theme),
            style: { marginBottom: 6 },
        },
        {
            key: 'frontend-translate',
            icon: <MessageOutlined />,
            label: `Bản dịch frontend (default ${defaultFrontendLocale.toUpperCase()}, xem ${frontendLocale.toUpperCase()})`,
            disabled: !canOpenThemeActions,
            onClick: () => onOpenFrontendTranslations?.(theme),
            style: { marginBottom: 6 },
        },
        {
            key: 'demo-create',
            icon: <FileTextOutlined />,
            label: 'Tạo data test',
            disabled: !canOpenThemeActions,
            onClick: () => onOpenDemoCreate?.(theme),
            style: { marginBottom: 6 },
        },
        {
            key: 'rebuild',
            icon: <ReloadOutlined />,
            label: 'Rebuild curated local demo',
            disabled: !canOpenThemeActions,
            onClick: () => onOpenRebuild?.(theme),
            style: { marginBottom: 6 },
        },
        {
            key: 'delete',
            icon: <DeleteOutlined />,
            label: <span style={{ color: 'var(--ant-danger-color)' }}>Xóa data test</span>,
            disabled: !canDeleteDemoData,
            onClick: () => onOpenDelete?.(theme),
            style: { marginBottom: 6 },
        },
    ];

    return (
        <Card size="small" title="Actions">
            <Menu mode="vertical" selectable={false} items={items} style={{ borderRadius: 8, boxShadow: '0 6px 18px rgba(0,0,0,0.04)', padding: 8 }} />
        </Card>
    );
}

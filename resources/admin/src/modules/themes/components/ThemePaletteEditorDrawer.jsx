import { useEffect, useState } from 'react';
import Button from 'antd/es/button';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;

function buildDefaultPalette(themeKey) {
    if (themeKey === 'TH0002') {
        return {
            primary_color: '#d67a2c',
            primary_color_deep: '#af5f1f',
            accent_color: '#d98d4a',
            accent_soft_color: '#efaa4c',
            background_color: '#faf6f1',
            surface_color: '#ffffff',
            surface_tint_color: '#fff4e8',
        };
    }

    return {
        primary_color: '#0f766e',
        primary_color_deep: '#115e59',
        accent_color: '#14b8a6',
        accent_soft_color: '#99f6e4',
        background_color: '#f8fafc',
        surface_color: '#ffffff',
        surface_tint_color: '#f0fdfa',
    };
}

function PaletteColorField({ label, value, fallback, disabled, onChange }) {
    return (
        <Form.Item label={label}>
            <Space wrap align="center">
                <input
                    disabled={disabled}
                    type="color"
                    value={value || fallback}
                    onChange={(event) => onChange(event.target.value)}
                    style={{ width: 48, height: 40, border: '1px solid #d9e6e2', borderRadius: 8, padding: 4, background: 'white' }}
                />
                <Input
                    disabled={disabled}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={fallback}
                    style={{ width: 160 }}
                />
            </Space>
        </Form.Item>
    );
}

export default function ThemePaletteEditorDrawer({ open, theme, siteProfile, canManagePalette, runAdminAction, callAdminApi, onClose, onSaved }) {
    const defaults = buildDefaultPalette(theme?.key);
    const branding = siteProfile?.branding ?? {};
    const siteName = siteProfile?.site_name ?? 'AIO Website';
    const websiteType = siteProfile?.website_type ?? theme?.website_type ?? 'ecommerce';
    const [primaryColor, setPrimaryColor] = useState(branding.primary_color ?? defaults.primary_color);
    const [primaryColorDeep, setPrimaryColorDeep] = useState(branding.primary_color_deep ?? defaults.primary_color_deep);
    const [accentColor, setAccentColor] = useState(branding.accent_color ?? defaults.accent_color);
    const [accentSoftColor, setAccentSoftColor] = useState(branding.accent_soft_color ?? defaults.accent_soft_color);
    const [backgroundColor, setBackgroundColor] = useState(branding.background_color ?? defaults.background_color);
    const [surfaceColor, setSurfaceColor] = useState(branding.surface_color ?? defaults.surface_color);
    const [surfaceTintColor, setSurfaceTintColor] = useState(branding.surface_tint_color ?? defaults.surface_tint_color);

    useEffect(() => {
        setPrimaryColor(branding.primary_color ?? defaults.primary_color);
        setPrimaryColorDeep(branding.primary_color_deep ?? defaults.primary_color_deep);
        setAccentColor(branding.accent_color ?? defaults.accent_color);
        setAccentSoftColor(branding.accent_soft_color ?? defaults.accent_soft_color);
        setBackgroundColor(branding.background_color ?? defaults.background_color);
        setSurfaceColor(branding.surface_color ?? defaults.surface_color);
        setSurfaceTintColor(branding.surface_tint_color ?? defaults.surface_tint_color);
    }, [branding.accent_color, branding.accent_soft_color, branding.background_color, branding.primary_color, branding.primary_color_deep, branding.surface_color, branding.surface_tint_color, defaults]);

    if (!theme) {
        return null;
    }

    return (
        <Drawer
            title={`Palette theme: ${theme.name}`}
            open={open}
            width={520}
            onClose={onClose}
            destroyOnHidden
            extra={(
                <Button
                    type="primary"
                    disabled={!canManagePalette}
                    onClick={() => runAdminAction(
                        () => callAdminApi('/admin/api/setup', {
                            method: 'PUT',
                            body: JSON.stringify({
                                site_name: siteName,
                                website_type: websiteType,
                                primary_color: primaryColor,
                                primary_color_deep: primaryColorDeep,
                                accent_color: accentColor,
                                accent_soft_color: accentSoftColor,
                                background_color: backgroundColor,
                                surface_color: surfaceColor,
                                surface_tint_color: surfaceTintColor,
                            }),
                        }),
                        'Đã lưu palette của theme.',
                        async () => {
                            await onSaved?.();
                            onClose?.();
                        },
                    )}
                >
                    Lưu palette
                </Button>
            )}
        >
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <div>
                    <Text className="card-label">Theme Palette</Text>
                    <Title level={5} style={{ marginTop: 0, marginBottom: 8 }}>Bộ màu storefront của {theme.key}</Title>
                    <Paragraph style={{ marginBottom: 0 }}>
                        Palette này được lưu trong DB qua branding và toàn bộ view TH0002 sẽ đọc lại qua CSS token chung.
                    </Paragraph>
                </div>

                <Form layout="vertical">
                    <div className="setup-form-grid setup-form-grid-branding">
                        <PaletteColorField label="Primary color" value={primaryColor} fallback={defaults.primary_color} disabled={!canManagePalette} onChange={setPrimaryColor} />
                        <PaletteColorField label="Primary color deep" value={primaryColorDeep} fallback={defaults.primary_color_deep} disabled={!canManagePalette} onChange={setPrimaryColorDeep} />
                        <PaletteColorField label="Accent color" value={accentColor} fallback={defaults.accent_color} disabled={!canManagePalette} onChange={setAccentColor} />
                        <PaletteColorField label="Accent soft color" value={accentSoftColor} fallback={defaults.accent_soft_color} disabled={!canManagePalette} onChange={setAccentSoftColor} />
                        <PaletteColorField label="Background color" value={backgroundColor} fallback={defaults.background_color} disabled={!canManagePalette} onChange={setBackgroundColor} />
                        <PaletteColorField label="Surface color" value={surfaceColor} fallback={defaults.surface_color} disabled={!canManagePalette} onChange={setSurfaceColor} />
                        <PaletteColorField label="Surface tint color" value={surfaceTintColor} fallback={defaults.surface_tint_color} disabled={!canManagePalette} onChange={setSurfaceTintColor} />
                    </div>
                </Form>
            </Space>
        </Drawer>
    );
}

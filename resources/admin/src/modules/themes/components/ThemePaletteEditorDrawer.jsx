import { useEffect, useMemo, useState } from 'react';
import Button from 'antd/es/button';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;

function buildDefaultPalette(themeKey) {
    if (themeKey === 'LAN0201') {
        return {
            primary_color: '#0f3557',
            primary_color_deep: '#0a2741',
            accent_color: '#c7923e',
            accent_soft_color: '#e6c98e',
            background_color: '#f5f1ea',
            surface_color: '#ffffff',
            surface_tint_color: '#f8f4ee',
        };
    }

    if (themeKey === 'TH0001') {
        return {
            primary_color: '#ef2b2d',
            primary_color_deep: '#d91c20',
            accent_color: '#79c400',
            accent_soft_color: '#86c440',
            background_color: '#f6f6f8',
            surface_color: '#ffffff',
            surface_tint_color: '#fff7f5',
        };
    }

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

    if (themeKey === 'SER0100') {
        return {
            primary_color: '#c2410c',
            primary_color_deep: '#ea580c',
            accent_color: '#1f6f78',
            accent_soft_color: '#f59e0b',
            background_color: '#f7fbfd',
            surface_color: '#ffffff',
            surface_tint_color: '#eef5f7',
        };
    }

    if (themeKey === 'SER0101') {
        return {
            primary_color: '#0f766e',
            primary_color_deep: '#0f5d56',
            accent_color: '#f0b429',
            accent_soft_color: '#f6d365',
            background_color: '#f6faf7',
            surface_color: '#ffffff',
            surface_tint_color: '#eef6f4',
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

function PalettePreviewCard({ primaryColor, primaryColorDeep, accentColor, accentSoftColor, backgroundColor, surfaceColor, surfaceTintColor }) {
    const chipStyle = {
        width: 44,
        height: 44,
        borderRadius: 12,
        border: '1px solid rgba(15, 23, 42, 0.08)',
        boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.35)',
    };

    return (
        <div
            style={{
                padding: 16,
                borderRadius: 18,
                background: `linear-gradient(135deg, ${backgroundColor} 0%, ${surfaceTintColor} 100%)`,
                border: '1px solid rgba(15, 23, 42, 0.08)',
            }}
        >
            <Space direction="vertical" size={12} style={{ width: '100%' }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
                    <div>
                        <Text className="card-label">Live Preview</Text>
                        <Title level={5} style={{ margin: '4px 0 0' }}>2 tông màu chủ đạo</Title>
                    </div>
                    <Space size={8}>
                        <div style={{ ...chipStyle, background: primaryColor }} title={`Primary: ${primaryColor}`} />
                        <div style={{ ...chipStyle, background: accentColor }} title={`Accent: ${accentColor}`} />
                    </Space>
                </div>

                <div
                    style={{
                        borderRadius: 16,
                        overflow: 'hidden',
                        background: surfaceColor,
                        boxShadow: '0 12px 32px rgba(15, 23, 42, 0.08)',
                    }}
                >
                    <div style={{ height: 10, background: `linear-gradient(90deg, ${primaryColor} 0%, ${accentColor} 100%)` }} />
                    <div style={{ padding: 16 }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginBottom: 12 }}>
                            <div>
                                <div style={{ color: primaryColor, fontSize: 18, fontWeight: 800, lineHeight: 1.2 }}>Menu / Header</div>
                                <div style={{ color: '#64748b', fontSize: 12 }}>Preview nhanh trước khi lưu</div>
                            </div>
                            <div
                                style={{
                                    padding: '6px 12px',
                                    borderRadius: 999,
                                    background: accentSoftColor,
                                    color: primaryColorDeep,
                                    fontSize: 12,
                                    fontWeight: 700,
                                }}
                            >
                                Accent soft
                            </div>
                        </div>

                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: 12,
                            }}
                        >
                            <div
                                style={{
                                    padding: 14,
                                    borderRadius: 14,
                                    background: surfaceTintColor,
                                    border: `1px solid ${accentSoftColor}`,
                                }}
                            >
                                <div style={{ color: primaryColor, fontWeight: 700, marginBottom: 6 }}>Primary block</div>
                                <div style={{ color: '#64748b', fontSize: 12 }}>Dùng cho menu, CTA, giá, tiêu đề mạnh.</div>
                            </div>
                            <div
                                style={{
                                    padding: 14,
                                    borderRadius: 14,
                                    background: '#fff',
                                    border: `1px solid ${accentSoftColor}`,
                                }}
                            >
                                <div style={{ color: accentColor, fontWeight: 700, marginBottom: 6 }}>Accent block</div>
                                <div style={{ color: '#64748b', fontSize: 12 }}>Dùng cho nhấn phụ, badge, tab và khối hỗ trợ.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </Space>
        </div>
    );
}

export default function ThemePaletteEditorDrawer({ open, theme, siteProfile, canManagePalette, runAdminAction, callAdminApi, onClose, onSaved }) {
    const defaults = useMemo(() => buildDefaultPalette(theme?.key), [theme?.key]);
    const branding = siteProfile?.branding ?? {};
    const themePalette = useMemo(
        () => siteProfile?.theme_palettes?.[theme?.key?.toUpperCase?.() ?? ''] ?? {},
        [siteProfile?.theme_palettes, theme?.key],
    );
    const [primaryColor, setPrimaryColor] = useState(themePalette.primary_color ?? branding.primary_color ?? defaults.primary_color);
    const [primaryColorDeep, setPrimaryColorDeep] = useState(themePalette.primary_color_deep ?? branding.primary_color_deep ?? defaults.primary_color_deep);
    const [accentColor, setAccentColor] = useState(themePalette.accent_color ?? branding.accent_color ?? defaults.accent_color);
    const [accentSoftColor, setAccentSoftColor] = useState(themePalette.accent_soft_color ?? branding.accent_soft_color ?? defaults.accent_soft_color);
    const [backgroundColor, setBackgroundColor] = useState(themePalette.background_color ?? branding.background_color ?? defaults.background_color);
    const [surfaceColor, setSurfaceColor] = useState(themePalette.surface_color ?? branding.surface_color ?? defaults.surface_color);
    const [surfaceTintColor, setSurfaceTintColor] = useState(themePalette.surface_tint_color ?? branding.surface_tint_color ?? defaults.surface_tint_color);

    useEffect(() => {
        setPrimaryColor(themePalette.primary_color ?? branding.primary_color ?? defaults.primary_color);
        setPrimaryColorDeep(themePalette.primary_color_deep ?? branding.primary_color_deep ?? defaults.primary_color_deep);
        setAccentColor(themePalette.accent_color ?? branding.accent_color ?? defaults.accent_color);
        setAccentSoftColor(themePalette.accent_soft_color ?? branding.accent_soft_color ?? defaults.accent_soft_color);
        setBackgroundColor(themePalette.background_color ?? branding.background_color ?? defaults.background_color);
        setSurfaceColor(themePalette.surface_color ?? branding.surface_color ?? defaults.surface_color);
        setSurfaceTintColor(themePalette.surface_tint_color ?? branding.surface_tint_color ?? defaults.surface_tint_color);
    }, [branding.accent_color, branding.accent_soft_color, branding.background_color, branding.primary_color, branding.primary_color_deep, branding.surface_color, branding.surface_tint_color, defaults, themePalette.accent_color, themePalette.accent_soft_color, themePalette.background_color, themePalette.primary_color, themePalette.primary_color_deep, themePalette.surface_color, themePalette.surface_tint_color]);

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
                        () => callAdminApi(`/admin/api/themes/${theme.key}/palette`, {
                            method: 'PUT',
                            body: JSON.stringify({
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
                        Palette này được lưu riêng theo từng theme. Khi storefront đang chạy theme hiện tại, hệ thống sẽ ưu tiên palette của đúng theme đó thay vì màu global trong branding.
                    </Paragraph>
                </div>

                <PalettePreviewCard
                    primaryColor={primaryColor || defaults.primary_color}
                    primaryColorDeep={primaryColorDeep || defaults.primary_color_deep}
                    accentColor={accentColor || defaults.accent_color}
                    accentSoftColor={accentSoftColor || defaults.accent_soft_color}
                    backgroundColor={backgroundColor || defaults.background_color}
                    surfaceColor={surfaceColor || defaults.surface_color}
                    surfaceTintColor={surfaceTintColor || defaults.surface_tint_color}
                />

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

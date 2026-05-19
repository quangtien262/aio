import React from 'react';
import Button from 'antd/es/button';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Text } = Typography;

export default function ThemeGrid({ themes = [], selectedThemeKey, onSelectTheme, onOpenPreview }) {
    if (!Array.isArray(themes)) return null;

    return (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: 16 }}>
            {themes.map((theme) => (
                <div
                    key={theme.key}
                    onClick={() => {
                        onSelectTheme?.(theme.key);
                        onOpenPreview?.(theme.key);
                    }}
                    style={{ cursor: 'pointer', textAlign: 'center', position: 'relative' }}
                >
                    <div style={{ width: '100%', aspectRatio: '1 / 1', overflow: 'hidden', borderRadius: 8, background: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: theme.key === selectedThemeKey ? '0 6px 22px rgba(0,0,0,0.12)' : '0 4px 12px rgba(0,0,0,0.06)' }}>
                        {theme.is_active ? (
                            <Tag color="green" style={{ position: 'absolute', top: 8, right: 8, zIndex: 3 }}>Đang kích hoạt</Tag>
                        ) : null}
                        <img src={theme.preview_urls?.thumbnail ?? theme.avatar_url ?? ''} alt={theme.name} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    </div>
                        <div style={{ marginTop: 8 }}>
                            <div style={{ fontWeight: 700 }}>{theme.name}</div>
                            {theme.website_type ? (
                                <div style={{ marginTop: 4 }}>
                                    <Text type="secondary" style={{ fontSize: 12 }}>{theme.website_type}</Text>
                                </div>
                            ) : null}
                        </div>
                </div>
            ))}
        </div>
    );
}

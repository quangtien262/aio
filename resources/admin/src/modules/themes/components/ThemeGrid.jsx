import React from 'react';
import Button from 'antd/es/button';
import Space from 'antd/es/space';

export default function ThemeGrid({ themes = [], selectedThemeKey, onSelectTheme, onOpenPreview }) {
    if (!Array.isArray(themes)) return null;

    return (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: 16 }}>
            {themes.map((theme) => (
                <div
                    key={theme.key}
                    onClick={() => onSelectTheme?.(theme.key)}
                    style={{ cursor: 'pointer', textAlign: 'center' }}
                >
                    <div style={{ width: '100%', aspectRatio: '1 / 1', overflow: 'hidden', borderRadius: 8, background: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: theme.key === selectedThemeKey ? '0 6px 22px rgba(0,0,0,0.12)' : '0 4px 12px rgba(0,0,0,0.06)' }}>
                        <img src={theme.preview_urls?.thumbnail ?? theme.avatar_url ?? ''} alt={theme.name} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    </div>
                    <div style={{ marginTop: 8 }}>
                        <div style={{ fontWeight: 700 }}>{theme.name}</div>
                    </div>
                </div>
            ))}
        </div>
    );
}

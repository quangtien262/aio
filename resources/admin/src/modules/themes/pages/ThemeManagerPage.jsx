import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Row from 'antd/es/row';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;
const ThemeListTable = lazy(() => import('../components/ThemeListTable'));
const ThemePreviewDetailsPanel = lazy(() => import('../components/ThemePreviewDetailsPanel'));
const ThemeActivateDialog = lazy(() => import('../components/ThemeActivateDialog'));

export default function ThemeManagerPage({ themes, onActivate, canActivate }) {
    const [selectedThemeKey, setSelectedThemeKey] = useState(null);
    const [activateThemeKey, setActivateThemeKey] = useState(null);

    useEffect(() => {
        if (!themes?.length) {
            setSelectedThemeKey(null);
            return;
        }

        const activeTheme = themes.find((theme) => theme.is_active);
        const fallbackThemeKey = activeTheme?.key ?? themes[0].key;

        if (!themes.some((theme) => theme.key === selectedThemeKey)) {
            setSelectedThemeKey(fallbackThemeKey);
        }
    }, [selectedThemeKey, themes]);

    const selectedTheme = useMemo(() => themes.find((theme) => theme.key === selectedThemeKey) ?? null, [selectedThemeKey, themes]);
    const activateTheme = useMemo(() => themes.find((theme) => theme.key === activateThemeKey) ?? null, [activateThemeKey, themes]);

    return (
        <Card title="Theme Engine Flow">
            <Space direction="vertical" size={4} style={{ marginBottom: 16 }}>
                <Text className="card-label">Theme Activation</Text>
                <Paragraph style={{ marginBottom: 0 }}>
                    Tách riêng danh sách theme, panel preview/chi tiết và hộp thoại kích hoạt để admin shell chỉ tải phần cần dùng.
                </Paragraph>
            </Space>

            <Row gutter={[16, 16]}>
                <Col xs={24} xl={15}>
                    <Suspense fallback={<Card loading title="Theme List" />}>
                        <ThemeListTable themes={themes} selectedThemeKey={selectedThemeKey} onSelectTheme={setSelectedThemeKey} />
                    </Suspense>
                </Col>

                <Col xs={24} xl={9}>
                    <Suspense fallback={<Card loading title="Theme Preview" />}>
                        <ThemePreviewDetailsPanel
                            theme={selectedTheme}
                            canActivate={canActivate}
                            onOpenActivateDialog={(theme) => setActivateThemeKey(theme.key)}
                        />
                    </Suspense>
                </Col>
            </Row>

            {activateThemeKey ? (
                <Suspense fallback={null}>
                    <ThemeActivateDialog
                        open={Boolean(activateThemeKey)}
                        theme={activateTheme}
                        canActivate={canActivate}
                        onCancel={() => setActivateThemeKey(null)}
                        onConfirm={async (themeKey) => {
                            await onActivate?.(themeKey);
                            setActivateThemeKey(null);
                        }}
                    />
                </Suspense>
            ) : null}
        </Card>
    );
}

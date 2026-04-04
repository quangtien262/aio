import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Row from 'antd/es/row';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;
const ThemeListTable = lazy(() => import('../components/ThemeListTable'));
const ThemePreviewDetailsPanel = lazy(() => import('../components/ThemePreviewDetailsPanel'));
const ThemeActivateDialog = lazy(() => import('../components/ThemeActivateDialog'));
const ThemeDemoDataModal = lazy(() => import('../components/ThemeDemoDataModal'));

export default function ThemeManagerPage({ themes, onActivate, onGenerateDemoData, canActivate, canGenerateDemoData }) {
    const [selectedThemeKey, setSelectedThemeKey] = useState(null);
    const [activateThemeKey, setActivateThemeKey] = useState(null);
    const [demoThemeKey, setDemoThemeKey] = useState(null);

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
    const demoTheme = useMemo(() => themes.find((theme) => theme.key === demoThemeKey) ?? null, [demoThemeKey, themes]);

    return (
        <Card
            title="Theme Engine Flow"
            extra={(
                <Button disabled={!selectedTheme || !canGenerateDemoData} onClick={() => setDemoThemeKey(selectedTheme?.key ?? null)}>
                    Tạo data test
                </Button>
            )}
        >
            <Space direction="vertical" size={4} style={{ marginBottom: 16 }}>
                <Text className="card-label">Theme Activation</Text>
                <Paragraph style={{ marginBottom: 0 }}>
                    Tách riêng danh sách theme, panel preview/chi tiết và hộp thoại kích hoạt để admin shell chỉ tải phần cần dùng. Data test phục vụ review nhanh giao diện TH0001 với dữ liệu thật.
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

            {demoThemeKey ? (
                <Suspense fallback={null}>
                    <ThemeDemoDataModal
                        open={Boolean(demoThemeKey)}
                        theme={demoTheme}
                        canGenerateDemoData={canGenerateDemoData}
                        onCancel={() => setDemoThemeKey(null)}
                        onSubmit={async (preset) => {
                            const didGenerate = await onGenerateDemoData?.(demoThemeKey, preset);

                            if (didGenerate !== false) {
                                setDemoThemeKey(null);
                            }

                            return didGenerate;
                        }}
                    />
                </Suspense>
            ) : null}
        </Card>
    );
}

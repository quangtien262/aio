import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { EyeOutlined } from '@ant-design/icons';
import { useNavigate, useSearchParams } from 'react-router-dom';
import ThemeActionMenuCard from '../components/ThemeActionMenuCard';
import ThemeTranslationDrawer from '../components/ThemeTranslationDrawer';

const { Paragraph, Text } = Typography;
const ThemeGrid = lazy(() => import('../components/ThemeGrid'));
const ThemePreviewDetailsPanel = lazy(() => import('../components/ThemePreviewDetailsPanel'));
const ThemeActivateDialog = lazy(() => import('../components/ThemeActivateDialog'));
const ThemeDemoDataModal = lazy(() => import('../components/ThemeDemoDataModal'));
const ThemeLocaleDrawer = lazy(() => import('../components/ThemeLocaleDrawer'));
const ThemePaletteEditorDrawer = lazy(() => import('../components/ThemePaletteEditorDrawer'));

export default function ThemeManagerPage({ themes, themesMeta = {}, activeTheme = null, siteProfile = null, onActivate, onGenerateDemoData, onDeleteDemoData, onSaveThemePalette, canActivate, canGenerateDemoData, callAdminApi, runAdminAction, frontendLocale = 'vi', defaultFrontendLocale = 'vi' }) {
    const [selectedThemeKey, setSelectedThemeKey] = useState(null);
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useSearchParams();
    const [previewThemeKey, setPreviewThemeKey] = useState(null);
    const [activateThemeKey, setActivateThemeKey] = useState(null);
    const [demoThemeKey, setDemoThemeKey] = useState(null);
    const [demoActionMode, setDemoActionMode] = useState('generate');
    const [translationThemeKey, setTranslationThemeKey] = useState(null);
    const [themeBlocksThemeKey, setThemeBlocksThemeKey] = useState(null);
    const [localeThemeKey, setLocaleThemeKey] = useState(null);
    const [paletteThemeKey, setPaletteThemeKey] = useState(null);
    const [deleteThemeKey, setDeleteThemeKey] = useState(null);

    useEffect(() => {
        if (!themes?.length) {
            setSelectedThemeKey(null);
            setPreviewThemeKey(null);
            return;
        }

        const activeTheme = themes.find((theme) => theme.is_active);
        const fallbackThemeKey = activeTheme?.key ?? themes[0].key;

        if (!themes.some((theme) => theme.key === selectedThemeKey)) {
            setSelectedThemeKey(fallbackThemeKey);
        }

        if (previewThemeKey && !themes.some((theme) => theme.key === previewThemeKey)) {
            setPreviewThemeKey(null);
        }
    }, [previewThemeKey, selectedThemeKey, themes]);

    const selectedTheme = useMemo(() => themes.find((theme) => theme.key === selectedThemeKey) ?? null, [selectedThemeKey, themes]);
    const previewTheme = useMemo(() => themes.find((theme) => theme.key === previewThemeKey) ?? null, [previewThemeKey, themes]);
    const activeThemeFromList = useMemo(() => themes.find((t) => t.is_active) ?? activeTheme ?? null, [themes, activeTheme]);
    const activateTheme = useMemo(() => themes.find((theme) => theme.key === activateThemeKey) ?? null, [activateThemeKey, themes]);
    const demoTheme = useMemo(() => themes.find((theme) => theme.key === demoThemeKey) ?? null, [demoThemeKey, themes]);
    const translationTheme = useMemo(() => themes.find((theme) => theme.key === translationThemeKey) ?? null, [themes, translationThemeKey]);
    const themeBlocksTheme = useMemo(() => themes.find((theme) => theme.key === themeBlocksThemeKey) ?? null, [themeBlocksThemeKey, themes]);
    const localeTheme = useMemo(() => themes.find((theme) => theme.key === localeThemeKey) ?? null, [localeThemeKey, themes]);
    const paletteTheme = useMemo(() => themes.find((theme) => theme.key === paletteThemeKey) ?? null, [paletteThemeKey, themes]);
    const deleteTheme = useMemo(() => themes.find((theme) => theme.key === deleteThemeKey) ?? null, [deleteThemeKey, themes]);
    const requestedAction = searchParams.get('action');
    const requestedThemeKey = searchParams.get('theme');

    const clearRequestedAction = () => {
        const nextParams = new URLSearchParams(searchParams);
        nextParams.delete('action');
        nextParams.delete('theme');
        setSearchParams(nextParams, { replace: true });
    };

    const handleOpenPreview = (themeKey) => {
        setSelectedThemeKey(themeKey);
        setPreviewThemeKey(themeKey);
    };

    useEffect(() => {
        if (!themes?.length || !requestedAction) {
            return;
        }

        const fallbackThemeKey = activeThemeFromList?.key ?? themes[0]?.key ?? null;
        const targetThemeKey = themes.some((theme) => theme.key === requestedThemeKey)
            ? requestedThemeKey
            : (selectedThemeKey ?? fallbackThemeKey);

        if (!targetThemeKey) {
            clearRequestedAction();
            return;
        }

        if (selectedThemeKey !== targetThemeKey) {
            setSelectedThemeKey(targetThemeKey);
            return;
        }

        const targetTheme = themes.find((theme) => theme.key === targetThemeKey) ?? null;

        switch (requestedAction) {
        case 'locale':
            if (canGenerateDemoData) {
                setLocaleThemeKey(targetThemeKey);
            }
            break;
        case 'palette':
            if (canGenerateDemoData && targetTheme?.key === 'TH0002') {
                setPaletteThemeKey(targetThemeKey);
            }
            break;
        case 'theme-translate':
            if (canGenerateDemoData) {
                setThemeBlocksThemeKey(targetThemeKey);
            }
            break;
        case 'frontend-translate':
            if (canGenerateDemoData) {
                setTranslationThemeKey(targetThemeKey);
            }
            break;
        case 'demo-create':
            if (canGenerateDemoData) {
                setDemoActionMode('generate');
                setDemoThemeKey(targetThemeKey);
            }
            break;
        case 'rebuild':
            if (canGenerateDemoData) {
                setDemoActionMode('rebuild');
                setDemoThemeKey(targetThemeKey);
            }
            break;
        case 'delete':
            if (canGenerateDemoData && targetTheme?.has_demo_data) {
                setDeleteThemeKey(targetThemeKey);
            }
            break;
        default:
            break;
        }

        clearRequestedAction();
    }, [activeThemeFromList?.key, canGenerateDemoData, requestedAction, requestedThemeKey, searchParams, selectedThemeKey, setSearchParams, themes]);

    return (
        <div style={{ display: 'flex', gap: 20, alignItems: 'flex-start' }}>
            <aside style={{ width: 280 }}>
                <ThemeActionMenuCard
                    theme={selectedTheme}
                    canManageThemeActions={canGenerateDemoData}
                    frontendLocale={frontendLocale}
                    defaultFrontendLocale={defaultFrontendLocale}
                    onOpenLocale={(theme) => setLocaleThemeKey(theme?.key ?? null)}
                    onOpenPalette={(theme) => setPaletteThemeKey(theme?.key ?? null)}
                    onOpenThemeTranslations={(theme) => setThemeBlocksThemeKey(theme?.key ?? null)}
                    onOpenFrontendTranslations={(theme) => setTranslationThemeKey(theme?.key ?? null)}
                    onOpenDemoCreate={(theme) => {
                        setDemoActionMode('generate');
                        setDemoThemeKey(theme?.key ?? null);
                    }}
                    onOpenSetup={() => navigate('../setup')}
                    onOpenRebuild={(theme) => {
                        setDemoActionMode('rebuild');
                        setDemoThemeKey(theme?.key ?? null);
                    }}
                    onOpenDelete={(theme) => setDeleteThemeKey(theme?.key ?? null)}
                />
            </aside>
            <div style={{ flex: 1 }}>
                <Card title="Theme Engine Flow">
            <Space direction="vertical" size={4} style={{ marginBottom: 16 }}>
                <Text className="card-label" strong>Theme Activation</Text>
                <Paragraph style={{ marginBottom: 0 }}>
                    Danh sách theme và preview được tách riêng để chỉ mở chi tiết khi cần. Bấm vào tiêu đề theme để xem preview trong drawer và thao tác kích hoạt nhanh.
                </Paragraph>
            </Space>

            {activeThemeFromList ? (
                <div style={{ marginBottom: 16 }}>
                    <Card size="small" bordered style={{ display: 'flex', gap: 16, alignItems: 'center' }}>
                        <div style={{ width: 260, flex: '0 0 260px', borderRadius: 12, overflow: 'hidden', background: '#fff', boxShadow: '0 4px 12px rgba(0,0,0,0.06)' }}>
                            <img src={activeThemeFromList.preview_urls?.cover ?? activeThemeFromList.preview_urls?.thumbnail ?? activeThemeFromList.avatar_url ?? ''} alt={activeThemeFromList.name} style={{ width: '100%', height: 150, objectFit: 'cover', display: 'block' }} />
                        </div>

                        <div style={{ flex: 1 }}>
                            <div style={{ display: 'flex', gap: 8, alignItems: 'center', justifyContent: 'space-between' }}>
                                <div>
                                    <div style={{ fontWeight: 700, fontSize: 16 }}>{activeThemeFromList.name}</div>
                                    <div style={{ marginTop: 8 }}>
                                        {activeThemeFromList.website_type ? <Tag color="gold">{activeThemeFromList.website_type}</Tag> : null}
                                        <Tag color={activeThemeFromList.is_active ? 'green' : 'default'} style={{ marginLeft: 8 }}>{activeThemeFromList.is_active ? 'Đang kích hoạt' : (activeThemeFromList.status ?? '')}</Tag>
                                    </div>
                                </div>

                                <div style={{ display: 'flex', gap: 8 }}>
                                    <Button icon={<EyeOutlined />} onClick={() => handleOpenPreview(activeThemeFromList.key)}>Xem chi tiết</Button>
                                </div>
                            </div>

                            <div style={{ marginTop: 12 }}>{activeThemeFromList.description ?? 'Theme chưa có mô tả.'}</div>
                        </div>
                    </Card>
                </div>
            ) : null}

            <Suspense fallback={<Card loading title="Theme List" />}>
                <div style={{ marginBottom: 16 }}>
                    <ThemeGrid
                        themes={themes}
                        selectedThemeKey={selectedThemeKey}
                        onSelectTheme={setSelectedThemeKey}
                        onOpenPreview={handleOpenPreview}
                    />
                    </div>
            </Suspense>

            <Drawer
                title={previewTheme ? `Theme Preview: ${previewTheme.name}` : 'Theme Preview'}
                open={Boolean(previewTheme)}
                width={520}
                onClose={() => setPreviewThemeKey(null)}
                destroyOnHidden
            >
                <Suspense fallback={<Card loading title="Theme Preview" />}>
                    <ThemePreviewDetailsPanel
                        theme={previewTheme}
                        canActivate={canActivate}
                        onOpenActivateDialog={(theme) => setActivateThemeKey(theme.key)}
                    />
                </Suspense>
            </Drawer>

            {activateThemeKey ? (
                <Suspense fallback={null}>
                    <ThemeActivateDialog
                        open={Boolean(activateThemeKey)}
                        theme={activateTheme}
                        currentTheme={activeTheme}
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
                        mode={demoActionMode}
                        canGenerateDemoData={canGenerateDemoData}
                        onCancel={() => {
                            setDemoThemeKey(null);
                            setDemoActionMode('generate');
                        }}
                        onSubmit={async (preset) => {
                            const didGenerate = await onGenerateDemoData?.(demoThemeKey, preset);

                            if (didGenerate !== false) {
                                setDemoThemeKey(null);
                                setDemoActionMode('generate');
                            }

                            return didGenerate;
                        }}
                    />
                </Suspense>
            ) : null}

            {themeBlocksThemeKey ? (
                <ThemeTranslationDrawer
                    open={Boolean(themeBlocksThemeKey)}
                    theme={themeBlocksTheme}
                    locale={frontendLocale}
                    canManageTranslations={canGenerateDemoData}
                    callAdminApi={callAdminApi}
                    runAdminAction={runAdminAction}
                    initialGroup="content"
                    initialEntity="theme"
                    title={themeBlocksTheme ? `bản dịch của theme: ${themeBlocksTheme.name} (${frontendLocale.toUpperCase()})` : 'bản dịch của theme'}
                    description="Màn này mở thẳng các block riêng của đúng theme đang chọn, ví dụ như khối Báo giá trong ngày, Tin mới hoặc các hero/footer đặc thù."
                    onClose={() => setThemeBlocksThemeKey(null)}
                />
            ) : null}

            {translationThemeKey ? (
                <ThemeTranslationDrawer
                    open={Boolean(translationThemeKey)}
                    theme={translationTheme}
                    locale={frontendLocale}
                    canManageTranslations={canGenerateDemoData}
                    callAdminApi={callAdminApi}
                    runAdminAction={runAdminAction}
                    onClose={() => setTranslationThemeKey(null)}
                />
            ) : null}

            {localeThemeKey ? (
                <Suspense fallback={null}>
                    <ThemeLocaleDrawer
                        open={Boolean(localeThemeKey)}
                        theme={localeTheme}
                        canManageLocales={canGenerateDemoData}
                        callAdminApi={callAdminApi}
                        runAdminAction={runAdminAction}
                        onClose={() => setLocaleThemeKey(null)}
                    />
                </Suspense>
            ) : null}

            {paletteThemeKey ? (
                <Suspense fallback={null}>
                    <ThemePaletteEditorDrawer
                        open={Boolean(paletteThemeKey)}
                        theme={paletteTheme}
                        siteProfile={siteProfile}
                        canManagePalette={canGenerateDemoData}
                        callAdminApi={callAdminApi}
                        runAdminAction={runAdminAction}
                        onSaved={onSaveThemePalette}
                        onClose={() => setPaletteThemeKey(null)}
                    />
                </Suspense>
            ) : null}

            <Modal
                title={deleteTheme ? `Xóa data test: ${deleteTheme.name}` : 'Xóa data test'}
                open={Boolean(deleteThemeKey)}
                onCancel={() => setDeleteThemeKey(null)}
                onOk={async () => {
                    const didDelete = await onDeleteDemoData?.(deleteThemeKey);

                    if (didDelete !== false) {
                        setDeleteThemeKey(null);
                    }
                }}
                okText="Xóa data test"
                cancelText="Hủy"
                okButtonProps={{ danger: true, disabled: !deleteTheme || !canGenerateDemoData || !deleteTheme?.has_demo_data }}
                destroyOnHidden
            >
                <Paragraph style={{ marginBottom: 0 }}>
                    Thao tác này chỉ xóa dữ liệu test do hệ thống tạo và đã được gắn marker demo cho theme hiện tại.
                </Paragraph>
            </Modal>
                </Card>
            </div>
        </div>
    );
}

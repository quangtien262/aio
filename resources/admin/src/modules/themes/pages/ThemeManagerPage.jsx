import { Suspense, lazy, useCallback, useEffect, useMemo, useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import Pagination from 'antd/es/pagination';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { EyeOutlined } from '@ant-design/icons';
import { useNavigate, useSearchParams } from 'react-router-dom';
import ThemeActionMenuCard from '../components/ThemeActionMenuCard';
import ThemeActionOverlayHost from '../components/ThemeActionOverlayHost';
import useThemeActionOverlayController from '../hooks/useThemeActionOverlayController';

const { Paragraph, Text } = Typography;
const ThemeGrid = lazy(() => import('../components/ThemeGrid'));
const ThemePreviewDetailsPanel = lazy(() => import('../components/ThemePreviewDetailsPanel'));
const ThemeActivateDialog = lazy(() => import('../components/ThemeActivateDialog'));
const SiteDomainMappingPanel = lazy(() => import('../components/SiteDomainMappingPanel'));
const THEME_PAGE_SIZES = [12, 24, 48];
const DEFAULT_THEME_PAGE_SIZE = THEME_PAGE_SIZES[0];

function positiveInteger(value, fallback) {
    const parsed = Number.parseInt(value, 10);

    return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

export default function ThemeManagerPage({ themes, themesMeta = {}, activeTheme = null, siteProfile = null, onActivate, onGenerateDemoData, onDeleteDemoData, onSaveThemePalette, canActivate, canGenerateDemoData, callAdminApi, runAdminAction, frontendLocale = 'vi', defaultFrontendLocale = 'vi' }) {
    const [selectedThemeKey, setSelectedThemeKey] = useState(null);
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useSearchParams();
    const [previewThemeKey, setPreviewThemeKey] = useState(null);
    const [activateThemeKey, setActivateThemeKey] = useState(null);
    const [activePanel, setActivePanel] = useState('themes');
    const themeActionController = useThemeActionOverlayController();

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
    const requestedAction = searchParams.get('action');
    const requestedThemeKey = searchParams.get('theme');
    const themeKeyword = searchParams.get('q') ?? '';
    const requestedThemePage = positiveInteger(searchParams.get('page'), 1);
    const requestedThemePageSize = positiveInteger(searchParams.get('per_page'), DEFAULT_THEME_PAGE_SIZE);
    const themePageSize = THEME_PAGE_SIZES.includes(requestedThemePageSize)
        ? requestedThemePageSize
        : DEFAULT_THEME_PAGE_SIZE;
    const filteredThemes = useMemo(() => {
        const normalizedKeyword = themeKeyword.trim().toLocaleLowerCase('vi');

        if (!normalizedKeyword) {
            return themes ?? [];
        }

        return (themes ?? []).filter((theme) => String(theme?.name ?? '')
            .toLocaleLowerCase('vi')
            .includes(normalizedKeyword));
    }, [themeKeyword, themes]);
    const themeLastPage = Math.max(1, Math.ceil(filteredThemes.length / themePageSize));
    const themePage = Math.min(requestedThemePage, themeLastPage);
    const paginatedThemes = useMemo(() => {
        const offset = (themePage - 1) * themePageSize;

        return filteredThemes.slice(offset, offset + themePageSize);
    }, [filteredThemes, themePage, themePageSize]);

    const updateThemeListParams = useCallback((updates, replace = true) => {
        const nextParams = new URLSearchParams(searchParams);

        Object.entries(updates).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                nextParams.delete(key);
                return;
            }

            nextParams.set(key, String(value));
        });

        setSearchParams(nextParams, { replace });
    }, [searchParams, setSearchParams]);

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
                themeActionController.openLocale(targetTheme);
            }
            break;
        case 'palette':
            if (canGenerateDemoData && targetTheme?.supports?.custom_css) {
                themeActionController.openPalette(targetTheme);
            }
            break;
        case 'theme-translate':
            if (canGenerateDemoData) {
                themeActionController.openThemeTranslations(targetTheme);
            }
            break;
        case 'frontend-translate':
            if (canGenerateDemoData) {
                themeActionController.openFrontendTranslations(targetTheme);
            }
            break;
        case 'demo-create':
            if (canGenerateDemoData) {
                themeActionController.openDemoCreate(targetTheme);
            }
            break;
        case 'rebuild':
            if (canGenerateDemoData) {
                themeActionController.openRebuild(targetTheme);
            }
            break;
        case 'delete':
            if (canGenerateDemoData && targetTheme?.has_demo_data) {
                themeActionController.openDelete(targetTheme);
            }
            break;
        default:
            break;
        }

        clearRequestedAction();
    }, [activeThemeFromList?.key, canGenerateDemoData, requestedAction, requestedThemeKey, searchParams, selectedThemeKey, setSearchParams, themeActionController, themes]);

    useEffect(() => {
        if (requestedThemePage <= themeLastPage) {
            return;
        }

        updateThemeListParams({ page: themeLastPage === 1 ? null : themeLastPage });
    }, [requestedThemePage, themeLastPage, updateThemeListParams]);

    return (
        <div style={{ display: 'flex', gap: 20, alignItems: 'flex-start' }}>
            <aside style={{ width: 280 }}>
                <ThemeActionMenuCard
                    theme={selectedTheme}
                    canViewThemeManager
                    canManageThemeActions={canGenerateDemoData}
                    frontendLocale={frontendLocale}
                    defaultFrontendLocale={defaultFrontendLocale}
                    onOpenThemeManager={() => setActivePanel('themes')}
                    onOpenLocale={themeActionController.openLocale}
                    onOpenPalette={themeActionController.openPalette}
                    onOpenThemeTranslations={themeActionController.openThemeTranslations}
                    onOpenFrontendTranslations={themeActionController.openFrontendTranslations}
                    onOpenDemoCreate={themeActionController.openDemoCreate}
                    onOpenSiteMappings={() => setActivePanel('site-mappings')}
                    onOpenSetup={() => navigate('../setup')}
                    onOpenRebuild={themeActionController.openRebuild}
                    onOpenDelete={themeActionController.openDelete}
                    isThemeManagerActive={activePanel === 'themes'}
                    isSiteMappingsActive={activePanel === 'site-mappings'}
                />
            </aside>
            <div style={{ flex: 1 }}>
                {activePanel === 'site-mappings' ? (
                    <Suspense fallback={<Card loading title="Domain demo" />}>
                        <SiteDomainMappingPanel
                            callAdminApi={callAdminApi}
                            runAdminAction={runAdminAction}
                            canManage={canGenerateDemoData}
                            themes={themes}
                        />
                    </Suspense>
                ) : (
                <Card title="Quản lý các mẫu giao diện website" bordered={false} style={{ marginBottom: 16 }}>
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
                            <img src={activeThemeFromList.avatar_url ?? activeThemeFromList.preview_urls?.thumbnail ?? ''} alt={activeThemeFromList.name} style={{ width: '100%', height: 150, objectFit: 'cover', display: 'block' }} />
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
                    <Space wrap style={{ width: '100%', justifyContent: 'space-between', marginBottom: 16 }}>
                        <Input.Search
                            allowClear
                            value={themeKeyword}
                            placeholder="Tìm kiếm theo tên theme..."
                            aria-label="Tìm kiếm theo tên theme"
                            style={{ width: 360, maxWidth: '100%' }}
                            onChange={(event) => updateThemeListParams({
                                q: event.target.value || null,
                                page: null,
                            })}
                        />
                        <Text type="secondary">
                            {themeKeyword.trim()
                                ? `Tìm thấy ${filteredThemes.length} theme`
                                : `Tổng cộng ${filteredThemes.length} theme`}
                        </Text>
                    </Space>

                    {paginatedThemes.length ? (
                        <ThemeGrid
                            themes={paginatedThemes}
                            selectedThemeKey={selectedThemeKey}
                            onSelectTheme={setSelectedThemeKey}
                            onOpenPreview={handleOpenPreview}
                        />
                    ) : (
                        <Empty description="Không tìm thấy theme phù hợp" />
                    )}

                    {filteredThemes.length > DEFAULT_THEME_PAGE_SIZE ? (
                        <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 24 }}>
                            <Pagination
                                current={themePage}
                                pageSize={themePageSize}
                                total={filteredThemes.length}
                                showSizeChanger
                                pageSizeOptions={THEME_PAGE_SIZES}
                                showTotal={(total, range) => `${range[0]}-${range[1]} / ${total} theme`}
                                onChange={(page, pageSize) => updateThemeListParams({
                                    page: page > 1 ? page : null,
                                    per_page: pageSize !== DEFAULT_THEME_PAGE_SIZE ? pageSize : null,
                                })}
                            />
                        </div>
                    ) : null}
                </div>
            </Suspense>

            <Drawer
                title={previewTheme ? `Theme Preview: ${previewTheme.name}` : 'Theme Preview'}
                open={Boolean(previewTheme)}
                width="min(1000px, 100vw)"
                onClose={() => setPreviewThemeKey(null)}
                destroyOnHidden
            >
                <Suspense fallback={<Card loading title="Theme Preview" />}>
                    <ThemePreviewDetailsPanel
                        theme={previewTheme}
                        canActivate={canActivate}
                        canOpenPalette={canGenerateDemoData && Boolean(previewTheme?.supports?.custom_css)}
                        canGenerateDemoData={canGenerateDemoData}
                        onOpenActivateDialog={(theme) => setActivateThemeKey(theme.key)}
                        onOpenPalette={(theme) => {
                            setPreviewThemeKey(null);
                            themeActionController.openPalette(theme);
                        }}
                        onOpenDemoCreate={(theme) => {
                            setPreviewThemeKey(null);
                            themeActionController.openDemoCreate(theme);
                        }}
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
                        onConfirm={async (themeKey, options) => {
                            await onActivate?.(themeKey, options);
                            setActivateThemeKey(null);
                        }}
                    />
                </Suspense>
            ) : null}

            <ThemeActionOverlayHost
                state={themeActionController.overlayState}
                themes={themes}
                siteProfile={siteProfile}
                canManageThemeActions={canGenerateDemoData}
                callAdminApi={callAdminApi}
                runAdminAction={runAdminAction}
                frontendLocale={frontendLocale}
                onGenerateDemoData={onGenerateDemoData}
                onDeleteDemoData={onDeleteDemoData}
                onSaveThemePalette={onSaveThemePalette}
                onClose={themeActionController.closeOverlay}
            />
                </Card>
                )}
            </div>
        </div>
    );
}

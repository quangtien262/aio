import { Suspense, lazy, useMemo } from 'react';
import Modal from 'antd/es/modal';
import Typography from 'antd/es/typography';
import ThemeTranslationDrawer from './ThemeTranslationDrawer';

const { Paragraph } = Typography;
const ThemeDemoDataModal = lazy(() => import('./ThemeDemoDataModal'));
const ThemeLocaleDrawer = lazy(() => import('./ThemeLocaleDrawer'));
const ThemePaletteEditorDrawer = lazy(() => import('./ThemePaletteEditorDrawer'));

export default function ThemeActionOverlayHost({
    state,
    themes,
    siteProfile = null,
    canManageThemeActions,
    callAdminApi,
    runAdminAction,
    frontendLocale = 'vi',
    onGenerateDemoData,
    onDeleteDemoData,
    onSaveThemePalette,
    onClose,
}) {
    const activeType = state?.type ?? null;
    const activeThemeKey = state?.themeKey ?? null;
    const activeTheme = useMemo(
        () => themes.find((theme) => theme.key === activeThemeKey) ?? null,
        [activeThemeKey, themes],
    );

    if (!activeType || !activeTheme) {
        return null;
    }

    return (
        <>
            {activeType === 'theme-translate' ? (
                <ThemeTranslationDrawer
                    open
                    theme={activeTheme}
                    locale={frontendLocale}
                    canManageTranslations={canManageThemeActions}
                    callAdminApi={callAdminApi}
                    runAdminAction={runAdminAction}
                    initialGroup="content"
                    initialEntity="theme"
                    title={`bản dịch của theme: ${activeTheme.name} (${frontendLocale.toUpperCase()})`}
                    description="Màn này mở thẳng các block riêng của đúng theme đang chọn, ví dụ như khối Báo giá trong ngày, Tin mới hoặc các hero/footer đặc thù."
                    onClose={onClose}
                />
            ) : null}

            {activeType === 'frontend-translate' ? (
                <ThemeTranslationDrawer
                    open
                    theme={activeTheme}
                    locale={frontendLocale}
                    canManageTranslations={canManageThemeActions}
                    callAdminApi={callAdminApi}
                    runAdminAction={runAdminAction}
                    onClose={onClose}
                />
            ) : null}

            {activeType === 'locale' ? (
                <Suspense fallback={null}>
                    <ThemeLocaleDrawer
                        open
                        theme={activeTheme}
                        canManageLocales={canManageThemeActions}
                        callAdminApi={callAdminApi}
                        runAdminAction={runAdminAction}
                        onClose={onClose}
                    />
                </Suspense>
            ) : null}

            {activeType === 'palette' ? (
                <Suspense fallback={null}>
                    <ThemePaletteEditorDrawer
                        open
                        theme={activeTheme}
                        siteProfile={siteProfile}
                        canManagePalette={canManageThemeActions}
                        callAdminApi={callAdminApi}
                        runAdminAction={runAdminAction}
                        onSaved={onSaveThemePalette}
                        onClose={onClose}
                    />
                </Suspense>
            ) : null}

            {activeType === 'demo-create' || activeType === 'rebuild' ? (
                <Suspense fallback={null}>
                    <ThemeDemoDataModal
                        open
                        theme={activeTheme}
                        mode={activeType === 'rebuild' ? 'rebuild' : 'generate'}
                        canGenerateDemoData={canManageThemeActions}
                        onCancel={onClose}
                        onSubmit={async (preset, options = {}) => {
                            const didGenerate = await onGenerateDemoData?.(activeTheme.key, preset, options);

                            if (didGenerate !== false) {
                                onClose?.();
                            }

                            return didGenerate;
                        }}
                    />
                </Suspense>
            ) : null}

            {activeType === 'delete' ? (
                <Modal
                    title={`Xóa data test: ${activeTheme.name}`}
                    open
                    onCancel={onClose}
                    onOk={async () => {
                        const didDelete = await onDeleteDemoData?.(activeTheme.key);

                        if (didDelete !== false) {
                            onClose?.();
                        }
                    }}
                    okText="Xóa data test"
                    cancelText="Hủy"
                    okButtonProps={{ danger: true, disabled: !canManageThemeActions || !activeTheme.has_demo_data }}
                    destroyOnHidden
                >
                    <Paragraph style={{ marginBottom: 0 }}>
                        Thao tác này chỉ xóa dữ liệu test do hệ thống tạo và đã được gắn marker demo cho theme hiện tại.
                    </Paragraph>
                </Modal>
            ) : null}
        </>
    );
}

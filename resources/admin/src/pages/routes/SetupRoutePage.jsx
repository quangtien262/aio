import { adminApi } from '../../shared/config/routes';
import Alert from 'antd/es/alert';
import Card from 'antd/es/card';
import { useLocation, useNavigate } from 'react-router-dom';
import SetupWizardPage from '../../modules/setup/pages/SetupWizardPage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

export default function SetupRoutePage({ canAccess, canComplete, canViewThemeManager, canManageThemeActions, callAdminApi, runAdminAction, frontendLocale, defaultFrontendLocale }) {
    const location = useLocation();
    const navigate = useNavigate();
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const [setupPayload, themesPayload] = await Promise.all([
                callAdminApi(`${adminApi('setup')}?locale=${encodeURIComponent(frontendLocale)}`),
                callAdminApi(adminApi('themes')),
            ]);

            return {
                setup: setupPayload.data ?? null,
                themes: themesPayload.data ?? [],
            };
        },
        deps: [frontendLocale],
        cacheKey: `admin.route.setup.${frontendLocale}`,
    });

    const pushSetupStepFeedback = (stepKey) => {
        const nextParams = new URLSearchParams(location.search);
        nextParams.set('focusStep', stepKey);
        nextParams.set('completedStep', stepKey);

        navigate({ pathname: location.pathname, search: `?${nextParams.toString()}` }, { replace: true });
    };

    if (loading && !data) {
        return <Card loading title="Setup" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    return (
        <SetupWizardPage
            setup={data?.setup ?? null}
            themes={data?.themes ?? []}
            activeTheme={(data?.themes ?? []).find((theme) => theme.is_active) ?? null}
            onSaveProfile={async (payload) => {
                const didSave = await runAdminAction(
                    () => callAdminApi(adminApi('setup'), {
                        method: 'PUT',
                        body: JSON.stringify({ ...payload, locale: frontendLocale }),
                    }),
                    'Đã lưu cấu hình setup.',
                    reload,
                );

                if (didSave) {
                    pushSetupStepFeedback('branding');
                }
            }}
            onCompleteStep={async (stepKey) => {
                const didComplete = await runAdminAction(
                    () => callAdminApi(adminApi(`setup/steps/${stepKey}`), { method: 'POST' }),
                    'Đã cập nhật bước setup.',
                    reload,
                );

                if (didComplete && stepKey === 'finish') {
                    pushSetupStepFeedback('finish');
                }
            }}
            canEditProfile={canComplete}
            canCompleteSteps={canComplete}
            canViewThemeManager={canViewThemeManager}
            canManageThemeActions={canManageThemeActions}
            frontendLocale={frontendLocale}
            defaultFrontendLocale={defaultFrontendLocale}
            onGenerateDemoData={(themeKey, preset) => runAdminAction(
                () => callAdminApi(adminApi(`themes/${themeKey}/demo-data`), { method: 'POST', body: JSON.stringify({ preset }) }),
                'Đã tạo data test cho theme.',
                reload,
            )}
            onDeleteDemoData={(themeKey) => runAdminAction(
                () => callAdminApi(adminApi(`themes/${themeKey}/demo-data`), { method: 'DELETE' }),
                'Đã xóa data test cho theme.',
                reload,
            )}
            onSaveThemePalette={reload}
            runAdminAction={runAdminAction}
            callAdminApi={callAdminApi}
        />
    );
}

import { adminApi } from '../../shared/config/routes';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import { useNavigate, useSearchParams } from 'react-router-dom';
import ThemeManagerPage from '../../modules/themes/pages/ThemeManagerPage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text } = Typography;

export default function ThemesRoutePage({ canAccess, canActivate, canGenerateDemoData, callAdminApi, runAdminAction, frontendLocale, defaultFrontendLocale }) {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const returnTo = searchParams.get('returnTo');
    const focusStep = searchParams.get('focusStep');
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const [themesPayload, setupPayload] = await Promise.all([
                callAdminApi(adminApi('themes')),
                callAdminApi(adminApi('setup')),
            ]);

            return {
                themes: themesPayload.data ?? [],
                meta: themesPayload.meta ?? {},
                siteProfile: setupPayload.data ?? null,
            };
        },
        cacheKey: 'admin.route.themes',
    });

    if (loading && !data) {
        return <Card loading title="Themes" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    const themes = data?.themes ?? [];
    const activeTheme = themes.find((theme) => theme.is_active) ?? null;

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            {returnTo ? (
                <Card>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <div>
                            <Text className="card-label">Setup Return</Text>
                            <Paragraph style={{ marginBottom: 0 }}>Sau khi kích hoạt theme xong, hệ thống sẽ tự quay lại Cài đặt website.</Paragraph>
                        </div>
                        <Button onClick={() => navigate(returnTo)}>Quay lại Cài đặt website</Button>
                    </Space>
                </Card>
            ) : null}

            <ThemeManagerPage
                themes={themes}
                themesMeta={data?.meta ?? {}}
                activeTheme={activeTheme}
                siteProfile={data?.siteProfile ?? null}
                frontendLocale={frontendLocale}
                defaultFrontendLocale={defaultFrontendLocale ?? data?.meta?.default_locale ?? 'vi'}
                onActivate={(themeKey, options = {}) => runAdminAction(
                    () => callAdminApi(adminApi(`themes/${themeKey}/activate`), { method: 'POST', body: JSON.stringify({ create_demo_data: Boolean(options.createDemoData) }) }),
                    'Đã kích hoạt theme.',
                    async () => {
                        await reload();

                        if (returnTo) {
                            navigate(`${returnTo}?focusStep=${encodeURIComponent(focusStep || 'theme')}&completedStep=${encodeURIComponent('theme')}`);
                        }
                    },
                )}
                onGenerateDemoData={(themeKey, preset, options = {}) => runAdminAction(
                    () => callAdminApi(adminApi(`themes/${themeKey}/demo-data`), { method: 'POST', body: JSON.stringify({ preset, reset_all: Boolean(options.resetAll) }) }),
                    'Đã tạo data test cho theme.',
                    reload,
                )}
                onDeleteDemoData={(themeKey) => runAdminAction(
                    () => callAdminApi(adminApi(`themes/${themeKey}/demo-data`), { method: 'DELETE' }),
                    'Đã xóa data test cho theme.',
                    reload,
                )}
                onSaveThemePalette={reload}
                canActivate={canActivate}
                canGenerateDemoData={canGenerateDemoData}
                callAdminApi={callAdminApi}
                runAdminAction={runAdminAction}
            />
        </Space>
    );
}

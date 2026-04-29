import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import { useNavigate, useSearchParams } from 'react-router-dom';
import ThemeManagerPage from '../../modules/themes/pages/ThemeManagerPage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text } = Typography;

export default function ThemesRoutePage({ canAccess, canActivate, canGenerateDemoData, callAdminApi, runAdminAction }) {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const returnTo = searchParams.get('returnTo');
    const focusStep = searchParams.get('focusStep');
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi('/admin/api/themes');

            return payload.data ?? [];
        },
        cacheKey: 'admin.route.themes',
    });

    if (loading && !data) {
        return <Card loading title="Themes" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

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
                themes={data}
                onActivate={(themeKey) => runAdminAction(
                    () => callAdminApi(`/admin/api/themes/${themeKey}/activate`, { method: 'POST' }),
                    'Đã kích hoạt theme.',
                    async () => {
                        await reload();

                        if (returnTo) {
                            navigate(`${returnTo}?focusStep=${encodeURIComponent(focusStep || 'theme')}&completedStep=${encodeURIComponent('theme')}`);
                        }
                    },
                )}
                onGenerateDemoData={(themeKey, preset) => runAdminAction(
                    () => callAdminApi(`/admin/api/themes/${themeKey}/demo-data`, { method: 'POST', body: JSON.stringify({ preset }) }),
                    'Đã tạo data test cho theme.',
                )}
                canActivate={canActivate}
                canGenerateDemoData={canGenerateDemoData}
            />
        </Space>
    );
}

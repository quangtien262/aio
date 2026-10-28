import { adminApi } from '../../shared/config/routes';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import { useNavigate, useSearchParams } from 'react-router-dom';
import ModuleStorePage from '../../modules/store/pages/ModuleStorePage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text } = Typography;

export default function ModulesRoutePage({ canAccess, permissions, callAdminApi, runAdminAction, refreshShell, canViewWebsiteLibrary = false }) {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const returnTo = searchParams.get('returnTo');
    const completeStep = searchParams.get('completeStep');
    const focusStep = searchParams.get('focusStep');
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi(adminApi('modules'));

            return payload.data ?? [];
        },
        cacheKey: 'admin.route.modules',
    });

    if (loading && !data) {
        return <Card loading title="App Store" />;
    }

    if (error && !data) {
        return <Alert type="error" showIcon message={error} action={<Button onClick={reload}>Thử lại</Button>} />;
    }

    const onAction = (moduleKey, action, payload = null) => {
        const endpointMap = {
            install: { url: adminApi(`modules/${moduleKey}/install`), method: 'POST', success: 'Đã cài đặt App.' },
            enable: { url: adminApi(`modules/${moduleKey}/enable`), method: 'POST', success: 'Đã kích hoạt App.' },
            disable: { url: adminApi(`modules/${moduleKey}/disable`), method: 'POST', success: 'Đã tắt App.' },
            upgrade: { url: adminApi(`modules/${moduleKey}/upgrade`), method: 'POST', success: 'Đã nâng cấp App.' },
            'demo-data': { url: adminApi(`modules/${moduleKey}/demo-data`), method: 'POST', success: 'Đã tạo dữ liệu demo cho App.' },
            'delete-demo-data': { url: adminApi(moduleKey === '__all__' ? 'modules/demo-data' : `modules/${moduleKey}/demo-data`), method: 'DELETE', success: 'Đã xóa dữ liệu demo được đánh dấu.' },
            uninstall: { url: adminApi(`modules/${moduleKey}`), method: 'DELETE', success: 'Đã gỡ App.' },
        };

        const target = endpointMap[action];

        if (!target) {
            return;
        }

        const successMessage = target.success;

        return runAdminAction(async () => {
            await callAdminApi(target.url, {
                method: target.method,
                body: payload ? JSON.stringify(payload) : undefined,
            });
        }, successMessage, async () => {
            await reload();
            await refreshShell?.();

            if (returnTo && completeStep === 'modules' && !['demo-data', 'delete-demo-data'].includes(action)) {
                await callAdminApi(adminApi(`setup/steps/${completeStep}`), { method: 'POST' });
                navigate(`${returnTo}?focusStep=${encodeURIComponent(focusStep || completeStep)}&completedStep=${encodeURIComponent(completeStep)}`);
            }
        });
    };

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            {error ? <Alert type="warning" showIcon message="Chưa thể cập nhật danh sách ứng dụng" description={error} action={<Button onClick={reload}>Tải lại</Button>} /> : null}
            {returnTo ? (
                <Card>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <div>
                            <Text className="card-label">Setup Return</Text>
                            <Paragraph style={{ marginBottom: 0 }}>Sau khi hoàn tất thao tác App cho bước này, hệ thống sẽ tự quay lại Cài đặt website.</Paragraph>
                        </div>
                        <Button onClick={() => navigate(returnTo)}>Quay lại Cài đặt website</Button>
                    </Space>
                </Card>
            ) : null}

            <ModuleStorePage canViewWebsiteLibrary={canViewWebsiteLibrary} onOpenWebsiteLibrary={() => navigate('../themes?catalog=website')} modules={data} onAction={onAction} permissions={permissions} callAdminApi={callAdminApi} />
        </Space>
    );
}

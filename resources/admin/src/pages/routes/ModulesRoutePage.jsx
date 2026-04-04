import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import { useNavigate, useSearchParams } from 'react-router-dom';
import ModuleStorePage from '../../modules/store/pages/ModuleStorePage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text } = Typography;

export default function ModulesRoutePage({ canAccess, permissions, callAdminApi, runAdminAction, refreshShell }) {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const returnTo = searchParams.get('returnTo');
    const completeStep = searchParams.get('completeStep');
    const focusStep = searchParams.get('focusStep');
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi('/admin/api/modules');

            return payload.data ?? [];
        },
    });

    if (loading) {
        return <Card loading title="Module Store" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    const onAction = (moduleKey, action) => {
        const endpointMap = {
            install: { url: `/admin/api/modules/${moduleKey}/install`, method: 'POST', success: 'Đã cài đặt module.' },
            enable: { url: `/admin/api/modules/${moduleKey}/enable`, method: 'POST', success: 'Đã kích hoạt module.' },
            disable: { url: `/admin/api/modules/${moduleKey}/disable`, method: 'POST', success: 'Đã tắt module.' },
            upgrade: { url: `/admin/api/modules/${moduleKey}/upgrade`, method: 'POST', success: 'Đã nâng cấp module.' },
            uninstall: { url: `/admin/api/modules/${moduleKey}`, method: 'DELETE', success: 'Đã gỡ module.' },
        };

        const target = endpointMap[action];

        if (!target) {
            return;
        }

        return runAdminAction(async () => {
            await callAdminApi(target.url, { method: target.method });
        }, target.success, async () => {
            await reload();
            await refreshShell?.();

            if (returnTo && completeStep === 'modules') {
                await callAdminApi(`/admin/api/setup/steps/${completeStep}`, { method: 'POST' });
                navigate(`${returnTo}?focusStep=${encodeURIComponent(focusStep || completeStep)}&completedStep=${encodeURIComponent(completeStep)}`);
            }
        });
    };

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            {returnTo ? (
                <Card>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <div>
                            <Text className="card-label">Setup Return</Text>
                            <Paragraph style={{ marginBottom: 0 }}>Sau khi hoàn tất thao tác module cho bước này, hệ thống sẽ tự quay lại Cài đặt website.</Paragraph>
                        </div>
                        <Button onClick={() => navigate(returnTo)}>Quay lại Cài đặt website</Button>
                    </Space>
                </Card>
            ) : null}

            <ModuleStorePage modules={data} onAction={onAction} permissions={permissions} />
        </Space>
    );
}

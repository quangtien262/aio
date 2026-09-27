import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import { Link } from 'react-router-dom';
import SiteDomainMappingPanel from '../../modules/themes/components/SiteDomainMappingPanel';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';
import { adminApi, adminPath } from '../../shared/config/routes';

export default function SiteMappingsRoutePage({ canAccess, canManage, callAdminApi, runAdminAction }) {
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi(adminApi('themes'));
            return payload.data ?? [];
        },
        cacheKey: 'admin.route.site-mappings.themes',
    });

    if (loading && !data) return <Card loading title="Cấu hình domain" />;
    if (error) return <Alert type="error" showIcon message={error} action={<Button onClick={reload}>Thử lại</Button>} />;

    return (
        <div style={{ minWidth: 0 }}>
            <div style={{ marginBottom: 16 }}><Link to={adminPath('themes')}>← Quản lý theme</Link></div>
            <SiteDomainMappingPanel themes={data ?? []} canManage={canManage} callAdminApi={callAdminApi} runAdminAction={runAdminAction} />
        </div>
    );
}

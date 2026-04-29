import Alert from 'antd/es/alert';
import Card from 'antd/es/card';
import AccessControlPage from '../../modules/access/pages/AccessControlPage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

export default function AccessRoutePage({ canAccess, canManageRoles, callAdminApi, runAdminAction }) {
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi('/admin/api/access');

            return payload.data ?? null;
        },
        cacheKey: 'admin.route.access',
    });

    if (loading && !data) {
        return <Card loading title="Access Control" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    const onCreateRole = (payload) => runAdminAction(
        () => callAdminApi('/admin/api/roles', { method: 'POST', body: JSON.stringify(payload) }),
        'Đã tạo role.',
        reload,
    );

    const onUpdateRole = (roleId, payload) => runAdminAction(
        () => callAdminApi(`/admin/api/roles/${roleId}`, { method: 'PUT', body: JSON.stringify(payload) }),
        'Đã cập nhật role.',
        reload,
    );

    const onDeleteRole = (roleId) => runAdminAction(
        () => callAdminApi(`/admin/api/roles/${roleId}`, { method: 'DELETE' }),
        'Đã xóa role.',
        reload,
    );

    return (
        <AccessControlPage
            accessControl={data}
            onCreateRole={onCreateRole}
            onUpdateRole={onUpdateRole}
            onDeleteRole={onDeleteRole}
            canManageRoles={canManageRoles}
        />
    );
}

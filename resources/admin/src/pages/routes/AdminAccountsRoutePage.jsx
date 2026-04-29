import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import { useNavigate, useSearchParams } from 'react-router-dom';
import AdminAccountsPage from '../../modules/admins/pages/AdminAccountsPage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text } = Typography;

export default function AdminAccountsRoutePage({ canAccess, currentAdmin, permissions, callAdminApi, runAdminAction }) {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const returnTo = searchParams.get('returnTo');
    const focusStep = searchParams.get('focusStep');
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi('/admin/api/admins');

            return payload.data ?? null;
        },
        cacheKey: 'admin.route.admin-accounts',
    });

    if (loading && !data) {
        return <Card loading title="Admin Accounts" />;
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
                            <Paragraph style={{ marginBottom: 0 }}>Sau khi tạo hoặc cập nhật admin xong, hệ thống sẽ tự quay lại Cài đặt website.</Paragraph>
                        </div>
                        <Button onClick={() => navigate(returnTo)}>Quay lại Cài đặt website</Button>
                    </Space>
                </Card>
            ) : null}

            <AdminAccountsPage
                adminAccounts={data?.admins ?? []}
                roles={data?.roles ?? []}
                scopeTypes={data?.scope_types ?? {}}
                currentAdmin={currentAdmin}
                canManageAdmins={permissions.manage}
                canResetPassword={permissions.resetPassword}
                canLockAdmins={permissions.lock}
                onCreateAdmin={(payload) => runAdminAction(() => callAdminApi('/admin/api/admins', { method: 'POST', body: JSON.stringify(payload) }), 'Đã tạo tài khoản admin.', async () => {
                    await reload();

                    if (returnTo) {
                        navigate(`${returnTo}?focusStep=${encodeURIComponent(focusStep || 'admin_account')}&completedStep=${encodeURIComponent('admin_account')}`);
                    }
                })}
                onUpdateAdmin={(adminId, payload) => runAdminAction(() => callAdminApi(`/admin/api/admins/${adminId}`, { method: 'PUT', body: JSON.stringify(payload) }), 'Đã cập nhật tài khoản admin.', async () => {
                    await reload();

                    if (returnTo) {
                        navigate(`${returnTo}?focusStep=${encodeURIComponent(focusStep || 'admin_account')}&completedStep=${encodeURIComponent('admin_account')}`);
                    }
                })}
                onResetPassword={(adminId, payload) => runAdminAction(() => callAdminApi(`/admin/api/admins/${adminId}/password`, { method: 'PUT', body: JSON.stringify(payload) }), 'Đã đặt lại mật khẩu admin.', reload)}
                onLockAdmin={(adminId, payload) => runAdminAction(() => callAdminApi(`/admin/api/admins/${adminId}/lock`, { method: 'POST', body: JSON.stringify(payload ?? {}) }), 'Đã khóa tài khoản admin.', reload)}
                onUnlockAdmin={(adminId) => runAdminAction(() => callAdminApi(`/admin/api/admins/${adminId}/unlock`, { method: 'POST' }), 'Đã mở khóa tài khoản admin.', reload)}
            />
        </Space>
    );
}

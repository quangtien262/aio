import Alert from 'antd/es/alert';
import Card from 'antd/es/card';
import DashboardPage from '../../pages/DashboardPage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

export default function DashboardRoutePage({ canAccess, callAdminApi }) {
    const { data, loading, error } = useAdminRouteResource({
        enabled: canAccess,
        loader: () => callAdminApi('/admin/api/dashboard'),
    });

    if (loading) {
        return <Card loading title="Dashboard" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    return <DashboardPage overview={data} />;
}

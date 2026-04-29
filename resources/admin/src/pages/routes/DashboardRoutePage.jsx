import { useEffect, useMemo } from 'react';
import Alert from 'antd/es/alert';
import Card from 'antd/es/card';
import DashboardPage from '../../pages/DashboardPage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

export default function DashboardRoutePage({ canAccess, callAdminApi }) {
    const dashboardCacheKey = useMemo(() => 'admin.route.dashboard', []);

    useEffect(() => {
        if (canAccess || typeof window === 'undefined') {
            return;
        }

        window.sessionStorage.removeItem(dashboardCacheKey);
    }, [canAccess, dashboardCacheKey]);

    const { data, loading, error } = useAdminRouteResource({
        enabled: canAccess,
        loader: () => callAdminApi('/admin/api/dashboard'),
        cacheKey: dashboardCacheKey,
    });

    if (loading && !data) {
        return <Card loading title="Trang chủ" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    return <DashboardPage overview={data} />;
}

import Alert from 'antd/es/alert';
import Card from 'antd/es/card';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import DashboardPage from '../../pages/DashboardPage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

const { Text, Title } = Typography;

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

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card>
                <div className="metric-grid">
                    {[
                        { label: 'Core Platform', value: 'Laravel 13' },
                        { label: 'Admin UI', value: 'React + AntD' },
                        { label: 'Modules Registered', value: data?.metrics?.modules ?? 0 },
                        { label: 'Themes Registered', value: data?.metrics?.themes ?? 0 },
                    ].map((item) => (
                        <div key={item.label} className="metric-tile">
                            <Text className="metric-label">{item.label}</Text>
                            <Title level={3} style={{ margin: 0 }}>{item.value}</Title>
                        </div>
                    ))}
                </div>
            </Card>
            <DashboardPage overview={data} />
        </Space>
    );
}

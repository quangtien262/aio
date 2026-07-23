import { adminApi } from '../../shared/config/routes';
import Alert from 'antd/es/alert';
import Card from 'antd/es/card';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

const { Text, Title } = Typography;

export default function AuditLogsRoutePage({ canAccess, callAdminApi }) {
    const { data, loading, error } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi(adminApi('audit-logs?per_page=50'));
            return payload.data ?? null;
        },
        cacheKey: 'admin.route.audit-logs',
    });

    if (error) return <Alert type="error" showIcon message={error} />;

    const columns = [
        { title: 'Thời gian', dataIndex: 'created_at', width: 190, render: (value) => value ? new Date(value).toLocaleString('vi-VN') : '' },
        { title: 'Người thao tác', key: 'actor', width: 180, render: (_, item) => item.actor ? `${item.actor.name} (@${item.actor.username})` : 'Hệ thống' },
        { title: 'Hành động', dataIndex: 'action', render: (value) => <Tag color="blue">{value}</Tag> },
        { title: 'Module', dataIndex: 'module_key', width: 110 },
        { title: 'Website', dataIndex: 'website_key', width: 160 },
        { title: 'Đối tượng', key: 'target', render: (_, item) => item.target_type ? `${item.target_type} #${item.target_id ?? ''}` : '' },
        { title: 'IP', dataIndex: 'ip_address', width: 130 },
    ];

    return (
        <Card loading={loading && !data}>
            <Text className="card-label">Security Audit</Text>
            <Title level={3}>Nhật ký bảo mật</Title>
            <Table rowKey="id" columns={columns} dataSource={data?.data ?? []} scroll={{ x: 1050 }} pagination={false} />
        </Card>
    );
}

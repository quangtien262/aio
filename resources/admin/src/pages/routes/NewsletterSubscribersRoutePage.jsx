import { useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text, Title } = Typography;

function formatDateTime(value) {
    if (!value) {
        return 'Chưa có';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
}

export default function NewsletterSubscribersRoutePage({ canAccess, callAdminApi }) {
    const [keyword, setKeyword] = useState('');
    const { data, loading, error } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi('/admin/api/newsletter-subscribers');

            return payload.data ?? { stats: {}, subscribers: [] };
        },
    });

    const filteredSubscribers = useMemo(() => {
        const normalizedKeyword = keyword.trim().toLowerCase();

        return (data?.subscribers ?? []).filter((subscriber) => {
            if (normalizedKeyword === '') {
                return true;
            }

            return [subscriber.email, subscriber.name, subscriber.phone, subscriber.source]
                .some((value) => String(value ?? '').toLowerCase().includes(normalizedKeyword));
        });
    }, [data?.subscribers, keyword]);

    if (loading) {
        return <Card loading title="Newsletter" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    const stats = data?.stats ?? {};

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card className="hero-card">
                <Text className="header-label">Retention</Text>
                <Title level={2} style={{ marginTop: 0 }}>Danh sách nhận bản tin</Title>
                <Paragraph style={{ maxWidth: 760, marginBottom: 0 }}>
                    Tất cả email đăng ký từ header storefront sẽ được gom về đây để đội vận hành theo dõi và tái sử dụng cho các chiến dịch nội dung.
                </Paragraph>
            </Card>

            <div className="metric-grid">
                <Card><Statistic title="Tổng subscriber" value={stats.total_subscribers ?? 0} /></Card>
                <Card><Statistic title="Đã liên kết customer" value={stats.linked_customers ?? 0} /></Card>
            </div>

            <Card title="Subscriber" extra={<Input allowClear value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm theo email, tên, điện thoại..." style={{ width: 320 }} />}>
                <Table
                    rowKey="id"
                    dataSource={filteredSubscribers}
                    locale={{ emptyText: <Empty description="Chưa có subscriber nào." /> }}
                    pagination={{ pageSize: 10 }}
                    columns={[
                        {
                            title: 'Email',
                            dataIndex: 'email',
                            key: 'email',
                            render: (value) => <Text strong>{value}</Text>,
                        },
                        {
                            title: 'Thông tin',
                            key: 'identity',
                            render: (_, record) => (
                                <Space direction="vertical" size={0}>
                                    <Text strong>{record.name || 'Khách vãng lai'}</Text>
                                    <Text type="secondary">{record.phone || 'Chưa có số điện thoại'}</Text>
                                </Space>
                            ),
                        },
                        {
                            title: 'Nguồn',
                            dataIndex: 'source',
                            key: 'source',
                            render: (value) => <Tag>{value}</Tag>,
                        },
                        {
                            title: 'Liên kết',
                            dataIndex: 'customer_id',
                            key: 'customer_id',
                            render: (value) => value ? <Tag color="green">Customer #{value}</Tag> : <Tag>Guest</Tag>,
                        },
                        {
                            title: 'Thời gian đăng ký',
                            dataIndex: 'subscribed_at',
                            key: 'subscribed_at',
                            render: (value) => formatDateTime(value),
                        },
                    ]}
                />
            </Card>
        </Space>
    );
}
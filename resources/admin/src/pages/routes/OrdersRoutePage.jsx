import { useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text, Title } = Typography;

function formatCurrency(value) {
    return `${Number(value ?? 0).toLocaleString('vi-VN')}đ`;
}

function formatDateTime(value) {
    if (!value) {
        return 'Chưa có';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
}

function resolveOrderStatusMeta(status) {
    const statusMap = {
        placed: { color: 'blue', label: 'Mới đặt' },
        pending: { color: 'gold', label: 'Chờ xử lý' },
        processing: { color: 'processing', label: 'Đang xử lý' },
        completed: { color: 'green', label: 'Hoàn tất' },
        cancelled: { color: 'red', label: 'Đã hủy' },
    };

    return statusMap[status] ?? { color: 'default', label: status || 'Không rõ' };
}

export default function OrdersRoutePage({ canAccess, callAdminApi }) {
    const [keyword, setKeyword] = useState('');
    const [selectedOrder, setSelectedOrder] = useState(null);
    const { data, loading, error } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi('/admin/api/orders');

            return payload.data ?? { stats: {}, orders: [] };
        },
    });

    const filteredOrders = useMemo(() => {
        const normalizedKeyword = keyword.trim().toLowerCase();

        return (data?.orders ?? []).filter((order) => {
            if (normalizedKeyword === '') {
                return true;
            }

            return [
                order.order_code,
                order.customer_name,
                order.customer_phone,
                order.customer_email,
                order.delivery_address,
            ].some((value) => String(value ?? '').toLowerCase().includes(normalizedKeyword));
        });
    }, [data?.orders, keyword]);

    if (loading) {
        return <Card loading title="Đơn hàng" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    const stats = data?.stats ?? {};

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card className="hero-card">
                <Text className="header-label">Commerce Ops</Text>
                <Title level={2} style={{ marginTop: 0 }}>Đơn hàng từ storefront</Title>
                <Paragraph style={{ maxWidth: 760, marginBottom: 0 }}>
                    Theo dõi toàn bộ đơn hàng đã đổ từ website vào admin, gồm trạng thái xử lý, thông tin khách và chi tiết line-item.
                </Paragraph>
            </Card>

            <div className="metric-grid">
                <Card><Statistic title="Tổng đơn" value={stats.total_orders ?? 0} /></Card>
                <Card><Statistic title="Doanh thu tạm tính" value={stats.gross_revenue ?? 0} formatter={(value) => formatCurrency(value)} /></Card>
                <Card><Statistic title="Đơn mới đặt" value={stats.status_counts?.placed ?? 0} /></Card>
                <Card><Statistic title="Đơn chờ xử lý" value={stats.status_counts?.pending ?? 0} /></Card>
            </div>

            <Card title="Danh sách đơn hàng" extra={<Input allowClear value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm theo mã đơn, khách hàng, điện thoại..." style={{ width: 320 }} />}>
                <Table
                    rowKey="id"
                    dataSource={filteredOrders}
                    locale={{ emptyText: <Empty description="Chưa có đơn hàng nào." /> }}
                    pagination={{ pageSize: 10 }}
                    onRow={(record) => ({ onClick: () => setSelectedOrder(record), style: { cursor: 'pointer' } })}
                    columns={[
                        {
                            title: 'Mã đơn',
                            dataIndex: 'order_code',
                            key: 'order_code',
                            render: (value) => <Text strong>{value}</Text>,
                        },
                        {
                            title: 'Khách hàng',
                            key: 'customer',
                            render: (_, record) => (
                                <Space direction="vertical" size={0}>
                                    <Text strong>{record.customer_name}</Text>
                                    <Text type="secondary">{record.customer_phone}</Text>
                                    <Text type="secondary">{record.customer_email || 'Chưa có email'}</Text>
                                </Space>
                            ),
                        },
                        {
                            title: 'Trạng thái',
                            dataIndex: 'status',
                            key: 'status',
                            render: (value) => {
                                const statusMeta = resolveOrderStatusMeta(value);

                                return <Tag color={statusMeta.color}>{statusMeta.label}</Tag>;
                            },
                        },
                        {
                            title: 'Thanh toán',
                            dataIndex: 'payment_label',
                            key: 'payment_label',
                        },
                        {
                            title: 'Tổng tiền',
                            dataIndex: 'subtotal',
                            key: 'subtotal',
                            align: 'right',
                            render: (value) => <Text strong>{formatCurrency(value)}</Text>,
                        },
                        {
                            title: 'Thời gian',
                            dataIndex: 'placed_at',
                            key: 'placed_at',
                            render: (value) => formatDateTime(value),
                        },
                    ]}
                />
            </Card>

            <Drawer title={selectedOrder ? `Chi tiết ${selectedOrder.order_code}` : 'Chi tiết đơn hàng'} open={Boolean(selectedOrder)} onClose={() => setSelectedOrder(null)} width={520} destroyOnHidden>
                {selectedOrder ? (
                    <Space direction="vertical" size={16} style={{ width: '100%' }}>
                        <Card size="small">
                            <div className="detail-grid detail-grid-2">
                                <div className="detail-tile">
                                    <Text className="detail-label">Khách hàng</Text>
                                    <Text strong>{selectedOrder.customer_name}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Trạng thái</Text>
                                    <Tag color={resolveOrderStatusMeta(selectedOrder.status).color}>{resolveOrderStatusMeta(selectedOrder.status).label}</Tag>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Điện thoại</Text>
                                    <Text strong>{selectedOrder.customer_phone}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Email</Text>
                                    <Text strong>{selectedOrder.customer_email || 'Chưa có email'}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Địa chỉ</Text>
                                    <Text strong>{selectedOrder.delivery_address}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Mail xác nhận</Text>
                                    <Text strong>{selectedOrder.email_queued_at ? 'Đã xếp hàng gửi' : 'Chưa xếp hàng'}</Text>
                                </div>
                            </div>
                        </Card>

                        <Card size="small" title="Sản phẩm trong đơn">
                            <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                {(selectedOrder.items ?? []).map((item) => (
                                    <div key={item.id} style={{ display: 'flex', justifyContent: 'space-between', gap: 12, paddingBottom: 12, borderBottom: '1px solid #f0f0f0' }}>
                                        <div>
                                            <Text strong>{item.product_name}</Text>
                                            <div><Text type="secondary">Số lượng: {item.quantity}</Text></div>
                                        </div>
                                        <Text strong>{formatCurrency(item.line_total)}</Text>
                                    </div>
                                ))}
                            </Space>
                        </Card>
                    </Space>
                ) : null}
            </Drawer>
        </Space>
    );
}
import ArrowRightOutlined from '@ant-design/icons/ArrowRightOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import List from 'antd/es/list';
import Progress from 'antd/es/progress';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useCallback, useMemo } from 'react';
import FnbPageHeader from '../../components/FnbPageHeader';
import { FnbResourceError } from '../../components/FnbResourceState';
import FnbStatusTag from '../../components/FnbStatusTag';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, formatDateTime, formatMinorMoney, formatQuantity } from '../../utils/fnbFormat';

const { Text } = Typography;

export default function FnbDashboardPage() {
    const { api, outletId, selectedOutlet, can, navigateSection } = useFnbWorkspace();
    const loadDashboard = useCallback(() => api.dashboard(), [api]);
    const resource = useFnbResource({
        enabled: Boolean(outletId),
        loader: loadDashboard,
        deps: [outletId],
    });
    const dashboard = resource.data ?? {};
    const totals = dashboard.totals ?? dashboard.report?.totals ?? dashboard.summary?.totals ?? {};
    const currency = dashboard.currency ?? selectedOutlet?.currency ?? 'VND';
    const alerts = asArray(dashboard.alerts);
    const recentSessions = asArray(dashboard.recent_sessions ?? dashboard.sessions);
    const topItems = asArray(dashboard.top_items ?? dashboard.report?.top_items);
    const maxTopQuantity = useMemo(() => Math.max(1, ...topItems.map((item) => Number(item.quantity) || 0)), [topItems]);

    if (resource.error && !resource.data) {
        return <FnbResourceError error={resource.error} onRetry={resource.reload} title="Không tải được tổng quan quán" />;
    }

    return (
        <div className="fnb-page-stack">
            <FnbPageHeader
                eyebrow={selectedOutlet?.name ?? 'Điểm bán'}
                title="Tổng quan vận hành"
                description="Doanh thu, ca hiện tại và các việc cần chú ý được tính từ dữ liệu máy chủ."
                actions={<Button icon={<ReloadOutlined />} loading={resource.refreshing} onClick={() => resource.reload({ silent: true })}>Làm mới</Button>}
            />

            {resource.error ? <Alert type="warning" showIcon message={resource.error.message} /> : null}

            <section className="fnb-kpi-grid" aria-label="Chỉ số vận hành">
                <Card className="fnb-kpi-card" loading={resource.loading}>
                    <Statistic title="Tiền đã thu" value={totals.gross_collected_minor ?? null} formatter={(value) => formatMinorMoney(value, currency)} />
                </Card>
                <Card className="fnb-kpi-card" loading={resource.loading}>
                    <Statistic title="Thực thu sau hoàn" value={totals.net_collected_minor ?? null} formatter={(value) => formatMinorMoney(value, currency)} />
                </Card>
                <Card className="fnb-kpi-card" loading={resource.loading}>
                    <Statistic title="Phiên đang phục vụ" value={dashboard.active_sessions ?? null} formatter={(value) => value ?? '—'} />
                </Card>
                <Card className="fnb-kpi-card" loading={resource.loading}>
                    <Statistic title="Ca đang mở" value={dashboard.open_shifts ?? null} formatter={(value) => value ?? '—'} />
                </Card>
            </section>

            <div className="fnb-two-column">
                <Card
                    className="fnb-panel"
                    title="Phiên phục vụ gần đây"
                    extra={can('fnb.order.create') ? <Button type="link" onClick={() => navigateSection('pos')}>Mở POS <ArrowRightOutlined /></Button> : null}
                    loading={resource.loading}
                >
                    {recentSessions.length ? (
                        <Table
                            rowKey={(record) => record.public_id ?? record.id}
                            pagination={false}
                            scroll={{ x: 620 }}
                            dataSource={recentSessions}
                            columns={[
                                { title: 'Phiên', render: (_, record) => <Text strong>{record.session_no ?? record.code ?? `#${record.id}`}</Text> },
                                { title: 'Khu vực', render: (_, record) => record.table?.name ?? record.service_type_label ?? record.service_type ?? 'Tại quầy' },
                                { title: 'Mở lúc', dataIndex: 'opened_at', render: formatDateTime },
                                { title: 'Tổng tiền', render: (_, record) => formatMinorMoney(record.grand_total_minor ?? record.total_minor, currency) },
                                { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                            ]}
                        />
                    ) : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có phiên phục vụ trong khoảng đang xem" />}
                </Card>

                <Card className="fnb-panel" title="Việc cần chú ý" loading={resource.loading}>
                    {alerts.length ? (
                        <List
                            dataSource={alerts}
                            renderItem={(item) => (
                                <List.Item>
                                    <List.Item.Meta
                                        title={<Space><Tag color={item.level === 'critical' ? 'red' : item.level === 'warning' ? 'orange' : 'blue'}>{item.label ?? item.type ?? 'Thông báo'}</Tag></Space>}
                                        description={item.message ?? item.description}
                                    />
                                </List.Item>
                            )}
                        />
                    ) : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Không có cảnh báo vận hành" />}
                </Card>
            </div>

            {topItems.length ? (
                <Card className="fnb-panel" title="Món bán nổi bật">
                    <div className="fnb-top-items">
                        {topItems.map((item) => {
                            const quantity = Number(item.quantity) || 0;

                            return (
                                <div className="fnb-top-item" key={item.item_id ?? item.item_code}>
                                    <div className="fnb-top-item-copy">
                                        <Text strong>{item.item_name}</Text>
                                        <Text type="secondary">{formatQuantity(item.quantity)} món · bán thuần {formatMinorMoney(item.net_sales_minor, currency)}</Text>
                                    </div>
                                    <Progress percent={Math.round((quantity / maxTopQuantity) * 100)} showInfo={false} strokeColor="#0f7a67" />
                                </div>
                            );
                        })}
                    </div>
                </Card>
            ) : null}
        </div>
    );
}

import ArrowRightOutlined from '@ant-design/icons/ArrowRightOutlined';
import BarChartOutlined from '@ant-design/icons/BarChartOutlined';
import CalendarOutlined from '@ant-design/icons/CalendarOutlined';
import ClockCircleOutlined from '@ant-design/icons/ClockCircleOutlined';
import CoffeeOutlined from '@ant-design/icons/CoffeeOutlined';
import DollarOutlined from '@ant-design/icons/DollarOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import ShopOutlined from '@ant-design/icons/ShopOutlined';
import WalletOutlined from '@ant-design/icons/WalletOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Tag from 'antd/es/tag';
import { useCallback } from 'react';
import { FnbResourceError } from '../../components/FnbResourceState';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, calendarDate, formatDateTime, formatMinorMoney, formatQuantity } from '../../utils/fnbFormat';
import '../../styles/fnb-dashboard.css';

export default function FnbDashboardPage() {
    const { api, outletId, terminalId, selectedOutlet, can, navigateSection } = useFnbWorkspace();
    const loadDashboard = useCallback(() => api.dashboard(), [api]);
    const resource = useFnbResource({
        enabled: Boolean(outletId),
        loader: loadDashboard,
        deps: [outletId, terminalId],
    });
    const dashboard = resource.data ?? {};
    const totals = dashboard.report?.totals ?? {};
    const currency = selectedOutlet?.currency ?? 'VND';
    const today = calendarDate(selectedOutlet?.timezone);
    const businessDay = dashboard.business_day;
    const topItems = asArray(dashboard.report?.top_items);
    const financialAvailable = totals.net_collected_minor !== undefined && totals.net_collected_minor !== null;
    const canViewReports = can('fnb.report.operations.view') || can('fnb.report.financial.view');
    const money = (value) => formatMinorMoney(value, currency);
    const count = (value) => value === undefined || value === null ? '—' : formatQuantity(value);
    const dateLabel = (value) => String(value).split('-').reverse().join('/');
    const quickActions = [
        { key: 'kitchen', permission: 'fnb.kitchen.view', icon: <CoffeeOutlined />, title: 'Bar / Bếp', description: 'Theo dõi món đang chế biến' },
        { key: 'shifts', permission: 'fnb.shift.view', icon: <ClockCircleOutlined />, title: 'Ca bán hàng', description: 'Quản lý ca và tiền tại quầy' },
        ...(canViewReports ? [{ key: 'reports', icon: <BarChartOutlined />, title: 'Báo cáo', description: 'Xem kết quả theo khoảng thời gian' }] : []),
    ].filter((action) => !action.permission || can(action.permission));
    const metrics = [
        { title: 'Thực thu sau hoàn', value: money(totals.net_collected_minor), icon: <WalletOutlined />, description: 'Tiền đã thu trừ tiền hoàn', primary: true },
        { title: 'Tiền đã thu', value: money(totals.gross_collected_minor), icon: <DollarOutlined />, description: terminalId ? 'Trong ngày · quầy đang chọn' : 'Trong ngày · toàn điểm bán' },
        { title: 'Đang phục vụ', value: count(dashboard.active_sessions), icon: <CoffeeOutlined />, description: 'Phiên hiện tại · toàn điểm bán' },
        { title: 'Ca đang mở', value: count(dashboard.open_shifts), icon: <ClockCircleOutlined />, description: terminalId ? 'Tại quầy đang chọn' : 'Tại toàn điểm bán' },
    ];

    if (resource.error && !resource.data) {
        return <FnbResourceError error={resource.error} onRetry={resource.reload} title="Không tải được tổng quan quán" />;
    }

    return (
        <div className="fnb-dashboard">
            <header className="fnb-dashboard-heading">
                <div>
                    <h1>Tổng quan hôm nay</h1>
                    <p><CalendarOutlined /> {dateLabel(today)}<span className="fnb-dashboard-heading-divider" />Theo dõi hoạt động tại {selectedOutlet?.name ?? 'điểm bán'}</p>
                </div>
                <Button icon={<ReloadOutlined />} loading={resource.refreshing || resource.loading} onClick={() => resource.reload({ silent: true }).catch(() => undefined)}>Làm mới</Button>
            </header>

            {resource.error ? <Alert type="warning" showIcon message="Chưa thể làm mới dữ liệu" description={resource.error.message} /> : null}

            <section className="fnb-dashboard-metrics" aria-label="Chỉ số vận hành">
                {metrics.map((metric) => (
                    <Card key={metric.title} className={`fnb-dashboard-metric${metric.primary ? ' is-primary' : ''}`} loading={resource.loading}>
                        <div className="fnb-dashboard-metric-label"><span>{metric.title}</span><span className="fnb-dashboard-metric-icon">{metric.icon}</span></div>
                        <strong className="fnb-dashboard-metric-value">{metric.value}</strong>
                        <span className="fnb-dashboard-metric-caption">{metric.description}</span>
                    </Card>
                ))}
            </section>

            <div className="fnb-dashboard-columns">
                <Card className="fnb-dashboard-panel fnb-dashboard-results" title="Kết quả trong ngày" loading={resource.loading}
                    extra={canViewReports ? <Button type="link" onClick={() => navigateSection('reports')}>Xem báo cáo <ArrowRightOutlined /></Button> : null}>
                    {financialAvailable ? <>
                        <div className="fnb-dashboard-financials">
                            <div><span>Hóa đơn đã chốt</span><strong>{count(totals.checks)}</strong></div>
                            <div><span>Giá trị hóa đơn TB</span><strong>{money(totals.average_check_minor)}</strong></div>
                            <div><span>Tiền đã hoàn</span><strong>{money(totals.refund_disbursed_minor)}</strong></div>
                        </div>
                        <div className="fnb-dashboard-items-heading"><h2>Món bán nổi bật</h2><span>Theo giá trị bán thuần</span></div>
                        {topItems.length ? <div className="fnb-dashboard-items">
                            {topItems.slice(0, 5).map((item, index) => (
                                <div className="fnb-dashboard-item" key={item.item_id ?? item.item_code ?? index}>
                                    <span className="fnb-dashboard-item-rank">{String(index + 1).padStart(2, '0')}</span>
                                    <strong>{item.item_name}</strong>
                                    <span>{formatQuantity(item.quantity)} món</span>
                                    <strong>{money(item.net_sales_minor)}</strong>
                                </div>
                            ))}
                        </div> : <div className="fnb-dashboard-empty">
                            <span className="fnb-dashboard-empty-icon"><CoffeeOutlined /></span>
                            <strong>Chưa có món bán trong ngày</strong>
                            <p>Các món từ hóa đơn đã chốt sẽ xuất hiện tại đây.</p>
                        </div>}
                    </> : <div className="fnb-dashboard-empty">
                        <span className="fnb-dashboard-empty-icon"><BarChartOutlined /></span>
                        <strong>{resource.loading ? 'Đang tải kết quả trong ngày' : 'Chưa có dữ liệu tài chính để hiển thị'}</strong>
                        <p>Các chỉ số thu và hoàn tiền cần quyền xem báo cáo tài chính.</p>
                    </div>}
                    <div className="fnb-dashboard-panel-note">{terminalId ? 'Kết quả theo quầy đang chọn.' : 'Kết quả của toàn điểm bán.'} Tiền thu và hoàn được ghi nhận riêng.</div>
                </Card>

                <div className="fnb-dashboard-sidebar">
                    {can('fnb.order.create') || quickActions.length ? <Card className="fnb-dashboard-panel fnb-dashboard-actions" title="Thao tác nhanh">
                        {can('fnb.order.create') ? <Button type="primary" block size="large" icon={<ShopOutlined />} onClick={() => navigateSection('pos')}>Mở màn hình bán hàng <ArrowRightOutlined /></Button> : null}
                        <div className="fnb-dashboard-action-list">
                            {quickActions.map((action) => <button type="button" key={action.key} onClick={() => navigateSection(action.key)} className="fnb-dashboard-action">
                                <span className="fnb-dashboard-action-icon">{action.icon}</span>
                                <span className="fnb-dashboard-action-copy"><strong>{action.title}</strong><span>{action.description}</span></span>
                                <ArrowRightOutlined />
                            </button>)}
                        </div>
                    </Card> : null}

                    <Card className="fnb-dashboard-panel fnb-dashboard-business-day" loading={resource.loading}>
                        <div className="fnb-dashboard-business-heading"><span><CalendarOutlined /> Ngày kinh doanh</span><Tag color={businessDay ? 'green' : 'default'}>{businessDay ? 'Đang mở' : 'Chưa mở'}</Tag></div>
                        <strong>{businessDay?.business_date ? dateLabel(businessDay.business_date) : 'Chưa có ngày đang mở'}</strong>
                        <p>{businessDay?.opened_at ? `Mở lúc ${formatDateTime(businessDay.opened_at, { timeZone: businessDay.timezone_snapshot ?? selectedOutlet?.timezone })}` : 'Quản lý ngày kinh doanh tại Ca bán hàng.'}</p>
                    </Card>
                </div>
            </div>

            {resource.updatedAt ? <footer className="fnb-dashboard-footer"><span className="fnb-dashboard-update-dot" />Cập nhật {formatDateTime(resource.updatedAt, { timeZone: selectedOutlet?.timezone })}{resource.refreshing ? ' · Đang làm mới…' : ''}</footer> : null}
        </div>
    );
}

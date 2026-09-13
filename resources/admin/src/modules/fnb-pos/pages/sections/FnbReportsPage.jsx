import BarChartOutlined from '@ant-design/icons/BarChartOutlined';
import DownloadOutlined from '@ant-design/icons/DownloadOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Progress from 'antd/es/progress';
import Row from 'antd/es/row';
import Segmented from 'antd/es/segmented';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useCallback, useMemo, useState } from 'react';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbPageHeader from '../../components/FnbPageHeader';
import { FnbResourceError } from '../../components/FnbResourceState';
import FnbStatusTag from '../../components/FnbStatusTag';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, formatDateTime, formatMinorMoney, formatQuantity } from '../../utils/fnbFormat';

const { Text } = Typography;

const today = () => {
    const date = new Date();
    const offset = date.getTimezoneOffset() * 60000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 10);
};

const firstOfMonth = () => `${today().slice(0, 8)}01`;

export default function FnbReportsPage() {
    const { message } = App.useApp();
    const { api, outletId, terminalId, selectedOutlet, can, runCommand } = useFnbWorkspace();
    const defaultType = can('fnb.report.financial.view') ? 'financial' : 'operations';
    const [filters, setFilters] = useState({ from: firstOfMonth(), to: today(), report_type: defaultType });
    const [exporting, setExporting] = useState(false);
    const [downloadingId, setDownloadingId] = useState(null);
    const [form] = Form.useForm();
    const loadReport = useCallback(() => api.report({ ...filters, terminal_id: terminalId || undefined }), [api, filters, terminalId]);
    const loadExports = useCallback(() => api.reportExports(), [api]);
    const reportResource = useFnbResource({ enabled: Boolean(outletId), loader: loadReport, deps: [outletId, filters, terminalId] });
    const exportsResource = useFnbResource({ enabled: Boolean(outletId && can('fnb.report.export')), loader: loadExports, deps: [outletId], pollMs: 5000 });
    const report = reportResource.data ?? {};
    const totals = report.totals ?? {};
    const currency = report.currency ?? selectedOutlet?.currency ?? 'VND';
    const topItems = asArray(report.top_items);
    const tenders = asArray(report.tenders);
    const maxQuantity = useMemo(() => Math.max(1, ...topItems.map((item) => Number(item.quantity) || 0)), [topItems]);
    const financial = filters.report_type === 'financial';

    const submit = (values) => setFilters({ ...values, report_type: filters.report_type });
    const changeType = (reportType) => setFilters((current) => ({ ...current, report_type: reportType }));
    const reloadAll = () => Promise.all([
        reportResource.reload({ silent: true }),
        can('fnb.report.export') ? exportsResource.reload({ silent: true }) : null,
    ]);

    const createExport = async () => {
        const key = createIdempotencyKey('report-export');
        setExporting(true);
        try {
            await runCommand({
                execute: (auth) => api.command('reports/exports', {
                    report_type: filters.report_type,
                    format: 'csv',
                    from: filters.from,
                    to: filters.to,
                }, { idempotencyKey: key, ...auth }),
                successMessage: 'Đã xếp báo cáo vào hàng đợi.',
                onSuccess: () => exportsResource.reload({ silent: true }),
                onConflict: exportsResource.reload,
            });
        } finally {
            setExporting(false);
        }
    };

    const downloadExport = async (item) => {
        setDownloadingId(item.id);
        try {
            const blob = await api.downloadExport(item.id);
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = `fnb-${item.report_type}-${item.id}.${item.format ?? 'csv'}`;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            URL.revokeObjectURL(url);
        } catch (error) {
            message.error(error.message || 'Không tải được file báo cáo.');
        } finally {
            setDownloadingId(null);
        }
    };

    if (reportResource.error && !reportResource.data) {
        return <FnbResourceError error={reportResource.error} onRetry={reportResource.reload} title="Không tải được báo cáo" />;
    }

    return (
        <div className="fnb-page-stack">
            <FnbPageHeader
                eyebrow="Số liệu theo điểm bán"
                title="Báo cáo vận hành"
                description="Khoảng ngày được diễn giải theo múi giờ điểm bán; số tiền lấy nguyên trạng từ máy chủ."
                actions={<Button icon={<ReloadOutlined />} loading={reportResource.refreshing} onClick={reloadAll}>Làm mới</Button>}
            />

            <Card className="fnb-panel">
                <Form form={form} layout="inline" initialValues={filters} onFinish={submit} className="fnb-report-filter">
                    <Form.Item label="Từ ngày" name="from" rules={[{ required: true }]}><Input type="date" /></Form.Item>
                    <Form.Item label="Đến ngày" name="to" rules={[{ required: true }]}><Input type="date" /></Form.Item>
                    <Button type="primary" htmlType="submit" icon={<BarChartOutlined />}>Xem báo cáo</Button>
                    <Segmented
                        value={filters.report_type}
                        onChange={changeType}
                        options={[
                            ...(can('fnb.report.financial.view') ? [{ value: 'financial', label: 'Tài chính' }] : []),
                            ...(can('fnb.report.operations.view') ? [{ value: 'operations', label: 'Vận hành' }] : []),
                        ]}
                    />
                </Form>
            </Card>

            {reportResource.error ? <Alert type="warning" showIcon message={reportResource.error.message} /> : null}

            <section className="fnb-kpi-grid">
                {financial ? <>
                    <Card className="fnb-kpi-card" loading={reportResource.loading}><Statistic title="Tiền đã thu" value={totals.gross_collected_minor} formatter={(value) => formatMinorMoney(value, currency)} /></Card>
                    <Card className="fnb-kpi-card" loading={reportResource.loading}><Statistic title="Tiền hoàn đã chi" value={totals.refund_disbursed_minor} formatter={(value) => formatMinorMoney(value, currency)} /></Card>
                    <Card className="fnb-kpi-card" loading={reportResource.loading}><Statistic title="Thực thu sau hoàn" value={totals.net_collected_minor} formatter={(value) => formatMinorMoney(value, currency)} /></Card>
                    <Card className="fnb-kpi-card" loading={reportResource.loading}><Statistic title="Chênh lệch két" value={totals.cash_variance_minor} formatter={(value) => formatMinorMoney(value, currency)} /></Card>
                </> : <>
                    <Card className="fnb-kpi-card" loading={reportResource.loading}><Statistic title="Số order" value={totals.orders ?? 0} /></Card>
                    <Card className="fnb-kpi-card" loading={reportResource.loading}><Statistic title="Số bill" value={totals.checks ?? 0} /></Card>
                </>}
            </section>

            <Row gutter={[16, 16]}>
                <Col xs={24} xl={financial ? 14 : 24}>
                    <Card className="fnb-panel" title="Món bán nổi bật">
                        {topItems.length ? <Space direction="vertical" size={15} style={{ width: '100%' }}>
                            {topItems.map((item) => {
                                const quantity = Number(item.quantity) || 0;
                                return <div key={item.item_id ?? item.item_code}>
                                    <div className="fnb-top-item-copy"><Text strong>{item.item_name}</Text><Text type="secondary">{formatQuantity(item.quantity)} món{financial && item.net_sales_minor !== undefined ? ` · bán thuần ${formatMinorMoney(item.net_sales_minor, currency)}` : ''}</Text></div>
                                    <Progress percent={Math.round((quantity / maxQuantity) * 100)} showInfo={false} strokeColor="#0f7a67" />
                                </div>;
                            })}
                        </Space> : <Text type="secondary">Chưa có dữ liệu trong khoảng đã chọn.</Text>}
                    </Card>
                </Col>
                {financial ? <Col xs={24} xl={10}>
                    <Card className="fnb-panel" title="Theo phương thức thanh toán">
                        <Table pagination={false} size="small" scroll={{ x: 560 }} rowKey="method_code" dataSource={tenders} columns={[
                            { title: 'Phương thức', render: (_, record) => record.method_name ?? record.method_code },
                            { title: 'Đã thu', dataIndex: 'gross_paid_minor', align: 'right', render: (value) => formatMinorMoney(value, currency) },
                            { title: 'Đã hoàn', dataIndex: 'refunded_minor', align: 'right', render: (value) => formatMinorMoney(value, currency) },
                            { title: 'Thực thu', dataIndex: 'net_collected_minor', align: 'right', render: (value) => formatMinorMoney(value, currency) },
                        ]} />
                    </Card>
                </Col> : null}
            </Row>

            {can('fnb.report.export') ? (
                <Card
                    className="fnb-panel"
                    title="Xuất báo cáo"
                    extra={<Button icon={<DownloadOutlined />} loading={exporting} onClick={createExport}>Tạo CSV</Button>}
                >
                    <Alert type="info" showIcon message="File xuất được tạo nền và chỉ tài khoản yêu cầu mới tải được" description="Khi trạng thái hoàn tất, nút tải dùng phiên đăng nhập cùng website và điểm bán; không lộ đường dẫn lưu trữ nội bộ." style={{ marginBottom: 14 }} />
                    <Table
                        rowKey="id"
                        loading={exportsResource.loading}
                        dataSource={asArray(exportsResource.data?.items)}
                        columns={[
                            { title: 'Loại', dataIndex: 'report_type', render: (value) => value === 'financial' ? 'Tài chính' : 'Vận hành' },
                            { title: 'Định dạng', dataIndex: 'format', render: (value) => <Tag>{String(value ?? '').toUpperCase()}</Tag> },
                            { title: 'Tạo lúc', dataIndex: 'queued_at', render: formatDateTime },
                            { title: 'Hoàn tất', dataIndex: 'completed_at', render: formatDateTime },
                            { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                            { title: '', render: (_, record) => record.status === 'completed' ? <Button size="small" icon={<DownloadOutlined />} loading={downloadingId === record.id} onClick={() => downloadExport(record)}>Tải file</Button> : null },
                        ]}
                    />
                </Card>
            ) : null}
        </div>
    );
}

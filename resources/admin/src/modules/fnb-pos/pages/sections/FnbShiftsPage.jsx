import CloseCircleOutlined from '@ant-design/icons/CloseCircleOutlined';
import DollarOutlined from '@ant-design/icons/DollarOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
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
import { asArray, formatDateTime, formatMinorMoney } from '../../utils/fnbFormat';

const { Text } = Typography;

const localDate = () => {
    const date = new Date();
    const offset = date.getTimezoneOffset() * 60000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 10);
};

function ShiftActionDrawer({ action, form, paymentMethods, saving, onClose, onSubmit }) {
    const titles = {
        openDay: 'Mở ngày kinh doanh',
        openShift: 'Mở ca tại quầy này',
        cash: 'Thu / chi tiền mặt',
        closeShift: 'Kiểm đếm và đóng ca',
    };

    return (
        <Drawer
            open={Boolean(action)}
            title={titles[action?.type] ?? 'Thao tác ca'}
            width="min(600px, 96vw)"
            onClose={onClose}
            destroyOnHidden
            extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Xác nhận</Button>}
        >
            <Form form={form} layout="vertical" onFinish={onSubmit}>
                {action?.type === 'openDay' ? (
                    <Form.Item name="business_date" label="Ngày kinh doanh" rules={[{ required: true }]}>
                        <Input type="date" />
                    </Form.Item>
                ) : null}
                {action?.type === 'openShift' ? (
                    <Form.Item name="opening_float_minor" label="Tiền đầu ca" rules={[{ required: true, message: 'Nhập tiền đầu ca.' }]}>
                        <InputNumber min={0} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} />
                    </Form.Item>
                ) : null}
                {action?.type === 'cash' ? <>
                    <Form.Item name="kind" label="Loại giao dịch" rules={[{ required: true }]}>
                        <Select options={[{ value: 'cash_in', label: 'Thu tiền vào két' }, { value: 'cash_out', label: 'Chi tiền khỏi két' }]} />
                    </Form.Item>
                    <Form.Item name="amount_minor" label="Số tiền" rules={[{ required: true, message: 'Nhập số tiền.' }]}>
                        <InputNumber min={1} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} />
                    </Form.Item>
                    <Form.Item name="reason" label="Lý do" rules={[{ required: true, min: 3, message: 'Nhập lý do từ 3 ký tự.' }]}>
                        <Input.TextArea rows={3} maxLength={500} showCount />
                    </Form.Item>
                    <Alert type="warning" showIcon message="Thu/chi tiền mặt là thao tác kiểm soát" description="Máy chủ có thể yêu cầu xác thực lại hoặc phê duyệt độc lập." />
                </> : null}
                {action?.type === 'closeShift' ? <>
                    <Alert type="info" showIcon message="Nhập số đã kiểm đếm thực tế" description="Chênh lệch được máy chủ tính từ sổ tiền của ca; trình duyệt không tự suy ra số kỳ vọng." style={{ marginBottom: 16 }} />
                    <Form.List name="counted_tenders">
                        {(fields) => <Space direction="vertical" size={10} style={{ width: '100%' }}>
                            {fields.map((field, index) => {
                                const method = paymentMethods[index];
                                return (
                                    <Card size="small" key={field.key}>
                                        <Form.Item {...field} name={[field.name, 'payment_method_id']} hidden><Input /></Form.Item>
                                        <Form.Item {...field} name={[field.name, 'counted_minor']} label={method?.name ?? `Phương thức #${index + 1}`} rules={[{ required: true, message: 'Nhập số đã đếm.' }]} style={{ marginBottom: 0 }}>
                                            <InputNumber min={0} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} />
                                        </Form.Item>
                                    </Card>
                                );
                            })}
                        </Space>}
                    </Form.List>
                    {!paymentMethods.length ? <Alert type="warning" showIcon message="Không đọc được danh sách phương thức thanh toán" description="Tải lại dữ liệu bán hàng trước khi đóng ca." /> : null}
                </> : null}
            </Form>
        </Drawer>
    );
}

export default function FnbShiftsPage() {
    const { api, outletId, terminalId, selectedTerminal, selectedOutlet, can, runCommand } = useFnbWorkspace();
    const [action, setAction] = useState(null);
    const [saving, setSaving] = useState(false);
    const [form] = Form.useForm();
    const loadShifts = useCallback(() => api.shifts(), [api]);
    const loadDay = useCallback(() => api.currentBusinessDay(), [api]);
    const loadPos = useCallback(() => api.pos(), [api]);
    const shiftsResource = useFnbResource({ enabled: Boolean(outletId), loader: loadShifts, deps: [outletId] });
    const dayResource = useFnbResource({ enabled: Boolean(outletId), loader: loadDay, deps: [outletId] });
    const posResource = useFnbResource({ enabled: Boolean(outletId && terminalId && can('fnb.order.view')), loader: loadPos, deps: [outletId, terminalId] });
    const items = asArray(shiftsResource.data?.items ?? shiftsResource.data?.shifts);
    const businessDay = dayResource.data?.business_day ?? (dayResource.data?.id ? dayResource.data : null);
    const currentShift = posResource.data?.shift ?? items.find((item) => item.status === 'open' && String(item.terminal_id) === String(terminalId));
    const hasAnyOpenShift = items.some((item) => item.status === 'open');
    const paymentMethods = asArray(posResource.data?.payment_methods);
    const currency = currentShift?.currency ?? selectedOutlet?.currency ?? 'VND';
    const isPosTerminal = !selectedTerminal?.type || selectedTerminal.type === 'pos';

    const reloadAll = async () => Promise.all([
        shiftsResource.reload({ silent: true }),
        dayResource.reload({ silent: true }),
        can('fnb.order.view') && terminalId ? posResource.reload({ silent: true }) : null,
    ]);

    const openAction = (type, record = null) => {
        form.resetFields();
        const initial = type === 'openDay'
            ? { business_date: localDate() }
            : type === 'openShift'
                ? { opening_float_minor: 0 }
                : type === 'cash'
                    ? { kind: 'cash_out' }
                    : type === 'closeShift'
                        ? { counted_tenders: paymentMethods.map((method) => ({ payment_method_id: method.id, counted_minor: method.kind === 'cash' ? record?.expected_cash_minor ?? 0 : 0 })) }
                        : {};
        form.setFieldsValue(initial);
        setAction({ type, record });
    };

    const submitAction = async (values) => {
        const record = action?.record;
        const key = createIdempotencyKey(action?.type ?? 'shift');
        const expected = record?.version ? {
            expected_version: record.version,
            expected_versions: { [`shift:${record.public_id ?? record.id}`]: record.version },
        } : {};
        const definitions = {
            openDay: {
                path: api.outletPath('business-days/open'),
                payload: { business_date: values.business_date },
                success: 'Đã mở ngày kinh doanh.',
            },
            openShift: {
                path: api.outletPath('shifts/open'),
                payload: { business_day_id: businessDay?.id, opening_float_minor: values.opening_float_minor },
                success: 'Đã mở ca tại quầy.',
            },
            cash: {
                path: `shifts/${record?.id}/cash-movements`,
                payload: { kind: values.kind, amount_minor: values.amount_minor, reason: values.reason, ...expected },
                success: 'Đã ghi nhận giao dịch két.',
            },
            closeShift: {
                path: `shifts/${record?.id}/close`,
                payload: { counted_tenders: values.counted_tenders ?? [], ...expected },
                success: 'Đã đóng ca và ghi nhận kiểm đếm.',
            },
        };
        const definition = definitions[action?.type];
        if (!definition) return;
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command(definition.path, definition.payload, { idempotencyKey: key, ...auth }),
                successMessage: definition.success,
                reason: action?.type === 'cash' ? values.reason : undefined,
                onSuccess: async () => { setAction(null); await reloadAll(); },
                onConflict: reloadAll,
            });
        } finally {
            setSaving(false);
        }
    };

    const reconcile = async (shift) => {
        const key = createIdempotencyKey('shift-reconcile');
        await runCommand({
            execute: (auth) => api.command(`shifts/${shift.id}/reconcile`, {
                expected_version: shift.version,
                expected_versions: { [`shift:${shift.public_id ?? shift.id}`]: shift.version },
            }, { idempotencyKey: key, ...auth }),
            successMessage: 'Đã đối soát ca.',
            reason: 'Đối soát ca theo số kiểm đếm đã xác nhận.',
            onSuccess: reloadAll,
            onConflict: reloadAll,
        });
    };

    const closeBusinessDay = async () => {
        const key = createIdempotencyKey('day-close');
        await runCommand({
            execute: (auth) => api.command(`business-days/${businessDay.id}/close`, {
                expected_version: businessDay.version,
                expected_versions: { [`business_day:${businessDay.public_id ?? businessDay.id}`]: businessDay.version },
            }, { idempotencyKey: key, ...auth }),
            successMessage: 'Đã đóng ngày kinh doanh.',
            onSuccess: reloadAll,
            onConflict: reloadAll,
        });
    };

    if (shiftsResource.error && !shiftsResource.data) {
        return <FnbResourceError error={shiftsResource.error} onRetry={reloadAll} title="Không tải được ca làm việc" />;
    }

    return (
        <div className="fnb-page-stack">
            <FnbPageHeader
                eyebrow={selectedTerminal?.name ?? 'Quầy hiện tại'}
                title="Ca làm việc & két tiền"
                description="Ngày kinh doanh, tiền đầu ca, thu chi và kiểm đếm cuối ca có dấu vết riêng."
                actions={<Button icon={<ReloadOutlined />} loading={shiftsResource.refreshing || dayResource.refreshing} onClick={reloadAll}>Làm mới</Button>}
            />

            {!terminalId || !isPosTerminal ? <Alert type="warning" showIcon message={!terminalId ? 'Chọn quầy POS để mở và đóng ca.' : 'Thiết bị đang chọn không phải quầy POS.'} /> : null}
            {posResource.error ? <Alert type="warning" showIcon message="Không tải được dữ liệu quầy" description={posResource.error.message} /> : null}

            <Row gutter={[14, 14]}>
                <Col xs={24} lg={12}>
                    <Card className="fnb-panel" title="Ngày kinh doanh" extra={businessDay ? <FnbStatusTag status={businessDay.status ?? 'open'} /> : null}>
                        {businessDay ? (
                            <Space direction="vertical" size={5}>
                                <Text strong>{businessDay.business_date}</Text>
                                <Text type="secondary">Mở lúc {formatDateTime(businessDay.opened_at)}</Text>
                                {can('fnb.shift.close') ? <Popconfirm title="Đóng ngày kinh doanh?" description="Mọi ca và phiên phục vụ phải đóng trước." onConfirm={closeBusinessDay}><Button danger size="small" disabled={hasAnyOpenShift}>Đóng ngày</Button></Popconfirm> : null}
                            </Space>
                        ) : can('fnb.shift.open') ? <Button type="primary" className="fnb-touch-button" onClick={() => openAction('openDay')}>Mở ngày hôm nay</Button> : <Text type="secondary">Chưa mở ngày kinh doanh.</Text>}
                    </Card>
                </Col>
                <Col xs={24} lg={12}>
                    <Card className="fnb-panel" title="Ca tại quầy" extra={currentShift ? <FnbStatusTag status={currentShift.status} /> : null}>
                        {currentShift ? (
                            <Space direction="vertical" size={9} style={{ width: '100%' }}>
                                <Text strong>Ca #{currentShift.id} · mở {formatDateTime(currentShift.opened_at)}</Text>
                                <Statistic title="Tiền đầu ca" value={currentShift.opening_float_minor} formatter={(value) => formatMinorMoney(value, currency)} />
                                <Space wrap>
                                    {can('fnb.cash.adjust') ? <Button icon={<DollarOutlined />} onClick={() => openAction('cash', currentShift)}>Thu / chi</Button> : null}
                                    {can('fnb.shift.close') ? <Button danger icon={<CloseCircleOutlined />} onClick={() => openAction('closeShift', currentShift)} disabled={!paymentMethods.length}>Đóng ca</Button> : null}
                                </Space>
                            </Space>
                        ) : businessDay && can('fnb.shift.open') ? <Button type="primary" className="fnb-touch-button" disabled={!terminalId || !isPosTerminal} onClick={() => openAction('openShift')}>Mở ca</Button> : <Text type="secondary">Cần mở ngày kinh doanh trước.</Text>}
                    </Card>
                </Col>
            </Row>

            <Card className="fnb-panel" title="Lịch sử ca">
                <Table
                    rowKey={(record) => record.public_id ?? record.id}
                    loading={shiftsResource.loading}
                    dataSource={items}
                    scroll={{ x: 900 }}
                    columns={[
                        { title: 'Ca', render: (_, record) => <Space direction="vertical" size={0}><Text strong>#{record.id}</Text><Text type="secondary">Quầy #{record.terminal_id}</Text></Space> },
                        { title: 'Mở ca', dataIndex: 'opened_at', render: formatDateTime },
                        { title: 'Đóng ca', dataIndex: 'closed_at', render: formatDateTime },
                        { title: 'Tiền đầu ca', dataIndex: 'opening_float_minor', render: (value, record) => formatMinorMoney(value, record.currency ?? currency) },
                        { title: 'Chênh lệch', dataIndex: 'variance_minor', render: (value, record) => value === null || value === undefined ? '—' : <Tag color={Number(value) === 0 ? 'green' : 'orange'}>{formatMinorMoney(value, record.currency ?? currency)}</Tag> },
                        { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                        { title: '', fixed: 'right', render: (_, record) => record.status === 'closed_pending_reconciliation' && can('fnb.shift.reconcile') ? <Popconfirm title="Xác nhận đối soát ca này?" description="Có thể cần quản lý khác phê duyệt." onConfirm={() => reconcile(record)}><Button icon={<SafetyCertificateOutlined />}>Đối soát</Button></Popconfirm> : null },
                    ]}
                />
            </Card>

            <ShiftActionDrawer action={action} form={form} paymentMethods={paymentMethods} saving={saving} onClose={() => setAction(null)} onSubmit={submitAction} />
        </div>
    );
}

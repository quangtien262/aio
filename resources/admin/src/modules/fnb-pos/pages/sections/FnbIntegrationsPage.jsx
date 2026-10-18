import { useCallback, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Col from 'antd/es/col';
import Checkbox from 'antd/es/checkbox';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import FnbPageHeader from '../../components/FnbPageHeader';
import FnbStatusTag from '../../components/FnbStatusTag';
import { FnbResourceError } from '../../components/FnbResourceState';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, formatDateTime } from '../../utils/fnbFormat';
import { createIdempotencyKey } from '../../api/fnbApi';

const destinationLabel = (value) => ({ inventory: 'Kho', 'accounting-tax': 'Kế toán' })[value] ?? value;

export default function FnbIntegrationsPage() {
    const { baseApi, outletId, can, runCommand } = useFnbWorkspace();
    const api = useMemo(() => baseApi.forScope({ outletId }), [baseApi, outletId]);
    const load = useCallback(() => api.read('chain/integrations', { outlet_id: outletId }), [api, outletId]);
    const resource = useFnbResource({ loader: load, deps: [api, outletId], pollMs: 10000 });
    const data = resource.data ?? {};
    const canManage = can('fnb.settings.manage') && can('fnb.chain.manage');
    const [destination, setDestination] = useState('');
    const [saving, setSaving] = useState(false);
    const [retryDeadLetter, setRetryDeadLetter] = useState(false);
    const [editor, setEditor] = useState(null);
    const [form] = Form.useForm();
    const mappingDestination = Form.useWatch('destination', form);
    const sourceType = Form.useWatch('source_type', form);
    const destinations = asArray(data.destinations).filter((item) => item.enabled && item.configured);
    const reconcile = async () => {
        const idempotencyKey = createIdempotencyKey('integration-reconcile');
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command('chain/integrations/reconcile', { outlet_id: Number(outletId), destination, retry_dead_letter: retryDeadLetter }, { idempotencyKey, ...auth }),
                authApi: api,
                title: 'Đối soát đồng bộ',
                successMessage: 'Đã ghi nhận yêu cầu đối soát đồng bộ.',
                onSuccess: resource.reload,
                onConflict: resource.reload,
            });
        } finally { setSaving(false); }
    };
    const openEditor = (kind, record = null) => {
        form.resetFields();
        form.setFieldsValue(record ?? { destination: 'inventory', source_type: 'ingredient', enabled: false, automatic: false, unit: 'g', factor: '1' });
        setEditor({ kind, record });
    };
    const saveMapping = async (values) => {
        const idempotencyKey = createIdempotencyKey('integration-mapping');
        const payload = { ...values, outlet_id: Number(outletId), ...(editor.record?.version ? { expected_version: editor.record.version } : {}) };
        setSaving(true);
        try {
            await runCommand({ execute: (auth) => api.command(`chain/integrations/${editor.kind === 'destination' ? 'mapping' : 'items'}`, payload, { method: 'PUT', idempotencyKey, ...auth }), authApi: api, title: 'Lưu ánh xạ đồng bộ',
                successMessage: 'Đã lưu ánh xạ đồng bộ.', onSuccess: async () => { setEditor(null); await resource.reload(); }, onConflict: resource.reload });
        } finally { setSaving(false); }
    };
    if (resource.error && !resource.data) return <FnbResourceError error={resource.error} onRetry={resource.reload} />;
    return <div className="fnb-page-stack">
        <FnbPageHeader title="Tích hợp & đồng bộ" eyebrow="Vận hành chuỗi" description="Theo dõi kết nối kho, kế toán và các giao dịch đang chờ đối soát." actions={<Button onClick={() => resource.reload()} loading={resource.refreshing}>Đồng bộ</Button>} />
        {resource.error ? <Alert type="warning" showIcon message={resource.error.message} /> : null}
        <Row gutter={[12, 12]}>{[['pending', 'Chờ đồng bộ'], ['failed', 'Cần xử lý'], ['uncertain', 'Chưa rõ kết quả'], ['dead_letter', 'Đã dừng · cần xử lý']].map(([key, label]) => <Col xs={24} sm={12} xl={6} key={key}><Card className="fnb-panel"><Statistic title={label} value={data.totals?.[key] ?? 0} /></Card></Col>)}</Row>
        <Card className="fnb-panel" title="Trạng thái kết nối"><Table rowKey={(item) => item.key ?? item.destination} pagination={false} dataSource={asArray(data.readiness)} columns={[
            { title: 'Kết nối', render: (_, item) => item.label ?? destinationLabel(item.key ?? item.destination) },
            { title: 'Trạng thái', render: (_, item) => <Tag color={item.ready ? 'green' : 'orange'}>{item.ready ? 'Sẵn sàng' : item.status === 'configured' ? 'Đã cấu hình · cần nghiệm thu' : 'Chưa sẵn sàng'}</Tag> },
            { title: 'Chi tiết', render: (_, item) => item.reason ?? item.message ?? '—' },
        ]} /></Card>
        <Card className="fnb-panel" title="Cấu hình kết nối" extra={canManage ? <Button onClick={() => openEditor('destination')}>Cấu hình kết nối</Button> : null}><Table rowKey="id" dataSource={asArray(data.mappings)} columns={[
            { title: 'Kết nối', dataIndex: 'destination', render: destinationLabel }, { title: 'Áp dụng', render: (_, item) => item.enabled ? 'Đã bật' : 'Đang tắt' },
            { title: 'Xử lý', render: (_, item) => item.automatic ? 'Tự động' : 'Đối soát thủ công' },
            { title: 'Kho / đơn vị kế toán', render: (_, item) => item.config?.warehouse_name ?? item.config?.organization_name ?? item.config?.warehouse_id ?? item.config?.organization_id ?? '—' },
            { title: '', render: (_, item) => canManage ? <Button onClick={() => openEditor('destination', item)}>Sửa</Button> : null },
        ]} /></Card>
        <Card className="fnb-panel" title="Ánh xạ nguyên liệu & món" extra={canManage ? <Button onClick={() => openEditor('item')}>Thêm ánh xạ</Button> : null}><Table rowKey={(item) => item.id ?? `${item.destination}:${item.source_type}:${item.source_id}`} dataSource={asArray(data.item_mappings)} scroll={{ x: 700 }} columns={[
            { title: 'Kết nối', dataIndex: 'destination', render: destinationLabel }, { title: 'Loại', render: (_, item) => item.source_type === 'ingredient' ? 'Nguyên liệu' : 'Món / size' }, { title: 'Nguồn', render: (_, item) => item.source_name ?? item.source_id }, { title: 'Đích', render: (_, item) => item.destination_name ?? item.destination_id }, { title: 'Đơn vị', dataIndex: 'unit' }, { title: 'Hệ số', dataIndex: 'factor' },
            { title: '', render: (_, item) => canManage ? <Button onClick={() => openEditor('item', item)}>Sửa</Button> : null },
        ]} /></Card>
        <Card className="fnb-panel" title="Hàng đợi đồng bộ" extra={canManage ? <Space wrap><Select placeholder="Chọn kết nối sẵn sàng" value={destination || undefined} onChange={setDestination} style={{ minWidth: 210 }} options={destinations.map((item) => ({ value: item.key, label: item.label ?? item.key }))} /><Checkbox checked={retryDeadLetter} onChange={(event) => setRetryDeadLetter(event.target.checked)}>Thử lại giao dịch đã dừng</Checkbox><Button loading={saving} disabled={!destination} onClick={reconcile}>Đối soát</Button></Space> : null}>
            <Alert type="info" showIcon message="Kết nối chưa sẵn sàng giữ dữ liệu chờ và không ngăn bán hàng." description="Giao dịch chưa rõ kết quả cần đối soát trước khi gửi lại; không tự tạo chứng từ ở hệ thống chưa được cấu hình." style={{ marginBottom: 16 }} />
            <Table rowKey="id" dataSource={asArray(data.backlog)} scroll={{ x: 900 }} columns={[
                { title: 'Kết nối', dataIndex: 'destination', render: destinationLabel }, { title: 'Sự kiện', dataIndex: 'event_type', render: (value) => value ?? 'Giao dịch' }, { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> }, { title: 'Số lần xử lý', dataIndex: 'attempts' },
                { title: 'Cập nhật', dataIndex: 'updated_at', render: formatDateTime }, { title: 'Lỗi gần nhất', dataIndex: 'last_error' },
            ]} />
        </Card>
        <Drawer open={Boolean(editor)} destroyOnHidden width="min(620px, 96vw)" title={editor?.kind === 'destination' ? 'Cấu hình kết nối' : 'Ánh xạ dữ liệu'} onClose={saving ? undefined : () => setEditor(null)} extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Lưu</Button>}>
            <Form form={form} layout="vertical" onFinish={saveMapping}>
                <Form.Item name="destination" label="Kết nối" rules={[{ required: true }]}><Select disabled={Boolean(editor?.record)} onChange={(value) => form.setFieldsValue({ source_type: value === 'inventory' ? 'ingredient' : 'variant', source_id: undefined, destination_id: undefined, config: {} })} options={[{ value: 'inventory', label: 'Kho' }, { value: 'accounting-tax', label: 'Kế toán' }]} /></Form.Item>
                {editor?.kind === 'destination' ? <>
                    <Form.Item name="enabled" label="Bật đồng bộ" valuePropName="checked"><Switch /></Form.Item>
                    <Form.Item name="automatic" valuePropName="checked" extra="Chỉ bật sau khi kiểm tra ánh xạ. Tác vụ nền kiểm tra lại quyền của tài khoản này khi xử lý; thay đổi quyền hoặc ánh xạ sẽ tạm dừng tự động."><Checkbox>Tự động đồng bộ bằng quyền tài khoản này</Checkbox></Form.Item>
                    <Form.Item name={['config', mappingDestination === 'inventory' ? 'warehouse_id' : 'organization_id']} label={mappingDestination === 'inventory' ? 'Kho xuất nguyên liệu' : 'Đơn vị kế toán'} rules={[{ required: true }]}>
                        <Select showSearch optionFilterProp="label" options={asArray(mappingDestination === 'inventory' ? data.destination_options?.warehouses : data.destination_options?.organizations).map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                </> : <>
                    <Form.Item name="source_type" label="Loại dữ liệu" rules={[{ required: true }]}><Select disabled options={mappingDestination === 'inventory' ? [{ value: 'ingredient', label: 'Nguyên liệu' }] : [{ value: 'variant', label: 'Món / size' }]} /></Form.Item>
                    <Form.Item name="source_id" label="Nguồn F&B" rules={[{ required: true }]}><Select showSearch optionFilterProp="label" disabled={Boolean(editor?.record)} options={asArray(sourceType === 'ingredient' ? data.source_options?.ingredients : data.source_options?.variants).map((item) => ({ value: item.id, label: `${item.code ?? ''} · ${item.item_name ? `${item.item_name} / ` : ''}${item.name}` }))} /></Form.Item>
                    <Form.Item name="destination_id" label="Hàng ở hệ thống đích" rules={[{ required: true }]}><Select showSearch optionFilterProp="label" onChange={(id) => {
                        const target = asArray(mappingDestination === 'inventory' ? data.destination_options?.inventory_items : data.destination_options?.accounting_items).find((item) => String(item.id) === String(id));
                        if (target?.unit) form.setFieldValue('unit', target.unit);
                    }} options={asArray(mappingDestination === 'inventory' ? data.destination_options?.inventory_items : data.destination_options?.accounting_items).map((item) => ({ value: item.id, label: `${item.name} (${item.unit ?? ''})` }))} /></Form.Item>
                    <Form.Item name="unit" label="Đơn vị" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="factor" label="Hệ số quy đổi" rules={[{ required: true }]}><InputNumber stringMode min="0.000001" precision={6} /></Form.Item>
                </>}
            </Form>
        </Drawer>
    </div>;
}

import EditOutlined from '@ant-design/icons/EditOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useCallback, useState } from 'react';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbPageHeader from '../../components/FnbPageHeader';
import { FnbResourceError } from '../../components/FnbResourceState';
import FnbStatusTag from '../../components/FnbStatusTag';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray } from '../../utils/fnbFormat';

const { Text } = Typography;

const configMeta = {
    outlet: { title: 'điểm bán', permission: 'fnb.outlet.manage' },
    terminal: { title: 'thiết bị', permission: 'fnb.terminal.manage', path: 'terminals' },
    area: { title: 'khu vực', permission: 'fnb.floor.manage', path: 'areas' },
    table: { title: 'bàn', permission: 'fnb.floor.manage', path: 'tables' },
    station: { title: 'trạm chế biến', permission: 'fnb.floor.manage', path: 'stations' },
    payment_method: { title: 'phương thức thanh toán', permission: 'fnb.settings.manage', path: 'payment-methods' },
};

function ConfigDrawer({ editor, form, areas, saving, onClose, onSubmit }) {
    const kind = editor?.kind;
    const record = editor?.record;
    const meta = configMeta[kind];
    const commonFields = kind && !['outlet'].includes(kind);

    return (
        <Drawer
            open={Boolean(editor)}
            title={`${record ? 'Cập nhật' : 'Thêm'} ${meta?.title ?? 'cấu hình'}`}
            width="min(620px, 96vw)"
            destroyOnHidden
            onClose={onClose}
            extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Lưu</Button>}
        >
            <Form form={form} layout="vertical" onFinish={onSubmit}>
                {kind === 'outlet' ? <>
                    <Form.Item name="name" label="Tên điểm bán" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="phone" label="Số điện thoại"><Input maxLength={40} /></Form.Item>
                    <Form.Item name="address" label="Địa chỉ"><Input.TextArea rows={3} maxLength={1000} /></Form.Item>
                </> : null}
                {commonFields ? <Row gutter={14}>
                    <Col xs={24} md={9}><Form.Item name="code" label="Mã" rules={[{ required: true }, { pattern: /^[A-Za-z0-9_-]+$/, message: 'Chỉ dùng chữ, số, gạch ngang hoặc gạch dưới.' }]}><Input maxLength={40} /></Form.Item></Col>
                    <Col xs={24} md={15}><Form.Item name="name" label="Tên" rules={[{ required: true }]}><Input /></Form.Item></Col>
                </Row> : null}
                {kind === 'terminal' ? <Form.Item name="type" label="Loại thiết bị" rules={[{ required: true }]}><Select options={[{ value: 'pos', label: 'Quầy POS' }, { value: 'kds', label: 'Màn hình Bar / Bếp' }]} /></Form.Item> : null}
                {kind === 'area' ? <Form.Item name="sort_order" label="Thứ tự"><InputNumber min={0} max={9999} precision={0} style={{ width: '100%' }} /></Form.Item> : null}
                {kind === 'table' ? <Row gutter={14}>
                    <Col xs={24} md={12}><Form.Item name="service_area_id" label="Khu vực" rules={[{ required: true }]}><Select options={areas.map((area) => ({ value: area.id, label: area.name }))} /></Form.Item></Col>
                    <Col xs={12} md={6}><Form.Item name="capacity" label="Sức chứa" rules={[{ required: true }]}><InputNumber min={1} max={100} precision={0} style={{ width: '100%' }} /></Form.Item></Col>
                    <Col xs={12} md={6}><Form.Item name="sort_order" label="Thứ tự"><InputNumber min={0} max={9999} precision={0} style={{ width: '100%' }} /></Form.Item></Col>
                    <Col span={24}><Form.Item name="status" label="Trạng thái"><Select options={[{ value: 'available', label: 'Sẵn sàng phục vụ' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item></Col>
                </Row> : null}
                {kind === 'station' ? <Form.Item name="sla_seconds" label="Mục tiêu hoàn tất (giây)"><InputNumber min={10} max={86400} precision={0} style={{ width: '100%' }} /></Form.Item> : null}
                {kind === 'payment_method' ? <>
                    <Form.Item name="kind" label="Loại thanh toán" rules={[{ required: true }]}><Select disabled={Boolean(record)} options={[{ value: 'cash', label: 'Tiền mặt' }, { value: 'transfer', label: 'Chuyển khoản' }, { value: 'card', label: 'Thẻ' }]} /></Form.Item>
                    <Form.Item name="requires_reference" label="Bắt buộc mã tham chiếu" valuePropName="checked"><Switch /></Form.Item>
                    {record ? <Alert type="info" showIcon message="Loại thanh toán không thể đổi sau khi đã sử dụng" description="Nếu cần loại khác, hãy ngừng phương thức cũ và tạo phương thức mới." /> : null}
                </> : null}
                {commonFields && kind !== 'table' ? <Form.Item name="status" label="Trạng thái"><Select options={[{ value: 'active', label: 'Đang hoạt động' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item> : null}
                {record ? <Alert type="warning" showIcon message="Dữ liệu sẽ được kiểm tra phiên bản khi lưu" description="Nếu có người khác vừa thay đổi, màn hình yêu cầu tải lại thay vì ghi đè." /> : null}
            </Form>
        </Drawer>
    );
}

function ConfigTable({ kind, rows, columns, canEdit, onCreate, onEdit, loading }) {
    return <>
        <div className="fnb-table-toolbar">
            <Text type="secondary">Mọi thay đổi được giới hạn trong điểm bán đang chọn.</Text>
            {canEdit ? <Button type="primary" icon={<PlusOutlined />} onClick={() => onCreate(kind)}>Thêm mới</Button> : null}
        </div>
        <Table
            rowKey={(record) => record.public_id ?? record.id}
            loading={loading}
            dataSource={rows}
            scroll={{ x: 700 }}
            columns={[...columns, { title: '', fixed: 'right', width: 90, render: (_, record) => canEdit ? <Button size="small" icon={<EditOutlined />} onClick={() => onEdit(kind, record)}>Sửa</Button> : null }]}
        />
    </>;
}

export default function FnbSettingsPage() {
    const { api, outletId, selectedOutlet, can, runCommand, reloadBootstrap } = useFnbWorkspace();
    const [editor, setEditor] = useState(null);
    const [saving, setSaving] = useState(false);
    const [form] = Form.useForm();
    const loadSettings = useCallback(() => api.settings(), [api]);
    const resource = useFnbResource({ enabled: Boolean(outletId), loader: loadSettings, deps: [outletId] });
    const settings = resource.data ?? {};
    const outlet = settings.outlet ?? selectedOutlet ?? {};
    const areas = asArray(settings.areas);

    const openEditor = (kind, record = null) => {
        form.resetFields();
        const defaults = {
            status: kind === 'table' ? 'available' : 'active',
            sort_order: 0,
            capacity: 2,
            type: kind === 'terminal' ? 'pos' : undefined,
            sla_seconds: kind === 'station' ? 600 : undefined,
            kind: kind === 'payment_method' ? 'cash' : undefined,
            requires_reference: false,
        };
        form.setFieldsValue(record ? { ...record } : defaults);
        setEditor({ kind, record });
    };

    const save = async (values) => {
        const kind = editor?.kind;
        const record = editor?.record;
        const meta = configMeta[kind];
        if (!meta) return;
        const key = createIdempotencyKey(`config-${kind}`);
        const payload = {
            ...values,
            ...(record?.version ? {
                expected_version: record.version,
                expected_versions: { [`${kind}:${record.public_id ?? record.id}`]: record.version },
            } : {}),
        };
        const path = kind === 'outlet'
            ? api.outletPath()
            : api.outletPath(`${meta.path}${record ? `/${record.id}` : ''}`);
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command(path, payload, { method: record ? 'PUT' : 'POST', idempotencyKey: key, ...auth }),
                successMessage: `Đã lưu ${meta.title}.`,
                onSuccess: async () => {
                    setEditor(null);
                    await resource.reload({ silent: true });
                    if (kind === 'outlet' || kind === 'terminal') await reloadBootstrap({ silent: true });
                },
                onConflict: resource.reload,
            });
        } finally {
            setSaving(false);
        }
    };

    if (resource.error && !resource.data) {
        return <FnbResourceError error={resource.error} onRetry={resource.reload} title="Không tải được thiết lập điểm bán" />;
    }

    const columns = {
        terminal: [
            { title: 'Thiết bị', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
            { title: 'Loại', dataIndex: 'type', render: (value) => <Tag>{value === 'kds' ? 'Bar / Bếp' : 'POS'}</Tag> },
            { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
        ],
        area: [
            { title: 'Khu vực', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
            { title: 'Số bàn', render: (_, record) => asArray(record.tables).length },
            { title: 'Thứ tự', dataIndex: 'sort_order' },
            { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
        ],
        table: [
            { title: 'Bàn', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
            { title: 'Khu vực', render: (_, record) => areas.find((area) => area.id === record.service_area_id)?.name ?? `#${record.service_area_id}` },
            { title: 'Sức chứa', dataIndex: 'capacity', render: (value) => `${value} khách` },
            { title: 'Thứ tự', dataIndex: 'sort_order' },
            { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
        ],
        station: [
            { title: 'Trạm', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
            { title: 'Mục tiêu', dataIndex: 'sla_seconds', render: (value) => value ? `${Math.round(Number(value) / 60)} phút` : '—' },
            { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
        ],
        payment_method: [
            { title: 'Phương thức', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
            { title: 'Loại', dataIndex: 'kind', render: (value) => ({ cash: 'Tiền mặt', transfer: 'Chuyển khoản', card: 'Thẻ' }[value] ?? value) },
            { title: 'Mã tham chiếu', dataIndex: 'requires_reference', render: (value) => value ? 'Bắt buộc' : 'Không bắt buộc' },
            { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
        ],
    };

    return (
        <div className="fnb-page-stack">
            <FnbPageHeader
                eyebrow="Cấu hình điểm bán"
                title="Thiết bị, bàn, Bar và thanh toán"
                description="Cấu hình có phiên bản, không sửa ngầm dữ liệu giao dịch đã phát sinh."
                actions={<Button icon={<ReloadOutlined />} loading={resource.refreshing} onClick={() => resource.reload({ silent: true })}>Làm mới</Button>}
            />
            {resource.error ? <Alert type="warning" showIcon message={resource.error.message} /> : null}
            <Card className="fnb-panel" title="Thông tin điểm bán" extra={can('fnb.outlet.manage') ? <Button icon={<EditOutlined />} onClick={() => openEditor('outlet', outlet)}>Cập nhật</Button> : null}>
                <Descriptions column={{ xs: 1, md: 2, xl: 3 }} items={[
                    { key: 'name', label: 'Tên', children: outlet.name ?? '—' },
                    { key: 'code', label: 'Mã', children: outlet.code ?? '—' },
                    { key: 'timezone', label: 'Múi giờ', children: outlet.timezone ?? '—' },
                    { key: 'currency', label: 'Tiền tệ', children: outlet.currency ?? 'VND' },
                    { key: 'phone', label: 'Điện thoại', children: outlet.phone ?? '—' },
                    { key: 'address', label: 'Địa chỉ', children: outlet.address ?? '—' },
                ]} />
            </Card>
            <Card className="fnb-panel">
                <Tabs items={[
                    { key: 'terminals', label: `Thiết bị (${asArray(settings.terminals).length})`, children: <ConfigTable kind="terminal" rows={asArray(settings.terminals)} columns={columns.terminal} canEdit={can('fnb.terminal.manage')} onCreate={openEditor} onEdit={openEditor} loading={resource.loading} /> },
                    { key: 'areas', label: `Khu vực (${areas.length})`, children: <ConfigTable kind="area" rows={areas} columns={columns.area} canEdit={can('fnb.floor.manage')} onCreate={openEditor} onEdit={openEditor} loading={resource.loading} /> },
                    { key: 'tables', label: `Bàn (${asArray(settings.tables).length})`, children: <ConfigTable kind="table" rows={asArray(settings.tables)} columns={columns.table} canEdit={can('fnb.floor.manage')} onCreate={openEditor} onEdit={openEditor} loading={resource.loading} /> },
                    { key: 'stations', label: `Trạm Bar (${asArray(settings.stations).length})`, children: <ConfigTable kind="station" rows={asArray(settings.stations)} columns={columns.station} canEdit={can('fnb.floor.manage')} onCreate={openEditor} onEdit={openEditor} loading={resource.loading} /> },
                    { key: 'payments', label: `Thanh toán (${asArray(settings.payment_methods).length})`, children: <ConfigTable kind="payment_method" rows={asArray(settings.payment_methods)} columns={columns.payment_method} canEdit={can('fnb.settings.manage')} onCreate={openEditor} onEdit={openEditor} loading={resource.loading} /> },
                ]} />
            </Card>
            <ConfigDrawer editor={editor} form={form} areas={areas} saving={saving} onClose={() => setEditor(null)} onSubmit={save} />
        </div>
    );
}

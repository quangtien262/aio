import { useEffect, useMemo, useState } from 'react';
import ApiOutlined from '@ant-design/icons/ApiOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import SyncOutlined from '@ant-design/icons/SyncOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import DatePicker from 'antd/es/date-picker';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import dayjs from 'dayjs';
import { adminApi } from '../../../shared/config/routes';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text, Title } = Typography;

function queryPath(path, params) {
    const query = new URLSearchParams();
    Object.entries(params ?? {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') query.set(key, value);
    });
    return adminApi(`${path}${query.size ? `?${query.toString()}` : ''}`);
}

function readinessColor(state) {
    return { installed: 'default', configured: 'blue', sandbox_verified: 'cyan', healthy: 'green', production_allowed: 'purple' }[state] ?? 'default';
}

export default function MinvoiceManagerPage({ callAdminApi, runAdminAction, organizationOptions = [], currentPermissions = [] }) {
    const [organizationId, setOrganizationId] = useState(null);
    const [connectionDrawerOpen, setConnectionDrawerOpen] = useState(false);
    const [editingConnection, setEditingConnection] = useState(null);
    const [productionConnection, setProductionConnection] = useState(null);
    const [connectionForm] = Form.useForm();
    const [productionForm] = Form.useForm();
    const canManageConnections = currentPermissions.includes('minvoice.connection.manage');
    const canConfigure = currentPermissions.includes('minvoice.configure');
    const canInbound = currentPermissions.includes('minvoice.inbound.sync');
    const canPreview = currentPermissions.includes('minvoice.outbound.preview');
    const canIssue = currentPermissions.includes('minvoice.outbound.issue');
    const canSyncOutbound = currentPermissions.includes('minvoice.outbound.sync');
    const canCreateInternalDraft = currentPermissions.includes('accounting.document.create');
    const canDownloadArtifact = currentPermissions.includes('minvoice.artifact.download');

    const organizations = organizationOptions;

    useEffect(() => {
        if (organizations.length && !organizations.some((organization) => organization.id === organizationId)) {
            setOrganizationId((organizations.find((organization) => organization.is_default) ?? organizations[0]).id);
        }
    }, [organizations, organizationId]);

    const connectionsResource = useAdminRouteResource({
        enabled: Boolean(organizationId),
        loader: async () => {
            const payload = await callAdminApi(queryPath('accounting-tax/minvoice/connections', { organization_id: organizationId }));
            return Array.isArray(payload.data) ? payload.data : (payload.data?.items ?? []);
        },
        deps: [organizationId],
    });
    const connections = connectionsResource.data ?? [];

    const openConnection = (connection = null) => {
        setEditingConnection(connection);
        connectionForm.setFieldsValue(connection ? {
            name: connection.name,
            channel: connection.channel,
            environment: connection.environment,
            base_url: connection.base_url,
            is_enabled: connection.is_enabled,
            settings: connection.settings ?? {},
            credentials: {},
        } : {
            name: '', channel: 'outbound', environment: 'sandbox', base_url: '', is_enabled: true,
            credentials: {}, settings: { signing_mode: 'draft_then_sign' },
        });
        setConnectionDrawerOpen(true);
    };

    const saveConnection = async () => {
        const values = await connectionForm.validateFields();
        const credentials = Object.fromEntries(Object.entries(values.credentials ?? {}).filter(([, value]) => value !== undefined && value !== ''));
        const payload = { ...values, organization_id: organizationId, credentials };
        const endpoint = editingConnection
            ? adminApi(`accounting-tax/minvoice/connections/${editingConnection.id}`)
            : adminApi('accounting-tax/minvoice/connections');
        const ok = await runAdminAction(
            () => callAdminApi(endpoint, { method: editingConnection ? 'PUT' : 'POST', body: JSON.stringify(payload) }),
            editingConnection ? 'Đã cập nhật kết nối Minvoice.' : 'Đã tạo kết nối Minvoice.',
            connectionsResource.reload,
        );
        if (ok) setConnectionDrawerOpen(false);
    };

    const connectionAction = (connection, suffix, body, message) => runAdminAction(
        () => callAdminApi(adminApi(`accounting-tax/minvoice/connections/${connection.id}/${suffix}`), { method: 'POST', body: JSON.stringify(body ?? {}) }),
        message, connectionsResource.reload,
    );

    const allowProduction = async () => {
        const values = await productionForm.validateFields();
        const ok = await connectionAction(productionConnection, 'allow-production', values, 'Đã mở cổng production cho kết nối.');
        if (ok) setProductionConnection(null);
    };

    if (!organizations.length) return <Card><Empty description="Cần tạo pháp nhân trong module Kế toán & Thuế trước khi cấu hình Minvoice." /></Card>;

    const sharedProps = {
        organizationId, connections, callAdminApi, runAdminAction, canInbound,
        canPreview, canIssue, canSyncOutbound, canCreateInternalDraft, canDownloadArtifact,
    };

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card>
                <Row gutter={[16, 12]} justify="space-between" align="middle">
                    <Col><Text className="card-label">Hóa đơn điện tử</Text><Title level={3} style={{ margin: '4px 0 0' }}>Minvoice / mSMI</Title></Col>
                    <Col><Select value={organizationId} onChange={setOrganizationId} style={{ minWidth: 260 }} options={organizations.map((organization) => ({ value: organization.id, label: `${organization.name}${organization.tax_code ? ` · ${organization.tax_code}` : ''}` }))} /></Col>
                </Row>
            </Card>
            <Alert
                type="warning" showIcon
                message="Production bị khóa theo nhiều lớp"
                description="Một kết nối chỉ được phép gọi production khi đã cấu hình, health check thành công, được xác nhận ALLOW PRODUCTION và kill switch đang tắt. Không lưu hoặc hiển thị lại credentials trên giao diện."
            />
            <Tabs items={[
                { key: 'connections', label: 'Kết nối & an toàn', children: (
                    <ConnectionsPanel
                        connections={connections} loading={connectionsResource.loading} error={connectionsResource.error}
                        canManageConnections={canManageConnections} canConfigure={canConfigure}
                        onCreate={() => openConnection()} onEdit={openConnection}
                        onTest={(connection) => connectionAction(connection, 'test', {}, 'Health check hoàn tất.')}
                        onKillSwitch={(connection, enabled) => connectionAction(connection, 'kill-switch', { enabled }, enabled ? 'Đã bật kill switch.' : 'Đã tắt kill switch.')}
                        onAllowProduction={(connection) => {
                            productionForm.resetFields();
                            productionForm.setFieldsValue({ confirmation: '' });
                            setProductionConnection(connection);
                        }}
                    />
                ) },
                { key: 'outbound', label: 'Hóa đơn đầu ra', children: <OutboundPanel {...sharedProps} /> },
                { key: 'inbound', label: 'Hóa đơn đầu vào mSMI', children: <InboundPanel {...sharedProps} /> },
            ]} />
            <ConnectionDrawer open={connectionDrawerOpen} form={connectionForm} editing={editingConnection} onClose={() => setConnectionDrawerOpen(false)} onSubmit={saveConnection} />
            <Modal title="Xác nhận mở production" open={Boolean(productionConnection)} onCancel={() => setProductionConnection(null)} onOk={allowProduction} okButtonProps={{ danger: true }} okText="Mở production">
                <Alert type="error" showIcon message="Thao tác nhạy cảm" description="Chỉ thực hiện sau UAT, xác nhận hợp đồng API và quy trình ký số hiện hành." style={{ marginBottom: 16 }} />
                <Form form={productionForm} layout="vertical">
                    <Form.Item name="contract_version" label="Phiên bản hợp đồng API/UAT đã duyệt" rules={[{ required: true }]}><Input placeholder="Ví dụ: minvoice-contract-2026-07" /></Form.Item>
                    <Form.Item name="confirmation" label="Nhập chính xác ALLOW PRODUCTION" rules={[{ required: true }, { validator: (_, value) => value === 'ALLOW PRODUCTION' ? Promise.resolve() : Promise.reject(new Error('Nội dung xác nhận chưa chính xác.')) }]}><Input /></Form.Item>
                </Form>
            </Modal>
        </Space>
    );
}

function ConnectionsPanel({ connections, loading, error, canManageConnections, canConfigure, onCreate, onEdit, onTest, onKillSwitch, onAllowProduction }) {
    if (error) return <Alert type="error" showIcon message={error} />;
    return (
        <Card title="Kết nối theo pháp nhân / môi trường" extra={<Button type="primary" icon={<PlusOutlined />} disabled={!canManageConnections} onClick={onCreate}>Thêm kết nối</Button>}>
            <Table rowKey="id" loading={loading} dataSource={connections} scroll={{ x: 1100 }} pagination={{ pageSize: 10 }} columns={[
                { title: 'Tên', dataIndex: 'name', render: (value, record) => <Button type="link" disabled={!canManageConnections} onClick={() => onEdit(record)}>{value}</Button> },
                { title: 'Kênh', dataIndex: 'channel', render: (value) => <Tag color={value === 'outbound' ? 'cyan' : 'purple'}>{value}</Tag> },
                { title: 'Môi trường', dataIndex: 'environment', render: (value) => <Tag color={value === 'production' ? 'red' : 'blue'}>{value}</Tag> },
                { title: 'Readiness', key: 'readiness', render: (_, record) => <Tag color={readinessColor(record.readiness?.state ?? record.readiness_state)}>{record.readiness?.state ?? record.readiness_state}</Tag> },
                { title: 'Health', dataIndex: 'health_status', render: (value) => <Tag color={value === 'healthy' ? 'green' : value === 'unhealthy' ? 'red' : 'default'}>{value}</Tag> },
                { title: 'Kill switch', dataIndex: 'kill_switch', render: (value, record) => <Switch checked={Boolean(value)} disabled={!canManageConnections} onChange={(enabled) => onKillSwitch(record, enabled)} /> },
                { title: 'Thao tác', width: 250, render: (_, record) => <Space wrap>
                    <Button size="small" icon={<ApiOutlined />} disabled={!canConfigure || record.kill_switch} onClick={() => onTest(record)}>Test</Button>
                    {record.environment === 'production' && !(record.readiness?.production_allowed) ? <Button size="small" danger icon={<SafetyCertificateOutlined />} disabled={!canConfigure} onClick={() => onAllowProduction(record)}>Mở production</Button> : null}
                </Space> },
            ]} />
        </Card>
    );
}

function ConnectionDrawer({ open, form, editing, onClose, onSubmit }) {
    const channel = Form.useWatch('channel', form);
    return (
        <Drawer title={editing ? 'Cập nhật kết nối' : 'Thêm kết nối Minvoice'} width={680} open={open} onClose={onClose} destroyOnHidden extra={<Button type="primary" onClick={onSubmit}>Lưu an toàn</Button>}>
            <Alert type="info" showIcon message={editing ? 'Để trống credential nếu không muốn thay đổi.' : 'Credential được mã hóa và không bao giờ trả lại qua API.'} style={{ marginBottom: 16 }} />
            <Form form={form} layout="vertical">
                <Row gutter={12}>
                    <Col span={12}><Form.Item name="name" label="Tên kết nối" rules={[{ required: true }]}><Input /></Form.Item></Col>
                    <Col span={6}><Form.Item name="channel" label="Kênh" rules={[{ required: true }]}><Select options={[{ value: 'outbound', label: 'Đầu ra' }, { value: 'inbound', label: 'Đầu vào mSMI' }]} /></Form.Item></Col>
                    <Col span={6}><Form.Item name="environment" label="Môi trường" rules={[{ required: true }]}><Select options={[{ value: 'sandbox', label: 'Sandbox' }, { value: 'production', label: 'Production' }]} /></Form.Item></Col>
                    <Col span={24}><Form.Item name="base_url" label="Base URL HTTPS" rules={[{ required: true }, { type: 'url' }]}><Input placeholder="https://..." /></Form.Item></Col>
                    <Col span={12}><Form.Item name={['credentials', 'tax_code']} label="Mã số thuế" rules={editing ? [] : [{ required: true }]}><Input autoComplete="off" /></Form.Item></Col>
                    {channel === 'inbound' ? (
                        <Col span={12}><Form.Item name={['credentials', 'api_token']} label="API token" rules={editing ? [] : [{ required: true }]}><Input.Password autoComplete="new-password" /></Form.Item></Col>
                    ) : <>
                        <Col span={12}><Form.Item name={['credentials', 'ma_dvcs']} label="Mã đơn vị cơ sở" rules={editing ? [] : [{ required: true }]}><Input autoComplete="off" /></Form.Item></Col>
                        <Col span={12}><Form.Item name={['credentials', 'username']} label="Username" rules={editing ? [] : [{ required: true }]}><Input autoComplete="off" /></Form.Item></Col>
                        <Col span={12}><Form.Item name={['credentials', 'password']} label="Password" rules={editing ? [] : [{ required: true }]}><Input.Password autoComplete="new-password" /></Form.Item></Col>
                    </>}
                    <Col span={12}><Form.Item name={['settings', 'default_series']} label="Ký hiệu mặc định"><Input /></Form.Item></Col>
                    <Col span={12}><Form.Item name="is_enabled" label="Kích hoạt cấu hình" valuePropName="checked"><Switch /></Form.Item></Col>
                </Row>
            </Form>
        </Drawer>
    );
}

function OutboundPanel({ connections, callAdminApi, runAdminAction, canPreview, canIssue, canSyncOutbound }) {
    const outboundConnections = useMemo(() => connections.filter((connection) => connection.channel === 'outbound'), [connections]);
    const [form] = Form.useForm();
    const [preview, setPreview] = useState(null);

    useEffect(() => {
        const selected = form.getFieldValue('connection_id');
        if (!outboundConnections.some((connection) => connection.id === selected)) {
            form.setFieldValue('connection_id', outboundConnections[0]?.id ?? null);
        }
    }, [form, outboundConnections]);

    const previewDocument = async () => {
        const values = await form.validateFields();
        try {
            const payload = await callAdminApi(adminApi(`accounting-tax/minvoice/documents/${values.document_id}/preview`), { method: 'POST', body: JSON.stringify({ connection_id: values.connection_id, series: values.series || undefined }) });
            setPreview(payload.data ?? null);
        } catch (error) {
            setPreview({ error: error instanceof Error ? error.message : 'Không preview được payload.' });
        }
    };

    const queueOperation = async (operation, successMessage) => {
        const values = await form.validateFields();
        return runAdminAction(
            () => callAdminApi(adminApi(`accounting-tax/minvoice/documents/${values.document_id}/${operation}`), { method: 'POST', body: JSON.stringify({ connection_id: values.connection_id, series: values.series || undefined }) }),
            successMessage,
        );
    };

    return (
        <Card title="Phát hành hóa đơn đầu ra">
            <Alert type="info" showIcon message="Luồng đề xuất: Preview → tạo draft provider → ký/gửi → đồng bộ trạng thái" description="Production guard ở backend luôn được kiểm tra lại; việc nút hiển thị trên UI không đồng nghĩa kết nối đã được phép gửi thật." style={{ marginBottom: 16 }} />
            <Form form={form} layout="inline" initialValues={{ connection_id: outboundConnections[0]?.id }}>
                <Form.Item name="connection_id" label="Kết nối" rules={[{ required: true }]}><Select style={{ minWidth: 220 }} options={outboundConnections.map((connection) => ({ value: connection.id, label: `${connection.name} · ${connection.environment}` }))} /></Form.Item>
                <Form.Item name="document_id" label="ID chứng từ" rules={[{ required: true }]}><Input style={{ width: 130 }} /></Form.Item>
                <Form.Item name="series" label="Ký hiệu"><Input style={{ width: 130 }} /></Form.Item>
                <Space wrap>
                    <Button disabled={!canPreview} onClick={previewDocument}>Preview</Button>
                    <Button disabled={!canIssue} onClick={() => queueOperation('create-draft', 'Đã đưa yêu cầu tạo draft vào hàng đợi.')}>Tạo draft</Button>
                    <Button type="primary" icon={<SafetyCertificateOutlined />} disabled={!canIssue} onClick={() => queueOperation('sign-send', 'Đã đưa yêu cầu ký/gửi vào hàng đợi.')}>Ký & gửi</Button>
                    <Button icon={<SyncOutlined />} disabled={!canSyncOutbound} onClick={() => queueOperation('sync-status', 'Đã đưa yêu cầu đồng bộ trạng thái vào hàng đợi.')}>Đồng bộ</Button>
                </Space>
            </Form>
            {preview ? <Card size="small" title="Preview đã làm sạch" style={{ marginTop: 16 }}><pre style={{ whiteSpace: 'pre-wrap', maxHeight: 420, overflow: 'auto' }}>{JSON.stringify(preview, null, 2)}</pre></Card> : null}
        </Card>
    );
}

function InboundPanel({ connections, callAdminApi, runAdminAction, canInbound, canCreateInternalDraft, canDownloadArtifact }) {
    const inboundConnections = useMemo(() => connections.filter((connection) => connection.channel === 'inbound'), [connections]);
    const [connectionId, setConnectionId] = useState(null);
    const [range, setRange] = useState([dayjs().subtract(30, 'day'), dayjs()]);

    useEffect(() => {
        if (!inboundConnections.some((connection) => connection.id === connectionId)) setConnectionId(inboundConnections[0]?.id ?? null);
    }, [inboundConnections, connectionId]);

    const invoicesResource = useAdminRouteResource({
        enabled: Boolean(connectionId),
        loader: async () => (await callAdminApi(queryPath('accounting-tax/minvoice/inbound', { connection_id: connectionId }))).data ?? null,
        deps: [connectionId],
    });
    const invoices = Array.isArray(invoicesResource.data) ? invoicesResource.data : (invoicesResource.data?.items ?? []);

    const sync = () => runAdminAction(
        () => callAdminApi(adminApi('accounting-tax/minvoice/inbound/sync'), {
            method: 'POST', body: JSON.stringify({ connection_id: connectionId, date_from: range?.[0]?.format('YYYY-MM-DD'), date_to: range?.[1]?.format('YYYY-MM-DD') }),
        }),
        'Đã đưa tác vụ đồng bộ mSMI vào hàng đợi.', invoicesResource.reload,
    );

    const invoiceAction = (invoice, action, message) => runAdminAction(
        () => callAdminApi(adminApi(`accounting-tax/minvoice/inbound/${invoice.id}/${action}`), { method: 'POST' }),
        message, invoicesResource.reload,
    );

    return (
        <Card title="Hóa đơn đầu vào mSMI">
            <Space wrap style={{ marginBottom: 16 }}>
                <Select value={connectionId} style={{ minWidth: 240 }} onChange={setConnectionId} options={inboundConnections.map((connection) => ({ value: connection.id, label: `${connection.name} · ${connection.environment}` }))} placeholder="Chọn kết nối đầu vào" />
                <DatePicker.RangePicker value={range} onChange={setRange} format="DD/MM/YYYY" />
                <Button type="primary" icon={<SyncOutlined />} disabled={!canInbound || !connectionId} onClick={sync}>Đồng bộ read-only</Button>
            </Space>
            <Table rowKey="id" loading={invoicesResource.loading} dataSource={invoices} pagination={{ pageSize: 12 }} scroll={{ x: 950 }} columns={[
                { title: 'Số hóa đơn', dataIndex: 'invoice_number' },
                { title: 'Ký hiệu', dataIndex: 'invoice_series' },
                { title: 'Ngày', dataIndex: 'invoice_date', render: (value) => value ? dayjs(value).format('DD/MM/YYYY') : '-' },
                { title: 'Người bán', dataIndex: 'seller_name' },
                { title: 'MST', dataIndex: 'seller_tax_code' },
                { title: 'Tổng tiền', dataIndex: 'total_amount', align: 'right', render: (value, record) => `${Number(value ?? 0).toLocaleString('vi-VN')} ${record.currency ?? 'VND'}` },
                { title: 'Đối soát', dataIndex: 'reconciliation_status', render: (value) => <Tag>{value}</Tag> },
                { title: 'Cảnh báo', dataIndex: 'warnings', render: (value) => (value ?? []).length ? <Tag color="orange">{value.length}</Tag> : '-' },
                { title: 'Thao tác', width: 310, render: (_, invoice) => <Space wrap>
                    <Button size="small" disabled={!canInbound} onClick={() => invoiceAction(invoice, 'check-warning', 'Đã làm mới cảnh báo hóa đơn.')}>Kiểm tra</Button>
                    {!invoice.document_id ? <Button size="small" type="primary" disabled={!canInbound || !canCreateInternalDraft} onClick={() => invoiceAction(invoice, 'create-internal-draft', 'Đã tạo chứng từ nội bộ chờ duyệt.')}>Tạo nháp nội bộ</Button> : <Tag color="green">Chứng từ #{invoice.document_id}</Tag>}
                    {invoice.has_xml ? <Button size="small" disabled={!canDownloadArtifact} onClick={() => { window.location.href = adminApi(`accounting-tax/minvoice/inbound/${invoice.id}/artifacts/xml`); }}>XML</Button> : <Button size="small" disabled={!canInbound} onClick={() => invoiceAction(invoice, 'fetch-xml', 'Đã tải XML vào vùng lưu trữ riêng tư.')}>Lấy XML</Button>}
                </Space> },
            ]} />
        </Card>
    );
}

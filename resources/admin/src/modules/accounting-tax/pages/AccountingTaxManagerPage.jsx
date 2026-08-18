import { useEffect, useMemo, useState } from 'react';
import DownloadOutlined from '@ant-design/icons/DownloadOutlined';
import MailOutlined from '@ant-design/icons/MailOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import SyncOutlined from '@ant-design/icons/SyncOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import DatePicker from 'antd/es/date-picker';
import Descriptions from 'antd/es/descriptions';
import Divider from 'antd/es/divider';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import dayjs from 'dayjs';
import { adminApi } from '../../../shared/config/routes';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text, Title } = Typography;

const statusColors = {
    draft: 'default', approved: 'blue', posted: 'green', void: 'red',
    queued: 'gold', processing: 'blue', completed: 'green', failed: 'red',
    sending: 'blue', retrying: 'orange', sent: 'green',
};

const itemKindLabels = {
    goods: 'Hàng hóa', service: 'Dịch vụ', charge: 'Phụ phí', asset: 'Tài sản', bundle: 'Gói',
};

const documentTypeLabels = {
    internal_invoice: 'Hóa đơn nội bộ', tax_invoice: 'Hóa đơn thuế', credit_note: 'Điều chỉnh giảm',
    debit_note: 'Điều chỉnh tăng', receipt: 'Phiếu thu', expense: 'Chi phí',
};

function withQuery(path, params) {
    const query = new URLSearchParams();
    Object.entries(params ?? {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') query.set(key, value);
    });
    const suffix = query.toString();
    return adminApi(suffix ? `${path}?${suffix}` : path);
}

function money(value, currency = 'VND') {
    return `${Number(value ?? 0).toLocaleString('vi-VN', { maximumFractionDigits: 2 })} ${currency}`;
}

function dateTime(value) {
    return value ? dayjs(value).format('DD/MM/YYYY HH:mm') : '-';
}

function StatusTag({ value }) {
    return <Tag color={statusColors[value] ?? 'default'}>{value ?? '-'}</Tag>;
}

export default function AccountingTaxManagerPage({ moduleMenu, callAdminApi, runAdminAction, currentPermissions = [] }) {
    const sectionKey = moduleMenu?.key ?? 'accounting-tax-dashboard';
    const [selectedOrganizationId, setSelectedOrganizationId] = useState(null);
    const [organizationModalOpen, setOrganizationModalOpen] = useState(false);
    const [organizationForm] = Form.useForm();
    const canConfigure = currentPermissions.includes('accounting.organization.manage');

    const organizationsResource = useAdminRouteResource({
        loader: async () => {
            const payload = await callAdminApi(adminApi('accounting-tax/organizations'));
            return payload.data?.items ?? [];
        },
        deps: [],
    });
    const organizations = organizationsResource.data ?? [];

    useEffect(() => {
        if (!organizations.length) {
            setSelectedOrganizationId(null);
            return;
        }

        if (!organizations.some((organization) => organization.id === selectedOrganizationId)) {
            const preferred = organizations.find((organization) => organization.is_default) ?? organizations[0];
            setSelectedOrganizationId(preferred.id);
        }
    }, [organizations, selectedOrganizationId]);

    const createOrganization = async () => {
        const values = await organizationForm.validateFields();
        const ok = await runAdminAction(
            () => callAdminApi(adminApi('accounting-tax/organizations'), {
                method: 'POST',
                body: JSON.stringify({ ...values, default_currency: 'VND', is_default: organizations.length === 0 }),
            }),
            'Đã tạo pháp nhân kế toán.',
            organizationsResource.reload,
        );
        if (ok) {
            setOrganizationModalOpen(false);
            organizationForm.resetFields();
        }
    };

    if (organizationsResource.error) {
        return <Alert type="error" showIcon message={organizationsResource.error} />;
    }

    if (organizationsResource.loading) {
        return <Card loading title="Kế toán & Thuế" />;
    }

    if (!organizations.length) {
        return (
            <Card>
                <Empty description="Chưa có pháp nhân kế toán">
                    <Button type="primary" disabled={!canConfigure} onClick={() => setOrganizationModalOpen(true)}>Tạo pháp nhân đầu tiên</Button>
                </Empty>
                <OrganizationModal open={organizationModalOpen} form={organizationForm} onCancel={() => setOrganizationModalOpen(false)} onSubmit={createOrganization} />
            </Card>
        );
    }

    const sharedProps = {
        organizationId: selectedOrganizationId,
        callAdminApi,
        runAdminAction,
        currentPermissions,
    };

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card>
                <Row gutter={[16, 12]} align="middle" justify="space-between">
                    <Col>
                        <Text className="card-label">Pháp nhân / MST</Text>
                        <Title level={3} style={{ margin: '4px 0 0' }}>{moduleMenu?.label ?? 'Kế toán & Thuế'}</Title>
                    </Col>
                    <Col>
                        <Space wrap>
                            <Select
                                value={selectedOrganizationId}
                                style={{ minWidth: 260 }}
                                onChange={setSelectedOrganizationId}
                                options={organizations.map((organization) => ({
                                    value: organization.id,
                                    label: `${organization.name}${organization.tax_code ? ` · ${organization.tax_code}` : ''}`,
                                }))}
                            />
                            <Button disabled={!canConfigure} onClick={() => setOrganizationModalOpen(true)} icon={<PlusOutlined />}>Pháp nhân</Button>
                        </Space>
                    </Col>
                </Row>
            </Card>

            {sectionKey === 'accounting-tax-documents' ? <DocumentsSection {...sharedProps} /> : null}
            {sectionKey === 'accounting-tax-items' ? <ItemsSection {...sharedProps} /> : null}
            {sectionKey === 'accounting-tax-reports' ? <ReportsSection {...sharedProps} /> : null}
            {sectionKey === 'accounting-tax-integrations' ? <IntegrationsSection {...sharedProps} /> : null}
            {!['accounting-tax-documents', 'accounting-tax-items', 'accounting-tax-reports', 'accounting-tax-integrations'].includes(sectionKey)
                ? <DashboardSection {...sharedProps} /> : null}

            <OrganizationModal open={organizationModalOpen} form={organizationForm} onCancel={() => setOrganizationModalOpen(false)} onSubmit={createOrganization} />
        </Space>
    );
}

function DashboardSection({ organizationId, callAdminApi }) {
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: Boolean(organizationId),
        loader: async () => {
            const [dashboardPayload, integrationsPayload] = await Promise.all([
                callAdminApi(withQuery('accounting-tax/dashboard', { organization_id: organizationId })),
                callAdminApi(withQuery('accounting-tax/integrations', { organization_id: organizationId })),
            ]);
            return { ...(dashboardPayload.data ?? {}), integrationDetails: integrationsPayload.data ?? {} };
        },
        deps: [organizationId],
    });

    if (error) return <Alert type="error" showIcon message={error} action={<Button onClick={reload}>Thử lại</Button>} />;
    if (loading) return <Card loading />;

    const metrics = data?.metrics ?? {};
    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} xl={6}><Card><Statistic title="Sản phẩm / dịch vụ" value={metrics.items ?? 0} /></Card></Col>
                <Col xs={24} sm={12} xl={6}><Card><Statistic title="Chứng từ nháp" value={metrics.draft_documents ?? 0} /></Card></Col>
                <Col xs={24} sm={12} xl={6}><Card><Statistic title="Đã ghi sổ" value={metrics.posted_documents ?? 0} /></Card></Col>
                <Col xs={24} sm={12} xl={6}><Card><Statistic title="HĐĐT chưa đối soát" value={metrics.unmatched_external_invoices ?? 0} /></Card></Col>
            </Row>
            <Alert
                type="info"
                showIcon
                message="Phạm vi dữ liệu theo pháp nhân"
                description="Chứng từ, báo cáo, email và kết nối hóa đơn điện tử được tách theo pháp nhân/MST. Các module Catalog, CMS, Kho và Minvoice chỉ hoạt động khi đã được cài và bật đúng capability."
            />
            <IntegrationCards integrations={data?.integrationDetails?.integrations ?? data?.integrations ?? {}} />
        </Space>
    );
}

function DocumentsSection({ organizationId, callAdminApi, runAdminAction, currentPermissions }) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editingDocument, setEditingDocument] = useState(null);
    const [detailDocument, setDetailDocument] = useState(null);
    const [emailDocument, setEmailDocument] = useState(null);
    const [partyModalOpen, setPartyModalOpen] = useState(false);
    const [partyManagerOpen, setPartyManagerOpen] = useState(false);
    const [editingParty, setEditingParty] = useState(null);
    const [documentAction, setDocumentAction] = useState(null);
    const [documentForm] = Form.useForm();
    const [emailForm] = Form.useForm();
    const [partyForm] = Form.useForm();
    const [actionForm] = Form.useForm();
    const canCreate = currentPermissions.includes('accounting.document.create');
    const canUpdate = currentPermissions.includes('accounting.document.update');
    const canApprove = currentPermissions.includes('accounting.document.approve');
    const canPost = currentPermissions.includes('accounting.document.post');
    const canMail = currentPermissions.includes('accounting.mail.send');
    const canManageParties = currentPermissions.includes('accounting.party.manage');
    const canVoid = currentPermissions.includes('accounting.document.void');
    const canPayment = currentPermissions.includes('accounting.document.payment.manage');
    const canInventory = currentPermissions.includes('accounting.inventory.post');
    const canAssessTax = currentPermissions.includes('accounting.tax.assess');

    const inventoryWarehousesResource = useAdminRouteResource({
        enabled: Boolean(organizationId && canInventory),
        loader: async () => {
            const integrationsPayload = await callAdminApi(withQuery('accounting-tax/integrations', { organization_id: organizationId }));
            const inventoryEnabled = integrationsPayload.data?.integrations?.['inventory.documents.write.v1']?.enabled === true;

            if (!inventoryEnabled) return { enabled: false, items: [] };

            const warehousesPayload = await callAdminApi(withQuery('accounting-tax/inventory/warehouses', { organization_id: organizationId }));
            return { enabled: true, items: warehousesPayload.data?.items ?? [] };
        },
        deps: [organizationId, canInventory],
    });

    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: Boolean(organizationId),
        loader: async () => {
            const [documentsPayload, partiesPayload, itemsPayload, deliveriesPayload] = await Promise.all([
                callAdminApi(withQuery('accounting-tax/documents', { organization_id: organizationId })),
                callAdminApi(withQuery('accounting-tax/parties', { organization_id: organizationId })),
                callAdminApi(withQuery('accounting-tax/items', { organization_id: organizationId })),
                callAdminApi(withQuery('accounting-tax/email-deliveries', { organization_id: organizationId })),
            ]);
            return {
                documents: documentsPayload.data ?? {},
                parties: partiesPayload.data?.items ?? [],
                items: itemsPayload.data?.items ?? [],
                deliveries: deliveriesPayload.data?.items ?? [],
            };
        },
        deps: [organizationId],
    });

    const openCreate = () => {
        setEditingDocument(null);
        documentForm.setFieldsValue({
            direction: 'outbound', document_type: 'internal_invoice', document_date: dayjs(), currency: 'VND',
            exchange_rate: 1,
            party_id: data?.parties?.[0]?.id ?? null,
            lines: [{ line_type: 'item', item_kind: 'service', name: '', unit: 'lần', quantity: 1, unit_price: 0, discount_amount: 0, tax_category: 'standard', tax_rate: 10 }],
        });
        setCreateOpen(true);
    };

    const openEditDocument = (document) => {
        setEditingDocument(document);
        documentForm.setFieldsValue({
            ...document,
            party_id: document.party?.id ?? null,
            document_date: document.document_date ? dayjs(document.document_date) : null,
            goods_transfer_at: document.metadata?.goods_transfer_at ? dayjs(document.metadata.goods_transfer_at) : null,
            service_completed_at: document.metadata?.service_completed_at ? dayjs(document.metadata.service_completed_at) : null,
            payment_received_at: document.metadata?.payment_received_at ? dayjs(document.metadata.payment_received_at) : null,
            tax_point_at: document.metadata?.tax_point_at ? dayjs(document.metadata.tax_point_at) : null,
            lines: document.lines ?? [],
        });
        setCreateOpen(true);
    };

    const createDocument = async () => {
        const values = await documentForm.validateFields();
        const {
            goods_transfer_at: goodsTransferAt,
            service_completed_at: serviceCompletedAt,
            payment_received_at: paymentReceivedAt,
            tax_point_at: taxPointAt,
            ...documentValues
        } = values;
        const metadata = { ...(editingDocument?.metadata ?? {}) };
        Object.entries({
            goods_transfer_at: goodsTransferAt,
            service_completed_at: serviceCompletedAt,
            payment_received_at: paymentReceivedAt,
            tax_point_at: taxPointAt,
        }).forEach(([key, value]) => {
            if (value) metadata[key] = value.format('YYYY-MM-DDTHH:mm:ssZ');
            else delete metadata[key];
        });
        const payload = {
            ...documentValues,
            metadata,
            ...(editingDocument ? { version: editingDocument.version } : { organization_id: organizationId }),
            document_date: documentValues.document_date?.format('YYYY-MM-DD'),
            ...(!editingDocument ? { idempotency_key: globalThis.crypto?.randomUUID?.() ?? `doc-${Date.now()}` } : {}),
        };
        const ok = await runAdminAction(
            () => callAdminApi(adminApi(editingDocument ? `accounting-tax/documents/${editingDocument.id}` : 'accounting-tax/documents'), { method: editingDocument ? 'PUT' : 'POST', body: JSON.stringify(payload) }),
            editingDocument ? 'Đã cập nhật chứng từ nháp.' : 'Đã tạo chứng từ nháp.', reload,
        );
        if (ok) {
            setCreateOpen(false);
            setEditingDocument(null);
        }
    };

    const transition = (document, action, message) => runAdminAction(
        () => callAdminApi(adminApi(`accounting-tax/documents/${document.id}/${action}`), { method: 'POST', body: JSON.stringify({ version: document.version, idempotency_key: globalThis.crypto?.randomUUID?.() ?? `${action}-${Date.now()}` }) }),
        message, reload,
    );

    const createParty = async () => {
        const values = await partyForm.validateFields();
        const endpoint = editingParty ? adminApi(`accounting-tax/parties/${editingParty.id}`) : adminApi('accounting-tax/parties');
        const ok = await runAdminAction(
            () => callAdminApi(endpoint, { method: editingParty ? 'PUT' : 'POST', body: JSON.stringify(editingParty ? values : { ...values, organization_id: organizationId }) }),
            editingParty ? 'Đã cập nhật đối tác.' : 'Đã tạo khách hàng/nhà cung cấp.', reload,
        );
        if (ok) {
            setPartyModalOpen(false);
            setEditingParty(null);
        }
    };

    const openNewParty = () => {
        setEditingParty(null);
        partyForm.setFieldsValue({ type: 'customer', name: '', tax_code: '', email: '', address: '' });
        setPartyModalOpen(true);
    };

    const openEditParty = (party) => {
        setEditingParty(party);
        partyForm.setFieldsValue(party);
        setPartyModalOpen(true);
    };

    const openAction = (kind, document) => {
        const mappedWarehouses = inventoryWarehousesResource.data?.items ?? [];
        const defaultWarehouse = mappedWarehouses.find((mapping) => mapping.is_default) ?? mappedWarehouses[0];
        actionForm.setFieldsValue({
            reason: '', amount: Math.max(0, Number(document.grand_total ?? 0) - Number(document.paid_amount ?? 0)),
            paid_at: dayjs(), reference: '', warehouse_id: kind === 'inventory' ? defaultWarehouse?.inventory_warehouse_id ?? null : null, tax_eligibility: 'ineligible',
        });
        setDocumentAction({ kind, document });
    };

    const submitDocumentAction = async () => {
        const values = await actionForm.validateFields();
        const { kind, document } = documentAction;
        const route = kind === 'payment' ? 'payments' : kind === 'inventory' ? 'inventory/propose' : kind === 'tax' ? 'tax-assessment' : kind;
        const payload = kind === 'payment'
            ? { amount: values.amount, paid_at: values.paid_at?.toISOString(), reference: values.reference, version: document.version, idempotency_key: globalThis.crypto?.randomUUID?.() ?? `pay-${Date.now()}` }
            : kind === 'inventory'
                ? { warehouse_id: values.warehouse_id }
                : kind === 'tax'
                    ? { tax_eligibility: values.tax_eligibility, reason: values.reason, version: document.version, idempotency_key: globalThis.crypto?.randomUUID?.() ?? `tax-${Date.now()}` }
                : { reason: values.reason, version: document.version, idempotency_key: globalThis.crypto?.randomUUID?.() ?? `${kind}-${Date.now()}` };
        const ok = await runAdminAction(
            () => callAdminApi(adminApi(`accounting-tax/documents/${document.id}/${route}`), { method: 'POST', body: JSON.stringify(payload) }),
            kind === 'payment' ? 'Đã ghi nhận thanh toán.' : kind === 'inventory' ? 'Đã tạo đề nghị phiếu kho.' : kind === 'tax' ? 'Đã lưu đánh giá điều kiện khấu trừ.' : kind === 'reverse' ? 'Đã tạo chứng từ đảo.' : 'Đã vô hiệu chứng từ.',
            reload,
        );
        if (ok) setDocumentAction(null);
    };

    const openEmail = (document) => {
        emailForm.setFieldsValue({
            recipient_email: document.party?.email ?? '', recipient_name: document.party?.name ?? '',
            subject: `Hóa đơn/chứng từ ${document.document_no ?? `#${document.id}`}`,
            include_document_csv: true,
        });
        setEmailDocument(document);
    };

    const sendEmail = async () => {
        const values = await emailForm.validateFields();
        const idempotencyKey = globalThis.crypto?.randomUUID?.() ?? `mail-${Date.now()}`;
        const ok = await runAdminAction(
            () => callAdminApi(adminApi(`accounting-tax/documents/${emailDocument.id}/email`), {
                method: 'POST', headers: { 'Idempotency-Key': idempotencyKey }, body: JSON.stringify(values),
            }),
            'Đã đưa email vào hàng đợi.', reload,
        );
        if (ok) setEmailDocument(null);
    };

    const retryEmail = (delivery) => runAdminAction(
        () => callAdminApi(adminApi(`accounting-tax/email-deliveries/${delivery.id}/retry`), { method: 'POST' }),
        'Đã đưa email lại vào hàng đợi.', reload,
    );

    if (error) return <Alert type="error" showIcon message={error} />;
    return (
        <Card title="Hóa đơn & chứng từ" extra={<Space><Button onClick={() => setPartyManagerOpen(true)}>Đối tác</Button><Button type="primary" icon={<PlusOutlined />} disabled={!canCreate} onClick={openCreate}>Tạo chứng từ</Button></Space>}>
            <Table
                rowKey="id" loading={loading} dataSource={data?.documents?.items ?? []} scroll={{ x: 1250 }} pagination={{ pageSize: 12 }}
                columns={[
                    { title: 'Số chứng từ', dataIndex: 'document_no', render: (value, record) => <Button type="link" onClick={() => setDetailDocument(record)}>{value || `#${record.id}`}</Button> },
                    { title: 'Ngày', dataIndex: 'document_date', render: (value) => value ? dayjs(value).format('DD/MM/YYYY') : '-' },
                    { title: 'Chiều', dataIndex: 'direction', render: (value) => <Tag color={value === 'outbound' ? 'cyan' : 'purple'}>{value === 'outbound' ? 'Đầu ra' : 'Đầu vào'}</Tag> },
                    { title: 'Loại', dataIndex: 'document_type', render: (value) => documentTypeLabels[value] ?? value },
                    { title: 'Đối tác', key: 'party', render: (_, record) => record.party?.name ?? '-' },
                    { title: 'Tổng tiền', dataIndex: 'grand_total', align: 'right', render: (value, record) => money(value, record.currency) },
                    { title: 'Quy trình', dataIndex: 'workflow_status', render: (value) => <StatusTag value={value} /> },
                    { title: 'Email', dataIndex: 'mail_status', render: (value) => <StatusTag value={value} /> },
                    { title: 'Thao tác', fixed: 'right', width: 380, render: (_, record) => (
                        <Space wrap>
                            {record.workflow_status === 'draft' ? <Button size="small" disabled={!canUpdate} onClick={() => openEditDocument(record)}>Sửa</Button> : null}
                            <Button size="small" disabled={!canApprove || record.workflow_status !== 'draft'} onClick={() => transition(record, 'approve', 'Đã duyệt chứng từ.')}>Duyệt</Button>
                            <Button size="small" type="primary" disabled={!canPost || record.workflow_status !== 'approved'} onClick={() => transition(record, 'post', 'Đã ghi sổ chứng từ.')}>Ghi sổ</Button>
                            <Button size="small" icon={<MailOutlined />} disabled={!canMail} onClick={() => openEmail(record)}>Email</Button>
                            {['draft', 'approved'].includes(record.workflow_status) ? <Button size="small" danger disabled={!canVoid} onClick={() => openAction('void', record)}>Vô hiệu</Button> : null}
                            {record.workflow_status === 'posted' && record.reversal_status === 'none' ? <Button size="small" danger disabled={!canVoid} onClick={() => openAction('reverse', record)}>Lập đảo</Button> : null}
                            {record.workflow_status === 'posted' ? <Button size="small" disabled={!canPayment} onClick={() => openAction('payment', record)}>Thanh toán</Button> : null}
                            {record.workflow_status === 'posted' && (record.lines ?? []).some((line) => line.item_kind === 'goods') ? <Button
                                size="small"
                                disabled={!canInventory || inventoryWarehousesResource.loading || !(inventoryWarehousesResource.data?.items ?? []).length}
                                title={!(inventoryWarehousesResource.data?.items ?? []).length ? 'Cần bật module Kho và gắn ít nhất một kho với pháp nhân này.' : undefined}
                                onClick={() => openAction('inventory', record)}
                            >Phiếu kho</Button> : null}
                            {record.workflow_status === 'posted' && record.direction === 'inbound' ? <Button size="small" disabled={!canAssessTax} onClick={() => openAction('tax', record)}>Khấu trừ VAT</Button> : null}
                        </Space>
                    ) },
                ]}
            />
            <Divider orientation="left">Lịch sử gửi email</Divider>
            <Table rowKey="id" size="small" dataSource={data?.deliveries ?? []} pagination={{ pageSize: 6 }} columns={[
                { title: 'Chứng từ', dataIndex: 'document_id', render: (value) => `#${value}` },
                { title: 'Người nhận', dataIndex: 'recipient_email' },
                { title: 'Tiêu đề', dataIndex: 'subject', ellipsis: true },
                { title: 'Trạng thái', dataIndex: 'status', render: (value) => <StatusTag value={value} /> },
                { title: 'Lần thử', dataIndex: 'attempt_count', align: 'right' },
                { title: 'Cập nhật', dataIndex: 'completed_at', render: (value, record) => dateTime(value ?? record.queued_at) },
                { title: '', render: (_, delivery) => delivery.status === 'failed' ? <Button size="small" danger disabled={!canMail} onClick={() => retryEmail(delivery)}>Gửi lại</Button> : null },
            ]} />
            <DocumentDrawer open={createOpen} editing={editingDocument} form={documentForm} parties={data?.parties ?? []} items={data?.items ?? []} onClose={() => { setCreateOpen(false); setEditingDocument(null); }} onSubmit={createDocument} />
            <DocumentDetail document={detailDocument} onClose={() => setDetailDocument(null)} />
            <Modal title="Gửi chứng từ qua email" open={Boolean(emailDocument)} onCancel={() => setEmailDocument(null)} onOk={sendEmail} okText="Đưa vào hàng đợi">
                <Form form={emailForm} layout="vertical">
                    <Form.Item name="recipient_email" label="Email người nhận" rules={[{ required: true, type: 'email' }]}><Input /></Form.Item>
                    <Form.Item name="recipient_name" label="Tên người nhận"><Input /></Form.Item>
                    <Form.Item name="subject" label="Tiêu đề"><Input /></Form.Item>
                    <Form.Item name="include_document_csv" label="Đính kèm snapshot CSV bất biến" valuePropName="checked"><Switch /></Form.Item>
                </Form>
            </Modal>
            <Drawer title="Khách hàng / nhà cung cấp" width={720} open={partyManagerOpen} onClose={() => setPartyManagerOpen(false)} extra={<Button type="primary" disabled={!canManageParties} onClick={openNewParty}>Thêm đối tác</Button>}>
                <Table rowKey="id" dataSource={data?.parties ?? []} pagination={{ pageSize: 10 }} columns={[
                    { title: 'Tên', dataIndex: 'name' },
                    { title: 'Loại', dataIndex: 'type' },
                    { title: 'MST', dataIndex: 'tax_code', render: (value) => value || '-' },
                    { title: 'Email', dataIndex: 'email', render: (value) => value || '-' },
                    { title: '', render: (_, party) => <Button size="small" disabled={!canManageParties} onClick={() => openEditParty(party)}>Sửa</Button> },
                ]} />
            </Drawer>
            <Modal title={editingParty ? 'Sửa đối tác' : 'Thêm khách hàng / nhà cung cấp'} open={partyModalOpen} onCancel={() => { setPartyModalOpen(false); setEditingParty(null); }} onOk={createParty} okText="Lưu đối tác">
                <Form form={partyForm} layout="vertical">
                    <Form.Item name="type" label="Loại" rules={[{ required: true }]}><Select options={[{ value: 'customer', label: 'Khách hàng' }, { value: 'supplier', label: 'Nhà cung cấp' }, { value: 'both', label: 'Cả hai' }]} /></Form.Item>
                    <Form.Item name="name" label="Tên" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="tax_code" label="Mã số thuế"><Input /></Form.Item>
                    <Form.Item name="email" label="Email"><Input /></Form.Item>
                    <Form.Item name="address" label="Địa chỉ"><Input.TextArea rows={2} /></Form.Item>
                </Form>
            </Modal>
            <DocumentActionModal
                action={documentAction}
                form={actionForm}
                inventoryWarehouses={inventoryWarehousesResource.data?.items ?? []}
                inventoryError={inventoryWarehousesResource.error}
                onCancel={() => setDocumentAction(null)}
                onSubmit={submitDocumentAction}
            />
        </Card>
    );
}

function DocumentDrawer({ open, editing, form, parties, items, onClose, onSubmit }) {
    return (
        <Drawer title={editing ? 'Sửa chứng từ nháp' : 'Tạo chứng từ nội bộ'} width={900} open={open} onClose={onClose} destroyOnHidden extra={<Button type="primary" onClick={onSubmit}>Lưu nháp</Button>}>
            <Form form={form} layout="vertical">
                <Row gutter={12}>
                    <Col span={8}><Form.Item name="direction" label="Chiều" rules={[{ required: true }]}><Select options={[{ value: 'outbound', label: 'Đầu ra' }, { value: 'inbound', label: 'Đầu vào' }]} /></Form.Item></Col>
                    <Col span={8}><Form.Item name="document_type" label="Loại" rules={[{ required: true }]}><Select options={Object.entries(documentTypeLabels).map(([value, label]) => ({ value, label }))} /></Form.Item></Col>
                    <Col span={8}><Form.Item name="document_no" label="Số chứng từ"><Input placeholder="Tự sinh nếu bỏ trống" /></Form.Item></Col>
                    <Col span={8}><Form.Item name="document_date" label="Ngày chứng từ" rules={[{ required: true }]}><DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" /></Form.Item></Col>
                    <Col span={8}><Form.Item name="currency" label="Tiền tệ" rules={[{ required: true }]}><Input maxLength={3} /></Form.Item></Col>
                    <Col span={8}><Form.Item name="exchange_rate" label="Tỷ giá sang tiền hạch toán" rules={[{ required: true }]}><InputNumber min={0.00000001} precision={8} style={{ width: '100%' }} /></Form.Item></Col>
                    <Col span={8}><Form.Item name="party_id" label="Khách hàng / nhà cung cấp" rules={[{ required: true }]}><Select showSearch optionFilterProp="label" options={parties.map((party) => ({ value: party.id, label: `${party.name}${party.tax_code ? ` · ${party.tax_code}` : ''}` }))} /></Form.Item></Col>
                    <Col span={16}><Form.Item name="notes" label="Ghi chú"><Input /></Form.Item></Col>
                </Row>
                <Divider orientation="left">Thời điểm nghiệp vụ để phát hành HĐĐT</Divider>
                <Alert type="info" showIcon message="Chỉ cần nhập mốc phù hợp với nội dung hóa đơn" description="Hàng hóa dùng thời điểm giao/chuyển quyền; dịch vụ dùng thời điểm hoàn thành hoặc nhận tiền. Ngày của mốc phải khớp ngày chứng từ trước khi gửi nhà cung cấp." style={{ marginBottom: 16 }} />
                <Row gutter={12}>
                    <Col span={12}><Form.Item name="goods_transfer_at" label="Giao hàng / chuyển quyền"><DatePicker showTime style={{ width: '100%' }} format="DD/MM/YYYY HH:mm" /></Form.Item></Col>
                    <Col span={12}><Form.Item name="service_completed_at" label="Hoàn thành dịch vụ"><DatePicker showTime style={{ width: '100%' }} format="DD/MM/YYYY HH:mm" /></Form.Item></Col>
                    <Col span={12}><Form.Item name="payment_received_at" label="Nhận tiền dịch vụ"><DatePicker showTime style={{ width: '100%' }} format="DD/MM/YYYY HH:mm" /></Form.Item></Col>
                    <Col span={12}><Form.Item name="tax_point_at" label="Mốc nghiệp vụ khác"><DatePicker showTime style={{ width: '100%' }} format="DD/MM/YYYY HH:mm" /></Form.Item></Col>
                </Row>
                <Divider orientation="left">Dòng hàng hóa / dịch vụ</Divider>
                <Form.List name="lines">
                    {(fields, { add, remove }) => (
                        <Space direction="vertical" style={{ width: '100%' }}>
                            {fields.map((field) => (
                                <Card key={field.key} size="small">
                                    <Row gutter={10}>
                                        <Col span={8}><Form.Item {...field} name={[field.name, 'accounting_item_id']} label="Item kế toán"><Select allowClear showSearch optionFilterProp="label" options={items.map((item) => ({ value: item.id, label: item.sku ? `${item.name} · ${item.sku}` : item.name }))} onChange={(itemId) => {
                                            const item = items.find((candidate) => candidate.id === itemId);
                                            if (item) {
                                                form.setFieldValue(['lines', field.name], { ...form.getFieldValue(['lines', field.name]), accounting_item_id: item.id, item_kind: item.kind, name: item.name, sku: item.sku, unit: item.unit, unit_price: item.default_price, tax_category: item.tax_category, tax_rate: item.tax_rate });
                                            }
                                        }} /></Form.Item></Col>
                                        <Col span={5}><Form.Item {...field} name={[field.name, 'item_kind']} label="Phân loại"><Select options={Object.entries(itemKindLabels).map(([value, label]) => ({ value, label }))} /></Form.Item></Col>
                                        <Col span={7}><Form.Item {...field} name={[field.name, 'name']} label="Tên" rules={[{ required: true }]}><Input /></Form.Item></Col>
                                        <Col span={4}><Form.Item {...field} name={[field.name, 'unit']} label="ĐVT"><Input /></Form.Item></Col>
                                        <Col span={3}><Form.Item {...field} name={[field.name, 'quantity']} label="SL"><InputNumber min={0} style={{ width: '100%' }} /></Form.Item></Col>
                                        <Col span={3}><Form.Item label=" "><Button danger block disabled={fields.length === 1} onClick={() => remove(field.name)}>Xóa</Button></Form.Item></Col>
                                        <Col span={8}><Form.Item {...field} name={[field.name, 'unit_price']} label="Đơn giá"><InputNumber min={0} style={{ width: '100%' }} /></Form.Item></Col>
                                        <Col span={8}><Form.Item {...field} name={[field.name, 'discount_amount']} label="Chiết khấu"><InputNumber min={0} style={{ width: '100%' }} /></Form.Item></Col>
                                        <Col span={8}><Form.Item {...field} name={[field.name, 'tax_rate']} label="Thuế suất %"><InputNumber min={0} max={100} style={{ width: '100%' }} /></Form.Item></Col>
                                        <Col span={8}><Form.Item {...field} name={[field.name, 'tax_category']} label="Nhóm thuế"><Select options={[{ value: 'standard', label: 'VAT tiêu chuẩn' }, { value: 'zero_rated', label: '0%' }, { value: 'not_subject', label: 'Không chịu thuế' }, { value: 'not_declared', label: 'Không kê khai' }, { value: 'exempt', label: 'Miễn thuế' }]} /></Form.Item></Col>
                                    </Row>
                                </Card>
                            ))}
                            <Button onClick={() => add({ item_kind: 'service', name: '', unit: 'lần', quantity: 1, unit_price: 0, discount_amount: 0, tax_category: 'standard', tax_rate: 10 })}>Thêm dòng</Button>
                        </Space>
                    )}
                </Form.List>
            </Form>
        </Drawer>
    );
}

function DocumentDetail({ document, onClose }) {
    return (
        <Drawer title={`Chi tiết ${document?.document_no ?? ''}`} width={780} open={Boolean(document)} onClose={onClose} destroyOnHidden>
            {document ? <>
                <Descriptions bordered size="small" column={2}>
                    <Descriptions.Item label="Loại">{documentTypeLabels[document.document_type] ?? document.document_type}</Descriptions.Item>
                    <Descriptions.Item label="Trạng thái"><StatusTag value={document.workflow_status} /></Descriptions.Item>
                    <Descriptions.Item label="Tiền trước thuế">{money(document.subtotal, document.currency)}</Descriptions.Item>
                    <Descriptions.Item label="Tiền thuế">{money(document.tax_total, document.currency)}</Descriptions.Item>
                    <Descriptions.Item label="Tổng thanh toán" span={2}>{money(document.grand_total, document.currency)}</Descriptions.Item>
                </Descriptions>
                <Table rowKey="id" size="small" pagination={false} style={{ marginTop: 16 }} dataSource={document.lines ?? []} columns={[
                    { title: 'Tên', dataIndex: 'name' },
                    { title: 'SL', dataIndex: 'quantity', align: 'right' },
                    { title: 'Đơn giá', dataIndex: 'unit_price', align: 'right', render: (value) => money(value, document.currency) },
                    { title: 'Thuế', dataIndex: 'tax_amount', align: 'right', render: (value) => money(value, document.currency) },
                    { title: 'Thành tiền', dataIndex: 'line_total', align: 'right', render: (value) => money(value, document.currency) },
                ]} />
            </> : null}
        </Drawer>
    );
}

function DocumentActionModal({ action, form, inventoryWarehouses = [], inventoryError, onCancel, onSubmit }) {
    const kind = action?.kind;
    const titles = { void: 'Vô hiệu chứng từ', reverse: 'Lập chứng từ đảo', payment: 'Ghi nhận thanh toán', inventory: 'Tạo đề nghị phiếu kho', tax: 'Đánh giá điều kiện khấu trừ VAT' };
    return (
        <Modal
            title={titles[kind] ?? 'Thao tác chứng từ'}
            open={Boolean(action)}
            onCancel={onCancel}
            onOk={onSubmit}
            okButtonProps={{ danger: ['void', 'reverse'].includes(kind), disabled: kind === 'inventory' && !inventoryWarehouses.length }}
            okText="Xác nhận"
        >
            <Form form={form} layout="vertical">
                {['void', 'reverse', 'tax'].includes(kind) ? <Form.Item name="reason" label="Lý do / bằng chứng đánh giá" rules={[{ required: true }]}><Input.TextArea rows={3} /></Form.Item> : null}
                {kind === 'payment' ? <>
                    <Form.Item name="amount" label="Số tiền" rules={[{ required: true }]}><InputNumber min={0.01} style={{ width: '100%' }} /></Form.Item>
                    <Form.Item name="paid_at" label="Ngày thanh toán"><DatePicker showTime style={{ width: '100%' }} /></Form.Item>
                    <Form.Item name="reference" label="Tham chiếu"><Input /></Form.Item>
                </> : null}
                {kind === 'inventory' ? <>
                    {inventoryError ? <Alert type="error" showIcon message={inventoryError} style={{ marginBottom: 16 }} /> : null}
                    {!inventoryError && !inventoryWarehouses.length ? <Alert type="warning" showIcon message="Pháp nhân chưa được gắn kho" description="Hãy vào Đồng bộ & tích hợp để chọn kho trước khi tạo phiếu kho." style={{ marginBottom: 16 }} /> : null}
                    <Form.Item name="warehouse_id" label="Kho thực hiện" rules={[{ required: true, message: 'Chọn kho đã gắn với pháp nhân' }]}>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            placeholder="Chọn kho"
                            options={inventoryWarehouses.map((mapping) => ({
                                value: mapping.inventory_warehouse_id,
                                label: `${mapping.warehouse?.code ?? `#${mapping.inventory_warehouse_id}`} · ${mapping.warehouse?.name ?? 'Kho'}`,
                            }))}
                        />
                    </Form.Item>
                </> : null}
                {kind === 'tax' ? <Form.Item name="tax_eligibility" label="Kết quả" rules={[{ required: true }]}><Select options={[{ value: 'eligible', label: 'Đủ điều kiện khấu trừ' }, { value: 'ineligible', label: 'Không đủ điều kiện' }]} /></Form.Item> : null}
            </Form>
        </Modal>
    );
}

function ItemsSection({ organizationId, callAdminApi, runAdminAction, currentPermissions }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [form] = Form.useForm();
    const canManage = currentPermissions.includes('accounting.item.manage');
    const canSync = currentPermissions.includes('accounting.integration.sync');
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: Boolean(organizationId),
        loader: async () => (await callAdminApi(withQuery('accounting-tax/items', { organization_id: organizationId }))).data ?? null,
        deps: [organizationId],
    });

    const openItem = (item = null) => {
        setEditingItem(item);
        form.setFieldsValue(item ?? { kind: 'service', unit: 'lần', default_price: 0, tax_rate: 10, tax_category: 'standard', is_stock_tracked: false, status: 'active' });
        setDrawerOpen(true);
    };
    const saveItem = async () => {
        const values = await form.validateFields();
        const path = editingItem ? `accounting-tax/items/${editingItem.id}` : 'accounting-tax/items';
        const ok = await runAdminAction(
            () => callAdminApi(adminApi(path), { method: editingItem ? 'PUT' : 'POST', body: JSON.stringify(editingItem ? values : { ...values, organization_id: organizationId }) }),
            editingItem ? 'Đã cập nhật item kế toán.' : 'Đã tạo item kế toán.', reload,
        );
        if (ok) setDrawerOpen(false);
    };
    const sync = () => runAdminAction(
        () => callAdminApi(withQuery('accounting-tax/items/sync-sources', { organization_id: organizationId }), { method: 'POST' }),
        'Đã đồng bộ nguồn Catalog/CMS đang bật.', reload,
    );

    if (error) return <Alert type="error" showIcon message={error} />;
    return (
        <Card title="Sản phẩm / dịch vụ kế toán" extra={<Space><Button icon={<SyncOutlined />} disabled={!canSync} onClick={sync}>Đồng bộ nguồn</Button><Button type="primary" icon={<PlusOutlined />} disabled={!canManage} onClick={() => openItem()}>Thêm item</Button></Space>}>
            <Table rowKey="id" loading={loading} dataSource={data?.items ?? []} pagination={{ pageSize: 15 }} columns={[
                { title: 'Tên', dataIndex: 'name', render: (value, record) => <Button type="link" disabled={!canManage} onClick={() => openItem(record)}>{value}</Button> },
                { title: 'SKU', dataIndex: 'sku', render: (value) => value || '-' },
                { title: 'Phân loại', dataIndex: 'kind', render: (value) => itemKindLabels[value] ?? value },
                { title: 'ĐVT', dataIndex: 'unit' },
                { title: 'Giá mặc định', dataIndex: 'default_price', align: 'right', render: (value) => money(value) },
                { title: 'Thuế', dataIndex: 'tax_rate', render: (value) => value === null ? '-' : `${value}%` },
                { title: 'Nguồn', dataIndex: 'sources', render: (sources) => (sources ?? []).map((source) => <Tag key={source.id}>{source.source_module}</Tag>) },
                { title: 'Kho', dataIndex: 'is_stock_tracked', render: (value) => value ? <Tag color="green">Theo dõi</Tag> : '-' },
            ]} />
            <Drawer title={editingItem ? 'Sửa item kế toán' : 'Thêm item kế toán'} width={600} open={drawerOpen} onClose={() => setDrawerOpen(false)} destroyOnHidden extra={<Button type="primary" onClick={saveItem}>Lưu</Button>}>
                <Form form={form} layout="vertical">
                    <Row gutter={12}>
                        <Col span={12}><Form.Item name="kind" label="Phân loại" rules={[{ required: true }]}><Select options={Object.entries(itemKindLabels).map(([value, label]) => ({ value, label }))} /></Form.Item></Col>
                        <Col span={12}><Form.Item name="sku" label="SKU"><Input /></Form.Item></Col>
                        <Col span={16}><Form.Item name="name" label="Tên" rules={[{ required: true }]}><Input /></Form.Item></Col>
                        <Col span={8}><Form.Item name="unit" label="ĐVT" rules={[{ required: true }]}><Input /></Form.Item></Col>
                        <Col span={12}><Form.Item name="default_price" label="Giá mặc định"><InputNumber min={0} style={{ width: '100%' }} /></Form.Item></Col>
                        <Col span={12}><Form.Item name="tax_rate" label="Thuế suất %"><InputNumber min={0} max={100} style={{ width: '100%' }} /></Form.Item></Col>
                        <Col span={12}><Form.Item name="tax_category" label="Nhóm thuế"><Select options={[{ value: 'standard', label: 'VAT tiêu chuẩn' }, { value: 'zero_rated', label: '0%' }, { value: 'not_subject', label: 'Không chịu thuế' }, { value: 'not_declared', label: 'Không kê khai' }, { value: 'exempt', label: 'Miễn thuế' }]} /></Form.Item></Col>
                        <Col span={12}><Form.Item name="is_stock_tracked" label="Theo dõi kho" valuePropName="checked"><Switch /></Form.Item></Col>
                    </Row>
                </Form>
            </Drawer>
        </Card>
    );
}

function ReportsSection({ organizationId, callAdminApi, runAdminAction, currentPermissions }) {
    const [range, setRange] = useState(null);
    const [exportFormat, setExportFormat] = useState('xlsx');
    const [periodModalOpen, setPeriodModalOpen] = useState(false);
    const [periodAction, setPeriodAction] = useState(null);
    const [periodForm] = Form.useForm();
    const [periodActionForm] = Form.useForm();
    const canExport = currentPermissions.includes('accounting.export.create');
    const canManagePeriods = currentPermissions.includes('accounting.period.manage');
    const filters = useMemo(() => ({
        organization_id: organizationId,
        from: range?.[0]?.format('YYYY-MM-DD'),
        to: range?.[1]?.format('YYYY-MM-DD'),
    }), [organizationId, range]);

    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: Boolean(organizationId),
        loader: async () => {
            const [summaryPayload, exportsPayload, periodsPayload] = await Promise.all([
                callAdminApi(withQuery('accounting-tax/reports/summary', filters)),
                callAdminApi(withQuery('accounting-tax/exports', { organization_id: organizationId })),
                callAdminApi(withQuery('accounting-tax/tax-periods', { organization_id: organizationId })),
            ]);
            return { summary: summaryPayload.data?.summary ?? {}, exports: exportsPayload.data?.items ?? [], periods: periodsPayload.data?.items ?? [] };
        },
        deps: [organizationId, filters.from, filters.to],
    });

    const createExport = (reportType) => runAdminAction(
        () => callAdminApi(adminApi('accounting-tax/exports'), {
            method: 'POST',
            headers: { 'Idempotency-Key': globalThis.crypto?.randomUUID?.() ?? `export-${Date.now()}` },
            body: JSON.stringify({ organization_id: organizationId, report_type: reportType, format: exportFormat, timezone: 'Asia/Ho_Chi_Minh', filters: { from: filters.from, to: filters.to } }),
        }),
        'Đã đưa báo cáo vào hàng đợi.', reload,
    );
    const retryExport = (record) => runAdminAction(
        () => callAdminApi(adminApi(`accounting-tax/exports/${record.id}/retry`), { method: 'POST' }),
        'Đã đưa lại báo cáo vào hàng đợi.', reload,
    );

    const createPeriod = async () => {
        const values = await periodForm.validateFields();
        const ok = await runAdminAction(
            () => callAdminApi(adminApi('accounting-tax/tax-periods'), {
                method: 'POST', body: JSON.stringify({ organization_id: organizationId, code: values.code, period_type: values.period_type, start_date: values.range[0].format('YYYY-MM-DD'), end_date: values.range[1].format('YYYY-MM-DD'), base_currency: 'VND' }),
            }),
            'Đã tạo kỳ thuế.', reload,
        );
        if (ok) setPeriodModalOpen(false);
    };

    const transitionPeriod = (period, action, payload = {}) => runAdminAction(
        () => callAdminApi(adminApi(`accounting-tax/tax-periods/${period.id}/transition`), { method: 'POST', body: JSON.stringify({ action, ...payload }) }),
        `Đã chuyển kỳ thuế sang bước ${action}.`, reload,
    );

    const submitPeriodAction = async () => {
        const values = await periodActionForm.validateFields();
        const ok = await transitionPeriod(periodAction.period, periodAction.action, values);
        if (ok) setPeriodAction(null);
    };

    if (error) return <Alert type="error" showIcon message={error} />;
    const summary = data?.summary ?? {};
    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card title="Báo cáo vận hành" extra={<Space><DatePicker.RangePicker value={range} onChange={setRange} format="DD/MM/YYYY" /><Button icon={<ReloadOutlined />} onClick={reload}>Làm mới</Button></Space>} loading={loading}>
                <Alert type="warning" showIcon message="Số VAT dưới đây chỉ là ước tính vận hành" description="Chưa đánh giá điều kiện khấu trừ, kỳ điều chỉnh và tính hợp pháp của hóa đơn; không dùng trực tiếp để kê khai thuế." style={{ marginBottom: 16 }} />
                <Row gutter={[16, 16]}>
                    <Col xs={24} md={8}><Statistic title="Đầu ra" value={summary.outbound_total ?? 0} formatter={(value) => money(value)} /></Col>
                    <Col xs={24} md={8}><Statistic title="Đầu vào" value={summary.inbound_total ?? 0} formatter={(value) => money(value)} /></Col>
                    <Col xs={24} md={8}><Statistic title="VAT ước tính" value={summary.vat_payable_estimate ?? 0} formatter={(value) => money(value)} /></Col>
                </Row>
                <Space style={{ marginTop: 18 }} wrap>
                    <Select value={exportFormat} onChange={setExportFormat} options={[{ value: 'xlsx', label: 'Excel XLSX' }, { value: 'pdf', label: 'PDF' }, { value: 'csv', label: 'CSV' }]} />
                    <Button type="primary" disabled={!canExport} onClick={() => createExport('document_register')}>Xuất sổ chứng từ</Button>
                    <Button disabled={!canExport} onClick={() => createExport('vat_operational_estimate')}>Xuất VAT ước tính</Button>
                </Space>
            </Card>
            <Card title="File báo cáo bất biến">
                <Table rowKey="id" loading={loading} dataSource={data?.exports ?? []} pagination={{ pageSize: 10 }} columns={[
                    { title: 'Báo cáo', dataIndex: 'report_type' },
                    { title: 'Phiên bản', dataIndex: 'definition_version' },
                    { title: 'Trạng thái', dataIndex: 'status', render: (value) => <StatusTag value={value} /> },
                    { title: 'Số dòng', dataIndex: 'row_count', align: 'right' },
                    { title: 'Tạo lúc', dataIndex: 'created_at', render: dateTime },
                    { title: 'Checksum', dataIndex: 'checksum', ellipsis: true, render: (value) => value ? <Text code copyable>{value.slice(0, 16)}…</Text> : '-' },
                    { title: '', render: (_, record) => <Space>
                        {record.download_url ? <Button icon={<DownloadOutlined />} onClick={() => { window.location.href = record.download_url; }}>Tải file</Button> : null}
                        {record.status === 'failed' ? <Button danger disabled={!canExport} onClick={() => retryExport(record)}>Thử lại</Button> : null}
                    </Space> },
                ]} />
            </Card>
            <Card title="Kỳ thuế & khóa sổ" extra={<Button disabled={!canManagePeriods} icon={<PlusOutlined />} onClick={() => { periodForm.setFieldsValue({ period_type: 'monthly', range: [dayjs().startOf('month'), dayjs().endOf('month')] }); setPeriodModalOpen(true); }}>Tạo kỳ</Button>}>
                <Table rowKey="id" dataSource={data?.periods ?? []} pagination={{ pageSize: 8 }} columns={[
                    { title: 'Mã kỳ', dataIndex: 'code' },
                    { title: 'Khoảng ngày', render: (_, record) => `${dayjs(record.start_date).format('DD/MM/YYYY')} – ${dayjs(record.end_date).format('DD/MM/YYYY')}` },
                    { title: 'Trạng thái', dataIndex: 'status', render: (value) => <StatusTag value={value} /> },
                    { title: 'Snapshot hash', dataIndex: 'snapshot_hash', ellipsis: true, render: (value) => value ? <Text code>{value.slice(0, 14)}…</Text> : '-' },
                    { title: 'Thao tác', render: (_, period) => <Space wrap>
                        {period.status === 'open' ? <Button size="small" disabled={!canManagePeriods} onClick={() => transitionPeriod(period, 'review')}>Đưa duyệt</Button> : null}
                        {period.status === 'review' ? <Button size="small" type="primary" disabled={!canManagePeriods} onClick={() => transitionPeriod(period, 'lock')}>Khóa kỳ</Button> : null}
                        {period.status === 'locked' ? <Button size="small" disabled={!canManagePeriods} onClick={() => { periodActionForm.resetFields(); setPeriodAction({ period, action: 'file' }); }}>Đã kê khai</Button> : null}
                        {['locked', 'filed'].includes(period.status) ? <Button size="small" danger disabled={!canManagePeriods} onClick={() => { periodActionForm.resetFields(); setPeriodAction({ period, action: 'reopen' }); }}>Mở lại</Button> : null}
                    </Space> },
                ]} />
            </Card>
            <Modal title="Tạo kỳ thuế" open={periodModalOpen} onCancel={() => setPeriodModalOpen(false)} onOk={createPeriod} okText="Tạo kỳ">
                <Form form={periodForm} layout="vertical">
                    <Form.Item name="code" label="Mã kỳ" rules={[{ required: true }]}><Input placeholder="2026-08" /></Form.Item>
                    <Form.Item name="period_type" label="Loại kỳ"><Select options={[{ value: 'monthly', label: 'Tháng' }, { value: 'quarterly', label: 'Quý' }, { value: 'yearly', label: 'Năm' }, { value: 'custom', label: 'Tùy chỉnh' }]} /></Form.Item>
                    <Form.Item name="range" label="Khoảng ngày" rules={[{ required: true }]}><DatePicker.RangePicker style={{ width: '100%' }} /></Form.Item>
                </Form>
            </Modal>
            <Modal title={periodAction?.action === 'file' ? 'Xác nhận đã kê khai' : 'Mở lại kỳ thuế'} open={Boolean(periodAction)} onCancel={() => setPeriodAction(null)} onOk={submitPeriodAction} okButtonProps={{ danger: periodAction?.action === 'reopen' }}>
                <Form form={periodActionForm} layout="vertical">
                    {periodAction?.action === 'file' ? <Form.Item name="filing_reference" label="Mã tham chiếu hồ sơ" rules={[{ required: true }]}><Input /></Form.Item> : null}
                    {periodAction?.action === 'reopen' ? <Form.Item name="reason" label="Lý do mở lại" rules={[{ required: true }]}><Input.TextArea rows={3} /></Form.Item> : null}
                </Form>
            </Modal>
        </Space>
    );
}

function IntegrationsSection({ organizationId, callAdminApi, runAdminAction, currentPermissions }) {
    const [warehouseForm] = Form.useForm();
    const canManageWarehouses = currentPermissions.includes('accounting.inventory.post');
    const { data, loading, error, reload } = useAdminRouteResource({
        loader: async () => {
            const integrationsPayload = await callAdminApi(withQuery('accounting-tax/integrations', { organization_id: organizationId }));
            const integrations = integrationsPayload.data?.integrations ?? {};
            const inventoryEnabled = integrations['inventory.documents.write.v1']?.enabled === true;
            let warehouses = { items: [], available_items: [] };

            if (inventoryEnabled && canManageWarehouses) {
                const warehousesPayload = await callAdminApi(withQuery('accounting-tax/inventory/warehouses', { organization_id: organizationId }));
                warehouses = warehousesPayload.data ?? warehouses;
            }

            return { integrations, inventoryEnabled, warehouses };
        },
        deps: [organizationId, canManageWarehouses],
    });

    useEffect(() => {
        warehouseForm.setFieldsValue({ warehouse_id: null, is_default: !(data?.warehouses?.items ?? []).length });
    }, [organizationId, data?.warehouses?.items?.length, warehouseForm]);

    const mapWarehouse = async () => {
        const values = await warehouseForm.validateFields();
        const ok = await runAdminAction(
            () => callAdminApi(adminApi('accounting-tax/inventory/warehouses'), {
                method: 'POST',
                body: JSON.stringify({ organization_id: organizationId, warehouse_id: values.warehouse_id, is_default: Boolean(values.is_default) }),
            }),
            'Đã gắn kho với pháp nhân.', reload,
        );
        if (ok) warehouseForm.resetFields();
    };

    const setDefaultWarehouse = (mapping) => runAdminAction(
        () => callAdminApi(adminApi('accounting-tax/inventory/warehouses'), {
            method: 'POST',
            body: JSON.stringify({ organization_id: organizationId, warehouse_id: mapping.inventory_warehouse_id, is_default: true }),
        }),
        'Đã chọn kho mặc định cho pháp nhân.', reload,
    );

    const unmapWarehouse = (mapping) => runAdminAction(
        () => callAdminApi(adminApi(`accounting-tax/inventory/warehouses/${mapping.id}`), { method: 'DELETE' }),
        'Đã ngắt liên kết kho khỏi pháp nhân.', reload,
    );

    if (error) return <Alert type="error" showIcon message={error} action={<Button onClick={reload}>Thử lại</Button>} />;
    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card title="Tích hợp module" loading={loading}>
                <IntegrationCards integrations={data?.integrations ?? {}} />
                <Divider />
                <Paragraph type="secondary">Kho chỉ được kết nối khi module Inventory đang bật. Minvoice cần đạt đủ các bước cấu hình, kiểm thử sandbox, health check và cho phép production trước khi phát hành thật.</Paragraph>
            </Card>
            <WarehouseMappingsCard
                loading={loading}
                enabled={data?.inventoryEnabled === true}
                canManage={canManageWarehouses}
                mappings={data?.warehouses?.items ?? []}
                availableWarehouses={data?.warehouses?.available_items ?? []}
                form={warehouseForm}
                onMap={mapWarehouse}
                onSetDefault={setDefaultWarehouse}
                onUnmap={unmapWarehouse}
                onReload={reload}
            />
        </Space>
    );
}

function WarehouseMappingsCard({ loading, enabled, canManage, mappings, availableWarehouses, form, onMap, onSetDefault, onUnmap, onReload }) {
    return (
        <Card title="Kho theo pháp nhân" loading={loading} extra={<Button icon={<ReloadOutlined />} onClick={onReload}>Làm mới</Button>}>
            {!enabled ? <Alert type="warning" showIcon message="Module Kho chưa sẵn sàng" description="Cài và bật module Kho trước khi thiết lập liên kết với pháp nhân kế toán." /> : null}
            {enabled && !canManage ? <Alert type="info" showIcon message="Bạn chưa có quyền cấu hình kho cho pháp nhân này." /> : null}
            {enabled && canManage ? <>
                <Paragraph type="secondary">Mỗi kho chỉ thuộc một pháp nhân kế toán. Chứng từ hàng hóa chỉ có thể tạo phiếu nhập/xuất tại các kho đã gắn bên dưới.</Paragraph>
                <Form form={form} layout="vertical" initialValues={{ is_default: mappings.length === 0 }}>
                    <Row gutter={[12, 0]} align="bottom">
                        <Col xs={24} lg={14}>
                            <Form.Item name="warehouse_id" label="Chọn kho chưa liên kết" rules={[{ required: true, message: 'Chọn một kho' }]}>
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    placeholder={availableWarehouses.length ? 'Chọn theo mã hoặc tên kho' : 'Không còn kho khả dụng'}
                                    disabled={!availableWarehouses.length}
                                    options={availableWarehouses.map((warehouse) => ({
                                        value: warehouse.id,
                                        label: `${warehouse.code} · ${warehouse.name}`,
                                    }))}
                                />
                            </Form.Item>
                        </Col>
                        <Col xs={12} lg={5}>
                            <Form.Item name="is_default" label="Kho mặc định" valuePropName="checked"><Switch /></Form.Item>
                        </Col>
                        <Col xs={12} lg={5}>
                            <Form.Item label=" "><Button type="primary" block disabled={!availableWarehouses.length} onClick={onMap}>Gắn kho</Button></Form.Item>
                        </Col>
                    </Row>
                </Form>
                <Table
                    rowKey="id"
                    size="small"
                    pagination={false}
                    locale={{ emptyText: 'Pháp nhân chưa được gắn kho' }}
                    dataSource={mappings}
                    columns={[
                        { title: 'Mã kho', render: (_, mapping) => mapping.warehouse?.code ?? `#${mapping.inventory_warehouse_id}` },
                        { title: 'Tên kho', render: (_, mapping) => mapping.warehouse?.name ?? '-' },
                        { title: 'Địa chỉ', render: (_, mapping) => mapping.warehouse?.address || '-' },
                        { title: 'Vai trò', render: (_, mapping) => mapping.is_default ? <Tag color="green">Mặc định</Tag> : <Tag>Kho bổ sung</Tag> },
                        { title: 'Thao tác', render: (_, mapping) => <Space wrap>
                            {!mapping.is_default ? <Button size="small" onClick={() => onSetDefault(mapping)}>Đặt mặc định</Button> : null}
                            <Popconfirm title="Ngắt liên kết kho này?" description="Sau khi ngắt, pháp nhân không thể tạo phiếu kho mới tại kho này." okText="Ngắt liên kết" cancelText="Giữ lại" onConfirm={() => onUnmap(mapping)}>
                                <Button size="small" danger>Ngắt liên kết</Button>
                            </Popconfirm>
                        </Space> },
                    ]}
                />
            </> : null}
        </Card>
    );
}

function IntegrationCards({ integrations }) {
    const labels = {
        'catalog.items.read.v1': 'Catalog · sản phẩm', 'cms.services.read.v1': 'CMS · dịch vụ',
        'inventory.stock.read.v1': 'Kho · tồn kho', 'inventory.documents.write.v1': 'Kho · phiếu nhập/xuất',
        'einvoice.minvoice.outbound.v1': 'Minvoice · đầu ra', 'einvoice.minvoice.inbound.v1': 'mSMI · đầu vào',
    };
    return (
        <Row gutter={[12, 12]}>
            {Object.entries(integrations).map(([key, state]) => (
                <Col xs={24} md={12} xl={8} key={key}>
                    <Card size="small">
                        <Space direction="vertical" size={4}>
                            <Text strong>{labels[key] ?? key}</Text>
                            <Space><Tag color={state.available ? 'blue' : 'default'}>{state.available ? 'Có thể cài' : 'Không có'}</Tag><Tag color={state.enabled ? 'green' : 'default'}>{state.enabled ? 'Đang bật' : 'Chưa bật'}</Tag></Space>
                        </Space>
                    </Card>
                </Col>
            ))}
        </Row>
    );
}

function OrganizationModal({ open, form, onCancel, onSubmit }) {
    return (
        <Modal title="Tạo pháp nhân kế toán" open={open} onCancel={onCancel} onOk={onSubmit} okText="Tạo pháp nhân" destroyOnHidden>
            <Form form={form} layout="vertical">
                <Form.Item name="name" label="Tên hiển thị" rules={[{ required: true }]}><Input /></Form.Item>
                <Form.Item name="legal_name" label="Tên pháp lý"><Input /></Form.Item>
                <Form.Item name="tax_code" label="Mã số thuế"><Input /></Form.Item>
                <Form.Item name="email" label="Email"><Input /></Form.Item>
                <Form.Item name="address" label="Địa chỉ"><Input.TextArea rows={2} /></Form.Item>
            </Form>
        </Modal>
    );
}

import { useCallback, useEffect, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Modal from 'antd/es/modal';
import EditOutlined from '@ant-design/icons/EditOutlined';
import SyncOutlined from '@ant-design/icons/SyncOutlined';
import BankOutlined from '@ant-design/icons/BankOutlined';
import Form from 'antd/es/form';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { createRetailApi } from '../api/retailApi';
import { formatDate, rows, uuid } from '../utils/retailFormat';

export default function RetailIntegrationPanel({ branch, callAdminApi, identity, onCommand, reloadRef }) {
    const { message } = App.useApp();
    const [data, setData] = useState(null);
    const [products, setProducts] = useState([]);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);
    const [editing, setEditing] = useState(false);
    const [busy, setBusy] = useState(false);
    const [form] = Form.useForm();
    const api = useMemo(() => createRetailApi(callAdminApi, { identity, branchId: uuid(branch) }), [callAdminApi, identity, branch]);
    const reload = useCallback(async () => {
        if (!branch) return;
        setLoading(true); setError(null);
        try { setData((await api.read('integrations', { branch_id: uuid(branch) })).data); }
        catch (failure) { setError(failure.message); }
        finally { setLoading(false); }
    }, [api, branch]);
    useEffect(() => { reload(); }, [reload]);
    useEffect(() => {
        if (!reloadRef) return;
        reloadRef.current = reload;
        return () => { if (reloadRef.current === reload) reloadRef.current = null; };
    }, [reload, reloadRef]);
    const accounting = data?.accounting ?? {};
    const open = async () => {
        try {
            setProducts(rows((await api.read('products', { branch_id: uuid(branch), include_catalog: 1 })).data));
            form.resetFields();
            form.setFieldsValue({ organization_id: accounting.mapping?.organization_id, enabled: accounting.mapping ? Boolean(accounting.mapping.enabled ?? accounting.mapping.is_enabled) : true, sku_mappings: accounting.sku_mappings ?? [] });
            setEditing(true);
        } catch (failure) { message.error(failure.message); }
    };
    const command = async (path, payload, method) => {
        setBusy(true); setError(null);
        try {
            const response = await api.command(path, payload, method);
            setEditing(false);
            message.success(path.endsWith('mapping') ? 'Đã lưu liên kết kế toán.' : `Đã xử lý ${response.data?.processed ?? 0} giao dịch; còn ${response.data?.pending ?? 0} giao dịch chờ.`);
            await reload();
        } catch (failure) { setError(failure.message); message.error(failure.message); }
        finally { setBusy(false); onCommand?.(); }
    };
    return <Card size="small" className="retail-settings-accounting" title={<span className="retail-settings-card-title"><BankOutlined /> Tích hợp kế toán</span>} loading={loading} extra={<Space wrap><Button size="small" icon={<EditOutlined />} disabled={!accounting.available || busy} onClick={open}>Sửa liên kết</Button>{accounting.enabled && <Button size="small" icon={<SyncOutlined />} type="primary" loading={busy} onClick={() => command('integrations/reconcile', { branch_id: uuid(branch), limit: 10 })}>Đối soát giao dịch</Button>}</Space>}>
        {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 12 }} />}
        <div className="retail-accounting-overview"><div><strong>{accounting.enabled ? 'Đồng bộ bản nháp đang bật' : accounting.available ? 'Sẵn sàng thiết lập liên kết' : 'Phân hệ Kế toán & thuế chưa sẵn sàng'}</strong><p>Giao dịch bán hàng tạo chứng từ nội bộ dạng nháp. Kế toán tiếp tục duyệt, ghi sổ và xử lý hóa đơn điện tử.</p></div><Space wrap><Tag color={accounting.enabled ? 'green' : 'default'}>Kế toán & thuế: {accounting.enabled ? 'Đang bật đồng bộ' : 'Chưa bật đồng bộ'}</Tag><Tag>Đang chờ: {accounting.pending_sales ?? 0}</Tag></Space></div>
        {(accounting.enabled || rows(accounting.deliveries).length > 0) && <Table size="small" rowKey={(item) => uuid(item) || item.source_id} dataSource={rows(accounting.deliveries)} pagination={{ pageSize: 5, showSizeChanger: false }} scroll={{ x: 650 }} columns={[{ title: 'Giao dịch nguồn', render: (_, item) => item.source_id ?? item.sale_id ?? '—' }, { title: 'Chứng từ kế toán', render: (_, item) => item.destination_id ?? item.document_id ?? item.accounting_document_id ?? '—' }, { title: 'Phiên bản liên kết', dataIndex: 'contract_version' }, { title: 'Thời gian', render: (_, item) => formatDate(item.updated_at ?? item.created_at) }]} locale={{ emptyText: 'Chưa có chứng từ đã đồng bộ.' }} />}
        <Modal open={editing} title="Liên kết pháp nhân và hàng hóa" width={740} centered forceRender maskClosable={false} closable={!busy} keyboard={!busy} onCancel={() => !busy && setEditing(false)} footer={<Space><Button disabled={busy} onClick={() => setEditing(false)}>Hủy</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>Lưu liên kết</Button></Space>}>
            {error && <Alert showIcon type="error" message={error} style={{ marginBottom: 16 }} />}
            <Form form={form} layout="vertical" disabled={busy} onFinish={(values) => command('integrations/mapping', { ...values, branch_id: uuid(branch), expected_version: accounting.mapping?.version ?? 0 }, 'PUT')}>
                <Form.Item name="organization_id" label="Pháp nhân kế toán" rules={[{ required: true }]}><Select options={rows(accounting.organizations).map((item) => ({ value: item.id, label: item.name }))} /></Form.Item>
                <Form.Item name="enabled" label="Cho phép đồng bộ bản nháp" valuePropName="checked"><Switch /></Form.Item>
                <Typography.Paragraph type="secondary">Thiết lập liên kết cần quyền tích hợp bán lẻ và quyền kế toán theo pháp nhân. Việc đổi liên kết không sửa chứng từ đã đồng bộ.</Typography.Paragraph>
                <Form.List name="sku_mappings">{(fields, { add, remove }) => <>{fields.map(({ key, name }) => <Space align="baseline" key={key} style={{ display: 'flex' }}><Form.Item name={[name, 'sku_id']} rules={[{ required: true }]} style={{ flex: 1 }}><Select placeholder="Hàng hóa bán lẻ" showSearch optionFilterProp="label" style={{ width: 250 }} options={products.map((item) => ({ value: uuid(item), label: `${item.sku} · ${item.name}` }))} /></Form.Item><Form.Item noStyle shouldUpdate>{({ getFieldValue }) => <Form.Item name={[name, 'accounting_item_id']} rules={[{ required: true }]}><Select placeholder="Hàng hóa kế toán" showSearch optionFilterProp="label" style={{ width: 260 }} options={rows(accounting.items).filter((item) => !getFieldValue('organization_id') || String(item.organization_id) === String(getFieldValue('organization_id'))).map((item) => ({ value: item.id, label: `${item.sku ?? item.code ?? ''} ${item.name}` }))} /></Form.Item>}</Form.Item><Button danger type="text" onClick={() => remove(name)}>×</Button></Space>)}<Button block type="dashed" onClick={() => add()}>Thêm liên kết hàng hóa</Button></>}</Form.List>
            </Form>
        </Modal>
    </Card>;
}

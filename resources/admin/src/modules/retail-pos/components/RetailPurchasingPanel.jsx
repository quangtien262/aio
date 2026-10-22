import { useCallback, useEffect, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import TeamOutlined from '@ant-design/icons/TeamOutlined';
import FileAddOutlined from '@ant-design/icons/FileAddOutlined';
import RetailSupplierManager from './RetailSupplierManager';
import { adminApi } from '../../../shared/config/routes';
import { createRetailApi } from '../api/retailApi';
import { formatDate, formatMoney, formatQuantity, quantityMicros, quantityText, rows, statusLabels, uuid } from '../utils/retailFormat';

const { Text } = Typography;
const qty = (zero = false) => <InputNumber stringMode min={zero ? '0' : '0.000001'} precision={6} style={{ width: '100%' }} />;
export default function RetailPurchasingPanel({ branch, bootstrap, callAdminApi, permissions, identity, onCommand, reloadRef }) {
    const { message } = App.useApp();
    const [suppliers, setSuppliers] = useState([]);
    const [supplierManagerOpen, setSupplierManagerOpen] = useState(false);
    const [orders, setOrders] = useState([]);
    const [skus, setSkus] = useState([]);
    const [detail, setDetail] = useState(null);
    const [drawer, setDrawer] = useState(null);
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);
    const [loading, setLoading] = useState(false);
    const [form] = Form.useForm();
    const api = useMemo(() => createRetailApi(callAdminApi, { identity, branchId: uuid(branch), namespace: 'purchasing' }), [callAdminApi, identity, branch]);
    const chain = rows(bootstrap?.chains).find((item) => uuid(item) === String(branch?.chain_id));
    const catalog = rows(bootstrap?.catalogs).find((item) => String(item.database_id) === String(chain?.catalog_id));
    const enabled = Boolean(bootstrap?.capabilities?.purchasing);
    const can = (permission) => permissions.has(permission);
    const reload = useCallback(async () => {
        if (!enabled || !branch || !can('purchasing.view')) return;
        setLoading(true); setError(null);
        try {
            const [supplierResult, orderResult] = await Promise.all([api.read('suppliers'), api.read('orders', { per_page: 100 })]);
            setSuppliers(rows(supplierResult.data));
            setOrders(rows(orderResult.data).filter((item) => String(item.warehouse_id) === String(branch.warehouse_id)));
            if (catalog && can('merchandise.view')) setSkus(rows((await callAdminApi(adminApi(`merchandise/catalogs/${uuid(catalog)}/skus`))).data));
        } catch (failure) { setError(failure.message); }
        finally { setLoading(false); }
    }, [api, enabled, branch, catalog?.public_id, callAdminApi, [...permissions].sort().join(',')]);
    useEffect(() => { reload(); }, [reload]);
    useEffect(() => {
        if (!reloadRef) return;
        reloadRef.current = reload;
        return () => { if (reloadRef.current === reload) reloadRef.current = null; };
    }, [reload, reloadRef]);
    useEffect(() => { if (drawer) { form.resetFields(); form.setFieldsValue(drawer.initial ?? {}); } }, [drawer, form]);
    const open = (config) => { setError(null); setDrawer(config); };
    const perform = async (path, payload, close = true) => {
        setBusy(true); setError(null);
        try {
            const response = await api.command(path, payload);
            if (close) setDrawer(null);
            if (response.data?.lines) setDetail(response.data);
            message.success('Đã ghi nhận nghiệp vụ mua hàng.');
            await reload();
        } catch (failure) { setError(failure.message); message.error(failure.message); }
        finally { setBusy(false); onCommand?.(); }
    };
    const showOrder = async (record) => {
        try { setDetail((await api.read(`orders/${uuid(record)}`)).data); }
        catch (failure) { message.error(failure.message); }
    };
    if (!enabled) return <Card title="Mua hàng & nhà cung cấp"><Alert type="info" showIcon message="Purchasing chưa được cài hoặc bật" description="Cài module Purchasing qua App Store để sử dụng đơn mua, nhận từng phần, trả nhà cung cấp và công nợ trên Inventory." /></Card>;
    if (!can('purchasing.view')) return <Alert type="info" showIcon message="Chưa được cấp quyền mua hàng" description="Tài khoản cần quyền Purchasing theo phạm vi được phép. Quyền Retail không tự cấp quyền mua hoặc nhận kho." />;
    const skuOptions = skus.map((item) => ({ value: item.database_id ?? item.id, label: `${item.sku} · ${item.name}` }));
    const orderFields = <>
        <Row gutter={16}>
            <Col xs={24} sm={15}><Form.Item name="supplier_id" label="Nhà cung cấp" rules={[{ required: true, message: 'Chọn nhà cung cấp.' }]}><Select showSearch optionFilterProp="label" placeholder="Chọn nhà cung cấp" options={suppliers.filter((item) => item.is_active).map((item) => ({ value: item.id, label: `${item.code} · ${item.name}` }))} /></Form.Item></Col>
            <Col xs={24} sm={9}><Form.Item name="expected_at" label="Ngày dự kiến nhận"><Input type="date" /></Form.Item></Col>
        </Row>
        <Form.Item name="note" label="Ghi chú"><Input.TextArea rows={2} /></Form.Item>
        <Form.List name="lines" rules={[{ validator: (_, value) => value?.length ? Promise.resolve() : Promise.reject(new Error('Thêm ít nhất một dòng hàng.')) }]}>{(fields, { add, remove }, { errors }) => <div className="retail-purchase-lines">
            <div className="retail-purchase-lines-heading"><strong>Chi tiết hàng mua <Text type="secondary">({fields.length})</Text></strong><Button icon={<PlusOutlined />} disabled={fields.length >= 300} onClick={() => add({ quantity: '1', unit_cost: '0' })}>Thêm dòng hàng</Button></div>
            <Table size="small" bordered rowKey="key" pagination={false} dataSource={fields} scroll={{ x: 760 }} locale={{ emptyText: 'Thêm dòng hàng để lập đơn mua.' }} columns={[
                { title: '#', width: 44, align: 'center', render: (_, record, index) => index + 1 },
                { title: 'Hàng hóa', width: 360, render: (_, { name }) => <Form.Item name={[name, 'sku_id']} rules={[{ required: true, message: 'Chọn hàng hóa.' }]}><Select aria-label={`Hàng hóa dòng ${name + 1}`} showSearch optionFilterProp="label" placeholder="Chọn hàng hóa" options={skuOptions} /></Form.Item> },
                { title: 'Số lượng đặt', width: 160, render: (_, { name }) => <Form.Item name={[name, 'quantity']} rules={[{ required: true, message: 'Nhập số lượng.' }]}><InputNumber aria-label={`Số lượng đặt dòng ${name + 1}`} stringMode min="0.000001" precision={6} style={{ width: '100%' }} /></Form.Item> },
                { title: 'Giá nhập / đơn vị (₫)', width: 190, render: (_, { name }) => <Form.Item name={[name, 'unit_cost']} rules={[{ required: true, message: 'Nhập giá nhập.' }]}><InputNumber aria-label={`Giá nhập dòng ${name + 1}`} stringMode min="0" precision={6} style={{ width: '100%' }} /></Form.Item> },
                { title: '', width: 48, align: 'center', render: (_, { name }) => <Button type="text" danger icon={<DeleteOutlined />} aria-label={`Xóa dòng hàng ${name + 1}`} onClick={() => remove(name)} /> },
            ]} />
            <Form.ErrorList errors={errors} />
        </div>}</Form.List>
        {!skuOptions.length && <Alert showIcon type="info" message="Cần danh mục hàng dùng chung" description="Tài khoản cần Merchandise view để tra SKU. Tạo hàng hóa trước khi lập đơn mua." />}
    </>;
    return <>
        {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}
        <Card title="Đơn mua của kho chi nhánh" loading={loading} extra={<Space>{can('purchasing.supplier.manage') && <Button icon={<TeamOutlined />} onClick={() => setSupplierManagerOpen(true)}>QL nhà cung cấp</Button>}{can('purchasing.order.create') && <Button type="primary" icon={<FileAddOutlined />} onClick={() => open({ title: 'Lập đơn mua', path: 'orders', initial: { warehouse_id: branch.warehouse_id, catalog_id: catalog?.database_id ?? chain?.catalog_id, lines: [{ quantity: '1', unit_cost: '0' }] }, fields: orderFields })}>Lập đơn mua</Button>}</Space>}>
            <Alert type="info" showIcon message="Đơn mua được duyệt bởi người khác" description="Người lập không tự duyệt. Nhận hàng thực tế ghi đồng thời phiếu nhận và biến động Inventory." style={{ marginBottom: 16 }} />
            <Table rowKey={uuid} dataSource={orders} columns={[{ title: 'Đơn mua', dataIndex: 'code', render: (value, record) => <Button type="link" onClick={() => showOrder(record)}>{value}</Button> }, { title: 'Nhà cung cấp', render: (_, record) => suppliers.find((item) => item.id === record.supplier_id)?.name ?? record.supplier_id }, { title: 'Trạng thái', dataIndex: 'status', render: (value) => <Tag>{statusLabels[value] ?? value}</Tag> }, { title: 'Ngày tạo', dataIndex: 'created_at', render: formatDate }, { title: 'Hạn trả', dataIndex: 'due_at' }]} pagination={{ pageSize: 20 }} />
        </Card>
        <RetailSupplierManager open={supplierManagerOpen} onClose={() => setSupplierManagerOpen(false)} suppliers={suppliers} api={api} onChanged={reload} onCommand={onCommand} />
        <Drawer title={detail?.code ?? 'Chi tiết đơn mua'} open={Boolean(detail)} onClose={() => setDetail(null)} width={800} extra={<Space wrap>
            {detail?.status === 'draft' && can('purchasing.order.approve') && <Popconfirm title="Duyệt đơn mua?" description="Người lập không được tự duyệt." onConfirm={() => perform(`orders/${uuid(detail)}/approve`, { expected_version: detail.version }, false)}><Button type="primary" loading={busy}>Duyệt</Button></Popconfirm>}
            {['approved', 'partially_received'].includes(detail?.status) && can('purchasing.receipt.manage') && <Button onClick={() => open({ title: 'Nhận hàng thực tế', path: `orders/${uuid(detail)}/receive`, initial: { expected_version: detail.version, lines: (detail.lines ?? []).filter((line) => quantityMicros(line.quantity_ordered) > quantityMicros(line.quantity_received)).map((line) => ({ order_line_id: line.id, quantity: quantityText(quantityMicros(line.quantity_ordered) - quantityMicros(line.quantity_received)) })) }, fields: <><Form.Item name="reference" label="Số phiếu giao hàng"><Input /></Form.Item><Form.List name="lines">{(fields) => fields.map(({ key, name }) => <Card size="small" title={`Dòng nhận ${name + 1}`} key={key} style={{ marginBottom: 10 }}><Form.Item name={[name, 'order_line_id']} hidden><Input /></Form.Item><Form.Item name={[name, 'quantity']} label="Số lượng thực nhận" rules={[{ required: true }]}>{qty(true)}</Form.Item><Form.Item name={[name, 'batch_code']} label="Mã lô"><Input /></Form.Item><Form.Item name={[name, 'expires_at']} label="Hạn sử dụng"><Input type="date" /></Form.Item></Card>)}</Form.List></> })}>Nhận hàng</Button>}
            {can('purchasing.payment.manage') && detail?.financials && quantityMicros(detail.financials.payable_minor) > 0n && <Button onClick={() => open({ title: 'Thanh toán nhà cung cấp', path: `orders/${uuid(detail)}/payments`, initial: { expected_version: detail.version, amount_minor: detail.financials.payable_minor, method: 'bank' }, fields: <><Form.Item name="amount_minor" label="Số tiền thanh toán" rules={[{ required: true }]}><InputNumber stringMode min="1" precision={0} style={{ width: '100%' }} /></Form.Item><Form.Item name="method" label="Phương thức"><Select options={[{ value: 'bank', label: 'Ngân hàng' }, { value: 'cash', label: 'Tiền mặt' }]} /></Form.Item><Form.Item name="reference" label="Tham chiếu thanh toán"><Input /></Form.Item></> })}>Thanh toán</Button>}
            {can('purchasing.order.approve') && !['closed', 'cancelled'].includes(detail?.status) && <Popconfirm title="Đóng phần đơn mua còn lại?" onConfirm={() => perform(`orders/${uuid(detail)}/close`, { expected_version: detail.version }, false)}><Button>Đóng đơn</Button></Popconfirm>}
        </Space>}>
            <Descriptions bordered column={2} items={[{ key: 'status', label: 'Trạng thái', children: statusLabels[detail?.status] ?? detail?.status }, { key: 'due', label: 'Hạn trả', children: detail?.due_at }, { key: 'received', label: 'Hàng đã nhận', children: formatMoney(detail?.financials?.received_minor) }, { key: 'payable', label: 'Còn phải trả', children: formatMoney(detail?.financials?.payable_minor) }, { key: 'paid', label: 'Đã thanh toán', children: formatMoney(detail?.financials?.paid_minor) }, { key: 'credit', label: 'NCC cần hoàn lại', children: formatMoney(detail?.financials?.supplier_credit_minor) }]} />
            <Table rowKey="id" style={{ marginTop: 18 }} dataSource={detail?.lines ?? []} pagination={false} columns={[{ title: 'Hàng hóa', dataIndex: 'name_snapshot' }, { title: 'Đặt', dataIndex: 'quantity_ordered', render: formatQuantity }, { title: 'Đã nhận', dataIndex: 'quantity_received', render: formatQuantity }, { title: 'Đã trả NCC', dataIndex: 'quantity_returned', render: formatQuantity }, { title: 'Giá nhập', dataIndex: 'unit_cost', render: (value) => `${value} ₫` }]} />
            <Card size="small" title="Phiếu nhận và trả nhà cung cấp" style={{ marginTop: 18 }}><Table rowKey="id" pagination={false} dataSource={(detail?.receipts ?? []).flatMap((receipt) => (receipt.lines ?? []).map((line) => ({ ...line, receipt_reference: receipt.reference, receipt_date: receipt.created_at })))} columns={[
                { title: 'Phiếu giao', dataIndex: 'receipt_reference', render: (value) => value ?? '—' },
                { title: 'Đã nhận', dataIndex: 'quantity', render: formatQuantity }, { title: 'Đã trả', dataIndex: 'quantity_returned', render: formatQuantity },
                { title: 'Mã lô', dataIndex: 'batch_code', render: (value) => value ?? '—' },
                { title: 'Thao tác', render: (_, line) => can('purchasing.return.manage') && quantityMicros(line.quantity) > quantityMicros(line.quantity_returned ?? '0') && <Button size="small" onClick={() => open({ title: 'Trả hàng nhà cung cấp', path: `orders/${uuid(detail)}/returns`, initial: { expected_version: detail.version, receipt_line_id: line.id, quantity: quantityText(quantityMicros(line.quantity) - quantityMicros(line.quantity_returned ?? '0')) }, fields: <><Alert type="info" showIcon message="Xuất trả đúng lô đã nhận" description="Phần đã trả trước được trừ khỏi giới hạn. Công nợ hoặc khoản NCC cần hoàn lại được tính theo phiếu nguồn." style={{ marginBottom: 16 }} /><Form.Item name="quantity" label="Số lượng trả" rules={[{ required: true }]}>{qty()}</Form.Item><Form.Item name="reason" label="Lý do" rules={[{ required: true }]}><Input.TextArea rows={3} /></Form.Item></> })}>Trả NCC</Button> },
            ]} /></Card>
            <div style={{ marginTop: 18 }}><Text type="secondary">Công nợ vận hành tính trên hàng đã nhận; hóa đơn và chứng từ kế toán có đối soát riêng.</Text></div>
        </Drawer>
        <Drawer title={drawer?.title} open={Boolean(drawer)} width={drawer?.path === 'orders' ? 1000 : 600} className={drawer?.path === 'orders' ? 'retail-purchase-order-drawer' : undefined} onClose={() => !busy && setDrawer(null)} maskClosable={!busy} closable={!busy} footer={<Space style={{ display: 'flex', justifyContent: 'flex-end' }}><Button disabled={busy} onClick={() => setDrawer(null)}>Đóng</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>Ghi nhận</Button></Space>}>
            {error && <Alert showIcon type="error" message={error} style={{ marginBottom: 16 }} />}
            <Form layout="vertical" form={form} disabled={busy} onFinish={(values) => { const body = { ...drawer.initial, ...values }; if (body.lines && drawer.path.endsWith('/receive')) body.lines = body.lines.filter((line) => quantityMicros(line.quantity ?? '0') > 0n); perform(drawer.path, body); }}>{drawer?.fields}</Form>
        </Drawer>
    </>;
}

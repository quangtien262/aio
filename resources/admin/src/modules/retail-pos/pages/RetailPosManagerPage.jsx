import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Descriptions from 'antd/es/descriptions';
import DatePicker from 'antd/es/date-picker';
import datePickerVi from 'antd/es/date-picker/locale/vi_VN';
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
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import ShopOutlined from '@ant-design/icons/ShopOutlined';
import ArrowRightOutlined from '@ant-design/icons/ArrowRightOutlined';
import DollarOutlined from '@ant-design/icons/DollarOutlined';
import ShoppingCartOutlined from '@ant-design/icons/ShoppingCartOutlined';
import WalletOutlined from '@ant-design/icons/WalletOutlined';
import UndoOutlined from '@ant-design/icons/UndoOutlined';
import InboxOutlined from '@ant-design/icons/InboxOutlined';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import dayjs from 'dayjs';
import 'dayjs/locale/vi';
import { createRetailApi } from '../api/retailApi';
import { pendingCommands } from '../state/retailCommandJournal';
import { formatDate, formatMoney, formatQuantity, quantityMicros, quantityText, rows, saleAmount, statusLabels, uuid } from '../utils/retailFormat';
import RetailSalePanel from '../components/RetailSalePanel';
import RetailPurchasingPanel from '../components/RetailPurchasingPanel';
import RetailSettingsPanel from '../components/RetailSettingsPanel';
import RetailProductForm from '../components/RetailProductForm';
import RetailShiftManager from '../components/RetailShiftManager';
import '../styles/retail-pos.css';

const { Text, Title, Paragraph } = Typography;
const sections = [
    ['dashboard', 'Tổng quan'], ['pos', 'Bán hàng'], ['products', 'Hàng hóa'], ['branches', 'Chi nhánh'],
    ['inventory', 'Tồn kho'], ['transfers', 'Điều chuyển'], ['stocktakes', 'Kiểm kê'], ['returns', 'Đổi trả'],
    ['shifts', 'Ca & tiền'], ['customers', 'Khách hàng'], ['purchasing', 'Mua hàng'], ['reports', 'Báo cáo'], ['settings', 'Cấu hình'],
];
const status = (value) => <Tag color={['completed', 'confirmed', 'posted', 'received', 'reconciled', 'active'].includes(value) ? 'green' : ['unknown', 'failed', 'exception', 'cancelled'].includes(value) ? 'red' : 'blue'}>{statusLabels[value] ?? value ?? '—'}</Tag>;
const field = (name, label, child = <Input />, required = true, extra = {}) => <Form.Item key={name} name={name} label={label} rules={required ? [{ required: true, message: `Nhập ${label.toLowerCase()}.` }] : []} {...extra}>{child}</Form.Item>;
const moneyInput = () => <InputNumber stringMode min="0" precision={0} style={{ width: '100%' }} />;

function ResourceDrawer({ config, busy, error, onClose, onSubmit, children }) {
    const [form] = Form.useForm();
    useEffect(() => {
        if (config) { form.resetFields(); form.setFieldsValue(config.initial ?? {}); }
    }, [config, form]);
    const footer = <div className="retail-resource-footer">{config?.footerNote ? <Text type="secondary">{config.footerNote}</Text> : null}<Space style={{ justifyContent: 'flex-end', flex: 1 }}><Button disabled={busy} onClick={onClose}>Đóng</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>{config?.submitLabel ?? 'Lưu'}</Button></Space></div>;
    const content = <>
        {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 18 }} />}
        {config?.renderForm ? config.renderForm({ form, onSubmit, busy }) : <Form form={form} layout="vertical" onFinish={onSubmit}>{config?.fields?.(form) ?? children}</Form>}
    </>;
    if (config?.presentation === 'modal') {
        return <Modal title={config.title} className={config.className} width={config.width ?? 600} open centered onCancel={() => !busy && onClose()}
            closable={!busy} keyboard={!busy} maskClosable={false} destroyOnHidden footer={footer}>{content}</Modal>;
    }
    const title = config?.description ? <div className="retail-resource-title"><span>{config.title}</span><p>{config.description}</p></div> : config?.title;
    return <Drawer title={title} width={config?.width ?? 600} className={config?.className} open={Boolean(config)} onClose={() => !busy && onClose()} destroyOnClose maskClosable={false}
        footer={footer}>{content}</Drawer>;
}

export default function RetailPosManagerPage({ moduleMenu, callAdminApi, currentAdmin, currentPermissions = [] }) {
    const { message } = App.useApp();
    const location = useLocation();
    const navigate = useNavigate();
    const routeSection = location.pathname.split('/').filter(Boolean).at(-1);
    const pageSection = sections.some(([key]) => key === routeSection) ? routeSection : String(moduleMenu?.key ?? '').replace(/^retail-/, '') || 'dashboard';
    const section = pageSection === 'dashboard' && new URLSearchParams(location.search).get('tab') === 'reports' ? 'reports' : pageSection;
    useEffect(() => {
        if (pageSection === 'reports') navigate('/modules/retail-pos/dashboard?tab=reports', { replace: true });
    }, [pageSection, navigate]);
    const [bootstrap, setBootstrap] = useState(null);
    const [branchId, setBranchId] = useState(() => window.localStorage.getItem('aio.retail.branch') ?? null);
    const [data, setData] = useState(null);
    useEffect(() => { setData(null); }, [section]);
    const [products, setProducts] = useState([]);
    const [terminals, setTerminals] = useState([]);
    const [shifts, setShifts] = useState([]);
    const [returns, setReturns] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [drawer, setDrawer] = useState(null);
    const [busy, setBusy] = useState(false);
    const [actionError, setActionError] = useState(null);
    const [pending, setPending] = useState([]);
    const [detail, setDetail] = useState(null);
    const [query, setQuery] = useState('');
    const [posBusy, setPosBusy] = useState(false);
    const [shiftManagerOpen, setShiftManagerOpen] = useState(false);
    const branchRef = useRef(branchId);
    branchRef.current = branchId;
    const loadGeneration = useRef(0);
    const purchasingReloadRef = useRef(null);
    const integrationReloadRef = useRef(null);
    const identity = `${currentAdmin?.current_website?.website_key ?? 'website-main'}:admin-${currentAdmin?.id ?? 'unknown'}`;
    const api = useMemo(() => createRetailApi(callAdminApi, { identity, branchId }), [callAdminApi, identity, branchId]);
    const branches = rows(bootstrap?.branches);
    const branch = branches.find((item) => uuid(item) === branchId);
    const permissions = new Set([...(currentPermissions ?? []), ...(bootstrap?.permissions ?? [])]);
    const can = (permission) => permissions.has(permission) || permissions.has('retail.manage');
    const canViewSales = can('retail.sale.view');
    const canManageShifts = can('retail.shift.manage');
    const canUsePos = can('retail.sale.create');
    useEffect(() => {
        if (section === 'shifts' && canUsePos) {
            navigate('/modules/retail-pos/pos?shifts=1', { replace: true });
        } else if (canManageShifts && (section === 'shifts' || (section === 'pos' && new URLSearchParams(location.search).get('shifts') === '1'))) {
            setShiftManagerOpen(true);
        }
    }, [section, canUsePos, canManageShifts, location.search, navigate]);
    const closeShiftManager = () => {
        setShiftManagerOpen(false);
        if (new URLSearchParams(location.search).has('shifts')) {
            const params = new URLSearchParams(location.search);
            params.delete('shifts');
            navigate({ pathname: location.pathname, search: params.toString() }, { replace: true });
        }
    };
    const branchOptions = branches.map((item) => ({ value: uuid(item), label: `${item.name}${item.code ? ` · ${item.code}` : ''}` }));
    const skuOptions = products.map((item) => ({ value: uuid(item), label: `${item.sku} · ${item.name}` }));

    const loadBootstrap = useCallback(async () => {
        const response = await api.read('bootstrap');
        const next = response.data ?? {};
        setBootstrap(next);
        const options = rows(next.branches);
        if (!options.some((item) => uuid(item) === branchId)) setBranchId(options[0] ? uuid(options[0]) : null);
        return next;
    }, [api, branchId]);
    useEffect(() => {
        let cancelled = false;
        loadBootstrap().catch((failure) => { if (!cancelled) setError(failure.message); });
        return () => { cancelled = true; };
    }, [loadBootstrap]);
    useEffect(() => {
        if (branchId) window.localStorage.setItem('aio.retail.branch', branchId);
        setPending(pendingCommands(identity));
        setData(null); setProducts([]); setTerminals([]); setShifts([]); setReturns([]);
    }, [branchId, identity]);

    const searchProducts = useCallback(async (search = '') => {
        if (!branchId) return [];
        const response = await api.read('products', { branch_id: branchId, query: search });
        const items = rows(response.data);
        if (branchRef.current === branchId) setProducts(items);
        return branchRef.current === branchId ? items : [];
    }, [api, branchId]);

    const reload = useCallback(async () => {
        if (!branchId && !['branches', 'settings'].includes(section)) return;
        const generation = ++loadGeneration.current;
        const live = () => generation === loadGeneration.current && branchRef.current === branchId;
        setLoading(true);
        setError(null);
        try {
            const params = { branch_id: branchId };
            if (section === 'dashboard') {
                const [summary, recent] = await Promise.all([
                    api.read('dashboard', params),
                    canViewSales ? api.read('sales', params).catch((failure) => ({ data: [], error: failure.message })) : Promise.resolve({ data: [] }),
                ]);
                if (!live()) return;
                setData({ ...summary.data, recent_sales: rows(recent.data).slice(0, 6), recent_sales_error: recent.error });
            } else if (section === 'pos') {
                const [items, terminalResponse, shiftResponse] = await Promise.all([searchProducts(''), api.read('terminals', params), api.read('shifts', params)]);
                if (!live()) return;
                setProducts(items); setTerminals(rows(terminalResponse.data)); setShifts(rows(shiftResponse.data));
            } else if (section === 'products') {
                const items = await searchProducts(query); if (live()) setData(items);
            } else if (['transfers', 'stocktakes', 'inventory'].includes(section)) {
                const [response] = await Promise.all([api.read(section, params), searchProducts('')]);
                if (!live()) return;
                setData(response.data);
            } else if (section === 'returns') {
                const [response, returnResponse, shiftResponse] = await Promise.all([api.read('sales', params), api.read('returns', params), api.read('shifts', params)]);
                if (!live()) return;
                setData(response.data); setReturns(rows(returnResponse.data)); setShifts(rows(shiftResponse.data));
            } else if (section === 'shifts') {
                const [response, terminalResponse] = await Promise.all([api.read('shifts', params), api.read('terminals', params)]);
                if (!live()) return;
                setData(response.data); setShifts(rows(response.data)); setTerminals(rows(terminalResponse.data));
            } else if (section === 'branches') {
                const response = await api.read('branches'); if (live()) setData(response.data);
            } else if (section === 'settings') {
                const [response] = await Promise.all([api.read('settings', params), integrationReloadRef.current?.()]);
                if (live()) setData(response.data);
            } else if (section === 'purchasing') {
                await purchasingReloadRef.current?.();
            } else {
                const response = await api.read(section === 'settings' ? 'settings' : section, params); if (live()) setData(response.data);
            }
        } catch (failure) { if (live()) setError(failure.message); }
        finally { if (live()) { setLoading(false); setPending(pendingCommands(identity)); } }
    }, [api, branchId, section, searchProducts, query, identity, canViewSales]);
    useEffect(() => { reload(); }, [reload]);

    const execute = async (path, payload, success = 'Đã ghi nhận thao tác.', method = 'POST') => {
        setBusy(true); setActionError(null);
        try {
            const result = await api.command(path, payload, method);
            message.success(success);
            setDrawer(null);
            await loadBootstrap();
            await reload();
            return result;
        } catch (failure) { setActionError(failure.message); message.error(failure.message); }
        finally { setBusy(false); setPending(pendingCommands(identity)); }
    };
    const open = (config) => { setActionError(null); setDrawer(config); };
    const openSetup = () => open({ title: 'Khởi tạo chuỗi bán lẻ', presentation: 'modal', path: 'setup', initial: { name: 'Chuỗi bán lẻ', branch_name: 'Cửa hàng trung tâm', legal_name: 'Doanh nghiệp' }, fields: () => <>{field('name', 'Tên chuỗi')}{field('legal_name', 'Tên pháp nhân vận hành')}{field('branch_name', 'Tên cửa hàng đầu tiên')}<Alert type="info" showIcon message="Khởi tạo bộ dữ liệu vận hành" description="Tạo chuỗi, pháp nhân, danh mục, cửa hàng, kho và quầy đầu tiên. Hàng hóa và số tồn sẽ được nhập riêng." /></> });
    const openProduct = () => open({
        title: 'Thêm hàng hóa', description: `Thiết lập hàng hóa và giá bán tại ${branch?.name ?? 'chi nhánh đang chọn'}.`,
        className: 'retail-product-drawer', width: 1000, submitLabel: 'Tạo hàng hóa',
        footerNote: 'Có thể nhập tồn kho sau.', path: 'products',
        initial: { branch_id: branchId, base_unit: 'pcs', tracking_mode: 'none', requires_expiry: false, price_minor: '0', opening_quantity: '0', unit_cost: '0' },
        renderForm: ({ form, onSubmit, busy }) => <RetailProductForm form={form} onSubmit={onSubmit} disabled={busy} api={api} branchId={branchId} onCommand={() => setPending(pendingCommands(identity))} />,
    });
    const openReceipt = () => open({ title: 'Nhập hàng vào kho chi nhánh', presentation: 'modal', className: 'retail-receipt-modal', width: 640, submitLabel: 'Nhập kho', path: 'inventory/receipts', initial: { branch_id: branchId, quantity: '1', unit_cost: '0' }, fields: () => <>
        <div className="retail-receipt-branch"><ShopOutlined /><span>Chi nhánh: <strong>{branch?.name ?? 'Đang chọn'}</strong></span></div>
        {field('sku_id', 'Hàng hóa', <Select showSearch optionFilterProp="label" placeholder="Chọn hàng hóa cần nhập" options={skuOptions} />)}
        <Row gutter={16}>
            <Col xs={24} sm={12}>{field('quantity', 'Số lượng', <InputNumber stringMode min="1" precision={0} controls={false} style={{ width: '100%' }} />)}</Col>
            <Col xs={24} sm={12}>{field('unit_cost', 'Giá vốn / đơn vị', <InputNumber stringMode min="0" precision={0} controls={false} suffix="₫" style={{ width: '100%' }} formatter={(value) => value == null || value === '' ? '' : String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.')} parser={(value) => (value ?? '').replace(/\./g, '')} />)}</Col>
        </Row>
        <div className="retail-receipt-batch"><div className="retail-receipt-batch-heading">Thông tin lô hàng <Text type="secondary">Tùy chọn theo hàng hóa</Text></div><Row gutter={16}>
            <Col xs={24} sm={12}>{field('batch_code', 'Mã lô', <Input placeholder="Nhập mã lô" maxLength={255} />, false)}</Col>
            <Col xs={24} sm={12}>{field('expires_at', 'Hạn sử dụng', <DatePicker locale={datePickerVi} format="DD/MM/YYYY" placeholder="Chọn ngày hết hạn" style={{ width: '100%' }} />, false, { getValueProps: (value) => ({ value: value ? dayjs(value).locale('vi') : null }), getValueFromEvent: (date) => date ? date.format('YYYY-MM-DD') : null })}</Col>
        </Row></div>
    </> });
    const openBranch = () => open({ title: 'Tạo chi nhánh', path: 'branches', initial: { chain_id: uuid(rows(bootstrap?.chains)[0]), legal_entity_id: uuid(rows(bootstrap?.legal_entities)[0]) }, fields: () => <>
        {field('chain_id', 'Chuỗi', <Select options={rows(bootstrap?.chains).map((item) => ({ value: uuid(item), label: item.name }))} />)}{field('legal_entity_id', 'Pháp nhân', <Select options={rows(bootstrap?.legal_entities).map((item) => ({ value: uuid(item), label: item.name }))} />)}{field('code', 'Mã chi nhánh')}{field('name', 'Tên chi nhánh')}
    </> });
    const openTransfer = () => open({ title: 'Điều chuyển hàng hóa', presentation: 'modal', className: 'retail-transfer-modal', width: 800, submitLabel: 'Xuất điều chuyển', path: 'transfers', initial: { source_branch_id: branchId, lines: [{ quantity: '1' }] }, fields: () => <>
        <Row gutter={16}>
            <Col xs={24} sm={12}><Form.Item label="Chi nhánh xuất"><Input prefix={<ShopOutlined />} value={branch?.name ?? ''} readOnly /></Form.Item></Col>
            <Col xs={24} sm={12}>{field('destination_branch_id', 'Chi nhánh nhận', <Select showSearch optionFilterProp="label" placeholder="Chọn chi nhánh nhận" options={branchOptions.filter((item) => item.value !== branchId)} />)}</Col>
        </Row>
        {field('note', 'Ghi chú', <Input.TextArea rows={2} placeholder="Nội dung điều chuyển hoặc lưu ý giao nhận" />, false)}
        <Form.List name="lines" rules={[{ validator: (_, value) => value?.length ? Promise.resolve() : Promise.reject(new Error('Thêm ít nhất một hàng hóa.')) }]}>{(fields, { add, remove }, { errors }) => <div className="retail-transfer-lines">
            <div className="retail-transfer-lines-heading"><strong>Hàng hóa điều chuyển <Text type="secondary">({fields.length})</Text></strong><Button icon={<PlusOutlined />} disabled={fields.length >= 200} onClick={() => add({ quantity: '1' })}>Thêm hàng hóa</Button></div>
            <Table rowKey="key" size="small" bordered pagination={false} dataSource={fields} scroll={{ x: 600, y: 260 }} locale={{ emptyText: 'Thêm hàng hóa cần điều chuyển.' }} columns={[
                { title: '#', width: 44, align: 'center', render: (_, record, index) => index + 1 },
                { title: 'Hàng hóa', render: (_, { name }) => <Form.Item name={[name, 'sku_id']} rules={[{ required: true, message: 'Chọn hàng hóa.' }]}><Select aria-label={`Hàng hóa dòng ${name + 1}`} placeholder="Chọn hàng hóa" showSearch optionFilterProp="label" options={skuOptions} /></Form.Item> },
                { title: 'Số lượng', width: 160, render: (_, { name }) => <Form.Item name={[name, 'quantity']} rules={[{ required: true, message: 'Nhập số lượng.' }]}><InputNumber aria-label={`Số lượng dòng ${name + 1}`} stringMode min="0.000001" precision={6} controls={false} style={{ width: '100%' }} formatter={(value) => String(value ?? '').replace(/(\.\d*?[1-9])0+$|\.0+$/, '$1')} /></Form.Item> },
                { title: '', width: 48, align: 'center', render: (_, { name }) => <Button danger type="text" icon={<DeleteOutlined />} aria-label={`Xóa hàng hóa dòng ${name + 1}`} onClick={() => remove(name)} /> },
            ]} />
            <Form.ErrorList errors={errors} />
        </div>}</Form.List>
        <div className="retail-transfer-hint"><InboxOutlined /><span>Hàng được xuất khỏi kho nguồn khi xác nhận. Chi nhánh nhận ghi nhận số lượng khi thực nhận.</span></div>
    </> });
    const openCustomer = () => open({ title: 'Thêm khách hàng', path: 'customers', initial: { branch_id: branchId }, fields: () => <>{field('name', 'Họ tên')}{field('phone', 'Điện thoại', <Input />, false)}{field('email', 'Email', <Input type="email" />, false)}</> });
    const openAssortment = async () => {
        try {
            const response = await api.read('products', { branch_id: branchId, include_catalog: 1 });
            const candidates = rows(response.data);
            open({ title: 'Đưa hàng dùng chung vào chi nhánh', presentation: 'modal', path: 'assortments', initial: { branch_id: branchId, price_minor: '0' }, fields: (form) => <>
                {field('sku_id', 'Hàng hóa trong danh mục chung', <Select showSearch optionFilterProp="label" onChange={(value) => { const selected = candidates.find((item) => uuid(item) === value); form.setFieldsValue({ price_minor: selected?.price_minor ?? '0', expected_version: selected?.is_listed ? selected.price_version : undefined }); }} options={candidates.map((item) => ({ value: uuid(item), label: `${item.sku} · ${item.name}${item.is_listed ? ' · đang bán' : ''}` }))} />)}
                <Form.Item name="expected_version" hidden><Input /></Form.Item>
                {field('price_minor', 'Giá bán tại chi nhánh (đồng)', moneyInput())}<Alert type="info" showIcon message="Giữ nguyên master SKU" description="Chỉ thiết lập danh mục kinh doanh và giá của chi nhánh, không tạo hàng hóa hoặc tồn kho trùng." />
            </> });
        } catch (failure) { message.error(failure.message); }
    };

    const rowActions = (record) => {
        if (section === 'transfers' && can('retail.transfer.manage')) return <Space wrap>{['dispatched', 'partially_received'].includes(record.status) && <Button size="small" onClick={() => open({ title: 'Xác nhận hàng thực nhận', path: `transfers/${uuid(record)}/receive`, initial: { expected_version: record.version, lines: (record.lines ?? []).filter((line) => quantityMicros(line.quantity) > quantityMicros(line.received_quantity ?? '0')).map((line) => ({ line_id: line.id, quantity: quantityText(quantityMicros(line.quantity) - quantityMicros(line.received_quantity ?? '0')) })) }, fields: () => <><Alert type="info" showIcon message="Chỉ ghi nhận số lượng thực nhận" style={{ marginBottom: 16 }} /><Form.List name="lines">{(fields) => fields.map(({ key, name }) => <Space key={key} align="baseline"><Form.Item name={[name, 'line_id']} hidden><Input /></Form.Item><Text>Dòng hàng {name + 1}</Text><Form.Item name={[name, 'quantity']} rules={[{ required: true }]}><InputNumber stringMode min="0" precision={6} style={{ width: '100%' }} /></Form.Item></Space>)}</Form.List></> })}>Nhận hàng</Button>}</Space>;
        if (section === 'stocktakes' && can('retail.stocktake.manage')) return ['draft', 'counting'].includes(record.status) && <Button size="small" onClick={() => open({ title: 'Nhập số đếm và duyệt kiểm kê', path: `stocktakes/${uuid(record)}/post`, initial: { expected_version: record.version, counts: (record.lines ?? []).map((line) => ({ balance_id: line.balance_id, quantity: '' })) }, fields: () => <><Alert type="warning" showIcon message="Người tạo đợt kiểm kê không tự duyệt" description="Nhập đầy đủ số thực đếm. Hệ thống ghi chứng từ chênh lệch và mở lại kho sau khi duyệt thành công." style={{ marginBottom: 16 }} /><Form.List name="counts">{(fields) => fields.map(({ key, name }) => <div className="retail-count-line" key={key}><Form.Item name={[name, 'balance_id']} hidden><Input /></Form.Item><Text>{record.lines?.[name]?.name ?? `Vị trí tồn #${record.lines?.[name]?.balance_id}`}</Text><Form.Item name={[name, 'quantity']} rules={[{ required: true, message: 'Nhập số thực đếm.' }]}><InputNumber stringMode min="0" precision={6} style={{ width: '100%' }} /></Form.Item></div>)}</Form.List></> })}>Nhập số đếm & duyệt</Button>;
        if (section === 'returns' && can('retail.return.manage') && record.status === 'completed') return <Button size="small" onClick={() => open({ title: 'Đổi trả theo đơn gốc', path: `sales/${uuid(record)}/returns`, initial: { expected_version: record.version, lines: (record.lines ?? []).map((line) => ({ sale_line_id: uuid(line), quantity: '0', disposition: 'saleable' })) }, fields: () => <>{field('reason', 'Lý do', <Input.TextArea rows={2} />)}<Form.List name="lines">{(fields) => fields.map(({ key, name }) => <Card size="small" title={record.lines?.[name]?.name ?? `Dòng ${name + 1}`} key={key} style={{ marginBottom: 12 }}><Form.Item name={[name, 'sale_line_id']} hidden><Input /></Form.Item><Row gutter={12}><Col span={10}><Form.Item label="Lượng trả" name={[name, 'quantity']}><InputNumber stringMode min="0" precision={6} style={{ width: '100%' }} /></Form.Item></Col><Col span={14}><Form.Item label="Tình trạng" name={[name, 'disposition']}><Select options={[{ value: 'saleable', label: 'Có thể bán lại' }, { value: 'quarantine', label: 'Cách ly' }, { value: 'damaged', label: 'Hỏng' }]} /></Form.Item></Col></Row></Card>)}</Form.List><Alert showIcon type="info" message="Giá hoàn được tính theo đơn gốc và các lần trả trước. Nhập 0 cho mặt hàng không trả." /></> })}>Trả hàng</Button>;
        return null;
    };

    const columns = (() => {
        if (section === 'products') return [{ title: 'SKU', dataIndex: 'sku', width: 150 }, { title: 'Hàng hóa', dataIndex: 'name' }, { title: 'Đơn vị', dataIndex: 'base_unit', width: 90 }, { title: 'Giá bán', dataIndex: 'price_minor', align: 'right', render: formatMoney }, { title: 'Có thể bán', dataIndex: 'available', align: 'right', render: formatQuantity }, { title: 'Mã vạch', dataIndex: 'barcode', render: (value, record) => value ?? record.barcodes?.map((item) => typeof item === 'string' ? item : item.barcode ?? item.code).join(', ') ?? '—' }, { title: 'Thao tác', render: (_, record) => can('retail.price.manage') && <Button size="small" onClick={() => open({ title: `Giá tại chi nhánh · ${record.name}`, path: `products/${uuid(record)}`, method: 'PUT', initial: { branch_id: branchId, price_minor: record.price_minor, expected_version: record.price_version }, fields: () => <>{field('price_minor', 'Giá bán (đồng)', moneyInput())}</> })}>Sửa giá</Button> }];
        if (section === 'branches') return [{ title: 'Mã', dataIndex: 'code' }, { title: 'Chi nhánh', dataIndex: 'name' }, { title: 'Pháp nhân', render: (_, record) => record.legal_entity?.name ?? rows(bootstrap?.legal_entities).find((item) => uuid(item) === String(record.legal_entity_id))?.name ?? '—' }, { title: 'Trạng thái', dataIndex: 'status', render: status }];
        if (section === 'inventory') return [{ title: 'SKU', dataIndex: 'sku' }, { title: 'Hàng hóa', dataIndex: 'name' }, { title: 'Tồn thực tế', render: (_, record) => (record.on_hand ?? record.quantity_on_hand) == null ? '—' : formatQuantity(record.on_hand ?? record.quantity_on_hand) }, { title: 'Đang giữ', render: (_, record) => (record.reserved ?? record.quantity_reserved) == null ? '—' : formatQuantity(record.reserved ?? record.quantity_reserved) }, { title: 'Có thể bán', dataIndex: 'available', render: formatQuantity }, { title: 'Lô', dataIndex: 'batch_code', render: (value) => value ?? '—' }, { title: 'Hạn dùng', dataIndex: 'expires_at', render: (value) => value ?? '—' }];
        if (section === 'customers') return [{ title: 'Khách hàng', dataIndex: 'name' }, { title: 'Điện thoại', dataIndex: 'phone', render: (value) => value ?? '—' }, { title: 'Email', dataIndex: 'email', render: (value) => value ?? '—' }, { title: 'Ngày tạo', dataIndex: 'created_at', render: formatDate }];
        return [{ title: section === 'returns' ? 'Đơn hàng' : 'Mã chứng từ', render: (_, record) => <Button type="link" onClick={() => setDetail(record)}>{record.code ?? record.number ?? uuid(record).slice(-8)}</Button> }, ...(section === 'transfers' ? [{ title: 'Nguồn → Đích', render: (_, record) => `${record.source_branch?.name ?? branches.find((item) => uuid(item) === record.source_branch_id)?.name ?? 'Kho nguồn'} → ${record.destination_branch?.name ?? branches.find((item) => uuid(item) === record.destination_branch_id)?.name ?? 'Kho nhận'}` }] : []), ...(section === 'returns' ? [{ title: 'Giá trị đơn', render: (_, record) => formatMoney(saleAmount(record)) }] : []), { title: 'Trạng thái', dataIndex: 'status', render: status }, { title: 'Thời gian', dataIndex: 'created_at', render: formatDate }, { title: 'Thao tác', render: rowActions }];
    })();

    const actions = (() => {
        if (section === 'products' && can('retail.product.manage')) return <Space>{can('retail.price.manage') && <Button onClick={openAssortment}>Chọn từ danh mục chung</Button>}<Button icon={<PlusOutlined />} type="primary" onClick={openProduct}>Thêm hàng hóa</Button></Space>;
        if (section === 'branches' && can('retail.branch.manage')) return <Button icon={<PlusOutlined />} type="primary" onClick={openBranch}>Thêm chi nhánh</Button>;
        if (section === 'inventory' && can('retail.inventory.view') && permissions.has('inventory.receipt.manage')) return <Button icon={<PlusOutlined />} type="primary" onClick={openReceipt}>Nhập hàng</Button>;
        if (section === 'transfers' && can('retail.transfer.manage')) return <Button icon={<PlusOutlined />} type="primary" onClick={openTransfer}>Tạo điều chuyển</Button>;
        if (section === 'stocktakes' && can('retail.stocktake.manage')) return <Button icon={<PlusOutlined />} type="primary" onClick={() => open({ title: 'Mở đợt kiểm kê', path: 'stocktakes', initial: { branch_id: branchId }, fields: () => <><Alert type="warning" showIcon message="Phạm vi kiểm kê sẽ tạm dừng chuyển động kho" description="Kết thúc các đơn giữ hàng và đồng bộ gateway trước khi chốt số đếm." style={{ marginBottom: 16 }} />{field('note', 'Ghi chú', <Input.TextArea rows={3} />, false)}</> })}>Mở kiểm kê</Button>;
        if (section === 'customers' && can('retail.customer.manage')) return <Button icon={<PlusOutlined />} type="primary" onClick={openCustomer}>Thêm khách hàng</Button>;
        return null;
    })();

    const renderDashboard = () => {
        const metrics = [
            { label: 'Doanh thu thuần', value: data?.net_sales_minor ?? data?.revenue_minor ?? data?.sales_minor, money: true, icon: <DollarOutlined />, note: 'Sau giảm giá và trả hàng', tone: 'primary' },
            { label: 'Đơn hoàn tất', value: data?.completed_sales ?? data?.sale_count ?? data?.sales_count, icon: <ShoppingCartOutlined />, note: 'Giao dịch hoàn tất trong ngày' },
            { label: 'Ca đang mở', value: data?.open_shifts ?? data?.active_shifts, icon: <WalletOutlined />, note: 'Đang vận hành tại chi nhánh' },
            { label: 'Giá trị trả hàng', value: data?.refunds_minor ?? data?.refund_minor ?? data?.returns_minor, money: true, icon: <UndoOutlined />, note: 'Phiếu trả ghi nhận trong ngày' },
        ];
        const quickActions = [
            { key: 'pos', label: 'Bán tại quầy', description: 'Tạo đơn và thanh toán', permission: 'retail.sale.create', icon: <ShopOutlined /> },
            { key: 'shifts', label: 'Ca & tiền quầy', description: 'Mở ca, kiểm đếm và đối soát', permission: 'retail.shift.manage', icon: <WalletOutlined /> },
            { key: 'inventory', label: 'Kiểm tra tồn kho', description: 'Theo dõi hàng có thể bán', permission: 'retail.inventory.view', icon: <InboxOutlined /> },
        ].filter((action) => can(action.permission));
        const recentSales = canViewSales ? rows(data?.recent_sales) : [];
        return <div className="retail-overview">
            <div className="retail-overview-caption"><span>Hiệu quả trong ngày</span><Text type="secondary">Theo múi giờ chi nhánh</Text></div>
            <div className="retail-overview-metrics">{metrics.map((metric) => <Card key={metric.label} className={`retail-overview-metric${metric.tone === 'primary' ? ' retail-overview-metric-primary' : ''}`}>
                <div className="retail-overview-metric-label"><span>{metric.label}</span><span className="retail-overview-metric-icon">{metric.icon}</span></div>
                <Statistic value={metric.value ?? '—'} formatter={() => metric.value == null ? '—' : metric.money ? formatMoney(metric.value) : String(metric.value)} />
                <Text className="retail-overview-metric-note">{metric.note}</Text>
            </Card>)}</div>
            <div className="retail-overview-body">
                <Card className="retail-overview-transactions" title={<div className="retail-overview-panel-title"><span>Giao dịch gần đây</span><Text type="secondary">Các đơn mới nhất tại chi nhánh</Text></div>} extra={recentSales.length > 0 ? <Tag>{recentSales.length} giao dịch</Tag> : null}>
                    {data?.recent_sales_error ? <Alert type="warning" showIcon message="Chưa tải được giao dịch" description={data.recent_sales_error} action={<Button size="small" onClick={reload}>Thử lại</Button>} /> : <Table
                        size="small" rowKey={uuid} dataSource={recentSales} loading={loading} pagination={false} scroll={{ x: 490 }}
                        columns={[
                            { title: 'Mã đơn', render: (_, record) => <Text strong>{record.code ?? uuid(record).slice(-8)}</Text> },
                            { title: 'Giá trị', align: 'right', render: (_, record) => formatMoney(saleAmount(record)) },
                            { title: 'Trạng thái', dataIndex: 'status', render: status },
                            { title: 'Thời gian', dataIndex: 'created_at', render: formatDate },
                        ]}
                        locale={{ emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={canViewSales ? 'Chưa có giao dịch tại chi nhánh này.' : 'Bạn chưa có quyền xem danh sách giao dịch.'} /> }}
                    />}
                </Card>
                <div className="retail-overview-sidebar">
                    <Card className="retail-overview-actions" title={quickActions.length > 0 ? 'Thao tác nhanh' : 'Đối soát hoàn tiền'}><div className="retail-overview-action-list">{quickActions.map((action) => <Button key={action.key} className="retail-overview-action" onClick={() => navigate(action.key === 'shifts' && canUsePos ? '/modules/retail-pos/pos?shifts=1' : `/modules/retail-pos/${action.key}`)}>
                        <span className="retail-overview-action-icon">{action.icon}</span><span className="retail-overview-action-copy"><strong>{action.label}</strong><small>{action.description}</small></span><ArrowRightOutlined />
                    </Button>)}</div>
                    <div className="retail-overview-refund-status">
                        <div className="retail-overview-refunds"><Text type="secondary">Phiếu chờ hoàn tiền</Text><strong>{data?.pending_refunds ?? '—'}</strong></div>
                        <Text type="secondary">{data?.pending_refunds == null ? 'Đang tải dữ liệu đối soát.' : Number(data.pending_refunds) > 0 ? 'Kiểm tra các phiếu trả hàng đang chờ hoàn tiền.' : 'Không có phiếu chờ hoàn tiền.'}</Text>
                        {Number(data?.pending_refunds) > 0 && can('retail.return.manage') ? <Button type="link" onClick={() => navigate('/modules/retail-pos/returns')}>Xem phiếu đổi trả <ArrowRightOutlined /></Button> : null}
                    </div></Card>
                </div>
            </div>
            <div className="retail-overview-updated"><span>Chi nhánh: {branch?.name ?? '—'}</span><span>Cập nhật: {formatDate(data?.as_of)}</span></div>
        </div>;
    };
    const renderReports = () => {
        const labels = { net_sales_minor: 'Doanh thu thuần', gross_minor: 'Doanh thu trước giảm', gross_sales_minor: 'Doanh thu trước giảm', discount_minor: 'Giảm giá', refunds_minor: 'Giá trị trả hàng', refund_minor: 'Hoàn trả', cost_minor: 'Giá vốn', gross_margin_minor: 'Lãi gộp', sale_count: 'Số đơn', pending_refunds: 'Phiếu chờ hoàn tiền' };
        return <><Row gutter={[16, 16]}>{Object.entries(data?.summary ?? data ?? {}).filter(([key]) => labels[key]).map(([key, value]) => <Col xs={24} md={8} key={key}><Card><Statistic title={labels[key]} value={value ?? '—'} formatter={() => key.endsWith('_minor') ? formatMoney(value) : String(value)} /></Card></Col>)}</Row><Alert type="info" showIcon style={{ marginTop: 18 }} message={`Dữ liệu đến ${formatDate(data?.as_of)}`} description={data?.cost_status === 'unavailable' ? 'Chưa công bố báo cáo giá vốn/lãi gộp khi dữ liệu giá vốn chưa được xác định đầy đủ.' : 'Các chỉ tiêu được đối chiếu từ giao dịch đã hoàn tất và phiếu trả liên quan.'} />{rows(data?.daily).length > 0 && <Card style={{ marginTop: 16 }} title="Doanh thu theo ngày"><Table rowKey={(item) => item.date ?? item.business_date} dataSource={rows(data.daily)} columns={[{ title: 'Ngày', render: (_, item) => item.date ?? item.business_date }, { title: 'Số đơn', render: (_, item) => item.sale_count ?? item.count ?? '—' }, { title: 'Doanh thu thuần', render: (_, item) => formatMoney(item.net_sales_minor ?? item.total_minor) }]} /></Card>}</>;
    };
    const recover = async (entry) => {
        try {
            const result = await api.recover(entry);
            if (result.data?.status === 'committed') window.dispatchEvent(new CustomEvent('retail-command-recovered', { detail: { entry, resource: result.data.resource } }));
            message.info(result.data?.status === 'committed' ? 'Giao dịch đã hoàn tất trên máy chủ.' : 'Kết quả vẫn cần đối soát; không gửi lại với định danh mới.');
            setPending(pendingCommands(identity));
            await reload();
        } catch (failure) { message.error(failure.message); }
    };

    const overviewPage = ['dashboard', 'reports'].includes(section);
    return <div className={`retail-workspace${overviewPage ? ' retail-workspace-overview' : ''}`}>
        <div className="retail-workspace-header"><div><span className="retail-eyebrow">{overviewPage ? 'ĐIỀU HÀNH KINH DOANH' : 'AIO RETAIL'}</span><Title level={3}>{overviewPage ? 'Tổng quan cửa hàng' : section === 'settings' ? 'Cấu hình cửa hàng' : 'Quản lý siêu thị & cửa hàng'}</Title><Text type="secondary">{overviewPage ? 'Theo dõi kết quả bán hàng và hoạt động tại chi nhánh.' : section === 'settings' ? 'Thiết lập vận hành và kết nối các phân hệ.' : 'Bán hàng tại quầy và điều hành chuỗi chi nhánh'}</Text></div><Space wrap><Select aria-label="Chi nhánh" prefix={<ShopOutlined />} value={branchId} options={branchOptions} onChange={setBranchId} disabled={posBusy} placeholder="Chọn chi nhánh" style={{ minWidth: 240 }} /><Button icon={<ReloadOutlined />} loading={loading} onClick={reload}>Tải lại</Button></Space></div>
        {pending.length > 0 && <Alert type="warning" showIcon style={{ marginBottom: 18 }} message={`${pending.length} giao dịch cần kiểm tra kết quả`} description={<Space direction="vertical">{pending.map((entry) => <Space wrap key={entry.idempotencyKey}><Text>{entry.path} · {formatDate(entry.createdAt)}</Text><Button size="small" onClick={() => recover(entry)}>Kiểm tra kết quả</Button></Space>)}</Space>} />}
        {error && <Alert type="error" showIcon message="Không tải được dữ liệu" description={error} action={<Button size="small" onClick={reload}>Thử lại</Button>} style={{ marginBottom: 18 }} />}
        {bootstrap && !branches.length ? <Card className="retail-onboarding"><ShopOutlined /><Title level={3}>Khởi tạo cửa hàng đầu tiên</Title><Paragraph>Tạo chuỗi, pháp nhân, danh mục hàng dùng chung và kho cửa hàng để bắt đầu bán hàng.</Paragraph><Button type="primary" size="large" onClick={openSetup} disabled={!can('retail.setup.manage') && !can('retail.branch.manage')}>Khởi tạo vận hành</Button></Card>
            : section === 'shifts' ? <Card title="Ca & tiền quầy" extra={<Button icon={<WalletOutlined />} onClick={() => setShiftManagerOpen(true)}>QL ca</Button>}><Text type="secondary">Mở quản lý ca để kiểm đếm tiền quầy và đối soát.</Text></Card>
            : section === 'pos' ? <RetailSalePanel onManageShifts={canManageShifts ? () => setShiftManagerOpen(true) : undefined} products={products} api={api} branch={branch} terminals={terminals} shifts={shifts} reload={reload} identity={identity} onSearch={searchProducts} loading={loading} canSell={can('retail.sale.create') && can('retail.payment.collect')} canConfirmTransfer={can('retail.settings.manage')} canViewCustomers={can('retail.customer.view')} canCreateCustomers={can('retail.customer.manage')} onScopeLock={setPosBusy} />
                : overviewPage ? <Tabs className="retail-overview-tabs" activeKey={section} onChange={(tab) => navigate(tab === 'reports' ? '/modules/retail-pos/dashboard?tab=reports' : '/modules/retail-pos/dashboard')} items={[
                    { key: 'dashboard', label: 'Hoạt động hôm nay', children: section === 'dashboard' ? renderDashboard() : null },
                    { key: 'reports', label: 'Báo cáo kinh doanh', children: section === 'reports' ? renderReports() : null },
                ]} />
                        : section === 'settings' ? <RetailSettingsPanel key={branchId} branch={branch} branchId={branchId} branchOptions={branchOptions} onBranchChange={setBranchId} bootstrap={bootstrap} callAdminApi={callAdminApi} identity={identity} canIntegrate={can('retail.integration.manage')} onCommand={() => setPending(pendingCommands(identity))} integrationReloadRef={integrationReloadRef} />
                            : section === 'purchasing' ? <RetailPurchasingPanel reloadRef={purchasingReloadRef} key={branchId} branch={branch} bootstrap={bootstrap} callAdminApi={callAdminApi} permissions={permissions} identity={identity} onCommand={() => setPending(pendingCommands(identity))} />
                                : <Card title={sections.find(([key]) => key === section)?.[1] ?? 'Vận hành'} extra={actions} loading={loading && data === null}>
                                    {section === 'products' && <Input.Search value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Tìm SKU, mã vạch hoặc tên hàng" onSearch={reload} allowClear style={{ maxWidth: 420, marginBottom: 18 }} />}
                                    <Table rowKey={uuid} dataSource={rows(data)} columns={columns} loading={loading} scroll={{ x: 'max-content' }} pagination={{ pageSize: 20, showSizeChanger: true, showTotal: (total) => `${total} bản ghi` }} locale={{ emptyText: 'Chưa có dữ liệu phù hợp.' }} />
                                    {section === 'returns' && <><Title level={5} style={{ marginTop: 24 }}>Phiếu trả hàng và tiền hoàn</Title><Table rowKey={uuid} dataSource={returns} pagination={{ pageSize: 10 }} columns={[
                                        { title: 'Phiếu', render: (_, record) => uuid(record).slice(-8) }, { title: 'Số tiền', dataIndex: 'amount_minor', render: formatMoney }, { title: 'Trạng thái hoàn', dataIndex: 'refund_status', render: status }, { title: 'Ngày', dataIndex: 'created_at', render: formatDate },
                                        { title: 'Thao tác', render: (_, record) => record.refund_status === 'pending' && can('retail.payment.collect') && <Button size="small" onClick={() => open({ title: 'Xác nhận hoàn tiền mặt', path: `returns/${uuid(record)}/refund`, initial: { method: 'cash' }, fields: () => <><Alert type="warning" showIcon message={`Hoàn ${formatMoney(record.amount_minor)} cho khách`} description="Chỉ xác nhận khi đã giao tiền thực tế. Giao dịch được ghi vào ca được chọn." style={{ marginBottom: 16 }} />{field('shift_id', 'Ca chi tiền', <Select options={shifts.filter((item) => item.status === 'open').map((item) => ({ value: uuid(item), label: item.code ?? `Ca ${uuid(item).slice(-6)}` }))} />)}</> })}>Hoàn tiền</Button> },
                                    ]} /></>}
                                </Card>}
        {canManageShifts && ['pos', 'shifts'].includes(section) && <RetailShiftManager key={branchId} open={shiftManagerOpen} onClose={closeShiftManager} branch={branch} shifts={shifts} terminals={terminals} api={api} onChanged={reload} onCommand={() => setPending(pendingCommands(identity))} canCreateTerminal={can('retail.branch.manage')} locked={posBusy} loading={loading} />}
        <ResourceDrawer config={drawer} busy={busy} error={actionError} onClose={() => setDrawer(null)} onSubmit={(values) => {
            const payload = { ...(drawer.initial ?? {}), ...values };
            if (drawer.path?.endsWith('/returns') || drawer.path?.endsWith('/receive')) payload.lines = (payload.lines ?? []).filter((line) => quantityMicros(line.quantity ?? '0') > 0n);
            execute(drawer.path, payload, drawer.success ?? 'Đã ghi nhận thao tác.', drawer.method);
        }} />
        <Drawer title="Chi tiết chứng từ" width={720} open={Boolean(detail)} onClose={() => setDetail(null)}><Descriptions bordered column={1} items={[{ key: 'code', label: 'Mã', children: detail?.code ?? uuid(detail) }, { key: 'status', label: 'Trạng thái', children: status(detail?.status) }, { key: 'date', label: 'Tạo lúc', children: formatDate(detail?.created_at) }]} /><Table style={{ marginTop: 18 }} rowKey={(line) => uuid(line) || line.sku_id} dataSource={detail?.lines ?? []} pagination={false} columns={[{ title: 'Hàng hóa', render: (_, line) => line.name ?? line.sku ?? line.sku_id }, { title: 'Số lượng', dataIndex: 'quantity', render: formatQuantity }, { title: 'Giá trị', render: (_, line) => line.total_minor ? formatMoney(line.total_minor) : '—' }]} /></Drawer>
    </div>;
}

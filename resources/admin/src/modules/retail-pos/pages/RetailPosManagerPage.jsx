import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import ShopOutlined from '@ant-design/icons/ShopOutlined';
import { createRetailApi } from '../api/retailApi';
import { pendingCommands } from '../state/retailCommandJournal';
import { formatDate, formatMoney, formatQuantity, quantityMicros, quantityText, rows, saleAmount, statusLabels, uuid } from '../utils/retailFormat';
import RetailSalePanel from '../components/RetailSalePanel';
import RetailPurchasingPanel from '../components/RetailPurchasingPanel';
import RetailIntegrationPanel from '../components/RetailIntegrationPanel';
import '../styles/retail-pos.css';

const { Text, Title, Paragraph } = Typography;
const sections = [
    ['dashboard', 'Tổng quan'], ['pos', 'Bán hàng'], ['products', 'Hàng hóa'], ['branches', 'Chi nhánh'],
    ['inventory', 'Tồn kho'], ['transfers', 'Điều chuyển'], ['stocktakes', 'Kiểm kê'], ['returns', 'Đổi trả'],
    ['shifts', 'Ca & tiền'], ['customers', 'Khách hàng'], ['purchasing', 'Mua hàng'], ['reports', 'Báo cáo'], ['settings', 'Cấu hình'],
];
const sectionPermissions = {
    dashboard: 'retail.report.view', pos: 'retail.sale.create', products: 'retail.product.view', branches: 'retail.branch.manage',
    inventory: 'retail.inventory.view', transfers: 'retail.transfer.manage', stocktakes: 'retail.stocktake.manage', returns: 'retail.return.manage',
    shifts: 'retail.shift.manage', customers: 'retail.customer.view', purchasing: 'retail.view', reports: 'retail.report.view', settings: 'retail.settings.manage',
};
const status = (value) => <Tag color={['completed', 'confirmed', 'posted', 'received', 'reconciled', 'active'].includes(value) ? 'green' : ['unknown', 'failed', 'exception', 'cancelled'].includes(value) ? 'red' : 'blue'}>{statusLabels[value] ?? value ?? '—'}</Tag>;
const field = (name, label, child = <Input />, required = true, extra = {}) => <Form.Item key={name} name={name} label={label} rules={required ? [{ required: true, message: `Nhập ${label.toLowerCase()}.` }] : []} {...extra}>{child}</Form.Item>;
const moneyInput = () => <InputNumber stringMode min="0" precision={0} style={{ width: '100%' }} />;
const quantityInput = () => <InputNumber stringMode min="0.000001" precision={6} style={{ width: '100%' }} />;

function ResourceDrawer({ config, busy, error, onClose, onSubmit, children }) {
    const [form] = Form.useForm();
    useEffect(() => {
        if (config) { form.resetFields(); form.setFieldsValue(config.initial ?? {}); }
    }, [config, form]);
    return <Drawer title={config?.title} width={600} open={Boolean(config)} onClose={() => !busy && onClose()} destroyOnClose maskClosable={false}
        footer={<Space style={{ justifyContent: 'flex-end', width: '100%' }}><Button disabled={busy} onClick={onClose}>Đóng</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>{config?.submitLabel ?? 'Lưu'}</Button></Space>}>
        {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 18 }} />}
        <Form form={form} layout="vertical" onFinish={onSubmit}>{config?.fields?.(form) ?? children}</Form>
    </Drawer>;
}

export default function RetailPosManagerPage({ moduleMenu, callAdminApi, currentAdmin, currentPermissions = [] }) {
    const { message } = App.useApp();
    const location = useLocation();
    const navigate = useNavigate();
    const routeSection = location.pathname.split('/').filter(Boolean).at(-1);
    const section = sections.some(([key]) => key === routeSection) ? routeSection : String(moduleMenu?.key ?? '').replace(/^retail-/, '') || 'dashboard';
    const [bootstrap, setBootstrap] = useState(null);
    const [branchId, setBranchId] = useState(() => window.localStorage.getItem('aio.retail.branch') ?? null);
    const [data, setData] = useState(null);
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
    const branchRef = useRef(branchId);
    branchRef.current = branchId;
    const loadGeneration = useRef(0);
    const identity = `${currentAdmin?.current_website?.website_key ?? 'website-main'}:admin-${currentAdmin?.id ?? 'unknown'}`;
    const api = useMemo(() => createRetailApi(callAdminApi, { identity, branchId }), [callAdminApi, identity, branchId]);
    const branches = rows(bootstrap?.branches);
    const branch = branches.find((item) => uuid(item) === branchId);
    const permissions = new Set([...(currentPermissions ?? []), ...(bootstrap?.permissions ?? [])]);
    const can = (permission) => permissions.has(permission) || permissions.has('retail.manage');
    const visibleSections = sections.filter(([key]) => can(sectionPermissions[key]));
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
            if (section === 'pos') {
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
            } else if (section === 'purchasing') {
                setData(null);
            } else {
                const response = await api.read(section === 'settings' ? 'settings' : section, params); if (live()) setData(response.data);
            }
        } catch (failure) { if (live()) setError(failure.message); }
        finally { if (live()) { setLoading(false); setPending(pendingCommands(identity)); } }
    }, [api, branchId, section, searchProducts, query, identity]);
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
    const openSetup = () => open({ title: 'Khởi tạo chuỗi bán lẻ', path: 'setup', initial: { name: 'Chuỗi bán lẻ', branch_name: 'Cửa hàng trung tâm', legal_name: 'Doanh nghiệp' }, fields: () => <>{field('name', 'Tên chuỗi')}{field('legal_name', 'Tên pháp nhân vận hành')}{field('branch_name', 'Tên cửa hàng đầu tiên')}<Alert type="info" showIcon message="Khởi tạo bộ dữ liệu vận hành" description="Tạo chuỗi, pháp nhân, danh mục, cửa hàng, kho và quầy đầu tiên. Hàng hóa và số tồn sẽ được nhập riêng." /></> });
    const openProduct = () => open({ title: 'Thêm hàng hóa', path: 'products', initial: { branch_id: branchId, base_unit: 'pcs', quantity_scale: 0, tracking_mode: 'none', requires_expiry: false, price_minor: '0', opening_quantity: '0', unit_cost: '0' }, fields: (form) => <>
        {field('sku', 'Mã SKU')}{field('name', 'Tên hàng hóa')}{field('base_unit', 'Đơn vị cơ sở', <Select onChange={(value) => form.setFieldValue('quantity_scale', ['kg', 'l'].includes(value) ? 6 : 0)} options={[{ value: 'pcs', label: 'Cái / đơn vị' }, { value: 'kg', label: 'Kilôgam' }, { value: 'l', label: 'Lít' }, { value: 'pack', label: 'Gói' }]} />)}
        {field('quantity_scale', 'Số chữ số lẻ của số lượng', <InputNumber min={0} max={6} style={{ width: '100%' }} />)}
        {field('tracking_mode', 'Theo dõi lô', <Select options={[{ value: 'none', label: 'Không theo lô' }, { value: 'batch', label: 'Theo lô hàng' }]} />)}
        <Form.Item name="requires_expiry" label="Bắt buộc hạn sử dụng" valuePropName="checked"><Switch /></Form.Item>
        {field('barcode', 'Mã vạch', <Input placeholder="Giữ nguyên số 0 ở đầu" />, false)}{field('price_minor', 'Giá bán (đồng)', moneyInput())}
        {field('opening_quantity', 'Số lượng nhập ban đầu', <InputNumber stringMode min="0" precision={6} style={{ width: '100%' }} />, false)}{field('unit_cost', 'Giá vốn mỗi đơn vị', <InputNumber stringMode min="0" precision={6} style={{ width: '100%' }} />, false)}
        {field('batch_code', 'Mã lô nhập ban đầu', <Input />, false)}{field('expires_at', 'Hạn dùng lô nhập ban đầu', <Input type="date" />, false)}
        <Title level={5}>Quy cách đóng gói</Title><Form.List name="packagings">{(fields, { add, remove }) => <>{fields.map(({ key, name }) => <Card size="small" key={key} style={{ marginBottom: 12 }} extra={<Button type="text" danger onClick={() => remove(name)}>Xóa</Button>}><Row gutter={12}><Col span={12}><Form.Item name={[name, 'code']} label="Mã quy cách" rules={[{ required: true }]}><Input placeholder="CARTON" /></Form.Item></Col><Col span={12}><Form.Item name={[name, 'name']} label="Tên" rules={[{ required: true }]}><Input placeholder="Thùng" /></Form.Item></Col></Row><Form.Item name={[name, 'quantity_multiplier']} label="Số đơn vị cơ sở / quy cách" rules={[{ required: true }]}>{quantityInput()}</Form.Item><Form.Item name={[name, 'barcode']} label="Mã vạch quy cách"><Input /></Form.Item></Card>)}<Button block type="dashed" onClick={() => add({ quantity_multiplier: '1' })}>Thêm lốc / thùng / quy cách</Button></>}</Form.List>
    </> });
    const openReceipt = () => open({ title: 'Nhập hàng vào kho chi nhánh', path: 'inventory/receipts', initial: { branch_id: branchId, quantity: '1', unit_cost: '0' }, fields: () => <>
        {field('sku_id', 'Hàng hóa', <Select showSearch optionFilterProp="label" options={skuOptions} />)}{field('quantity', 'Số lượng', quantityInput())}{field('unit_cost', 'Giá vốn mỗi đơn vị', <InputNumber stringMode min="0" precision={6} style={{ width: '100%' }} />)}
        {field('batch_code', 'Mã lô', <Input />, false)}{field('expires_at', 'Hạn sử dụng', <Input type="date" />, false)}
    </> });
    const openBranch = () => open({ title: 'Tạo chi nhánh', path: 'branches', initial: { chain_id: uuid(rows(bootstrap?.chains)[0]), legal_entity_id: uuid(rows(bootstrap?.legal_entities)[0]) }, fields: () => <>
        {field('chain_id', 'Chuỗi', <Select options={rows(bootstrap?.chains).map((item) => ({ value: uuid(item), label: item.name }))} />)}{field('legal_entity_id', 'Pháp nhân', <Select options={rows(bootstrap?.legal_entities).map((item) => ({ value: uuid(item), label: item.name }))} />)}{field('code', 'Mã chi nhánh')}{field('name', 'Tên chi nhánh')}
    </> });
    const openShift = () => open({ title: 'Mở ca bán hàng', path: 'shifts', initial: { branch_id: branchId, opening_minor: '0' }, fields: () => <>{field('terminal_id', 'Quầy', <Select options={terminals.map((item) => ({ value: uuid(item), label: item.name ?? item.code }))} />)}{field('opening_minor', 'Tiền đầu ca (đồng)', moneyInput())}</> });
    const openTransfer = () => open({ title: 'Đề nghị điều chuyển', path: 'transfers', initial: { source_branch_id: branchId, lines: [{ quantity: '1' }] }, fields: () => <>
        {field('destination_branch_id', 'Chi nhánh nhận', <Select options={branchOptions.filter((item) => item.value !== branchId)} />)}{field('note', 'Ghi chú', <Input.TextArea rows={2} />, false)}
        <Form.List name="lines">{(fields, { add, remove }) => <>{fields.map(({ key, name, ...rest }) => <div className="retail-form-line" key={key}><Form.Item {...rest} name={[name, 'sku_id']} rules={[{ required: true }]}><Select placeholder="Hàng hóa" showSearch optionFilterProp="label" options={skuOptions} /></Form.Item><Form.Item {...rest} name={[name, 'quantity']} rules={[{ required: true }]}>{quantityInput()}</Form.Item><Button danger type="text" onClick={() => remove(name)}>×</Button></div>)}<Button block type="dashed" onClick={() => add({ quantity: '1' })}>Thêm hàng hóa</Button></>}</Form.List>
    </> });
    const openCustomer = () => open({ title: 'Thêm khách hàng', path: 'customers', initial: { branch_id: branchId }, fields: () => <>{field('name', 'Họ tên')}{field('phone', 'Điện thoại', <Input />, false)}{field('email', 'Email', <Input type="email" />, false)}</> });
    const openAssortment = async () => {
        try {
            const response = await api.read('products', { branch_id: branchId, include_catalog: 1 });
            const candidates = rows(response.data);
            open({ title: 'Đưa hàng dùng chung vào chi nhánh', path: 'assortments', initial: { branch_id: branchId, price_minor: '0' }, fields: (form) => <>
                {field('sku_id', 'Hàng hóa trong danh mục chung', <Select showSearch optionFilterProp="label" onChange={(value) => { const selected = candidates.find((item) => uuid(item) === value); form.setFieldsValue({ price_minor: selected?.price_minor ?? '0', expected_version: selected?.is_listed ? selected.price_version : undefined }); }} options={candidates.map((item) => ({ value: uuid(item), label: `${item.sku} · ${item.name}${item.is_listed ? ' · đang bán' : ''}` }))} />)}
                <Form.Item name="expected_version" hidden><Input /></Form.Item>
                {field('price_minor', 'Giá bán tại chi nhánh (đồng)', moneyInput())}<Alert type="info" showIcon message="Giữ nguyên master SKU" description="Chỉ thiết lập danh mục kinh doanh và giá của chi nhánh, không tạo hàng hóa hoặc tồn kho trùng." />
            </> });
        } catch (failure) { message.error(failure.message); }
    };

    const rowActions = (record) => {
        if (section === 'shifts') return <Space wrap>{record.status === 'open' && can('retail.shift.manage') && <Button size="small" onClick={() => open({ title: 'Đóng ca và kiểm đếm', path: `shifts/${uuid(record)}/close`, initial: { expected_version: record.version, counted_minor: '0' }, fields: () => <><Alert type="info" showIcon message="Nhập đúng tiền kiểm đếm thực tế" description="Chênh lệch được lưu để đối soát; số hệ thống không bị sửa để khớp số đếm." style={{ marginBottom: 16 }} />{field('counted_minor', 'Tiền mặt thực đếm (đồng)', moneyInput())}</> })}>Đóng ca</Button>}{record.status === 'closed' && can('retail.shift.manage') && <Button size="small" onClick={() => execute(`shifts/${uuid(record)}/reconcile`, { expected_version: record.version })}>Đối soát</Button>}</Space>;
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
        if (section === 'shifts') return [{ title: 'Ca', render: (_, record) => record.code ?? uuid(record).slice(-8) }, { title: 'Quầy', render: (_, record) => record.terminal?.name ?? record.terminal_name ?? terminals.find((item) => [uuid(item), String(item.database_id)].includes(String(record.terminal_id)))?.name ?? '—' }, { title: 'Đầu ca', dataIndex: 'opening_minor', render: formatMoney }, { title: 'Hệ thống', render: (_, record) => formatMoney(record.expected_minor ?? record.expected_cash_minor) }, { title: 'Thực đếm', dataIndex: 'counted_minor', render: (value) => value == null ? '—' : formatMoney(value) }, { title: 'Chênh lệch', dataIndex: 'variance_minor', render: (value) => value == null ? '—' : formatMoney(value) }, { title: 'Trạng thái', dataIndex: 'status', render: status }, { title: 'Thao tác', render: rowActions }];
        return [{ title: section === 'returns' ? 'Đơn hàng' : 'Mã chứng từ', render: (_, record) => <Button type="link" onClick={() => setDetail(record)}>{record.code ?? record.number ?? uuid(record).slice(-8)}</Button> }, ...(section === 'transfers' ? [{ title: 'Nguồn → Đích', render: (_, record) => `${record.source_branch?.name ?? branches.find((item) => uuid(item) === record.source_branch_id)?.name ?? 'Kho nguồn'} → ${record.destination_branch?.name ?? branches.find((item) => uuid(item) === record.destination_branch_id)?.name ?? 'Kho nhận'}` }] : []), ...(section === 'returns' ? [{ title: 'Giá trị đơn', render: (_, record) => formatMoney(saleAmount(record)) }] : []), { title: 'Trạng thái', dataIndex: 'status', render: status }, { title: 'Thời gian', dataIndex: 'created_at', render: formatDate }, { title: 'Thao tác', render: rowActions }];
    })();

    const actions = (() => {
        if (section === 'products' && can('retail.product.manage')) return <Space>{can('retail.price.manage') && <Button onClick={openAssortment}>Chọn từ danh mục chung</Button>}<Button icon={<PlusOutlined />} type="primary" onClick={openProduct}>Thêm hàng hóa</Button></Space>;
        if (section === 'branches' && can('retail.branch.manage')) return <Button icon={<PlusOutlined />} type="primary" onClick={openBranch}>Thêm chi nhánh</Button>;
        if (section === 'inventory' && can('retail.inventory.view') && permissions.has('inventory.receipt.manage')) return <Button icon={<PlusOutlined />} type="primary" onClick={openReceipt}>Nhập hàng</Button>;
        if (section === 'transfers' && can('retail.transfer.manage')) return <Button icon={<PlusOutlined />} type="primary" onClick={openTransfer}>Tạo điều chuyển</Button>;
        if (section === 'stocktakes' && can('retail.stocktake.manage')) return <Button icon={<PlusOutlined />} type="primary" onClick={() => open({ title: 'Mở đợt kiểm kê', path: 'stocktakes', initial: { branch_id: branchId }, fields: () => <><Alert type="warning" showIcon message="Phạm vi kiểm kê sẽ tạm dừng chuyển động kho" description="Kết thúc các đơn giữ hàng và đồng bộ gateway trước khi chốt số đếm." style={{ marginBottom: 16 }} />{field('note', 'Ghi chú', <Input.TextArea rows={3} />, false)}</> })}>Mở kiểm kê</Button>;
        if (section === 'shifts' && can('retail.shift.manage')) return <Space><Button onClick={() => open({ title: 'Thêm quầy bán', path: 'terminals', initial: { branch_id: branchId }, fields: () => <>{field('code', 'Mã quầy')}{field('name', 'Tên quầy')}</> })}>Thêm quầy</Button><Button icon={<PlusOutlined />} type="primary" onClick={openShift}>Mở ca</Button></Space>;
        if (section === 'customers' && can('retail.customer.manage')) return <Button icon={<PlusOutlined />} type="primary" onClick={openCustomer}>Thêm khách hàng</Button>;
        return null;
    })();

    const renderDashboard = () => {
        const metrics = [
            ['Doanh thu thuần', data?.net_sales_minor ?? data?.revenue_minor ?? data?.sales_minor, true],
            ['Đơn hoàn tất', data?.completed_sales ?? data?.sale_count ?? data?.sales_count, false],
            ['Ca đang mở', data?.open_shifts ?? data?.active_shifts, false],
            ['Giá trị hoàn', data?.refunds_minor ?? data?.refund_minor ?? data?.returns_minor, true],
        ];
        return <><Row gutter={[16, 16]}>{metrics.map(([label, value, money]) => <Col xs={24} sm={12} xl={6} key={label}><Card className="retail-metric"><Statistic title={label} value={value ?? '—'} formatter={() => value == null ? '—' : money ? formatMoney(value) : String(value)} /></Card></Col>)}</Row>
            <Row gutter={[16, 16]} style={{ marginTop: 18 }}><Col xs={24} lg={15}><Card title="Giao dịch gần đây"><Table rowKey={uuid} dataSource={rows(data?.recent_sales ?? data?.sales)} columns={[{ title: 'Đơn', render: (_, record) => record.code ?? uuid(record).slice(-8) }, { title: 'Giá trị', render: (_, record) => formatMoney(saleAmount(record)) }, { title: 'Trạng thái', dataIndex: 'status', render: status }, { title: 'Thời gian', dataIndex: 'created_at', render: formatDate }]} pagination={false} locale={{ emptyText: 'Chưa có giao dịch trong chi nhánh.' }} /></Card></Col><Col xs={24} lg={9}><Card title="Bắt đầu vận hành"><Space direction="vertical" style={{ width: '100%' }}><Paragraph>Danh mục và giá dùng chung; tồn và ca bán được kiểm soát theo từng chi nhánh.</Paragraph><Button block type="primary" onClick={() => navigate('/modules/retail-pos/pos')}>Mở màn hình bán hàng</Button><Button block onClick={() => navigate('/modules/retail-pos/shifts')}>Quản lý ca & tiền</Button><Button block onClick={() => navigate('/modules/retail-pos/inventory')}>Kiểm tra tồn kho</Button></Space></Card></Col></Row></>;
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

    return <div className="retail-workspace">
        <div className="retail-workspace-header"><div><span className="retail-eyebrow">AIO RETAIL</span><Title level={3}>Quản lý siêu thị & cửa hàng</Title><Text type="secondary">Bán hàng tại quầy và điều hành chuỗi chi nhánh</Text></div><Space wrap><Select prefix={<ShopOutlined />} value={branchId} options={branchOptions} onChange={setBranchId} disabled={posBusy} placeholder="Chọn chi nhánh" style={{ minWidth: 240 }} /><Button icon={<ReloadOutlined />} loading={loading} onClick={reload}>Tải lại</Button></Space></div>
        <Tabs className="retail-workspace-tabs" activeKey={section} onChange={(key) => navigate(`/modules/retail-pos/${key}`)} items={visibleSections.map(([key, label]) => ({ key, label }))} />
        {pending.length > 0 && <Alert type="warning" showIcon style={{ marginBottom: 18 }} message={`${pending.length} giao dịch cần kiểm tra kết quả`} description={<Space direction="vertical">{pending.map((entry) => <Space wrap key={entry.idempotencyKey}><Text>{entry.path} · {formatDate(entry.createdAt)}</Text><Button size="small" onClick={() => recover(entry)}>Kiểm tra kết quả</Button></Space>)}</Space>} />}
        {error && <Alert type="error" showIcon message="Không tải được dữ liệu" description={error} action={<Button size="small" onClick={reload}>Thử lại</Button>} style={{ marginBottom: 18 }} />}
        {bootstrap && !branches.length ? <Card className="retail-onboarding"><ShopOutlined /><Title level={3}>Khởi tạo cửa hàng đầu tiên</Title><Paragraph>Tạo chuỗi, pháp nhân, danh mục hàng dùng chung và kho cửa hàng để bắt đầu bán hàng.</Paragraph><Button type="primary" size="large" onClick={openSetup} disabled={!can('retail.setup.manage') && !can('retail.branch.manage')}>Khởi tạo vận hành</Button></Card>
            : section === 'pos' ? <RetailSalePanel products={products} api={api} branch={branch} terminals={terminals} shifts={shifts} reload={reload} identity={identity} onSearch={searchProducts} loading={loading} canSell={can('retail.sale.create') && can('retail.payment.collect')} canConfirmTransfer={can('retail.settings.manage')} onScopeLock={setPosBusy} />
                : section === 'dashboard' ? renderDashboard()
                    : section === 'reports' ? renderReports()
                        : section === 'settings' ? <Card title="Cấu hình và tích hợp"><Descriptions bordered column={1} items={[{ key: 'branch', label: 'Chi nhánh đang vận hành', children: branch?.name ?? '—' }, { key: 'currency', label: 'Tiền tệ', children: data?.currency ?? 'VND' }, { key: 'mode', label: 'Chế độ giao dịch', children: <Tag color="blue">Online · có phục hồi định danh</Tag> }, { key: 'stock', label: 'Nguồn tồn chính thức', children: 'Inventory' }, { key: 'modules', label: 'Capability', children: Object.entries(bootstrap?.capabilities ?? {}).map(([key, value]) => <Tag key={key} color={value ? 'green' : 'default'}>{key}: {value ? 'Sẵn sàng' : 'Chưa sẵn sàng'}</Tag>) }]} /><Alert type="info" showIcon message="Phạm vi offline" description="Giữ giỏ và phục hồi request không phải bán offline. Chỉ vận hành mất WAN khi gateway và quota đã được cấp, kiểm chứng." style={{ marginTop: 18 }} /></Card>
                            : section === 'purchasing' ? <RetailPurchasingPanel key={branchId} branch={branch} bootstrap={bootstrap} callAdminApi={callAdminApi} permissions={permissions} identity={identity} onCommand={() => setPending(pendingCommands(identity))} />
                                : <Card title={sections.find(([key]) => key === section)?.[1] ?? 'Vận hành'} extra={actions} loading={loading && data === null}>
                                    {section === 'products' && <Input.Search value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Tìm SKU, mã vạch hoặc tên hàng" onSearch={reload} allowClear style={{ maxWidth: 420, marginBottom: 18 }} />}
                                    <Table rowKey={uuid} dataSource={rows(data)} columns={columns} loading={loading} scroll={{ x: 'max-content' }} pagination={{ pageSize: 20, showSizeChanger: true, showTotal: (total) => `${total} bản ghi` }} locale={{ emptyText: 'Chưa có dữ liệu phù hợp.' }} />
                                    {section === 'returns' && <><Title level={5} style={{ marginTop: 24 }}>Phiếu trả hàng và tiền hoàn</Title><Table rowKey={uuid} dataSource={returns} pagination={{ pageSize: 10 }} columns={[
                                        { title: 'Phiếu', render: (_, record) => uuid(record).slice(-8) }, { title: 'Số tiền', dataIndex: 'amount_minor', render: formatMoney }, { title: 'Trạng thái hoàn', dataIndex: 'refund_status', render: status }, { title: 'Ngày', dataIndex: 'created_at', render: formatDate },
                                        { title: 'Thao tác', render: (_, record) => record.refund_status === 'pending' && can('retail.payment.collect') && <Button size="small" onClick={() => open({ title: 'Xác nhận hoàn tiền mặt', path: `returns/${uuid(record)}/refund`, initial: { method: 'cash' }, fields: () => <><Alert type="warning" showIcon message={`Hoàn ${formatMoney(record.amount_minor)} cho khách`} description="Chỉ xác nhận khi đã giao tiền thực tế. Giao dịch được ghi vào ca được chọn." style={{ marginBottom: 16 }} />{field('shift_id', 'Ca chi tiền', <Select options={shifts.filter((item) => item.status === 'open').map((item) => ({ value: uuid(item), label: item.code ?? `Ca ${uuid(item).slice(-6)}` }))} />)}</> })}>Hoàn tiền</Button> },
                                    ]} /></>}
                                </Card>}
        {section === 'settings' && can('retail.integration.manage') && <RetailIntegrationPanel key={branchId} branch={branch} callAdminApi={callAdminApi} identity={identity} onCommand={() => setPending(pendingCommands(identity))} />}
        <ResourceDrawer config={drawer} busy={busy} error={actionError} onClose={() => setDrawer(null)} onSubmit={(values) => {
            const payload = { ...(drawer.initial ?? {}), ...values };
            if (drawer.path?.endsWith('/returns') || drawer.path?.endsWith('/receive')) payload.lines = (payload.lines ?? []).filter((line) => quantityMicros(line.quantity ?? '0') > 0n);
            execute(drawer.path, payload, drawer.success ?? 'Đã ghi nhận thao tác.', drawer.method);
        }} />
        <Drawer title="Chi tiết chứng từ" width={720} open={Boolean(detail)} onClose={() => setDetail(null)}><Descriptions bordered column={1} items={[{ key: 'code', label: 'Mã', children: detail?.code ?? uuid(detail) }, { key: 'status', label: 'Trạng thái', children: status(detail?.status) }, { key: 'date', label: 'Tạo lúc', children: formatDate(detail?.created_at) }]} /><Table style={{ marginTop: 18 }} rowKey={(line) => uuid(line) || line.sku_id} dataSource={detail?.lines ?? []} pagination={false} columns={[{ title: 'Hàng hóa', render: (_, line) => line.name ?? line.sku ?? line.sku_id }, { title: 'Số lượng', dataIndex: 'quantity', render: formatQuantity }, { title: 'Giá trị', render: (_, line) => line.total_minor ? formatMoney(line.total_minor) : '—' }]} /></Drawer>
    </div>;
}

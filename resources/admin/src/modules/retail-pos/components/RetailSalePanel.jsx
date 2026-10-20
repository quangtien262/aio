import { useEffect, useMemo, useRef, useState } from 'react';
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
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import CustomerPicker from '../../customers/components/CustomerPicker';
import { addQuantity, estimateLine, formatDate, formatMoney, formatQuantity, minor, quantityMicros, rows, saleAmount, uuid } from '../utils/retailFormat';

const { Text, Title } = Typography;

export default function RetailSalePanel({ products, api, branch, terminals, shifts, reload, identity, onSearch, loading, canSell, canConfirmTransfer, canViewCustomers, canCreateCustomers, onScopeLock }) {
    const { message } = App.useApp();
    const scanRef = useRef(null);
    const storageKey = `aio.retail.cart.${identity}.${uuid(branch)}`;
    const quoteKey = `${storageKey}.quote`;
    const [cart, setCart] = useState([]);
    const [customer, setCustomer] = useState(null);
    const [customerContext, setCustomerContext] = useState({ mode: 'legacy', ready: true });
    useEffect(() => {
        let cancelled = false;
        setCustomer(null);
        if (branch && canViewCustomers) api.read('customer-context', { branch_id: uuid(branch) }).then((response) => { if (!cancelled) setCustomerContext(response.data); }).catch(() => { if (!cancelled) setCustomerContext({ mode: 'unavailable', ready: false }); });
        return () => { cancelled = true; };
    }, [storageKey, canViewCustomers, api]);
    const [hydratedKey, setHydratedKey] = useState(null);
    const [terminalId, setTerminalId] = useState(null);
    const [shiftId, setShiftId] = useState(null);
    const [scan, setScan] = useState('');
    const [sale, setSale] = useState(null);
    const [paymentOpen, setPaymentOpen] = useState(false);
    const [working, setWorking] = useState(false);
    const [paymentError, setPaymentError] = useState(null);
    const [receipt, setReceipt] = useState(null);
    const [paymentForm] = Form.useForm();
    useEffect(() => { onScopeLock?.(working || Boolean(sale)); return () => onScopeLock?.(false); }, [working, sale, onScopeLock]);

    useEffect(() => {
        try {
            const saved = JSON.parse(window.localStorage.getItem(storageKey) ?? '[]');
            setCart(Array.isArray(saved) ? saved.filter((item) => item.sku_id && /^\d+(?:\.\d{1,6})?$/.test(String(item.quantity))) : []);
        } catch { setCart([]); }
        setHydratedKey(storageKey);
        setSale(null);
        setPaymentOpen(false);
        setTerminalId(null);
        setShiftId(null);
    }, [storageKey]);
    useEffect(() => {
        let cancelled = false;
        const savedId = window.localStorage.getItem(quoteKey);
        if (savedId && branch) api.read('sales', { branch_id: uuid(branch) }).then((result) => {
            if (cancelled) return;
            const saved = rows(result.data).find((item) => uuid(item) === savedId);
            if (saved && ['priced', 'awaiting_payment', 'draft'].includes(saved.status)) { setSale(saved); paymentForm.setFieldsValue({ method: 'cash', tendered_minor: saleAmount(saved) }); }
            else if (saved?.status === 'completed') { setCart([]); window.localStorage.removeItem(quoteKey); }
            else if (saved) window.localStorage.removeItem(quoteKey);
        }).catch(() => { /* Recovery banner and transaction list remain available. */ });
        return () => { cancelled = true; };
    }, [quoteKey, api]);
    useEffect(() => {
        if (sale) window.localStorage.setItem(quoteKey, uuid(sale));
    }, [sale, quoteKey]);
    useEffect(() => {
        const handler = (event) => {
            const { entry, resource } = event.detail ?? {};
            if (entry?.identity !== identity || entry?.branchId !== uuid(branch)) return;
            if (entry.path.endsWith('/complete') && resource?.status === 'completed') {
                setSale(null); setPaymentOpen(false); setCart([]); window.localStorage.removeItem(quoteKey);
            } else if (entry.path === 'sales' && resource && ['priced', 'awaiting_payment'].includes(resource.status)) {
                setSale(resource); setPaymentOpen(true); paymentForm.setFieldsValue({ method: 'cash', tendered_minor: saleAmount(resource) });
            }
        };
        window.addEventListener('retail-command-recovered', handler);
        return () => window.removeEventListener('retail-command-recovered', handler);
    }, [identity, branch, quoteKey, paymentForm]);
    useEffect(() => {
        if (hydratedKey === storageKey) {
            try { window.localStorage.setItem(storageKey, JSON.stringify(cart)); } catch { /* Cart remains in memory. */ }
        }
    }, [cart, storageKey, hydratedKey]);
    useEffect(() => {
        if (sale) {
            const quotedTerminal = terminals.find((item) => [uuid(item), String(item.database_id)].includes(String(sale.terminal_id)));
            const quotedShift = shifts.find((item) => [uuid(item), String(item.database_id)].includes(String(sale.shift_id)));
            if (quotedTerminal) setTerminalId(uuid(quotedTerminal));
            if (quotedShift) setShiftId(uuid(quotedShift));
        } else if (!terminalId && terminals.length) setTerminalId(uuid(terminals[0]));
    }, [terminals, shifts, terminalId, sale]);
    const activeShifts = shifts.filter((item) => item.status === 'open' && (!terminalId || String(item.terminal_id) === terminalId || uuid(item.terminal) === terminalId));
    useEffect(() => {
        if (!sale && !activeShifts.some((item) => uuid(item) === shiftId)) setShiftId(activeShifts[0] ? uuid(activeShifts[0]) : null);
    }, [activeShifts.map((item) => uuid(item)).join(','), shiftId, sale]);
    const estimate = useMemo(() => cart.reduce((sum, line) => {
        try { return sum + estimateLine(line.quantity, line.price_minor); } catch { return sum; }
    }, 0n), [cart]);

    const add = (product) => {
        if (!canSell || sale) return;
        const id = uuid(product);
        const increment = String(product.quantity_multiplier ?? '1');
        setCart((current) => {
            const existing = current.find((line) => line.sku_id === id);
            return existing ? current.map((line) => line.sku_id === id ? { ...line, quantity: addQuantity(line.quantity, increment) } : line)
                : [...current, { sku_id: id, sku: product.sku, name: product.name, base_unit: product.base_unit, quantity: increment, price_minor: String(product.price_minor ?? '0') }];
        });
        setScan('');
        scanRef.current?.focus();
    };
    const scanProduct = async (value) => {
        if (!value.trim()) return;
        try {
            const result = await onSearch(value.trim());
            const matches = result ?? products;
            const exact = matches.filter((item) => item.sku === value.trim() || item.barcode === value.trim()
                || (item.barcodes ?? []).some((code) => (typeof code === 'string' ? code : code.barcode ?? code.code) === value.trim()));
            if (exact.length === 1) add(exact[0]);
            else if (matches.length === 1) add(matches[0]);
            else if (!matches.length) message.warning('Không tìm thấy hàng hóa. Kiểm tra lại mã hoặc tạo hàng trước khi bán.');
        } catch (error) { message.error(error.message); }
    };
    const startPayment = async () => {
        if (sale) { setPaymentOpen(true); return; }
        if (!branch || !terminalId || !shiftId) return message.warning('Chọn quầy và mở ca bán hàng trước khi thu tiền.');
        if (!cart.length) return;
        try {
            cart.forEach((line) => { if (quantityMicros(line.quantity) <= 0n) throw new Error('Số lượng phải lớn hơn 0.'); });
            setWorking(true);
            setPaymentError(null);
            const result = await api.command('sales', {
                branch_id: uuid(branch), terminal_id: terminalId, shift_id: shiftId,
                ...(customer ? { [customerContext.mode === 'shared' ? 'shared_customer_id' : 'customer_id']: customer.value } : {}),
                lines: cart.map(({ sku_id, quantity, packaging_id }) => ({ sku_id, quantity, ...(packaging_id ? { packaging_id } : {}) })),
            });
            const quoted = result.data;
            setSale(quoted);
            setPaymentOpen(true);
            paymentForm.setFieldsValue({ method: 'cash', tendered_minor: saleAmount(quoted) });
        } catch (error) { message.error(error.message); }
        finally { setWorking(false); }
    };
    const finishPayment = async () => {
        try {
            const input = await paymentForm.validateFields();
            const amount = minor(saleAmount(sale));
            const cashPart = input.method === 'mixed' ? minor(input.cash_minor) : amount;
            if (input.method === 'mixed' && (cashPart <= 0n || cashPart >= amount)) throw new Error('Phần tiền mặt phải lớn hơn 0 và nhỏ hơn tổng đơn.');
            if (input.method !== 'manual_transfer' && minor(input.tendered_minor) < cashPart) throw new Error('Tiền khách đưa chưa đủ.');
            const tenders = input.method === 'mixed'
                ? [{ method: 'cash', amount_minor: cashPart.toString(), tendered_minor: String(input.tendered_minor) }, { method: 'manual_transfer', amount_minor: (amount - cashPart).toString(), reference: input.reference }]
                : [{ method: input.method, amount_minor: amount.toString(), ...(input.method === 'cash' ? { tendered_minor: String(input.tendered_minor) } : { reference: input.reference }) }];
            setWorking(true);
            setPaymentError(null);
            const result = await api.command(`sales/${encodeURIComponent(uuid(sale))}/complete`, {
                expected_version: sale.version,
                tenders,
            });
            setSale(null);
            setPaymentOpen(false);
            setCart([]);
            setCustomer(null);
            setReceipt(result.data);
            window.localStorage.removeItem(quoteKey);
            message.success(`Đã hoàn tất đơn ${result.data?.code ?? result.data?.number ?? uuid(result.data).slice(-8)}.`);
            await reload();
            scanRef.current?.focus();
        } catch (error) {
            if (!error.errorFields) setPaymentError(error.message);
        } finally { setWorking(false); }
    };

    const cartColumns = [
        { title: 'Hàng hóa', key: 'item', render: (_, line) => <><Text strong>{line.name}</Text><div className="retail-muted">{line.sku} · {line.base_unit}</div></> },
        { title: 'Số lượng', width: 116, render: (_, line) => <InputNumber stringMode min="0.000001" precision={6} disabled={Boolean(sale)} value={line.quantity} style={{ width: 100 }} onChange={(value) => value !== null && setCart((current) => current.map((item) => item.sku_id === line.sku_id ? { ...item, quantity: String(value) } : item))} /> },
        { title: 'Tạm tính', width: 125, align: 'right', render: (_, line) => { try { return formatMoney(estimateLine(line.quantity, line.price_minor)); } catch { return '—'; } } },
        { width: 42, render: (_, line) => <Button type="text" danger disabled={Boolean(sale)} onClick={() => setCart((current) => current.filter((item) => item.sku_id !== line.sku_id))}>×</Button> },
    ];
    return <>
        <Row gutter={[18, 18]}>
            <Col xs={24} xl={12}>
                <Card className="retail-scan-card">
                    <Input.Search ref={scanRef} size="large" autoFocus placeholder="Quét mã vạch hoặc nhập SKU, tên hàng…" value={scan} onChange={(event) => setScan(event.target.value)} onSearch={scanProduct} enterButton="Tìm hàng" loading={loading} />
                    <div className="retail-muted retail-scan-hint">Quét mã rồi Enter để thêm hàng. Giá cuối cùng được máy chủ xác nhận trước khi thu tiền.</div>
                </Card>
                <div className="retail-product-grid">
                    {products.map((product) => <button type="button" className="retail-product-card" key={uuid(product)} onClick={() => add(product)} disabled={!canSell || Boolean(sale)}>
                        <span className="retail-product-initial">{String(product.name ?? '').slice(0, 1)}</span>
                        <span className="retail-product-name">{product.name}</span>
                        <span className="retail-muted">{product.sku}</span>
                        <strong>{formatMoney(product.price_minor)}</strong>
                        <span className="retail-muted">Có thể bán: {formatQuantity(product.available)} {product.base_unit}</span>
                    </button>)}
                </div>
                {!products.length && <Card><Empty description="Chưa có hàng phù hợp trong chi nhánh." /></Card>}
            </Col>
            <Col xs={24} xl={12}>
                <Card title={<Space><span>Giỏ hàng</span><Tag>{cart.length} mặt hàng</Tag></Space>} extra={<Popconfirm title="Xóa các mặt hàng chưa thanh toán?" onConfirm={() => setCart([])}><Button type="text" disabled={!cart.length || Boolean(sale)}>Làm mới</Button></Popconfirm>}>
                    <Row gutter={12}>
                        <Col span={12}><div className="retail-field-label">Quầy bán</div><Select value={terminalId} disabled={Boolean(sale) || working} onChange={(value) => { setTerminalId(value); setShiftId(null); }} options={terminals.map((item) => ({ value: uuid(item), label: item.name ?? item.code }))} placeholder="Chọn quầy" style={{ width: '100%' }} /></Col>
                        <Col span={12}><div className="retail-field-label">Ca đang mở</div><Select value={shiftId} disabled={Boolean(sale) || working} onChange={setShiftId} options={activeShifts.map((item) => ({ value: uuid(item), label: item.code ?? `Ca ${uuid(item).slice(-6)}` }))} placeholder="Chưa có ca mở" style={{ width: '100%' }} /></Col>
                    </Row>
                    {canViewCustomers && <div style={{ marginTop: 14 }}><div className="retail-field-label">Khách hàng · {customerContext.mode === 'shared' ? 'Dùng chung' : 'Theo chuỗi'}</div><CustomerPicker key={`${storageKey}.${customerContext.mode}.${customerContext.book_id ?? ''}`} value={sale?.buyer_snapshot?.id ?? customer?.value ?? null} selectedOption={sale?.buyer_snapshot ? { value: sale.buyer_snapshot.id, label: sale.buyer_snapshot.name } : customer} disabled={Boolean(sale) || working || !customerContext.ready} onChange={(value, option) => setCustomer(value ? { ...option, value } : null)} loadOptions={async (query) => { const response = await api.read('customers', { branch_id: uuid(branch), query }); return rows(response.data).map((item) => ({ ...item, value: uuid(item), label: [item.name, item.code, item.phone].filter(Boolean).join(' · ') })); }} createCustomer={canCreateCustomers && customerContext.ready ? async (values) => { const response = await api.command('customers', { ...values, branch_id: uuid(branch) }); return { ...response.data, value: uuid(response.data), label: response.data.name }; } : undefined} /><Text type="secondary">Có thể để trống để bán khách lẻ. Khách hàng được khóa khi tạo bản tính giá.</Text>{!customerContext.ready && <Alert type="warning" showIcon message="Nguồn khách hàng chưa sẵn sàng. Không tự chuyển về dữ liệu cũ." />}</div>}
                    <Table className="retail-cart-table" rowKey="sku_id" columns={cartColumns} dataSource={cart} pagination={false} size="small" locale={{ emptyText: 'Quét hàng để bắt đầu một giao dịch.' }} scroll={{ x: 440 }} />
                    <div className="retail-checkout-total"><div><Text type="secondary">Tạm tính</Text><Title level={2}>{formatMoney(estimate)}</Title></div><Button type="primary" size="large" disabled={!cart.length || !canSell || !shiftId} loading={working} onClick={startPayment}>{sale ? 'Tiếp tục thanh toán' : 'Thanh toán'}</Button></div>
                    {!shiftId && <Alert type="info" showIcon message="Chưa có ca đang mở" description="Mở ca tại mục Ca & tiền trước khi bắt đầu thu tiền." />}
                </Card>
            </Col>
        </Row>
        <Drawer title="Xác nhận thanh toán" open={paymentOpen} width={520} onClose={() => !working && setPaymentOpen(false)} maskClosable={false} footer={<Space direction="vertical" style={{ width: '100%' }}><Button type="primary" size="large" block loading={working} onClick={finishPayment}>Xác nhận đã thu tiền</Button><Popconfirm title="Hủy bản tính giá chưa thu tiền?" description="Các mặt hàng được giữ lại trong giỏ để chỉnh sửa." onConfirm={async () => { try { setWorking(true); await api.command(`sales/${uuid(sale)}/cancel`, { expected_version: sale.version }); setSale(null); setPaymentOpen(false); window.localStorage.removeItem(quoteKey); await reload(); } catch (failure) { setPaymentError(failure.message); } finally { setWorking(false); } }}><Button block danger disabled={working}>Hủy bản tính giá</Button></Popconfirm></Space>}>
            <Descriptions column={1} bordered items={[
                { key: 'code', label: 'Đơn hàng', children: sale?.code ?? sale?.number ?? uuid(sale) },
                { key: 'total', label: 'Cần thanh toán', children: <Title level={3} style={{ margin: 0 }}>{formatMoney(saleAmount(sale))}</Title> },
                ...(sale?.quote_expires_at ? [{ key: 'expiry', label: 'Báo giá có hiệu lực đến', children: formatDate(sale.quote_expires_at) }] : []),
            ]} />
            {paymentError && <Alert style={{ marginTop: 16 }} showIcon type="error" message="Chưa hoàn tất giao dịch" description={paymentError} />}
            <Typography.Paragraph type="secondary" style={{ marginTop: 12 }}>Khi báo giá hết hạn hoặc bảng giá thay đổi, hủy bản tính giá rồi chọn Thanh toán để xác nhận giá mới. Các mặt hàng vẫn được giữ trong giỏ.</Typography.Paragraph>
            <Form form={paymentForm} layout="vertical" style={{ marginTop: 24 }}>
                <Form.Item name="method" label="Phương thức" rules={[{ required: true }]}><Select options={[{ value: 'cash', label: 'Tiền mặt' }, ...(canConfirmTransfer ? [{ value: 'manual_transfer', label: 'Chuyển khoản — quản lý xác nhận' }, { value: 'mixed', label: 'Tiền mặt + chuyển khoản' }] : [])]} /></Form.Item>
                <Form.Item noStyle shouldUpdate>{({ getFieldValue }) => {
                    const method = getFieldValue('method');
                    let transferPart;
                    let change;
                    try { transferPart = formatMoney(minor(saleAmount(sale)) - minor(getFieldValue('cash_minor') ?? '0')); } catch { transferPart = '—'; }
                    try {
                        const due = method === 'mixed' ? minor(getFieldValue('cash_minor') ?? '0') : minor(saleAmount(sale));
                        const excess = minor(getFieldValue('tendered_minor') ?? '0') - due;
                        change = excess < 0n ? `Còn thiếu: ${formatMoney(-excess)}` : `Tiền thừa trả khách: ${formatMoney(excess)}`;
                    } catch { change = 'Kiểm tra lại số tiền khách đưa.'; }
                    return <>
                        {method === 'mixed' && <><Form.Item name="cash_minor" label="Số tiền thanh toán bằng tiền mặt" rules={[{ required: true }]}><InputNumber stringMode min="1" precision={0} style={{ width: '100%' }} /></Form.Item><Alert type="info" message={`Phần chuyển khoản: ${transferPart}`} style={{ marginBottom: 16 }} /></>}
                        {method !== 'manual_transfer' && <><Form.Item name="tendered_minor" label="Tiền mặt khách đưa (đồng)" rules={[{ required: true }]}><InputNumber stringMode min="0" precision={0} size="large" style={{ width: '100%' }} /></Form.Item><Alert type="info" message={change} style={{ marginBottom: 16 }} /></>}
                        {method !== 'cash' && <><Form.Item name="reference" label="Mã giao dịch ngân hàng / bằng chứng đối soát" rules={[{ required: true }]}><Input /></Form.Item><Alert type="warning" showIcon message="Xác nhận giao dịch thực tế" description="Chỉ xác nhận sau khi kiểm tra tiền đã nhận trên ngân hàng. Mã QR không phải bằng chứng thanh toán." /></>}
                    </>;
                }}</Form.Item>
            </Form>
            <Text type="secondary">Lỗi mạng có thể khiến kết quả chưa rõ. Giữ nguyên giao dịch và kiểm tra mục Giao dịch cần đối soát trước khi thu lại.</Text>
        </Drawer>
        <Drawer title="Giao dịch đã hoàn tất" open={Boolean(receipt)} onClose={() => setReceipt(null)} width={430} footer={<Button type="primary" block onClick={() => window.print()}>In bill bán hàng</Button>}>
            <div className="retail-print-receipt"><h2>{branch?.name}</h2><div>BILL BÁN HÀNG</div><div>{receipt?.code ?? uuid(receipt)}</div><div>{formatDate(receipt?.completed_at)}</div><hr />
                {(receipt?.lines ?? []).map((line) => <div className="retail-receipt-line" key={uuid(line)}><div>{line.name}<small>{formatQuantity(line.quantity)} {line.unit} × {formatMoney(line.price_minor)}</small></div><strong>{formatMoney(line.total_minor)}</strong></div>)}
                <hr /><div className="retail-receipt-line"><strong>TỔNG CỘNG</strong><strong>{formatMoney(saleAmount(receipt))}</strong></div>
                {(receipt?.tenders ?? []).map((tender) => <div key={uuid(tender)}><div className="retail-receipt-line"><span>{tender.method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản'}</span><span>{formatMoney(tender.amount_minor)}</span></div>{tender.method === 'cash' && <><div className="retail-receipt-line"><span>Khách đưa</span><span>{formatMoney(tender.tendered_minor)}</span></div><div className="retail-receipt-line"><strong>Tiền thừa</strong><strong>{formatMoney(tender.change_minor)}</strong></div></>}</div>)}
                <p>Cảm ơn Quý khách!</p><small>Bill bán lẻ · trạng thái hóa đơn điện tử được quản lý riêng.</small>
            </div>
        </Drawer>
    </>;
}

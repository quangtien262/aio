import { useCallback, useEffect, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Checkbox from 'antd/es/checkbox';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import QRCode from 'antd/es/qr-code';
import Radio from 'antd/es/radio';
import Spin from 'antd/es/spin';
import Typography from 'antd/es/typography';
import { createIdempotencyKey } from '../api/fnbApi';
import useFnbResource from '../hooks/useFnbResource';
import { useFnbWorkspace } from '../state/FnbWorkspaceContext';
import { asArray, formatDateTime, formatMinorMoney } from '../utils/fnbFormat';
import { isZeroCharge, paymentMethodAllowed, paymentMethodLabel, rebalancePaymentParts, serverAmountDue } from '../utils/fnbPayment';

const { Text, Title } = Typography;
const moneyProps = {
    inputMode: 'numeric', precision: 0, controls: false, addonAfter: '₫', style: { width: '100%' },
    formatter: (value) => value === undefined || value === null || value === '' ? '' : String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.'),
    parser: (value) => (value ?? '').replace(/\./g, ''),
};

function TransferFields({ form, index, check, currency, operational, saving, maxAmount, requiresReference }) {
    const { api } = useFnbWorkspace();
    const amount = Form.useWatch(['payments', index, 'amount_minor'], form);
    const accountId = Form.useWatch(['payments', index, 'bank_account_id'], form);
    const [intent, setIntent] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [manual, setManual] = useState(false);
    const [retry, setRetry] = useState(0);
    const [expired, setExpired] = useState(false);
    const path = (name) => [index, name];
    const loader = useCallback(() => api.read(api.outletPath('bank-accounts/payment-options')), [api]);
    const resource = useFnbResource({ enabled: true, loader, deps: [check.id] });
    const accounts = asArray(resource.data?.accounts);
    const manualReference = manual || (!resource.loading && Boolean(resource.data || resource.error) && !accounts.length);
    const valid = intent && !expired && Number(intent.account_id) === Number(accountId)
        && Number(intent.amount_minor) === Number(amount) && Number(intent.check_id) === Number(check.id)
        && new Date(intent.expires_at).getTime() > Date.now();

    useEffect(() => {
        form.setFieldValue(['payments', index, 'qr_ready'], manualReference || Boolean(valid));
    }, [manualReference, valid, index, form]);

    useEffect(() => {
        if (accounts.length && !accounts.some((account) => String(account.id) === String(accountId))) {
            form.setFieldValue(['payments', index, 'bank_account_id'], (accounts.find((account) => account.is_default) ?? accounts[0]).id);
        }
    }, [resource.data, accountId, index, form]);

    useEffect(() => {
        let cancelled = false;
        setIntent(null);
        setError(null);
        setLoading(false);
        setExpired(false);
        form.setFieldValue(['payments', index, 'reference'], undefined);
        if (manualReference || !operational || !accountId || !Number.isSafeInteger(amount) || amount <= 0 || amount > maxAmount) return;
        const timer = window.setTimeout(async () => {
            setLoading(true);
            try {
                const result = await api.command(`checks/${check.id}/bank-intents`, { amount_minor: amount, account_id: Number(accountId) }, { idempotencyKey: createIdempotencyKey('bank-intent') });
                if (cancelled) return;
                setIntent(result.data);
                form.setFieldValue(['payments', index, 'reference'], result.data?.reference);
            } catch (exception) {
                if (!cancelled) setError(exception.message || 'Chưa tạo được QR chuyển khoản.');
            } finally { if (!cancelled) setLoading(false); }
        }, 300);
        return () => { cancelled = true; window.clearTimeout(timer); };
    }, [api, check.id, amount, accountId, manualReference, operational, maxAmount, retry, index, form]);

    useEffect(() => {
        if (!intent?.expires_at) return;
        const timer = window.setTimeout(() => setExpired(true), Math.max(0, new Date(intent.expires_at).getTime() - Date.now()));
        return () => window.clearTimeout(timer);
    }, [intent]);

    return <>
        <Form.Item hidden name={path('qr_ready')} valuePropName="checked"><Checkbox /></Form.Item>
        <section className="fnb-transfer-panel">
            <div className="fnb-transfer-panel-heading"><Text strong>{manualReference ? 'Giao dịch chuyển khoản' : 'QR chuyển khoản'}</Text>{loading || resource.loading ? <Spin size="small" /> : null}{accounts.length > 0 ? <Button type="link" size="small" aria-label={manual ? 'Quay lại thanh toán QR' : 'Nhập mã giao dịch thay QR'} onClick={() => setManual((value) => !value)}>{manual ? 'Dùng QR' : 'Nhập mã'}</Button> : null}</div>
            <Form.Item hidden={!manualReference} name={path('reference')} label="Mã giao dịch ngân hàng"
                extra={manualReference ? 'Mã do ngân hàng cấp sau khi chuyển tiền, dùng để đối soát.' : undefined}
                rules={[{ required: !manualReference || requiresReference, whitespace: true, message: 'Nhập mã giao dịch hoặc chờ QR chuyển khoản.' }, { validator: () => manualReference || valid ? Promise.resolve() : Promise.reject(new Error('Chờ QR hợp lệ hoặc chọn nhập mã giao dịch.')) }]}><Input maxLength={160} autoComplete="off" /></Form.Item>
            {resource.error ? <Alert type="warning" message="Chưa tải được tài khoản ngân hàng." action={<Button size="small" onClick={resource.reload}>Thử lại</Button>} /> : null}
            {!resource.loading && !resource.error && !accounts.length ? <Alert type="info" message="Chưa có tài khoản nhận tiền" description="Thêm tài khoản tại Cấu hình → Thiết lập → Ngân hàng & in phiếu." /> : null}
            <Form.Item hidden={accounts.length < 2} name={path('bank_account_id')} label="Tài khoản nhận tiền"><Radio.Group className="fnb-bank-account-radio" options={accounts.map((account) => ({ value: account.id, label: `${account.bank_name} · ${account.account_number_masked}${account.is_default ? ' · Mặc định' : ''}` }))} /></Form.Item>
            {!manualReference && accounts.length > 0 ? <>
                {error ? <Alert type="warning" message={error} /> : null}
                {valid ? <div className="fnb-transfer-details"><QRCode value={intent.qr_payload} size={144} bordered={false} /><div className="fnb-transfer-copy"><div className="fnb-transfer-bank-line"><Text strong>{intent.bank_name ?? intent.bank_code}</Text><Text strong>{formatMinorMoney(intent.amount_minor, currency)}</Text></div><Text>{intent.account_name}</Text><Text copyable>{intent.account_number}</Text><div><Text type="secondary">Nội dung: </Text><Text copyable>{intent.reference}</Text></div><Text type="secondary">Hiệu lực đến {formatDateTime(intent.expires_at, { timeZone: check.timezone_snapshot })}</Text></div></div>
                    : <div className="fnb-transfer-qr-empty">{loading ? <Text type="secondary">Đang tạo QR…</Text> : <Button disabled={!operational || saving || !amount || amount > maxAmount} onClick={() => setRetry((value) => value + 1)}>{expired ? 'Tạo QR mới' : 'Tạo lại QR chuyển khoản'}</Button>}</div>}
                <Text type="secondary" className="fnb-transfer-notice">Kiểm tra báo có trước khi xác nhận.</Text>
            </> : null}
        </section>
    </>;
}

export default function FnbPaymentModal({ open, form, methods, currency, saving, operational, onClose, onSubmit }) {
    const rows = Form.useWatch('payments', form) ?? [];
    const zeroCharge = isZeroCharge(open?.check);
    const amount = serverAmountDue(open?.check);
    const total = rows.reduce((sum, row) => sum + (Number(row?.amount_minor) || 0), 0);
    const difference = Number(amount) - total;
    const canSplit = !open?.check?.settlement_mode || open.check.settlement_mode === 'mixed';
    const selectedIds = rows.map((row) => row.payment_method_id);
    const waitingForQr = rows.some((row) => methods.find((method) => String(method.id) === String(row.payment_method_id))?.kind === 'transfer' && !row.qr_ready);
    const hasTransfer = rows.some((row) => methods.find((method) => String(method.id) === String(row.payment_method_id))?.kind === 'transfer');

    const syncCashTendered = (index, value) => {
        const path = ['payments', index, 'tendered_minor'];
        form.setFieldValue(path, value);
        // Amount dependencies may already be validating the old tendered value.
        // A new validation supersedes that result using the synchronized value.
        form.validateFields([path]).catch(() => {});
    };

    const updateAmount = (index, value) => {
        const next = rebalancePaymentParts(rows, index, value, Number(amount), methods);
        next.forEach((row, rowIndex) => {
            if (row.amount_minor === rows[rowIndex].amount_minor) return;
            if (rowIndex !== index) {
                const path = ['payments', rowIndex, 'amount_minor'];
                form.setFieldValue(path, row.amount_minor);
                form.validateFields([path]).catch(() => {});
            }
            if (row.tendered_minor !== rows[rowIndex].tendered_minor) syncCashTendered(rowIndex, row.tendered_minor);
            else if (methods.find((method) => String(method.id) === String(row.payment_method_id))?.kind === 'cash') {
                form.validateFields([['payments', rowIndex, 'tendered_minor']]).catch(() => {});
            }
        });
    };

    const changeMethods = (ids) => {
        // A locked single-tender bill keeps its original settlement contract.
        const selected = canSplit ? ids : ids.filter((id) => !selectedIds.includes(id)).slice(-1);
        const nextIds = selected.length ? selected : ids.slice(-1);
        const share = nextIds.length ? Math.floor(Number(amount) / nextIds.length) : 0;
        form.setFieldValue('payments', nextIds.map((id, index) => {
            const method = methods.find((candidate) => String(candidate.id) === String(id));
            const value = share + (index === nextIds.length - 1 ? Number(amount) - share * nextIds.length : 0);
            return { payment_method_id: id, amount_minor: value, tendered_minor: method?.kind === 'cash' ? value : undefined };
        }));
    };

    if (!open) return null;

    return <Modal open={Boolean(open)} title={open?.returnTable ? 'Thanh toán & trả bàn' : 'Thu tiền'} width={hasTransfer && !zeroCharge ? 860 : 620} centered className={`fnb-payment-modal fnb-payment-modal-compact ${hasTransfer ? 'has-transfer' : ''}`}
        onCancel={saving ? undefined : onClose} closable={!saving} maskClosable={!saving} keyboard={!saving} destroyOnHidden
        footer={<>
            {!zeroCharge && rows.length > 1 ? <div className={`fnb-payment-footer-balance ${difference !== 0 ? 'is-unbalanced' : ''}`}><span>Đã phân bổ <strong>{formatMinorMoney(total, currency)}</strong></span><span>{difference < 0 ? 'Vượt số cần thu' : 'Còn phân bổ'} <strong>{formatMinorMoney(Math.abs(difference), currency)}</strong></span></div> : null}
            <div className="fnb-payment-footer"><Button disabled={saving} onClick={onClose}>Hủy</Button><Button type="primary" loading={saving} disabled={!operational || (!zeroCharge && (!rows.length || difference !== 0 || Number(amount) <= 0 || waitingForQr))} onClick={() => form.submit()}>{open?.returnTable ? 'Xác nhận thanh toán & trả bàn' : zeroCharge ? 'Đóng bill 0 đồng' : 'Xác nhận thu tiền'}</Button></div>
        </>}>
        <div className="fnb-payment-summary"><div><Text type="secondary">Cần thanh toán</Text><Title level={2}>{formatMinorMoney(amount, currency)}</Title></div><Text type="secondary">{open?.check?.check_no}</Text></div>
        {open?.returnTable ? <Alert type="info" showIcon message="Tự trả bàn khi đã thu đủ tiền và hoàn tất phục vụ." /> : null}
        <Form form={form} layout="vertical" disabled={saving} onFinish={onSubmit}>
            {!zeroCharge ? <>
                <div className="fnb-payment-method-picker"><Text strong>Hình thức thanh toán</Text>
                    <Checkbox.Group className="fnb-payment-methods" value={selectedIds} onChange={changeMethods} disabled={saving} options={methods.map((method) => ({ value: method.id, label: paymentMethodLabel(method, methods), disabled: !paymentMethodAllowed(open?.check, method) }))} />
                </div>
                <Form.List name="payments" rules={[{ validator: (_, values) => values?.length ? Promise.resolve() : Promise.reject(new Error('Chọn ít nhất một hình thức thanh toán.')) }]}>
                    {(fields, _, { errors }) => <><Form.ErrorList errors={errors} /><div className={`fnb-payment-layout ${hasTransfer ? 'has-transfer' : ''}`}><div className="fnb-payment-amount-column">{fields.map((field) => {
                        const row = rows[field.name];
                        const method = methods.find((candidate) => String(candidate.id) === String(row?.payment_method_id));
                        if (!method) return null;
                        return <section className="fnb-payment-part" key={method.id}>
                            <div className="fnb-payment-part-heading"><Text strong>{paymentMethodLabel(method, methods)}</Text></div>
                            <Form.Item name={[field.name, 'payment_method_id']} hidden><Input /></Form.Item>
                            <div className="fnb-payment-money-fields">
                                <Form.Item name={[field.name, 'amount_minor']} label="Số tiền" rules={[{ required: true, type: 'integer', min: 1, max: Number(amount), message: 'Nhập số tiền nguyên lớn hơn 0, không vượt số tiền cần thu.' }]}><InputNumber {...moneyProps} min={1} max={Number(amount)} onChange={(value) => updateAmount(field.name, value)} /></Form.Item>
                                {method.kind === 'cash' ? <Form.Item name={[field.name, 'tendered_minor']} label="Khách đưa" dependencies={[[ 'payments', field.name, 'amount_minor' ]]} rules={[{ required: true, message: 'Nhập tiền khách đưa.' }, { validator: (_, value) => Number.isSafeInteger(value) && value >= Number(form.getFieldValue(['payments', field.name, 'amount_minor'])) ? Promise.resolve() : Promise.reject(new Error('Khách đưa phải đủ phần tiền mặt.')) }]}><InputNumber {...moneyProps} min={0} /></Form.Item> : null}
                            </div>
                            {method.kind === 'cash' && Number(row.tendered_minor) > Number(row.amount_minor) ? <Text type="secondary" className="fnb-payment-change">Tiền thừa trả khách: <Text strong>{formatMinorMoney(row.tendered_minor - row.amount_minor, currency)}</Text></Text> : null}
                            {method.kind !== 'transfer' && method.requires_reference ? <Form.Item name={[field.name, 'reference']} label="Mã giao dịch" rules={[{ required: true, whitespace: true, message: 'Nhập mã giao dịch.' }]}><Input maxLength={160} autoComplete="off" /></Form.Item> : null}
                        </section>;
                    })}{rows.length > 1 ? <Text type="secondary" className="fnb-payment-auto-hint">Nhập một phần, phần còn lại tự cân đối.</Text> : null}</div>
                    {hasTransfer ? <aside className="fnb-payment-transfer-column">{fields.map((field) => {
                        const method = methods.find((candidate) => String(candidate.id) === String(rows[field.name]?.payment_method_id));
                        return method?.kind === 'transfer' ? <TransferFields key={`${open.check.id}-${method.id}`} form={form} index={field.name} check={open.check} currency={currency} operational={operational} saving={saving} maxAmount={Number(amount)} requiresReference={Boolean(method.requires_reference)} /> : null;
                    })}</aside> : null}</div></>}
                </Form.List>
            </> : <Form.Item name="zero_charge_reason" label="Lý do bill 0 đồng" rules={[{ required: true, min: 3, message: 'Nhập lý do từ 3 ký tự.' }]}><Input.TextArea maxLength={500} /></Form.Item>}
            <Text type="secondary" className="fnb-payment-note">Chỉ xác nhận khi đã nhận đủ tiền của tất cả hình thức đã chọn.</Text>
        </Form>
    </Modal>;
}
